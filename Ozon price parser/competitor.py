# competitor.py
from __future__ import annotations
import os
import re
import json
import time
import random
import logging
from typing import List, Dict, Optional, Tuple
from urllib.parse import urlencode

from config import (
    LOGS_DIR,
    OZON_COOKIES,
    BROWSER_HEADLESS,
    DELAY_BETWEEN_REQUESTS,
)
from cookies_util import parse_ozon_cookies, override_domain_path

# Playwright
try:
    from playwright.sync_api import sync_playwright, Browser, BrowserContext, Page
except Exception as e:
    raise RuntimeError(
        "Playwright не установлен или не установлены браузеры. "
        "Выполни:  pip install playwright  затем  python -m playwright install chromium"
    )

os.makedirs(LOGS_DIR, exist_ok=True)

DEFAULT_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/130.0.0.0 Safari/537.36"
)


# ----------------- ВСПОМОГАТЕЛЬНЫЕ -----------------

def _save_debug_html(prefix: str, html: str) -> str:
    ts = int(time.time())
    rnd = f"{random.randint(0, 0xFFFFFF):06x}"
    path = os.path.join(LOGS_DIR, f"{prefix}_{ts}_{rnd}.html")
    with open(path, "w", encoding="utf-8") as f:
        f.write(html)
    logging.info("Сохранён HTML: %s", path)
    return path


def _human_wait(a=0.35, b=0.9):
    time.sleep(random.uniform(a, b))


def _price_from_text(txt: str) -> Optional[int]:
    """
    Достаёт целое число из строки типа '866 ₽', '1 299 руб.'.
    Возвращает цену в рублях (int) или None.
    """
    if not txt:
        return None
    m = re.search(r"(\d[\d\s\u00A0\u202F]*)\s*(?:₽|руб)", txt)
    if not m:
        m = re.search(r"(\d[\d\s\u00A0\u202F]*)", txt)
    if not m:
        return None
    num = re.sub(r"[\s\u00A0\u202F]", "", m.group(1))
    try:
        return int(num)
    except Exception:
        return None


def _block_heavy(route):
    """Блокируем картинки/медиа/фонты для ускорения, оставляем HTML/CSS/JS/XHR."""
    r = route.request
    if r.resource_type in {"image", "media", "font"}:
        return route.abort()
    return route.continue_()


def _prepare_context(p, user_agent: str, cookies_for_ctx: List[Dict]) -> Tuple[Browser, BrowserContext]:
    browser = p.chromium.launch(headless=BROWSER_HEADLESS)
    context = browser.new_context(
        user_agent=user_agent or DEFAULT_UA,
        locale="ru-RU",
        timezone_id="Europe/Moscow",
    )
    if cookies_for_ctx:
        # нормализуем домен/путь на всякий
        cookies_pw = override_domain_path(cookies_for_ctx, domain=".ozon.ru", path="/")
        context.add_cookies(cookies_pw)
    return browser, context


def _navigate_with_retry(page: Page, url: str, tries: int = 2):
    for i in range(1, tries + 1):
        try:
            page.goto(url, wait_until="domcontentloaded", timeout=45000)
            _human_wait(0.45, 1.1)
            return
        except Exception:
            if i == tries:
                raise
            _human_wait(1.0, 1.6)


def _challenge_or_blocked(html: str) -> bool:
    return (
        ("Доступ ограничен" in html)
        or ("abt-challenge" in html)
        or ("Please, enable JavaScript to continue" in html)
    )


def _extract_search_cards(page: Page) -> List[str]:
    """
    Из страницы поиска собираем ссылки на карточки товаров.
    Возвращаем список абсолютных ссылок /product/... .
    """
    # Устойчивый путь — взять все <a href^="/product/">
    anchors = page.query_selector_all('a[href^="/product/"]')
    hrefs = []
    for a in anchors:
        href = a.get_attribute("href") or ""
        if not href.startswith("/product/"):
            continue
        cut = href.split("?", 1)[0]
        hrefs.append("https://www.ozon.ru" + cut)

    # Доп. попытка: некоторые карточки обёрнуты дивами и ссылка внутри data-widget
    # (на будущее, но обычно первый способ достаточен)

    hrefs = sorted(list(set(hrefs)))
    return hrefs


