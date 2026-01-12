"""
Отправка уведомлений (Email и Telegram)
"""
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
import logging
from config import (
    EMAIL_RECIPIENT, TELEGRAM_ENABLED, TELEGRAM_CHAT_ID,
    CRITICAL_PRICE_DIFF_PERCENT,
    EMAIL_SMTP, EMAIL_PORT, EMAIL_USER, EMAIL_PASS, EMAIL_FROM
)

logging.basicConfig(level=logging.INFO)

class NotificationManager:
    """Класс для отправки уведомлений"""

    def __init__(self):
        self.email_recipient = EMAIL_RECIPIENT
        self.telegram_enabled = TELEGRAM_ENABLED
        self.telegram_chat_id = TELEGRAM_CHAT_ID
        self.critical_diff = CRITICAL_PRICE_DIFF_PERCENT

    def send_completion_notification(self, stats, critical_items, excel_file_path):
        """
        Отправляет уведомление о завершении парсинга

        Args:
            stats: Статистика парсинга (словарь)
            critical_items: Список критических товаров
            excel_file_path: Путь к файлу отчета
        """
        # Формируем сообщение
        message = self._create_message(stats, critical_items, excel_file_path)

        # Отправляем Email
        if self.email_recipient:
            self._send_email(message)

        # Отправляем в Telegram
        if self.telegram_enabled and self.telegram_chat_id:
            self._send_telegram(message)

    def send_error_notification(self, error_message):
        """
        Отправляет уведомление об ошибке

        Args:
            error_message: Текст ошибки
        """
        message = f"⚠️ ОШИБКА ПАРСЕРА OZON\n\n{error_message}"

        if self.email_recipient:
            self._send_email(message, subject="Ошибка парсера Ozon")

        if self.telegram_enabled and self.telegram_chat_id:
            self._send_telegram(message)

    def _create_message(self, stats, critical_items, excel_file_path):
        """Создает текст сообщения"""
        message = f"""
📊 ОТЧЕТ О ПАРСИНГЕ ЦЕН OZON

✅ Парсинг завершен успешно!

📈 СТАТИСТИКА:
• Всего проверено товаров: {stats.get('total', 0)}
• Найдено конкурентов: {stats.get('found', 0)}
• Не найдено: {stats.get('not_found', 0)}
• Ошибок парсинга: {stats.get('errors', 0)}

⚠️ КРИТИЧЕСКИЕ ПОЗИЦИИ:
Найдено товаров с разницей > {self.critical_diff}%: {len(critical_items)}
"""

        # Добавляем топ-5 критических товаров
        if critical_items:
            message += "\n🔥 ТОП-5 ТОВАРОВ С НАИБОЛЬШЕЙ РАЗНИЦЕЙ:\n"

            sorted_items = sorted(
                critical_items,
                key=lambda x: abs(x.get('price_diff_percent', 0) or 0),
                reverse=True
            )[:5]

            for i, item in enumerate(sorted_items, 1):
                title = item.get('title', 'Без названия')[:50]
                diff = item.get('price_diff_percent', 0)
                message += f"\n{i}. {title}\n   Разница: {diff:.1f}%\n"

        message += f"\n📁 Отчет сохранен: {excel_file_path}\n"
        message += f"\n🕐 Дата: {stats.get('date', '')}\n"

        return message

    def _send_email(self, message, subject="Отчет о парсинге Ozon"):
        """
        Отправляет Email уведомление.
        Требуются переменные из .env: EMAIL_SMTP, EMAIL_PORT, EMAIL_USER, EMAIL_PASS, EMAIL_FROM, EMAIL_RECIPIENT.
        """
        try:
            # Настройки SMTP из config.py
            smtp_server = EMAIL_SMTP
            smtp_port = EMAIL_PORT
            sender_email = EMAIL_FROM or EMAIL_USER
            sender_password = EMAIL_PASS

            # Валидация настроек
            if not (smtp_server and smtp_port and EMAIL_USER and sender_password and self.email_recipient):
                raise ValueError("Не заданы параметры SMTP или EMAIL_RECIPIENT")

            # Формируем письмо
            msg = MIMEMultipart()
            msg['From'] = sender_email
            msg['To'] = self.email_recipient
            msg['Subject'] = subject
            msg.attach(MIMEText(message, 'plain', 'utf-8'))

            # TLS (587) или SSL (465)
            if str(smtp_port) == "465":
                with smtplib.SMTP_SSL(smtp_server, smtp_port) as server:
                    server.login(EMAIL_USER, sender_password)
                    server.send_message(msg)
            else:
                with smtplib.SMTP(smtp_server, smtp_port) as server:
                    server.ehlo()
                    server.starttls()
                    server.ehlo()
                    server.login(EMAIL_USER, sender_password)
                    server.send_message(msg)

            logging.info(f"Email уведомление отправлено на {self.email_recipient}")

        except Exception as e:
            logging.error(f"Ошибка отправки Email: {e}")
            logging.warning("Для работы Email-уведомлений заполните SMTP-параметры в .env и config.py")

    def _send_telegram(self, message):
        """
        Отправляет Telegram уведомление

        ВАЖНО: Для работы нужен Telegram Bot Token
        """
        try:
            import requests

            # TODO: замените на реальный токен бота
            bot_token = "YOUR_TELEGRAM_BOT_TOKEN"

            url = f"https://api.telegram.org/bot{bot_token}/sendMessage"

            payload = {
                'chat_id': self.telegram_chat_id,
                'text': message,
                'parse_mode': 'HTML'
            }

            response = requests.post(url, json=payload)

            if response.status_code == 200:
                logging.info("Telegram уведомление отправлено")
            else:
                logging.error(f"Ошибка отправки Telegram: {response.text}")

        except Exception as e:
            logging.error(f"Ошибка отправки Telegram: {e}")
            logging.warning("Для работы Telegram уведомлений настройте Bot Token в notifications.py")


# Пример самостоятельного запуска
if __name__ == "__main__":
    notifier = NotificationManager()

    test_stats = {
        'total': 100,
        'found': 75,
        'not_found': 20,
        'errors': 5,
        'date': '2025-10-31 16:00:00'
    }

    test_critical_items = [
        {'title': 'Тестовая книга 1', 'price_diff_percent': -25.5},
        {'title': 'Тестовая книга 2', 'price_diff_percent': -30.2}
    ]

    message = notifier._create_message(test_stats, test_critical_items, "output/test.xlsx")
    print(message)
