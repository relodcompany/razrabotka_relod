# gui.py
import threading
import tkinter as tk
from tkinter import ttk, messagebox
from typing import List

from ozon_api import OzonAPI
from competitor import find_top_competitor_offers_by_isbn, DEFAULT_UA
from config import DELAY_BETWEEN_REQUESTS, OZON_COOKIES


LOG_MAX_LINES = 2000


# ---------- универсальные биндинги копирования/вставки ----------

def _bind_copy_only(widget: tk.Widget):
    """Для Listbox/читалок: только копирование (Ctrl+C и контекст Copy)."""
    def _copy_event(_e=None):
        try:
            sel = widget.selection_get()
        except Exception:
            # Для Listbox соберём строками
            if isinstance(widget, tk.Listbox):
                indices = widget.curselection()
                lines = [widget.get(i) for i in indices]
                sel = "\n".join(lines)
            else:
                sel = ""
        if sel:
            widget.clipboard_clear()
            widget.clipboard_append(sel)
        return "break"

    widget.bind("<Control-c>", _copy_event)
    widget.bind("<Control-C>", _copy_event)

    # контекстное меню (только Copy)
    menu = tk.Menu(widget, tearoff=0)
    menu.add_command(label="Copy", command=_copy_event)

    def _popup(e):
        try:
            menu.tk_popup(e.x_root, e.y_root)
        finally:
            menu.grab_release()

    widget.bind("<Button-3>", _popup)   # Windows
    widget.bind("<Button-2>", _popup)   # macOS middle


def _bind_copy_paste(widget: tk.Widget):
    """Для Text/Entry: Copy/Paste/Cut + контекстное меню."""
    def _copy(_=None):
        try:
            sel = widget.selection_get()
            widget.clipboard_clear()
            widget.clipboard_append(sel)
        except Exception:
            pass
        return "break"

    def _paste(_=None):
        try:
            data = widget.clipboard_get()
        except Exception:
            data = ""
        if not data:
            return "break"
        try:
            widget.insert("insert", data)
        except Exception:
            pass
        return "break"

    def _cut(_=None):
        try:
            sel = widget.selection_get()
            widget.clipboard_clear()
            widget.clipboard_append(sel)
            widget.delete("sel.first", "sel.last")
        except Exception:
            pass
        return "break"

    widget.bind("<Control-c>", _copy)
    widget.bind("<Control-C>", _copy)
    widget.bind("<Control-v>", _paste)
    widget.bind("<Control-V>", _paste)
    widget.bind("<Control-x>", _cut)
    widget.bind("<Control-X>", _cut)

    menu = tk.Menu(widget, tearoff=0)
    menu.add_command(label="Cut", command=_cut)
    menu.add_command(label="Copy", command=_copy)
    menu.add_command(label="Paste", command=_paste)

    def _popup(e):
        try:
            menu.tk_popup(e.x_root, e.y_root)
        finally:
            menu.grab_release()

    widget.bind("<Button-3>", _popup)
    widget.bind("<Button-2>", _popup)


# ---------- лог-панель ----------

class LogPane(ttk.Frame):
    def __init__(self, master):
        super().__init__(master)
        self.text = tk.Text(self, height=14, wrap="word")
        self.text.configure(state="disabled")
        # Разрешаем выделение и копирование
        _bind_copy_only(self.text)

        sb = ttk.Scrollbar(self, command=self.text.yview)
        self.text.configure(yscrollcommand=sb.set)
        self.text.grid(row=0, column=0, sticky="nsew")
        sb.grid(row=0, column=1, sticky="ns")
        self.grid_rowconfigure(0, weight=1)
        self.grid_columnconfigure(0, weight=1)

    def write(self, line: str):
        self.text.configure(state="normal")
        self.text.insert("end", line.rstrip() + "\n")
        # ограничиваем количество строк
        if int(self.text.index('end-1c').split('.')[0]) > LOG_MAX_LINES:
            self.text.delete("1.0", "2.0")
        self.text.see("end")
        self.text.configure(state="disabled")
        self.update_idletasks()


