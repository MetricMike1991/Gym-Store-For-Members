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

1. Admin stores login URL + credentials (AES-256-CBC encrypted) + XPath selectors
   in `wp_options` via the Settings page.
2. Admin clicks **Scrape Now** → nonce-protected AJAX → `Scraper::run()`.
3. `wp_remote_post()` to login form URL, capture session cookie from `Set-Cookie`.
4. Loop paginated listing pages with `wp_remote_get()` + cookie.
5. Parse HTML with `DOMDocument` + `DOMXPath` using stored selectors:
   product container, title, image, price, stock indicator.
6. Upsert each product into `wp_gss_products` (match on `supplier_ref`).
7. Return counts (new / updated / out-of-stock) to the admin UI.

> **Selectors are admin-configurable** so a supplier layout change needs no code
> edit. They still need to be identified once by inspecting the live site
> (DevTools → right-click a product card → Copy → Copy XPath).

## Security

- All AJAX + form posts protected by WordPress nonces (CSRF).
- All DB access via `$wpdb->prepare()` (SQL injection).
- Credentials encrypted with `openssl_encrypt` AES-256-CBC using a key derived
  from WP salts; never stored in plaintext.
- Capability checks (`manage_options`) on every admin action.
- Output escaped with `esc_html` / `esc_attr` / `esc_url`.

---

## Status / Progress

- [x] Repo cloned, PLAN.md committed
- [ ] Plugin scaffolding (main file, activation)
- [ ] Database class
- [ ] Products CRUD
- [ ] Scraper
- [ ] Wishlist
- [ ] CSV export
- [ ] Admin UI (products / requests / settings)
- [ ] Public shop + account shortcodes
- [ ] CSS / JS
- [ ] Selectors tuned for protaminonutrition.com (needs live-site inspection)

## Open Items

1. **XPath selectors for protaminonutrition.com** — identify product container,
   title, image, price, stock selectors from the logged-in listing page.
2. **Pagination pattern** — confirm how the supplier paginates (query param `?page=`,
   `/page/N/`, infinite scroll/AJAX). Affects the scrape loop.
3. **Anti-bot** — if PHP requests get blocked, may need request delays or a headless
   browser step. Test the plain PHP approach first.
