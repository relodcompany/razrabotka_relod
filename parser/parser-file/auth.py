import requests
from bs4 import BeautifulSoup

LOGIN_PAGE_URL = "https://www.gardners.com/Account/LogOn"

session = requests.Session()

def set_proxy(proxy_str=None):
    session.proxies.clear()
    if proxy_str:
        session.proxies = {"http": proxy_str, "https": proxy_str}

def set_headers():
    session.headers.update({
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml",
    })

def login(account, username, password):
    set_headers()
    resp_get = session.get(LOGIN_PAGE_URL)
    resp_get.raise_for_status()
    soup = BeautifulSoup(resp_get.text, "lxml")
    form = soup.find("form", {"action": "/Account/LogOn"})
    if not form:
        print("‼ Не нашёл форму авторизации на странице.")
        return False
    payload = {inp["name"]: inp.get("value", "") for inp in form.find_all("input", {"type": "hidden"})}
    payload.update({
        "AccountNumber": account,
        "UserName":      username,
        "Password":      password
    })
    headers = {
        "Referer": LOGIN_PAGE_URL,
        "Origin":  "https://www.gardners.com"
    }
    resp_post = session.post(LOGIN_PAGE_URL, data=payload, headers=headers, allow_redirects=True)
    post_soup = BeautifulSoup(resp_post.text, "lxml")
    if post_soup.select_one("a.logout") or "class=\"authenticated\"" in resp_post.text:
        print("✔ Успешный вход в Gardners")
        return True
    else:
        print("‼ Не удалось войти — проверьте CSRF-токен, учётные данные или прокси")
        with open("debug_post_response.html", "w", encoding="utf-8") as f:
            f.write(resp_post.text)
        print("DEBUG: тело ответа сохранено в debug_post_response.html")
        return False