# ---------- приложение ----------

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Ozon Парсер — выбор товаров и парсинг конкурентов")
        self.geometry("1100x760")
        self.minsize(1000, 650)

        self.api = OzonAPI()
        self.index_by_category = {}
        self.index_by_id = {}

        self._build_ui()
        threading.Thread(target=self._load_data, daemon=True).start()

    # --- UI ---
    def _build_ui(self):
        paned = ttk.PanedWindow(self, orient=tk.HORIZONTAL)
        paned.pack(fill=tk.BOTH, expand=True, padx=8, pady=8)

        # ЛЕВО — категории
        left = ttk.Frame(paned)
        self.categories = tk.Listbox(left, selectmode=tk.EXTENDED, exportselection=False)
        self.categories.bind("<<ListboxSelect>>", self._on_category_select)
        ttk.Label(left, text="Категории (можно выбрать несколько)").pack(anchor="w")
        self.categories.pack(fill=tk.BOTH, expand=True)
        paned.add(left, weight=1)
        _bind_copy_only(self.categories)

        # ЦЕНТР — товары выбранных категорий
        mid = ttk.Frame(paned)
        self.products = tk.Listbox(mid, selectmode=tk.EXTENDED, exportselection=False)
        ttk.Label(mid, text="Товары (двойной клик — отметить/снять)").pack(anchor="w")
        self.products.pack(fill=tk.BOTH, expand=True)
        self.products.bind("<Double-1>", lambda e: None)
        paned.add(mid, weight=2)
        _bind_copy_only(self.products)

        # ПРАВО — выбрано к анализу
        right = ttk.Frame(paned)
        self.selected = tk.Listbox(right, selectmode=tk.EXTENDED, exportselection=False)
        ttk.Label(right, text="Выбрано к анализу").pack(anchor="w")
        self.selected.pack(fill=tk.BOTH, expand=True)

        btns = ttk.Frame(right)
        ttk.Button(btns, text="Добавить выбранные →", command=self._add_selected).grid(row=0, column=0, padx=2, pady=2)
        ttk.Button(btns, text="← Удалить выделенные", command=self._remove_selected).grid(row=0, column=1, padx=2, pady=2)
        ttk.Button(btns, text="Очистить список", command=self._clear_selected).grid(row=0, column=2, padx=2, pady=2)
        btns.pack(fill=tk.X, pady=6)
        paned.add(right, weight=1)
        _bind_copy_only(self.selected)

        # НИЗ — управление, поиск и лог
        bottom = ttk.Frame(self)
        bottom.pack(fill=tk.BOTH, expand=False, padx=8, pady=(0, 8))

        # Ручной ввод ISBN
        isbn_frame = ttk.LabelFrame(bottom, text="Ручной ввод ISBN (по одному в строке)")
        isbn_frame.pack(fill=tk.X, pady=6)
        self.isbn_text = tk.Text(isbn_frame, height=4, wrap="none")
        self.isbn_text.pack(fill=tk.X, padx=6, pady=6)
        _bind_copy_paste(self.isbn_text)
        # следим за изменениями для активации кнопки
        self.isbn_text.bind("<<Modified>>", lambda e: self._on_text_modified(self.isbn_text))

        # Поиск по названию/ISBN (визуальная фильтрация списка)
        search_frame = ttk.LabelFrame(bottom, text="Поиск товара по названию или ISBN (фильтрует список ниже)")
        search_frame.pack(fill=tk.X, pady=6)
        sf = ttk.Frame(search_frame)
        sf.pack(fill=tk.X, padx=6, pady=6)
        ttk.Label(sf, text="Строка поиска:").grid(row=0, column=0, sticky="w")
        self.search_var = tk.StringVar(value="")
        entry = ttk.Entry(sf, textvariable=self.search_var, width=50)
        entry.grid(row=0, column=1, sticky="w", padx=6)
        _bind_copy_paste(entry)
        ttk.Button(sf, text="Найти в списке", command=self._filter_products).grid(row=0, column=2, padx=6)

        self.progress = ttk.Progressbar(bottom, orient=tk.HORIZONTAL, mode="determinate")
        self.progress.pack(fill=tk.X, pady=6)

        ctrl = ttk.Frame(bottom)
        self.btn_run = ttk.Button(
            ctrl,
            text="Старт анализа конкурентов (по ISBN)",
            command=self._start_run,
            state=tk.DISABLED
        )
        self.btn_run.grid(row=0, column=0, padx=4)
        ttk.Label(ctrl, text="Задержка между карточками, сек:").grid(row=0, column=1, padx=(10, 2))
        self.delay_var = tk.StringVar(value=str(DELAY_BETWEEN_REQUESTS))
        delay_entry = ttk.Entry(ctrl, textvariable=self.delay_var, width=6)
        delay_entry.grid(row=0, column=2)
        _bind_copy_paste(delay_entry)
        ctrl.pack(fill=tk.X)

        self.logger = LogPane(self)
        self.logger.pack(fill=tk.BOTH, expand=True, padx=8, pady=(0, 8))

        if OZON_COOKIES:
            self._log(f"Куки OZON обнаружены (длина строки: {len(OZON_COOKIES)}). Playwright будет их использовать.")
        else:
            self._log("⚠ Куки OZON не заданы. Вероятен антибот «Доступ ограничен». Заполни OZON_COOKIES в .env (JSON или header-строка).")

        # обновляем состояние кнопки на старте
        self._update_run_button_state()

    # --- загрузка данных из API ---
    def _load_data(self):
        self._log("Загрузка списка товаров/категорий из Ozon API…")
        try:
            self.index_by_category, self.index_by_id = self.api.build_index()
            cats = list(self.index_by_category.keys())
            cats.sort(key=lambda x: str(x))
            self.categories.delete(0, "end")
            for cat in cats:
                cnt = len(self.index_by_category[cat])
                self.categories.insert("end", f"{cat}  ({cnt})")
            self._log(f"Готово. Категорий: {len(cats)}")
            # если данные загрузились — не блокируем кнопку, но окончательно решаем по содержимому
            self._update_run_button_state()
        except Exception as e:
            self._log(f"Ошибка загрузки: {e}")
            messagebox.showerror("Ошибка", str(e))
            self._update_run_button_state()

    # --- вспомогательные обработчики ---
    def _on_text_modified(self, text_widget: tk.Text):
        try:
            # Сбрасываем флаг модификации, иначе событие будет крутиться
            text_widget.edit_modified(False)
        except Exception:
            pass
        self._update_run_button_state()

    def _on_category_select(self, _evt=None):
        sel = self.categories.curselection()
        self.products.delete(0, "end")
        if not sel:
            self._update_run_button_state()
            return
        items = []
        for idx in sel:
            row = self.categories.get(idx)
            cat_key = row.rsplit("  (", 1)[0]
            for p in self.index_by_category.get(cat_key, []):
                isbn = p.get("barcode") or p.get("offer_id") or ""
                title = p.get("name") or ("#" + str(p.get("product_id") or ""))
                label = f"{isbn} — {title}" if isbn else title
                items.append(label)
        items.sort(key=lambda t: t.lower())
        for it in items:
            self.products.insert("end", it)
        self._update_run_button_state()

    def _add_selected(self):
        sel = self.products.curselection()
        if not sel:
            return
        existing = set(self.selected.get(0, "end"))
        added = 0
        for i in sel:
            line = self.products.get(i)
            if line not in existing:
                self.selected.insert("end", line)
                added += 1
        self._log(f"Добавлено: {added}")
        self._update_run_button_state()

    def _remove_selected(self):
        sel = list(self.selected.curselection())
        sel.reverse()
        for i in sel:
            self.selected.delete(i)
        self._update_run_button_state()

    def _clear_selected(self):
        self.selected.delete(0, "end")
        self._update_run_button_state()

    def _filter_products(self):
        query = (self.search_var.get() or "").strip().lower()
        if not query:
            messagebox.showinfo("Поиск", "Введите строку (название или ISBN) и нажмите «Найти».")
            return
        all_items = list(self.products.get(0, "end"))
        self.products.delete(0, "end")
        for it in all_items:
            if query in it.lower():
                self.products.insert("end", it)
        self._log(f"Отфильтровано по '{query}': {self.products.size()} записей.")

    # --- бизнес-логика ---
    def _collect_isbns(self) -> List[str]:
        isbns: List[str] = []

        # из ручного ввода
        raw = self.isbn_text.get("1.0", "end").strip()
        for line in raw.splitlines():
            s = line.strip()
            if s:
                isbns.append(s)

        # из выбранных справа
        sel = list(self.selected.get(0, "end"))
        for line in sel:
            if "—" in line:
                isbn = line.split("—", 1)[0].strip()
                if isbn and isbn not in isbns:
                    isbns.append(isbn)

        out: List[str] = []
        for s in isbns:
            s2 = s.replace(" ", "").replace("-", "")
            if s2:
                out.append(s2)
        return out

    def _update_run_button_state(self):
        """Включаем кнопку, если есть что анализировать (есть ISBN)."""
        has_manual = bool(self.isbn_text.get("1.0", "end").strip())
        has_selected = self.selected.size() > 0
        if has_manual or has_selected:
            self.btn_run.configure(state=tk.NORMAL)
        else:
            self.btn_run.configure(state=tk.DISABLED)

    def _start_run(self):
        try:
            isbns = self._collect_isbns()
            if not isbns:
                messagebox.showinfo("Внимание", "Добавьте товары справа или введите ISBN вручную (по одному в строке).")
                self._update_run_button_state()
                return

            try:
                delay = float(self.delay_var.get())
            except Exception:
                delay = float(DELAY_BETWEEN_REQUESTS)

            # блокируем кнопку только на время выполнения
            self.btn_run.configure(state=tk.DISABLED)
            self.progress.configure(value=0, maximum=len(isbns))
            t = threading.Thread(target=self._run_worker, args=(isbns, delay), daemon=True)
            t.start()
        except Exception as e:
            self._log(f"Ошибка запуска: {e}")
            # если что-то пошло не так — не держим кнопку выключенной
            self._update_run_button_state()

    def _run_worker(self, isbns: List[str], delay: float):
        from excel_reporter import write_competitor_report_row, save_workbook
        try:
            self._log(f"Старт анализа по ISBN: {len(isbns)} шт. Задержка {delay}s. Фильтр доставки=8 включён.")
            ok = 0
            for i, isbn in enumerate(isbns, start=1):
                self._log(f"[{i}/{len(isbns)}] Поиск конкурентов по ISBN {isbn} …")
                try:
                    offers = find_top_competitor_offers_by_isbn(
                        isbn=isbn,
                        max_cards=16,
                        top_n=3,
                        user_agent=DEFAULT_UA,
                    )
                    if offers:
                        title = offers[0].get("title") or ""
                        write_competitor_report_row(
                            isbn=isbn,
                            title=title,
                            our_price=None,
                            competitors=offers,
                        )
                        self._log(f"   ✔ Найдено предложений: {len(offers)} (в Excel записано).")
                        ok += 1
                    else:
                        write_competitor_report_row(
                            isbn=isbn,
                            title="",
                            our_price=None,
                            competitors=[],
                        )
                        self._log("   ⚠ Конкуренты не найдены или антибот ограничил доступ (смотри логи в папке logs).")
                except Exception as e:
                    self._log(f"   ✖ Ошибка: {e}")

                self.progress.configure(value=i)
                self.progress.update_idletasks()
                if delay > 0:
                    import time as _t
                    _t.sleep(delay)

            path = save_workbook()
            self._log(f"Готово. Успешно: {ok}/{len(isbns)}. Excel сохранён: {path}")
            if ok == 0 and not OZON_COOKIES:
                self._log("Похоже, антибот не даёт искать. Заполни OZON_COOKIES в .env (JSON-массив или header-строка).")
        finally:
            # по завершении — всегда возвращаем кнопке готовность в зависимости от содержимого
            self._update_run_button_state()

    def _log(self, msg: str):
        self.logger.write(msg)


def run():
    app = App()
    app.mainloop()


if __name__ == "__main__":
    run()
