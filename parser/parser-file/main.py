import pandas as pd
from auth import login, session, set_proxy
from catalog_parser import get_product_links
from product_parser import parse_product_page
from isbn_parser import parse_by_isbn

def main():
    print("Gardners Parser v1.0 (терминал)\n")
    # --- Получение прокси ---
    proxy = input("Прокси (например, socks5://user:pass@ip:port), или пусто: ").strip()
    set_proxy(proxy if proxy else None)

    # --- Получение логина/пароля ---
    account = input("Gardners AccountNumber (например, REL006): ").strip()
    username = input("Gardners UserName (например, REL006): ").strip()
    password = input("Gardners Password: ").strip()

    # --- Авторизация ---
    if not login(account, username, password):
        print("Ошибка авторизации!")
        return

    # --- Выбор режима ---
    print("\nВыберите режим:")
    print("1 — Парсинг по ссылкам каталога")
    print("2 — Парсинг по списку ISBN")
    mode = ""
    while mode not in ("1", "2"):
        mode = input("Ваш выбор (1/2): ").strip()

    # --- Парсинг по каталогу ---
    if mode == "1":
        print("\nВставьте ссылки на каталоги (по одной в строке, затем пустая строка):")
        links = []
        while True:
            line = input()
            if not line.strip():
                break
            links.append(line.strip())
        if not links:
            print("Нет ссылок — выход.")
            return

        all_product_links = []
        for url in links:
            print(f"Собираю товары из: {url}")
            prods = get_product_links(session, url)
            print(f"  Найдено товаров: {len(prods)}")
            all_product_links.extend(prods)
        print(f"Всего товаров для парсинга: {len(all_product_links)}")
        results = []
        for i, prod_url in enumerate(all_product_links):
            print(f"[{i+1}/{len(all_product_links)}] {prod_url}")
            try:
                data = parse_product_page(session, prod_url)
                results.append(data)
            except Exception as e:
                print(f"  Ошибка: {e}")
        outname = "gardners_catalog_results.xlsx"
        pd.DataFrame(results).to_excel(outname, index=False)
        print(f"Готово! Результаты сохранены в {outname}")

    # --- Парсинг по ISBN ---
    else:
        print("\nВставьте список ISBN (по одному в строке, затем пустая строка):")
        isbn_list = []
        while True:
            line = input()
            if not line.strip():
                break
            isbn_list.append(line.strip())
        if not isbn_list:
            print("Нет ISBN — выход.")
            return
        results = parse_by_isbn(session, isbn_list)
        outname = "gardners_isbn_results.xlsx"
        pd.DataFrame(results).to_excel(outname, index=False)
        print(f"Готово! Результаты сохранены в {outname}")

if __name__ == "__main__":
    main()
