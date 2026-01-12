# cookies_util.py
from __future__ import annotations
import json
import re
from typing import List, Dict, Any, Optional

OZON_DEFAULT_URL = "https://www.ozon.ru"
OZON_DEFAULT_DOMAIN = ".ozon.ru"
OZON_DEFAULT_PATH = "/"

# mappers для приведения к формату Playwright
_SAMESITE_MAP = {
    None: None,
    "": None,
    "unspecified": None,
    "lax": "Lax",
    "strict": "Strict",
    "no_restriction": "None",
    "none": "None",
    "Lax": "Lax",
    "Strict": "Strict",
    "None": "None",
}

_BOOL = {"true": True, "false": False, True: True, False: False, None: None, "": None}

def _to_bool(v: Any, default: Optional[bool] = None) -> Optional[bool]:
    if isinstance(v, bool):
        return v
    if v is None:
        return default
    s = str(v).strip().lower()
    return _BOOL.get(s, default)

def _to_int(v: Any, default: Optional[int] = None) -> Optional[int]:
    try:
        if v is None or v == "":
            return default
        # поддержим float timestamp
        return int(float(v))
    except Exception:
        return default

def _kv_header_to_list(header: str) -> List[Dict[str, Any]]:
    """
    Разбирает строку вида "a=1; b=2; c=3" в список PW-cookie.
    """
    cookies = []
    for part in header.split(";"):
        part = part.strip()
        if not part:
            continue
        if "=" not in part:
            continue
        name, value = part.split("=", 1)
        name = name.strip()
        value = value.strip()
        if not name:
            continue
        cookies.append({
            "name": name,
            "value": value,
            # минимально достаточный набор:
            "domain": OZON_DEFAULT_DOMAIN,
            "path": OZON_DEFAULT_PATH,
            "secure": True,         # ozon под https
            "httpOnly": False,
        })
    return cookies

def _json_array_to_list(raw: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """
    Приводит JSON-объекты (из расширения браузера) к формату Playwright.
    Входные поля: name, value, domain|hostOnly, path, expirationDate, httpOnly, secure, sameSite
    Выходные: name, value, domain/path (или url), httpOnly, secure, sameSite [Lax/Strict/None], expires
    """
    out: List[Dict[str, Any]] = []
    for c in raw:
        name = c.get("name")
        value = c.get("value")
        if not name:
            continue

        domain = c.get("domain") or OZON_DEFAULT_DOMAIN
        path = c.get("path") or OZON_DEFAULT_PATH

        same_site_src = c.get("sameSite")
        same_site = _SAMESITE_MAP.get(same_site_src, None)

        http_only = _to_bool(c.get("httpOnly"), default=False)
        secure = _to_bool(c.get("secure"), default=True)

        # playwright ждёт 'expires' (unix seconds), 0 или None = сессионная
        expires = _to_int(c.get("expirationDate"), default=None)

        cookie: Dict[str, Any] = {
            "name": str(name),
            "value": str(value),
            "domain": str(domain),
            "path": str(path),
            "secure": True if secure is None else bool(secure),
            "httpOnly": False if http_only is None else bool(http_only),
        }
        if same_site:
            cookie["sameSite"] = same_site
        if expires:
            cookie["expires"] = expires

        out.append(cookie)
    return out

def parse_ozon_cookies(raw: str | None) -> List[Dict[str, Any]]:
    """
    Принимает:
      - None/"" -> []
      - строку с 'k=v; ...' -> список cookie
      - JSON-массив (как экспорт из браузера) -> список cookie
    """
    if not raw:
        return []
    s = raw.strip()
    # Пробуем как JSON-массив
    if s.startswith("["):
        try:
            arr = json.loads(s)
            if isinstance(arr, list):
                return _json_array_to_list(arr)
        except Exception:
            pass
    # Иначе — как "k=v; k2=v2"
    return _kv_header_to_list(s)

def override_domain_path(cookies: List[Dict[str, Any]],
                         domain: str = OZON_DEFAULT_DOMAIN,
                         path: str = OZON_DEFAULT_PATH) -> List[Dict[str, Any]]:
    """
    Форсируем domain/path на случай, если экспорт дал несовместимые значения.
    """
    out = []
    for c in cookies:
        c = dict(c)
        c["domain"] = domain
        c["path"] = path
        out.append(c)
    return out
