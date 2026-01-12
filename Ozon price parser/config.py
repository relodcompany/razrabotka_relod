"""
Конфигурация парсера Ozon.
Загрузка переменных окружения из .env и подготовка настроек.
"""
import os
from dotenv import load_dotenv

# ---------- helpers ----------
def _to_bool(val: str | None, default: bool = False) -> bool:
    if val is None:
        return default
    return str(val).strip().lower() in {"1", "true", "yes", "y", "on"}

def _to_int(val: str | None, default: int) -> int:
    if val is None or val == "":
        return default
    try:
        # допускаем "1.0" -> 1
        return int(float(val))
    except Exception:
        return default

def _to_float(val: str | None, default: float) -> float:
    if val is None or val == "":
        return default
    try:
        return float(val)
    except Exception:
        return default

# ---------- load env ----------
load_dotenv()

# ---------- API ----------
OZON_CLIENT_ID = os.getenv("OZON_CLIENT_ID", "").strip()
OZON_API_KEY = os.getenv("OZON_API_KEY", "").strip()

# ---------- UI / режим ----------
USE_GUI = _to_bool(os.getenv("USE_GUI"), default=False)

# ---------- Прокси ----------
PROXY_SOCKS5 = os.getenv("PROXY_SOCKS5", "").strip()  # один прокси строкой (может быть пустым)
PROXIES_FILE = os.getenv("PROXIES_FILE", "proxies.txt").strip()  # файл со списком прокси

# ---------- Парсинг и сеть ----------
DELAY_BETWEEN_REQUESTS = _to_float(os.getenv("DELAY_BETWEEN_REQUESTS"), default=1.0)
CRITICAL_PRICE_DIFF_PERCENT = _to_int(os.getenv("CRITICAL_PRICE_DIFF_PERCENT"), default=20)

MAX_RETRIES = _to_int(os.getenv("MAX_RETRIES"), default=3)
BACKOFF_BASE = _to_float(os.getenv("BACKOFF_BASE"), default=1.5)
REQUEST_TIMEOUT = _to_int(os.getenv("REQUEST_TIMEOUT"), default=25)  # <-- важный ключ

# ---------- Браузер / антибот ----------
BROWSER_ENABLED = _to_bool(os.getenv("BROWSER_ENABLED"), default=True)
BROWSER_HEADLESS = _to_bool(os.getenv("BROWSER_HEADLESS"), default=True)
OZON_COOKIES = os.getenv("OZON_COOKIES", "").strip()  # можно оставить пустым

# ---------- Уведомления (необязательно) ----------
EMAIL_RECIPIENT = os.getenv("EMAIL_RECIPIENT", "").strip()
EMAIL_SMTP = os.getenv("EMAIL_SMTP", "").strip()
EMAIL_PORT = _to_int(os.getenv("EMAIL_PORT"), default=587)
EMAIL_USER = os.getenv("EMAIL_USER", "").strip()
EMAIL_PASS = os.getenv("EMAIL_PASS", "").strip()
EMAIL_FROM = os.getenv("EMAIL_FROM", EMAIL_USER or "").strip()

TELEGRAM_ENABLED = _to_bool(os.getenv("TELEGRAM_ENABLED"), default=False)
TELEGRAM_CHAT_ID = os.getenv("TELEGRAM_CHAT_ID", "").strip()

# ---------- Пути ----------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
OUTPUT_DIR = os.path.join(BASE_DIR, "output")
HISTORY_DIR = os.path.join(BASE_DIR, "history")
LOGS_DIR = os.path.join(BASE_DIR, "logs")
INPUT_DIR = os.path.join(BASE_DIR, "input")  # может не использоваться в API-режиме, но пусть будет

for d in (OUTPUT_DIR, HISTORY_DIR, LOGS_DIR, INPUT_DIR):
    os.makedirs(d, exist_ok=True)

# ---------- DEBUG/поиск конкурентов ----------
# Сохранять HTML ответов поиска (в папку debug/)
DEBUG_SAVE_HTML = _to_bool(os.getenv("DEBUG_SAVE_HTML"), default=True)
DEBUG_DIR = os.getenv("DEBUG_DIR", "debug").strip()

# Сколько раз пробовать получить HTML (requests -> playwright -> повтор requests)
MAX_SEARCH_RETRIES = _to_int(os.getenv("MAX_SEARCH_RETRIES"), default=2)

# Ограничение числа «смысловых» токенов в запросе
SEARCH_TOKENS_LIMIT = _to_int(os.getenv("SEARCH_TOKENS_LIMIT"), default=6)

# Минимальная длина токена, чтобы считать его осмысленным
SEARCH_MIN_TOKEN_LEN = _to_int(os.getenv("SEARCH_MIN_TOKEN_LEN"), default=3)

# Логировать найденных кандидатов (топ-5) после парсинга
LOG_CANDIDATES = _to_bool(os.getenv("LOG_CANDIDATES"), default=True)

if DEBUG_SAVE_HTML and not os.path.exists(DEBUG_DIR):
    os.makedirs(DEBUG_DIR, exist_ok=True)

# ---------- sanity checks ----------
if not OZON_CLIENT_ID or not OZON_API_KEY:
    print("⚠️ ОШИБКА: Не указаны OZON_CLIENT_ID или OZON_API_KEY в .env (seller.ozon.ru → Настройки → API-ключи).")

# Если задержка вдруг отрицательная — поправим на 1.0
if DELAY_BETWEEN_REQUESTS < 0:
    DELAY_BETWEEN_REQUESTS = 1.0
