from ozon_api import OzonAPI

api = OzonAPI()
ids = api.get_all_my_product_ids()
print("Всего товаров:", len(ids))
print("Первые 20:", ids[:20])

index_by_category, index_by_id = api.build_index()
print("Категорий:", len(index_by_category))
for cat, items in list(index_by_category.items())[:5]:
    print(f"- {cat}: {len(items)} шт. (пример: {items[0]['name'] if items else '—'})")
