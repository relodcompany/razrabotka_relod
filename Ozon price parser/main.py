# main.py
import os
import logging
from dotenv import load_dotenv

load_dotenv()

USE_GUI = os.getenv("USE_GUI", "false").lower() in ("1", "true", "yes")

logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s:%(message)s",
)

if USE_GUI:
    from gui import run as run_gui
    if __name__ == "__main__":
        run_gui()
else:
    # Простейший CLI-запуск для проверки (можно расширять)
    from ozon_api import OzonAPI
    from competitor import find_best_competitor_offer

    if __name__ == "__main__":
        api = OzonAPI()
        idx_by_cat, idx_by_id = api.build_index()
        logging.info("Категорий: %s", len(idx_by_cat))
        # берём 3 товара из первой категории и проверим «конкурентов»
        sample = []
        for items in idx_by_cat.values():
            sample = [it["product_id"] for it in items[:3]]
            break
        for pid in sample:
            name = (idx_by_id.get(pid) or {}).get("name") or f"#{pid}"
            logging.info("Пробный поиск конкурентов для: %s", name)
            best = find_best_competitor_offer(name)
            if best:
                logging.info("Лучшая цена: %s — %s", best["price"], best["title"])
                logging.info("URL: %s", best["url"])
            else:
                logging.info("Не найдено предложений.")
