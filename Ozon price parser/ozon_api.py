# ozon_api.py
import os
import json
import time
import logging
from typing import Dict, List, Tuple, Optional
import requests

from config import (
    OZON_CLIENT_ID, OZON_API_KEY,
    REQUEST_TIMEOUT, MAX_RETRIES, BACKOFF_BASE,
    DELAY_BETWEEN_REQUESTS,
)

BASE_URL = "https://api-seller.ozon.ru"


def _ensure_dir(path: str) -> None:
    d = os.path.dirname(path)
    if d and not os.path.exists(d):
        os.makedirs(d, exist_ok=True)


class OzonAPI:
    """
    Обёртка над ключевыми методами Ozon Seller API с нормализацией под единый формат.
    Поддерживает /v3/product/list и /v3/product/info/list, с фолбэком на /v2 при 404 (если понадобится).
    """

    def __init__(self) -> None:
        self.session = requests.Session()
        # Жёстко просим только gzip/deflate — чтобы не прилетал br
        self.session.headers.update({
            "Client-Id": str(OZON_CLIENT_ID),
            "Api-Key": str(OZON_API_KEY),
            "Content-Type": "application/json; charset=utf-8",
            "Accept": "application/json",
            "Accept-Encoding": "gzip, deflate",
            "Connection": "close",
        })

    # ---------------------- низкоуровневые утилиты ----------------------

    def _request_with_retry(
        self, method: str, url: str, json_payload: Optional[dict] = None
    ) -> requests.Response:
        last_err: Optional[Exception] = None
        for attempt in range(1, MAX_RETRIES + 1):
            try:
                logging.debug(
                    "POST %s | payload=%s | headers=%s",
                    url,
                    (json.dumps(json_payload, ensure_ascii=False)[:500] if json_payload else None),
                    {
                        "Client-Id": self.session.headers.get("Client-Id"),
                        "Api-Key": f"{self.session.headers.get('Api-Key', '')[:4]}...{self.session.headers.get('Api-Key', '')[-4:]}",
                        "Content-Type": self.session.headers.get("Content-Type"),
                    },
                )
                r = self.session.request(
                    method=method.upper(),
                    url=url,
                    json=json_payload,
                    timeout=REQUEST_TIMEOUT,
                )
                logging.debug(
                    'RESP %s | code=%s | headers={x-ratelimit-remaining-second: %s} | body~=%s',
                    url, r.status_code, r.headers.get('x-ratelimit-remaining-second'),
                    r.text[:500].replace("\n", " "),
                )
                # Пропускаем 2xx
                if 200 <= r.status_code < 300:
                    return r
                # 404 отдаём наверх – может быть другая версия метода
                if r.status_code == 404:
                    return r
                r.raise_for_status()
            except Exception as e:
                last_err = e
                sleep_s = (BACKOFF_BASE ** (attempt - 1))
                logging.warning(
                    "Ошибка запроса (%s %s) попытка %s/%s: %s. Повтор через %.1fs",
                    method.upper(), url, attempt, MAX_RETRIES, e, sleep_s,
                )
                time.sleep(sleep_s)
        if last_err:
            raise last_err
        raise RuntimeError("Неизвестная ошибка при запросе")

    def _json(self, r: requests.Response) -> dict:
        """
        Безопасный JSON-разбор. Если не JSON — сохраняем тело в logs/last_response_*.txt
        """
        try:
            return r.json()
        except Exception:
            s = r.text or ""
            ts = str(int(time.time()))
            path = os.path.join("logs", f"last_response_{ts}.txt")
            _ensure_dir(path)
            try:
                with open(path, "w", encoding="utf-8") as f:
                    f.write(s)
                logging.error("Ответ не JSON. Сохранён файл: %s", path)
            except Exception as e:
                logging.error("Не удалось сохранить raw-ответ: %s", e)
            # попытка ручного json.loads (иногда помогает, если есть лишние пробелы/мусор)
            try:
                return json.loads(s)
            except Exception as e:
                logging.error("Ручной парсинг JSON не удался: %s", e)
                logging.error("Первые 500 символов ответа: %r", s[:500])
                raise

    # ---------------------- обёртки над API ----------------------

    def _product_list(self, last_id: str, limit: int = 1000) -> dict:
        """
        Получение списка продуктов продавца.
        Основной путь: /v3/product/list
        Фолбэк на /v2/product/list только при 404.
        """
        # v3
        url_v3 = f"{BASE_URL}/v3/product/list"
        payload_v3 = {
            "last_id": last_id or "",
            "limit": limit,
            "filter": {"visibility": "ALL"},
        }
        r = self._request_with_retry("POST", url_v3, json_payload=payload_v3)
        if r.status_code != 404:
            data = self._json(r)
            logging.info("LIST: получено %s", len((data.get("result") or {}).get("items", [])) or len(data.get("items", [])))
            return data

        # v2 (редкий кейс сейчас)
        url_v2 = f"{BASE_URL}/v2/product/list"
        payload_v2 = {
            "last_id": last_id or "",
            "limit": limit,
            "filter": {"visibility": "ALL"},
        }
        r = self._request_with_retry("POST", url_v2, json_payload=payload_v2)
        data = self._json(r)
        logging.info("LIST[v2]: получено %s", len((data.get("result") or {}).get("items", [])))
        return data

    def _product_info_list(self, product_ids: List[int]) -> dict:
        """
        Детальная инфа по товарам.
        Основной путь: /v3/product/info/list (отдаёт { "items": [...] }).
        Фолбэк: /v2/product/info/list (отдаёт { "result": [...] }) или /v2/product/info.
        """
        # v3
        url_v3 = f"{BASE_URL}/v3/product/info/list"
        payload_v3 = {"product_id": product_ids}
        r = self._request_with_retry("POST", url_v3, json_payload=payload_v3)
        if r.status_code != 404:
            return self._json(r)

        # v2/info/list
        url_v2_list = f"{BASE_URL}/v2/product/info/list"
        payload_v2 = {"product_id": product_ids}
        r = self._request_with_retry("POST", url_v2_list, json_payload=payload_v2)
        if r.status_code != 404:
            return self._json(r)

        # v2/info (альтернативный)
        url_v2 = f"{BASE_URL}/v2/product/info"
        r = self._request_with_retry("POST", url_v2, json_payload=payload_v2)
        return self._json(r)

    # ---------------------- публичные методы ----------------------

    def get_all_my_product_ids(self) -> List[int]:
        """
        Возвращает ВСЕ product_id продавца, идя по страницам с last_id.
        Поддерживает и v3 (result.items) и v2 (result.items).
        """
        product_ids: List[int] = []
        last_id = ""
        page = 0
        while True:
            page += 1
            data = self._product_list(last_id=last_id, limit=1000)

            # v3 обычно: {"result": {"items":[{"product_id":...}], "last_id":"...", "total":N}}
            result = data.get("result") or {}
            items = result.get("items")
            total = result.get("total")

            # иногда встречается плоский формат с "items" без "result"
            if items is None:
                items = data.get("items", [])
                total = data.get("total", total)

            for it in items:
                pid = it.get("product_id") or it.get("id")  # на всякий случай
                if isinstance(pid, int):
                    product_ids.append(pid)

            last_id = (result.get("last_id") or data.get("last_id") or "")
            logging.info("Получена страница %s: %s товаров (накоплено: %s)", page, len(items), len(product_ids))
            time.sleep(DELAY_BETWEEN_REQUESTS)
            if not last_id:
                break

        if total is not None and len(product_ids) != int(total):
            logging.debug("LIST: итог=%s, по API total=%s", len(product_ids), total)

        return product_ids

    def _normalize_item(self, raw: dict) -> dict:
        """
        Приводим элемент ответа info/list к единому виду.
        На вход могут прийти:
          - v3: {"items":[ {"id":..., "name":..., "offer_id":..., "description_category_id":..., "price":"..."} ]}
          - v2: {"result":[ {"product_id":..., "name"/"title":..., "price":{...}} ]}
        На выходе:
          {
            "product_id": int,
            "name": str,
            "offer_id": str|None,
            "barcode": str|None,
            "category_id": int|None,
            "category_name": str|None,
            "price": str|float|None,
            "old_price": str|float|None,
            "min_price": str|float|None,
            "marketing_price": str|float|None,
          }
        """
        product_id = raw.get("product_id") or raw.get("id")
        name = raw.get("name") or raw.get("title") or ""
        offer_id = raw.get("offer_id")
        barcodes = raw.get("barcodes") or []
        barcode = None
        if isinstance(barcodes, list) and barcodes:
            barcode = str(barcodes[0])

        category_name = raw.get("category_name")
        category_id = raw.get("category_id") or raw.get("description_category_id")

        # цена в v3 часто строкой на верхнем уровне; в v2 бывает вложенный объект price
        price = raw.get("price")
        old_price = raw.get("old_price")
        min_price = raw.get("min_price")
        marketing_price = raw.get("marketing_price")

        if isinstance(raw.get("price"), dict):
            # v2: {"price": {"price":"...", "old_price":"..."}}
            price_obj = raw.get("price") or {}
            price = price_obj.get("price") or price
            old_price = price_obj.get("old_price") or old_price
            min_price = price_obj.get("min_price") or min_price
            marketing_price = price_obj.get("marketing_price") or marketing_price

        out = {
            "product_id": product_id,
            "name": name,
            "offer_id": offer_id,
            "barcode": barcode,
            "category_id": category_id,
            "category_name": category_name,
            "price": price,
            "old_price": old_price,
            "min_price": min_price,
            "marketing_price": marketing_price,
        }
        return out

    def get_products_info(self, ids: List[int]) -> List[dict]:
        """
        Возвращает список НОРМАЛИЗОВАННЫХ продуктов (chunk по 500 id) — независимо от версии API.
        """
        out: List[dict] = []
        CHUNK = 500
        for i in range(0, len(ids), CHUNK):
            chunk = ids[i:i + CHUNK]
            logging.info("INFO[product_id]: пачка=%s | first=%s | last=%s", len(chunk), chunk[0], chunk[-1])

            data = self._product_info_list(chunk)

            # v3: {"items":[{...}, {...}]}
            items = data.get("items")
            # v2: {"result":[{...}]}
            if items is None:
                items = data.get("result")

            if isinstance(items, list) and items:
                norm = [self._normalize_item(x) for x in items]
                out.extend(norm)
                logging.info("INFO: нормализовано %s", len(norm))
                logging.debug("INFO: пример после нормализации: %s", json.dumps(norm[0], ensure_ascii=False)[:400])
            else:
                logging.info("INFO: пустой ответ на пачку id. Фрагмент: %s", json.dumps(data, ensure_ascii=False)[:400])

            time.sleep(DELAY_BETWEEN_REQUESTS)

        logging.info("INFO: всего нормализовано %s записей", len(out))
        return out

    def build_index(self) -> Tuple[Dict[str, List[dict]], Dict[int, dict]]:
        """
        Собирает два индекса:
          - index_by_category: { str(category_name или category_id): [product_dict, ...] }
          - index_by_id: { product_id: product_dict }
        """
        ids = self.get_all_my_product_ids()
        if not ids:
            logging.error("У аккаунта не найдено ни одного товара (список id пуст).")
            return {}, {}

        info_list = self.get_products_info(ids)
        if not info_list:
            logging.error("Не удалось получить детальную информацию о товарах — список пуст.")
            return {}, {}

        index_by_category: Dict[str, List[dict]] = {}
        index_by_id: Dict[int, dict] = {}

        for p in info_list:
            pid = p.get("product_id")
            if not isinstance(pid, int):
                continue

            name = p.get("name") or ""
            cat_name = p.get("category_name")
            cat_id = p.get("category_id")

            # метка категории: имя если есть, иначе ID, иначе «Без категории»
            cat_key = str(cat_name) if cat_name else (str(cat_id) if cat_id is not None else "Без категории")

            index_by_id[pid] = p
            index_by_category.setdefault(cat_key, []).append({
                "product_id": pid,
                "name": name,
                "offer_id": p.get("offer_id"),
                "barcode": p.get("barcode"),
                "category_name": cat_name,
                "category_id": cat_id,
                "price": p.get("price"),
                "old_price": p.get("old_price"),
                "min_price": p.get("min_price"),
                "marketing_price": p.get("marketing_price"),
            })

        # сортируем внутри категорий по названию
        for cat, items in index_by_category.items():
            items.sort(key=lambda x: (x.get("name") or "").lower())

        logging.info("INDEX: категорий=%s; примеры: %s", len(index_by_category), list(index_by_category.keys())[:10])
        return index_by_category, index_by_id
