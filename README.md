# Gym Store For Members

A self-contained WordPress plugin (no WooCommerce) that scrapes wholesale
supplement products from a supplier site and presents them in a member-facing
shop. Gym members log in with WordPress accounts and **request** products they
want ordered. The admin exports a CSV of requests to cross-reference with active
members in Glofox before placing the wholesale order.

See [PLAN.md](PLAN.md) for the full architecture and roadmap.

## Features

- **Supplier-agnostic scraping** — you log into the supplier in your browser and
  paste your session cookie; the plugin never stores supplier passwords.
- **Category crawl** — give it a list of category URLs; it follows pagination,
  finds every product link, and extracts each product page.
- **Robust extraction** using web standards first: schema.org **JSON-LD** →
  **OpenGraph** meta tags → optional **OpenAI** fallback → legacy XPath.
- **Batched background processing** with a live progress bar, so a full-catalogue
  scrape never hits a request timeout. Jobs resume after a page reload.
- Captures **thumbnail, title, wholesale price, and stock status**.
- **Member shop** via `[gym_shop]` shortcode — shows only in-stock products.
- Members **request** items; admin sees all requests and exports them as CSV.
- Admin sets a **display price** (shown in EUR) per product, separate from the
  scraped wholesale price.
- `[gym_account]` shortcode lets members see their own requests and statuses.
- No payment processing — members pay on arrival. No automated notifications —
  the admin notifies members via Glofox.

## Installation

**Option A — Upload zip**

1. Run `./build_zip.ps1` (or use the committed `gym-store-for-members.zip`).
2. In WP Admin go to **Plugins → Add New → Upload Plugin**, choose the zip,
   install, and activate.

> The build script produces forward-slash zip paths. Do not use Windows'
> `Compress-Archive` — it writes backslash paths that break installs on Linux
> WordPress servers.

**Option B — Git clone on the server**

```bash
cd wp-content/plugins
git clone https://github.com/MetricMike1991/Gym-Store-For-Members gym-store-for-members
```

Then activate **Gym Store For Members** in WP Admin.

## Setup

1. Log into the supplier site in your browser.
2. Copy your **session cookie**: open DevTools (F12) → Application/Storage →
   Cookies (or copy the full `Cookie` request header from the Network tab).
3. Go to **Supplement Shop → Settings** and:
   - Paste the **session cookie**.
   - Add your **category URLs**, one per line
     (e.g. `https://protaminonutrition.com/product-category/creatine/`).
   - Optionally set the **product link pattern** (default `/product/`).
   - Optionally enable the **AI fallback** and add an OpenAI key — only needed
     for suppliers with no structured data.
4. Go to **Supplement Shop → Products** and click **Scrape Now**. Watch the
   progress bar; the page refreshes when the job completes.
5. Set your **display price** per product and toggle visibility as needed.
6. Add `[gym_shop]` to a page for the shop, and `[gym_account]` to a page for
   members to view their requests.

### How extraction works

For each product page the scraper tries, in order: schema.org **JSON-LD** →
**OpenGraph** meta tags → **OpenAI** (if enabled) → legacy XPath. The first two
are web standards published by most modern stores, so it works across different
suppliers without configuration.

### Refreshing the catalogue

Session cookies expire after a day or two. Since you typically refresh weekly or
fortnightly, just grab a fresh cookie before each scrape. Re-run **Scrape Now**
to update prices and stock; existing products are matched by SKU (or URL) and
updated in place — your display prices are preserved.

## Placing an order

1. Go to **Supplement Shop → Requests**.
2. Filter by status if needed and click **Export CSV**.
3. Cross-reference with active Glofox members, place the wholesale order.
4. When stock arrives, set requests to **Arrived** and notify members via Glofox.

## Security

- All AJAX and form actions are nonce-protected (CSRF).
- All database access uses `$wpdb->prepare()`.
- The OpenAI key and the legacy supplier password are AES-256-CBC encrypted using
  a key derived from WordPress salts. The pasted session cookie is a short-lived
  bearer token stored as-is.
- Admin actions require the `manage_options` capability.

## Requirements

- WordPress 5.8+
- PHP 7.4+ with the OpenSSL and DOM extensions (standard on most hosts).
