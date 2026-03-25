=== Dropshipping Import Products dla WooCommerce ===
Contributors: yourname
Tags: woocommerce, import, dropshipping, xml, csv, product import, bulk import, synchronization, field mapping, price rules
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 8.2
WC tested up to: 9.5
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk import and scheduled synchronization of products from XML and CSV feeds into WooCommerce. Designed for dropshipping stores, agencies, and technical teams.

== Description ==

**Dropshipping Import Products dla WooCommerce** is a professional plugin for bulk importing and automatically synchronizing products from XML and CSV supplier feeds into WooCommerce.

Built for dropshipping stores, WooCommerce agencies, and technical ecommerce teams who need full control over product data: field mapping, price rules, conditional logic, scheduled sync, and detailed import logs.

= Key Features =

* Import products from XML and CSV files (remote URL or local path)
* Scheduled synchronization via Action Scheduler (hourly, daily, weekly, etc.)
* Drag-and-drop field mapping in admin — map feed fields to WooCommerce fields
* Conditional import logic — include, skip, or deactivate records based on field values
* Price modification rules: % markup, fixed add/subtract, set fixed, round, min price
* Import product categories (hierarchical tree support), attributes, tags, and images
* Match existing products by SKU, EAN/GTIN, product name, or custom meta key
* Selective field updates — choose which fields to create vs. update
* Create new products as drafts optionally
* Feed preview before running import (first 5 records)
* Per-record import logs with status (created / updated / skipped / error)
* Support for simple, variable, and affiliate (external) product types
* Compatible with custom fields (ACF, Yoast SEO meta, arbitrary meta keys)
* HPOS (High-Performance Order Storage) compatible
* Multilingual: Polish and English included

= Ecosystem Integration =

* Uses WooCommerce CRUD objects — no direct post meta writes
* Action Scheduler for scalable, traceable, retry-safe background jobs
* Compatible with ACF-style custom field workflows
* Compatible with SEO metadata workflows (Yoast, RankMath custom keys)
* Marketplace-ready: Allegro and similar channel workflows supported via custom meta mapping

== Installation ==

1. Upload the `WP-Dropshipping-Import-Products` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins > Installed Plugins**
3. Go to **WooCommerce > Import Products** to add your first feed
4. Enter the feed URL, select the type (XML or CSV), map fields, and configure sync

== Configuration ==

= Adding a Feed =

1. Navigate to **WooCommerce > Import Products > Add New Feed**
2. Enter the feed name and source URL (supports XML and CSV)
3. Click **Detect Fields** to auto-detect feed structure
4. Map source fields to WooCommerce product fields using the drag-and-drop builder
5. Configure matching, price rules, conditional logic, and schedule
6. Click **Save Feed**

= Field Mapping =

Map any feed field (dot-notation supported for nested XML: `price.net`) to WooCommerce fields including name, SKU, price, stock, categories, attributes, images, and custom meta keys.

= Price Rules =

Rules are applied in order:
* **Add % markup** — multiply price by (1 + value/100)
* **Subtract % discount** — multiply price by (1 - value/100)
* **Add fixed amount** — add a flat value to the price
* **Set fixed price** — override price to exactly this value
* **Round to decimals** — round to N decimal places
* **Round up to ending** — round up so fractional part equals value (e.g. .99)
* **Minimum price** — ensure price is never below this value

= Scheduled Sync =

Set a sync interval in the feed settings. The plugin uses Action Scheduler (bundled with WooCommerce) to run syncs reliably in the background. Sync is idempotent — safe to run repeatedly.

= Product Matching =

Configure how existing products are matched before deciding to create or update:
* By **SKU** (default)
* By **EAN / GTIN** meta
* By **product name**
* By **custom meta key**

= Selective Field Updates =

Choose which fields are updated on existing products. This lets you sync only prices and stock without overwriting descriptions or images.

= Conditional Logic =

Define rules to include, skip, or exclude records based on any mapped field value. Supports: equals, not equals, greater/less than, contains, is empty, and more.

== Data Storage ==

The plugin creates three custom database tables:

* `{prefix}dip_feeds` — feed configurations, mapping, and settings
* `{prefix}dip_runs` — import run metadata and counters
* `{prefix}dip_logs` — per-record import log entries

All data is kept private and never transmitted externally. Feed files are downloaded to a temporary location and cleaned up after processing.

== Compatibility ==

* WordPress: 6.4+
* PHP: 8.0+
* WooCommerce: 8.2+
* HPOS (custom_order_tables): compatible
* Multisite: not tested

== Uninstall Behavior ==

By default, plugin data (feeds, logs, tables) is **preserved** on uninstall to prevent accidental data loss. To remove all data on uninstall, enable **Delete Data on Uninstall** in **WooCommerce > Import Settings > Settings**.

== Frequently Asked Questions ==

= Does it support variable products? =

Yes. Set the `product_type` field mapping to `variable` and the processor will create `WC_Product_Variable`. Full variation management is on the roadmap.

= Does it import images? =

Yes. Map the `image` field to a URL. Images are downloaded and added to the WordPress media library. Duplicate downloads are avoided using a source URL cache.

= Does it work with large feeds (10 000+ products)? =

Yes. XML feeds are parsed using `XMLReader` streaming (memory-efficient). CSV feeds are read line-by-line. Action Scheduler batches imports in chunks of 50 records to stay within PHP time limits.

= Can I run multiple feeds? =

Yes. You can add an unlimited number of feeds, each with its own mapping, schedule, and settings.

== Changelog ==

= 1.0.0 =
* Initial release
* XML and CSV import with streaming parsers
* Drag-and-drop field mapping UI
* Price rules engine
* Conditional import logic
* Product matching by SKU, EAN, name, custom meta
* Action Scheduler-based scheduled sync
* Per-feed import logs
* Simple, variable, and affiliate product support
* HPOS compatibility declaration
* Polish and English translations

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.

== Support ==

For support, please open an issue in the plugin repository or contact the plugin author.
