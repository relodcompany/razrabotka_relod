"""
Управление историей парсинга
Сохранение и сравнение результатов
"""
import os
import shutil
from datetime import datetime
import logging
from config import HISTORY_DIR

logging.basicConfig(level=logging.INFO)

class HistoryManager:
    """Класс для управления историей парсинга"""
    
    def __init__(self):
        self.history_dir = HISTORY_DIR
        
        # Создаем папку истории если не существует
        if not os.path.exists(self.history_dir):
            os.makedirs(self.history_dir)
    
    def save_to_history(self, excel_file_path):
        """
        Сохраняет файл отчета в архив истории
        
        Args:
            excel_file_path: Путь к файлу Excel отчета
        """
        try:
            timestamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
            
            # Создаем папку для текущей даты
            date_folder = os.path.join(self.history_dir, datetime.now().strftime("%Y-%m-%d"))
            if not os.path.exists(date_folder):
                os.makedirs(date_folder)
            
            # Копируем файл в папку истории
            filename = f"results_{timestamp}.xlsx"
            destination = os.path.join(date_folder, filename)
            
            shutil.copy2(excel_file_path, destination)
            
            logging.info(f"Результаты сохранены в историю: {destination}")
            return destination
            
        except Exception as e:
            logging.error(f"Ошибка сохранения в историю: {e}")
            return None
    
    def get_history_files(self, limit=10):
        """
        Получает список последних файлов истории
        
        Args:
            limit: Максимальное количество файлов
            
        Returns:
            list: Список путей к файлам
        """
        files = []
        
        try:
            # Проходим по всем папкам с датами
            for date_folder in sorted(os.listdir(self.history_dir), reverse=True):
                folder_path = os.path.join(self.history_dir, date_folder)
                
                if os.path.isdir(folder_path):
                    # Получаем файлы из папки
                    for filename in sorted(os.listdir(folder_path), reverse=True):
                        if filename.endswith('.xlsx'):
                            file_path = os.path.join(folder_path, filename)
                            files.append(file_path)
                            
                            if len(files) >= limit:
                                return files
        
        except Exception as e:
            logging.error(f"Ошибка получения истории: {e}")
        
        return files
    
    def compare_periods(self, file1_path, file2_path):
        """
        Сравнивает результаты двух периодов
        
        Args:
            file1_path: Путь к первому файлу
            file2_path: Путь к второму файлу
            
        Returns:
            dict: Результаты сравнения
        """
        # Эта функция может быть расширена для детального сравнения
        # Здесь базовая реализация
        
        try:
            comparison = {
                'file1': file1_path,
                'file2': file2_path,
                'file1_date': os.path.basename(file1_path).split('_')[1],
                'file2_date': os.path.basename(file2_path).split('_')[1],
                'message': 'Сравнение доступно. Откройте оба файла для анализа.'
            }
            
            return comparison
            
        except Exception as e:
            logging.error(f"Ошибка сравнения периодов: {e}")
            return None
    
    def cleanup_old_history(self, days_to_keep=90):
        """
        Удаляет старые файлы истории
        
        Args:
            days_to_keep: Количество дней для хранения
        """
        try:
            from datetime import timedelta
            
            cutoff_date = datetime.now() - timedelta(days=days_to_keep)
            
            for date_folder in os.listdir(self.history_dir):
                folder_path = os.path.join(self.history_dir, date_folder)
                
                if os.path.isdir(folder_path):
                    try:
                        folder_date = datetime.strptime(date_folder, "%Y-%m-%d")
                        
                        if folder_date < cutoff_date:
                            shutil.rmtree(folder_path)
                            logging.info(f"Удалена старая папка истории: {date_folder}")
                    except:
                        pass
                        
        except Exception as e:
            logging.error(f"Ошибка очистки истории: {e}")


# Пример использования
if __name__ == "__main__":
    history_manager = HistoryManager()
    
    # Получаем последние файлы истории
    recent_files = history_manager.get_history_files(limit=5)
    
    print(f"\nПоследние {len(recent_files)} файлов истории:")
    for i, file_path in enumerate(recent_files, 1):
        print(f"{i}. {file_path}")