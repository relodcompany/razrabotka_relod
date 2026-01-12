import logging
import time
from typing import List, Dict, Any
from config import DELAY_BETWEEN_REQUESTS
from browser_client import BrowserClient  # ваш клиент на Playwright/requests
# ожидается, что BrowserClient имеет метод search_ozon(query) -> List[dict] с полями {title, url, price_gray, seller, rating}

class CompetitorParser:
    def __init__(self):
        self.client = BrowserClient()

    def _best_competitor(self, offers: List[dict]) -> dict | None:
        clean = [o for o in offers if o.get("price_gray") is not None]
        if not clean:
            return None
        return sorted(clean, key=lambda x: x["price_gray"])[0]

    def run_for_products(self, products: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        products: [{product_id, name, isbn, my_price, category_name, ...}]
        """
        results: List[Dict[str, Any]] = []
        for i, p in enumerate(products, 1):
            name = p.get("name") or ""
            isbn = p.get("isbn") or ""
            my_price = p.get("my_price")

            logging.info(f"[{i}/{len(products)}] Ищу конкурентов: {name} (ISBN: {isbn or '-'})")

            offers = []
            # 1) Пытаемся по ISBN
            if isbn:
                offers = self.client.search_ozon(isbn)
                if not offers:
                    # 2) fallback по названию
                    offers = self.client.search_ozon(name)
            else:
                offers = self.client.search_ozon(name)

            status = "Не найдено"
            best = None
            if offers:
                best = self._best_competitor(offers)
                if best:
                    status = "Найдено"
                else:
                    status = "Только цена с картой"  # если ничего без карты

            res = {
                "ISBN": isbn,
                "Название": name,
                "Автор": "",                 # можно дополнить при обогащении
                "Издательство": "",          # можно дополнить при обогащении
                "Наша цена": my_price,
                "Цена конкурента": best["price_gray"] if best else None,
                "Разница (руб.)": None,
                "Разница (%)": None,
                "Статус": status,
                "Ссылка": best["url"] if best else "",
                "Продавец": best.get("seller") if best else "",
                "Рейтинг продавца": best.get("rating") if best else "",
                "Дата парсинга": time.strftime("%Y-%m-%d %H:%M:%S")
            }

            if my_price is not None and best and best.get("price_gray") is not None:
                diff = best["price_gray"] - float(my_price)
                res["Разница (руб.)"] = round(diff, 2)
                if my_price:
                    res["Разница (%)"] = round(diff / float(my_price) * 100, 2)

            results.append(res)
            time.sleep(DELAY_BETWEEN_REQUESTS)

        return results
