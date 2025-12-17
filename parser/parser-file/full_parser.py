import time
import pandas as pd
from bs4 import BeautifulSoup
from urllib.parse import urljoin
from auth import login, session
from product_parser import parse_product_page

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

def save_to_excel(data, filename):
    df = pd.DataFrame(data)
    df.to_excel(filename, index=False)

def parse_catalog(session, catalog_links, logger=None, progress=None):
    # универсальная функция для gui
    results = []
    product_links = []
    for cat_url in catalog_links:
        links = get_product_links(session, cat_url)
        product_links.extend(links)
    total = len(product_links)
    for i, url in enumerate(product_links):
        if logger: logger(f"[{i+1}/{total}] {url}")
        else: print(f"[{i+1}/{total}] {url}")
        try:
            results.append(parse_product_page(session, url))
        except Exception as e:
            if logger: logger(f"  Ошибка парсинга: {e}")
            else: print(f"  Ошибка парсинга: {e}")
        if progress: progress(i+1)
        time.sleep(0.5)
    return results

def main():
    # для совместимости со старым способом запуска
    CATEGORY_URLS = [ ... ]
    if not login():
        return
    all_products = parse_catalog(session, CATEGORY_URLS)
    save_to_excel(all_products, "gardners_all_products.xlsx")
    print("Готово! Файл gardners_all_products.xlsx")

if __name__ == "__main__":
    main()