def _extract_seller_and_gray_price(page: Page) -> Tuple[Optional[str], Optional[int]]:
    """
    На странице товара тянем:
      - продавца (название магазина)
      - "серую цену" (из <span class="pdp_b7f tsHeadline500Medium">... ₽</span>)
    Возвращаем (seller_name, gray_price_int)
    """
    html = page.content()

    # Продавец — сначала пробуем класс из ТЗ
    seller = None
    el_seller = page.query_selector('span.b35_3_10-b6')
    if el_seller:
        seller = (el_seller.text_content() or "").strip()

    if not seller:
        # Часто есть блок с data-qa="seller-block_name" или ссылка на /seller/
        el2 = page.query_selector('[data-qa="seller-block_name"]') or page.query_selector('a[href*="/seller/"]')
        if el2:
            seller = (el2.text_content() or "").strip()

    if not seller:
        # Фолбэк по HTML
        m = re.search(r"(Продавец|Продавец:)\s*</span>\s*<[^>]*>([^<]+)</", html, re.IGNORECASE)
        if m:
            seller = m.group(2).strip()

    # Серая цена — сначала точный селектор из ТЗ
    gray_price = None
    el_price = page.query_selector('span.pdp_b7f.tsHeadline500Medium')
    if el_price:
        gray_price = _price_from_text(el_price.text_content() or "")

    if gray_price is None:
        # Фолбэк по типовым Headline-классам в карточке
        m = re.search(
            r'<span[^>]*class="[^"]*tsHeadline500Medium[^"]*"[^>]*>([^<]+)</span>',
            html
        )
        if m:
            gray_price = _price_from_text(m.group(1))

    return seller, gray_price


# ----------------- ОСНОВНАЯ ФУНКЦИЯ -----------------

def find_top_competitor_offers_by_isbn(
    isbn: str,
    max_cards: int = 16,
    top_n: int = 3,
    user_agent: str = DEFAULT_UA,
) -> List[Dict]:
    """
    Ищет по ISBN через реальный браузер (Playwright) с куками из .env.
    Для каждой найденной карточки заходит внутрь и вынимает:
      - seller_name
      - "серую цену"
    Возвращает top_n самых дешёвых предложений: [{"seller","price","title","url","isbn"}, ...]
    """

    # Подхватываем и нормализуем куки (JSON-массив или "k=v; k2=v2")
    cookies_list = parse_ozon_cookies(OZON_COOKIES)

    with sync_playwright() as p:
        browser, context = _prepare_context(p, user_agent=user_agent, cookies_for_ctx=cookies_list)
        try:
            page = context.new_page()
            page.route("**/*", _block_heavy)

            # Поиск: без /category/, только delivery=8
            base = "https://www.ozon.ru/search/"
            q = {
                "from_global": "true",
                "deny_category_prediction": "true",
                "delivery": "8",
                "text": isbn,
            }
            url = f"{base}?{urlencode(q)}"

            _navigate_with_retry(page, url, tries=2)
            _human_wait(0.6, 1.1)

            # антибот?
            html = page.content()
            if _challenge_or_blocked(html):
                _save_debug_html(f"search_blocked_{isbn}", html)
                logging.warning("Поиск %s: страница заблокирована антиботом.", isbn)
                return []

            # динамика — слегка проскроллим, чтобы прогрузились карточки
            for frac in (0.40, 0.75):
                page.evaluate(f"window.scrollTo(0, document.body.scrollHeight * {frac});")
                _human_wait(0.45, 0.9)

            # собираем карточки
            cards = _extract_search_cards(page)
            if not cards:
                _save_debug_html(f"search_empty_{isbn}", html)
                logging.info("Поиск по ISBN %s: карточек не найдено.", isbn)
                return []

            if len(cards) > max_cards:
                cards = cards[:max_cards]

            results: List[Dict] = []

            # обходим карточки
            for idx, card_url in enumerate(cards, start=1):
                try:
                    _navigate_with_retry(page, card_url, tries=2)
                except Exception as e:
                    logging.warning("Не открыл карточку %s: %s", card_url, e)
                    continue

                _human_wait(0.6, 1.2)
                chtml = page.content()
                if _challenge_or_blocked(chtml):
                    _save_debug_html(f"card_blocked_{isbn}", chtml)
                    logging.warning("Карточка %s заблокирована антиботом.", card_url)
                    continue

                title = (page.title() or "").strip()
                seller, gray_price = _extract_seller_and_gray_price(page)

                if gray_price is not None:
                    results.append({
                        "isbn": isbn,
                        "title": title or "",
                        "seller": seller or "",
                        "price": gray_price,
                        "url": card_url,
                    })

                # небольшая пауза между карточками
                _human_wait(0.4, 0.9)

            # сортировка и top_n
            results.sort(key=lambda x: (x.get("price") or 10**12))
            return results[:top_n]

        finally:
            context.close()
            browser.close()


# ----------------- СОВМЕСТИМОСТЬ (если где-то зовут старый интерфейс) -----------------

def find_best_competitor_offer(query: str, cookies: Optional[str] = None, user_agent: str = DEFAULT_UA) -> Optional[Dict]:
    """
    Простой враппер для совместимости: берёт top=1 по "query" как по ISBN.
    Ожидает, что query — это и есть ISBN (как ты используешь сейчас).
    """
    offers = find_top_competitor_offers_by_isbn(isbn=query, max_cards=16, top_n=1, user_agent=user_agent)
    return offers[0] if offers else None
