import time
from product_parser import parse_product_page

SEARCH_URL = "https://www.gardners.com/Search/KeywordSubmit"

def get_product_url_by_isbn(session, isbn):
    payload = {
        "productType": "2",
        "keyword": isbn.strip()
    }
    resp = session.post(SEARCH_URL, data=payload, allow_redirects=False)
    if resp.status_code in (302, 303) and "Location" in resp.headers:
        prod_url = resp.headers["Location"]
        if not prod_url.startswith("http"):
            prod_url = "https://www.gardners.com" + prod_url
        return prod_url
    return None

def parse_by_isbn(session, isbn_list):
    results = []
    for i, isbn in enumerate(isbn_list):
        print(f"[{i+1}/{len(isbn_list)}] Поиск ISBN: {isbn}")
        url = get_product_url_by_isbn(session, isbn)
        if url:
            try:
                data = parse_product_page(session, url)
                results.append(data)
            except Exception as e:
                print(f"  Ошибка парсинга: {e}")
        else:
            print("  Не найден или не найден редирект.")
        time.sleep(0.5)
    return results
