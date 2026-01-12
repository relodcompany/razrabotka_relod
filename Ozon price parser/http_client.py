# http_client.py
import logging
import random
import time
from typing import Dict, Optional, Tuple

import requests

from config import (
    REQUEST_TIMEOUT,
    MAX_RETRIES,
    BACKOFF_BASE,
    PROXY_SOCKS5,
    PROXIES_FILE,
)

# Базовый набор "человеческих" заголовков (можно переопределять снаружи)
DEFAULT_BASE_HEADERS: Dict[str, str] = {
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "ru-RU,ru;q=0.9,en-US;q=0.7,en;q=0.5",
    "Connection": "close",
    "Sec-Fetch-Dest": "document",
    "Sec-Fetch-Mode": "navigate",
    "Sec-Fetch-Site": "none",
    "Sec-Fetch-User": "?1",
    "Upgrade-Insecure-Requests": "1",
}

# Несколько user-agent; подменяем на каждый запрос, если не задан явно
_UA_POOL = [
    # Chrome Windows
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    # Chrome Linux
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    # Firefox
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0",
]

def _read_proxy_list(path: str) -> list[str]:
    out: list[str] = []
    try:
        with open(path, "r", encoding="utf-8") as f:
            for line in f:
                s = line.strip()
                if not s or s.startswith("#"):
                    continue
                out.append(s)
    except FileNotFoundError:
        pass
    return out

def parse_cookie_string(raw: str | None) -> Dict[str, str]:
    """
    Принимает строку вида: "a=1; b=2; __Secure-x=y" -> {"a":"1","b":"2","__Secure-x":"y"}
    Если строка пустая — вернёт {}.
    """
    jar: Dict[str, str] = {}
    if not raw:
        return jar
    for chunk in raw.split(";"):
        if "=" not in chunk:
            continue
        k, v = chunk.split("=", 1)
        k = k.strip()
        v = v.strip()
        if k:
            jar[k] = v
    return jar


class HttpClient:
    """
    Лёгкий HTTP-клиент на requests с ретраями, подменой User-Agent,
    поддержкой куков и (по желанию) прокси/ротации прокси.
    """

    def __init__(
        self,
        base_headers: Optional[Dict[str, str]] = None,
        cookies: Optional[Dict[str, str]] | Optional[str] = None,
        proxy: Optional[str] = None,
    ):
        # базовые заголовки
        self.base_headers: Dict[str, str] = dict(DEFAULT_BASE_HEADERS)
        if base_headers:
            self.base_headers.update(base_headers)

        # куки можно передать dict или строкой "k=v; ..."
        if isinstance(cookies, str):
            self.cookies: Dict[str, str] = parse_cookie_string(cookies)
        else:
            self.cookies = cookies or {}

        # прокси по умолчанию: либо явный, либо из .env PROXY_SOCKS5
        self.proxy = proxy or (PROXY_SOCKS5.strip() if PROXY_SOCKS5 else None)

        # список прокси из файла (для ротации)
        self.proxy_pool = _read_proxy_list(PROXIES_FILE) if PROXIES_FILE else []

        # одна сессия на клиент
        self.session = requests.Session()
        if self.cookies:
            self.session.cookies.update(self.cookies)

    # --- внутренняя логика выбора прокси ---
    def _choose_proxies(self) -> Optional[Dict[str, str]]:
        px = None
        if self.proxy:
            px = self.proxy
        elif self.proxy_pool:
            px = random.choice(self.proxy_pool)
        if not px:
            return None
        # поддерживаем http/https/socks5
        return {"http": px, "https": px}

    # --- единая обёртка с ретраями ---
    def _request(
        self,
        method: str,
        url: str,
        headers: Optional[Dict[str, str]] = None,
        timeout: Optional[int] = None,
        allow_redirects: bool = True,
        **kwargs,
    ) -> requests.Response:
        last_err: Optional[Exception] = None
        for attempt in range(1, MAX_RETRIES + 1):
            try:
                # собираем заголовки
                h = dict(self.base_headers)
                # динамический user-agent (если не задан)
                if "User-Agent" not in h:
                    h["User-Agent"] = random.choice(_UA_POOL)
                if headers:
                    h.update(headers)

                resp = self.session.request(
                    method=method.upper(),
                    url=url,
                    headers=h,
                    timeout=timeout or REQUEST_TIMEOUT,
                    allow_redirects=allow_redirects,
                    proxies=self._choose_proxies(),
                    **kwargs,
                )

                # простая обработка банов/ограничений
                if resp.status_code in (403, 429):
                    raise requests.HTTPError(f"HTTP {resp.status_code} (anti-bot?)", response=resp)

                resp.raise_for_status()
                return resp

            except Exception as e:
                last_err = e
                sleep_s = BACKOFF_BASE ** (attempt - 1)
                logging.warning(
                    "HTTP %s %s попытка %s/%s: %s — повтор через %.1fs",
                    method.upper(), url, attempt, MAX_RETRIES, e, sleep_s,
                )
                time.sleep(sleep_s)

        # если не удалось за все попытки — пробрасываем
        if last_err:
            raise last_err
        raise RuntimeError("Неизвестная ошибка HTTP-клиента")

    # --- публичные методы ---
    def get(self, url: str, headers: Optional[Dict[str, str]] = None, **kwargs) -> requests.Response:
        return self._request("GET", url, headers=headers, **kwargs)

    def get_html(self, url: str, headers: Optional[Dict[str, str]] = None, **kwargs) -> str:
        r = self.get(url, headers=headers, **kwargs)
        # декодируем корректно
        r.encoding = r.apparent_encoding or r.encoding or "utf-8"
        return r.text
