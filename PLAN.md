# Gym Store For Members — Project Plan

A self-contained WordPress plugin (no WooCommerce) that scrapes products from a
wholesaler supplement supplier and presents them in a member-facing shop. Gym
members log in with WordPress accounts and "request" products they want ordered.
The admin exports a CSV of requests to cross-reference with active members in
Glofox when placing the wholesale order.

> This file exists so any chat session / agent can pick up the project with full
> context. If you are an AI agent reading this: read this whole file first.

---

## Business Context

- **Owner**: Gym operator who buys supplements wholesale and resells to members.
- **Supplier**: https://protaminonutrition.com/ (login required to see prices).
- **Members**: Gym clients who log in and mark products they want ordered.
- **Flow**: Scrape supplier stock → members request items → admin exports CSV →
  admin manually cross-references with Glofox membership → places wholesale order →
  notifies members via Glofox when stock arrives.

## Key Decisions

| Decision | Choice |
| --- | --- |
| Platform | Custom WordPress plugin (NO WooCommerce) |
| Payment | None in v1 — members pay on arrival |
| Notifications | None in code — admin uses Glofox built-in notifications |
| Member status | Admin cross-references Glofox manually; plugin just exports requests |
| Product fields | Thumbnail image, title, wholesale price, stock status |
| Pricing | Store supplier price; admin sets a display price (shown in EUR to members) |
| Images | Hotlinked from supplier in v1 (not re-hosted locally) |
| Auth | Native WordPress user accounts |
| Deployment | Zip + upload via WP Admin, OR git clone into wp-content/plugins |
| Testing | Directly on the live site (no local WP environment) |

## Out of Scope (v1)

- WP-Cron automated scraping (manual "Scrape Now" button only)
- Product variants (size / flavour)
- Category filtering / search
- Cart / checkout / payment processing
- Automated email / SMS
- Glofox API integration

---

## Architecture

Repo root **is** the plugin folder. Plugin slug: `gym-store-for-members`.

```
gym-store-for-members.php     Main file: header, constants, activation, bootstrap
uninstall.php                 Cleanup on plugin delete
includes/
  class-database.php          Creates/updates custom DB tables on activation
  class-products.php          CRUD for wp_gss_products
  class-scraper.php           Supplier login + scrape + parse + upsert
  class-wishlist.php          Add/remove/list member requests
  class-export.php            Streams CSV of requests
admin/
  class-admin.php             Admin menu, asset enqueue, AJAX handlers
  views/
    products.php              Product table + Scrape Now + price/visibility editing
    requests.php              All member requests + status controls + export button
    settings.php              Supplier login URL, credentials (encrypted), selectors
public/
  class-public.php            Shortcodes, asset enqueue, front-end AJAX handlers
  views/
    shop.php                  [gym_shop] product grid
    account.php               [gym_account] member's own requests
  css/style.css
  js/shop.js                  AJAX request/cancel toggle
```

## Database Tables

**`wp_gss_products`**

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT PK AI | |
| supplier_ref | VARCHAR(191) | SKU / unique key for upsert matching |
| title | VARCHAR(255) | |
| image_url | TEXT | Hotlinked supplier thumbnail |
| supplier_price | DECIMAL(10,2) | Wholesale price scraped |
| display_price | DECIMAL(10,2) | Price shown to members (admin set) |
| in_stock | TINYINT(1) | 1 = in stock, 0 = out |
| visible | TINYINT(1) | Admin can hide a product |
| last_scraped | DATETIME | |

**`wp_gss_wishlist`**

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT PK AI | |
| user_id | BIGINT | WP user |
| product_id | BIGINT | FK to wp_gss_products.id |
| requested_at | DATETIME | |
| status | VARCHAR(20) | pending / arrived / collected |
| notes | TEXT | Optional admin note |

## Scraper Flow

The scraper is **supplier-agnostic** and prefers a manual-login session + category
crawl. It only falls back to programmatic login + XPath when no category URLs are set.

**Primary (recommended):**
1. Admin logs into the supplier in their browser and pastes the **session cookie**
   into Settings, plus a list of **category URLs**.
2. Admin clicks **Scrape Now** → AJAX → `Scraper::run()`.
3. For each category, the crawler fetches the page (following pagination via
   `rel=next` / `.next` links up to a cap) and collects links whose URL contains
   the **product link pattern** (default `/product/`).
4. Each product page is fetched with the session cookie and extracted using the
   first strategy that succeeds:
   - **JSON-LD** schema.org Product (standard, structured, free) ⭐
   - **OpenGraph** / product meta tags (structured, free)
   - **OpenAI** fallback (optional, only if enabled + key set)
5. Products are upserted into `wp_gss_products` keyed on SKU, else product URL.

**Legacy fallback:** two-step programmatic login (harvests the WooCommerce nonce)
+ listing-page XPath selectors. Used only when no category URLs are configured.

## Batched Background Processing

A full-catalogue scrape runs as a batched job so a single request never times out:
- `gsfm_scrape_start` — resets the job, begins the **discover** phase (or runs the
  legacy one-shot scrape when no category URLs are set).
- `gsfm_scrape_step` — the browser calls this repeatedly:
  - **discover** phase: crawls one category per step, collecting product URLs.
  - **process** phase: extracts `BATCH` (5) product pages per step.
- `gsfm_scrape_status` — lets the Products page resume a job after a reload.
- Job state lives in the `gsfm_scrape_job` option (autoload off); a progress bar
  shows discovery count, then `processed / total` with new/updated/skipped tallies.

## Security

- Session cookie and OpenAI key stored in options; key + legacy password are
  AES-256-CBC encrypted. (Session cookie is stored as-is since it is a short-lived
  bearer token the admin pastes deliberately.)
- All AJAX + form posts protected by WordPress nonces (CSRF).
- All DB access via `$wpdb->prepare()` (SQL injection).
- Capability checks (`manage_options`) on every admin action.
- Output escaped with `esc_html` / `esc_attr` / `esc_url`.

---

## Status / Progress

- [x] Repo cloned, PLAN.md committed
- [x] Plugin scaffolding (main file, activation)
- [x] Database class
- [x] Products CRUD
- [x] Scraper (session-cookie crawl + JSON-LD/OpenGraph/AI/XPath extraction)
- [x] Batched background processing with progress bar (resumable)
- [x] Wishlist
- [x] CSV export
- [x] Admin UI (products / requests / settings)
- [x] Public shop + account shortcodes
- [x] CSS / JS
- [x] `build_zip.ps1` for correct forward-slash zips
- [ ] Validated against a real logged-in scrape of protaminonutrition.com

## Open Items

1. **First live scrape** — confirm titles, prices (EUR), images and stock come
   through cleanly. Most WooCommerce/Salient stores expose JSON-LD, so no XPath
   tuning should be needed; the legacy XPath path remains as a fallback.
2. **Session cookie refresh** — cookies expire in ~1–2 days; re-paste before each
   weekly/fortnightly scrape. Future upgrade: bookmarklet or browser extension to
   remove the manual cookie step.
3. **Anti-bot** — if requests get blocked, add per-request delays or a headless
   browser step.
4. **AI fallback** — off by default; enable + add OpenAI key only for suppliers
   with no structured data. Later: use AI for categorization / member recommendations.
