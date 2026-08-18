# Gym Store For Members

A self-contained WordPress plugin (no WooCommerce) that scrapes wholesale
supplement products from a supplier site and presents them in a member-facing
shop. Gym members log in with WordPress accounts and **request** products they
want ordered. The admin exports a CSV of requests to cross-reference with active
members in Glofox before placing the wholesale order.

See [PLAN.md](PLAN.md) for the full architecture and roadmap.

## Features

- **Scrape products** from a login-protected supplier with a single click.
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

1. Zip the repository contents (the `gym-store-for-members.php` file must be at
   the zip root).
2. In WP Admin go to **Plugins → Add New → Upload Plugin**, choose the zip,
   install, and activate.

**Option B — Git clone on the server**

```bash
cd wp-content/plugins
git clone https://github.com/MetricMike1991/Gym-Store-For-Members gym-store-for-members
```

Then activate **Gym Store For Members** in WP Admin.

## Setup

1. Go to **Supplement Shop → Settings**.
2. Enter the supplier **login URL**, **username**, and **password** (stored
   AES-256 encrypted).
3. Enter the **listing URL** and the **XPath selectors** for the product item,
   title, image, price, and stock. Inspect the supplier listing page in your
   browser's DevTools to find these.
4. Go to **Supplement Shop → Products** and click **Scrape Now**.
5. Set your **display price** per product and toggle visibility as needed.
6. Add `[gym_shop]` to a page for the shop, and `[gym_account]` to a page for
   members to view their requests.

## Placing an order

1. Go to **Supplement Shop → Requests**.
2. Filter by status if needed and click **Export CSV**.
3. Cross-reference with active Glofox members, place the wholesale order.
4. When stock arrives, set requests to **Arrived** and notify members via Glofox.

## Security

- All AJAX and form actions are nonce-protected (CSRF).
- All database access uses `$wpdb->prepare()`.
- Supplier credentials are AES-256-CBC encrypted using a key derived from
  WordPress salts.
- Admin actions require the `manage_options` capability.

## Requirements

- WordPress 5.8+
- PHP 7.4+ with the OpenSSL and DOM extensions (standard on most hosts).
