# browser_client.py
import json
import logging
import time
from pathlib import Path
from typing import Optional, List, Dict

try:
    from playwright.sync_api import sync_playwright, Error as PWError  # type: ignore
    _PW_OK = True
except Exception:
    _PW_OK = False


DEFAULT_UA = (
    # Маскируемся под обычный десктопный Chrome 131 (НЕ headless)
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)


def _normalize_cookies(cookies_json_str: str) -> List[Dict]:
    """
    Принимает строку из .env (JSON-список печенек в формате расширений Chrome/Firefox)
    и преобразует в список куков Playwright (name, value, domain, path, expires, httpOnly, secure, sameSite).
    """
    if not cookies_json_str:
        return []
    try:
        raw = json.loads(cookies_json_str)
        if not isinstance(raw, list):
            return []
    except Exception as e:
        logging.warning("Не удалось распарсить OZON_COOKIES как JSON: %s", e)
        return []

    out = []
    for c in raw:
        try:
            name = c.get("name")
            value = c.get("value")
            domain = c.get("domain") or "www.ozon.ru"
            # Playwright требует без ведущей точки в domain
            if domain.startswith("."):
                domain = domain[1:]
            path = c.get("path") or "/"
            expires = None
            # некоторые экспортеры отдают expirationDate как float timestamp
            if "expirationDate" in c and isinstance(c["expirationDate"], (int, float)):
                expires = int(c["expirationDate"])
            httpOnly = bool(c.get("httpOnly"))
            secure = bool(c.get("secure"))
            sameSite_str = str(c.get("sameSite") or "").lower()
            if sameSite_str in ("lax", "no_restriction", "none"):
                sameSite = "Lax" if sameSite_str == "lax" else "None"
            elif sameSite_str in ("strict",):
                sameSite = "Strict"
            else:
                sameSite = "Lax"

            out.append({
                "name": name,
                "value": value,
                "domain": domain,
                "path": path,
                **({"expires": expires} if expires else {}),
                "httpOnly": httpOnly,
                "secure": secure,
                "sameSite": sameSite,
            })
        except Exception:
            continue
    return out


class BrowserClient:
    """
    Простая обёртка над Playwright.
    Умеет: подставлять UA, куки; сохранять HTML-дампы; подождать рендер.
    """

    def __init__(
        self,
        headless: bool = True,
        cookies_json_str: str = "",
        user_agent: Optional[str] = None,
        logs_dir: Optional[str] = None,
    ):
        self.headless = headless
        self.user_agent = user_agent or DEFAULT_UA
        self.cookies = _normalize_cookies(cookies_json_str)
        self.logs_dir = Path(logs_dir or "logs")
        self.logs_dir.mkdir(parents=True, exist_ok=True)

        if not _PW_OK:
            logging.warning("Playwright недоступен. Будет использоваться только HTTP-клиент.")

    def get_html(self, url: str, save_name_prefix: Optional[str] = None) -> Optional[str]:
        if not _PW_OK:
            return None
        try:
            with sync_playwright() as p:
                browser = p.chromium.launch(headless=self.headless, args=[
                    "--disable-blink-features=AutomationControlled",
                    "--no-sandbox",
                ])
                context = browser.new_context(
                    user_agent=self.user_agent,
                    java_script_enabled=True,
                    locale="ru-RU",
                    extra_http_headers={
                        "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
                        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                    },
                )
                if self.cookies:
                    try:
                        context.add_cookies(self.cookies)
                        logging.info("Загружено куки в контекст браузера: %s шт.", len(self.cookies))
                    except PWError as e:
                        logging.warning("Не удалось установить куки в браузер: %s", e)

                page = context.new_page()
                page.set_default_timeout(30000)
                page.set_default_navigation_timeout(45000)

                page.goto(url, wait_until="domcontentloaded")

                # Подождём, чтобы дорисовались плитки поиска
                # 1) если отрисовалась зона поиска товаров — отлично
                # 2) если антибот — будет другая разметка, но мы всё равно сохраним HTML
                try:
                    page.wait_for_selector('div[data-widget="searchResultsV2"], a[href^="/product/"]', timeout=10000)
                except Exception:
                    pass  # просто пойдём дальше

                # небольшой randomized sleep — имитация человека
                time.sleep(1.25)

                html = page.content()

                # Сохраним снапшот
                try:
                    stamp = str(int(time.time()))
                    prefix = (save_name_prefix or "page").strip().replace(" ", "_")[:60]
                    fname = f"{prefix}_{stamp}.html"
                    (self.logs_dir / fname).write_text(html, encoding="utf-8")
                    logging.info("Сохранён снапшот браузера: %s", self.logs_dir / fname)
                except Exception:
                    pass

                context.close()
                browser.close()
                return html
        except Exception as e:
            logging.error("BrowserClient.get_html error: %s", e)
            return None
