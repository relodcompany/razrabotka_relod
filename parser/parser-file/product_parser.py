from bs4 import BeautifulSoup
from urllib.parse import urljoin

def parse_product_page(session, product_url):
    resp = session.get(product_url)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "lxml")

    def text(sel):
        el = soup.select_one(sel)
        return el.get_text(strip=True) if el else ""

    title         = text("h1")
    author        = text("div.titleContributor h2 a")
    series        = text("li.series span + span a")
    format_       = text("li.format ul.col2 li.format_title")
    pages         = text("li.format ul.col2 li.pagination")

    publisher = imprint = ""
    for li in soup.select("li.imprint"):
        spans = li.find_all("span")
        if spans and len(spans) >= 2:
            label = spans[0].get_text(strip=True)
            val   = spans[1].get_text(strip=True)
            if label.startswith("Publisher"):
                publisher = val
            elif label.startswith("Imprint"):
                imprint = val

    isbn = text("li.isbn span:nth-of-type(2)")

    # --- Исправленный Published ---
    published_element = soup.find('li', class_='published')
    if published_element:
        spans = published_element.find_all('span')
        published = spans[1].get_text(strip=True) if len(spans) > 1 else ''
    else:
        published = ''

    classifications = ", ".join(
        a.get_text(strip=True)
        for a in soup.select("li.classifications ul li a")
    )

    weight = ""
    for li in soup.select("li.format"):
        spans = li.find_all("span")
        if spans and spans[0].get_text(strip=True).startswith("Weight"):
            weight = spans[1].get_text(strip=True)
            break

    dimensions    = text("li.dimensions span + span")
    unit          = text("li.dimensions span.unitOfMeasure")
    pub_country   = text("li.pubCountry span + span")
    country_origin= text("li.countryOfOrigin span + span")
    description   = text("div.productDescription")

    price_tag = soup.select_one("p.youPay > span.hideInclusiveVat")
    if price_tag:
        price = price_tag.get_text(strip=True)
    else:
        price = text("p.rrp span.retailPrice")

    avail = soup.select_one("div.availability")
    availability = avail["data-copies"] if (avail and avail.has_attr("data-copies")) else ""

    img = soup.select_one("img.productImage")
    image_url = urljoin(resp.url, img["src"]) if img else ""

    return {
        "URL":             product_url,
        "Title":           title,
        "Author":          author,
        "Series":          series,
        "Format":          format_,
        "Pages":           pages,
        "Publisher":       publisher,
        "Imprint":         imprint,
        "ISBN":            isbn,
        "Published":       published,
        "Classifications": classifications,
        "Weight":          weight,
        "Dimensions":      f"{dimensions} {unit}".strip(),
        "Pub Country":     pub_country,
        "Country Origin":  country_origin,
        "Description":     description,
        "Price":           price,
        "Availability":    availability,
        "Image URL":       image_url,
    }

