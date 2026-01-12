# pw_check.py
from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto("https://example.com", wait_until="domcontentloaded")
    print("Title:", page.title())
    print("UA:", page.evaluate("navigator.userAgent"))
    browser.close()

