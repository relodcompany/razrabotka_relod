import time
from bs4 import BeautifulSoup
from urllib.parse import urljoin

def get_product_links(session, category_url):
    product_links = set()
    next_url = category_url

    while next_url:
        resp = session.get(next_url)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "lxml")
        for a in soup.select("li.resultItem.book a.image"):
            product_links.add(urljoin(resp.url, a["href"]))
        nxt = soup.find("a", rel="next")
        next_url = urljoin(resp.url, nxt["href"]) if (nxt and nxt.get("href")) else None
        time.sleep(1)
    return list(product_links)
