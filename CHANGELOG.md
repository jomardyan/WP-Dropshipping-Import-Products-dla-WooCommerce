# Changelog

All notable changes to **Dropshipping Import Products dla WooCommerce** are documented here.
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2025-05-16

### Added — Core Plugin
- Plugin bootstrap with singleton `DIP_Plugin`, HPOS/custom-tables compatibility declaration.
- Custom database tables: `dip_feeds`, `dip_runs`, `dip_logs` managed via `DIP_DB` with `dbDelta()`.

### Added — Feed Management
- Full feed CRUD with list and edit screens (name, URL, source type, status).
- XML source support via `DIP_XML_Parser` (memory-efficient `XMLReader` streaming).
- CSV source support via `DIP_CSV_Parser` (BOM stripping, auto delimiter detection, IOF multi-value cells).
- Remote feed fetching with configurable HTTP timeout via `DIP_Feed_Manager`.

### Added — Field Mapping
- Visual drag-and-drop mapping builder with source ↔ WooCommerce target columns.
- `DIP_Field_Mapper`: dot-notation extraction, nested path traversal, list aggregation (max for numeric, first for string).
- Auto field detection (`Detect Fields` AJAX button, fills dropdowns from a live preview).

### Added — Product Processing
- `DIP_Product_Processor`: creates and updates products using WooCommerce CRUD objects (`WC_Product_Simple`, `WC_Product_External`).
- Supported fields: name, description, short description, SKU, regular price, sale price, stock, weight, dimensions, categories, tags, attributes, images, external URL and button text, custom meta.
- `DIP_Category_Handler`: resolves category paths, creates missing terms, caches within run.
- `DIP_Image_Handler`: sideloads remote images, deduplicates via `_dip_source_url` meta.

### Added — Price Rules
- `DIP_Price_Rules` engine with 8 rule types: `percent_markup`, `percent_discount`, `fixed_add`, `fixed_subtract`, `set_fixed`, `round`, `round_up_to`, `min_price`.
- Chainable rules applied in order; currency-safe decimal comma handling.
- Visual price-rules builder in the feed edit form (add/remove rules, live preview).

### Added — Conditional Logic
- Conditional import filter: skip products that do not satisfy all defined conditions.
- 10 operators: `==`, `!=`, `>`, `<`, `>=`, `<=`, `contains`, `not_contains`, `empty`, `not_empty`.
- Visual conditions builder in the feed edit form.

### Added — Product Matching & Sync
- `DIP_Matcher`: four match strategies — by SKU, custom post meta, product name, custom PHP callable.
- `DIP_Sync_Runner`: full import orchestration with per-run counters (created / updated / skipped / errors).
- Configurable update behaviour: choose which fields to overwrite on existing products; option to create new products as drafts.

### Added — Scheduled Sync
- `DIP_Scheduler`: Action Scheduler integration; per-feed recurring sync at intervals from hourly to weekly.
- Cron-based daily log cleanup via WP-Cron (`dip_cleanup_logs`); retention period respects global settings.

### Added — Admin UI
- Three-level log viewer: feeds → runs → log entries (paginated, 100 rows/page).
- Global Settings page: HTTP timeout, log retention, debug mode, delete-on-uninstall.
- Inline AJAX for import trigger, field detection, and feed preview.

### Added — REST API (`dip/v1`)
- `GET  /feeds`, `POST /feeds` — list and create feeds.
- `GET  /feeds/{id}`, `PUT /feeds/{id}`, `DELETE /feeds/{id}` — read, update, delete a feed.
- `POST /feeds/{id}/run` — trigger a synchronous import run.
- `GET  /feeds/{id}/runs` — list past runs for a feed.
- `GET  /runs/{run_id}/logs` — paginated log entries for a run (headers: `X-DIP-Total-Logs`, `X-DIP-Total-Pages`).
- `GET  /settings`, `POST /settings` — read and update global settings.
- All endpoints require `manage_woocommerce` capability.

### Added — Tooling & Quality
- `composer.json` with PHPUnit 10.x, WordPress Coding Standards (PHPCS), PHPStan level 6.
- `phpunit.xml` with `unit` and `integration` test suites; stub-based bootstrap (no WP install needed for unit tests).
- Unit test suites: `PriceRulesTest` (17), `FieldMapperTest` (15), `ConditionsTest` (22), `ParsersTest` (15+).
- `phpcs.xml.dist` enforcing WordPress standards, `dip` text domain, `dip`/`DIP` prefix.
- `phpstan.neon.dist` at level 6 targeting PHP 8.0.
- `package.json` with ESLint (`@wordpress/eslint-plugin`) and Stylelint (`stylelint-config-wordpress`).
- GitHub Actions CI matrix (PHP 8.0 – 8.3 × WP 6.4 / 6.6 / latest): unit tests + PHPCS + ESLint.
- `.distignore` excluding dev-only files from distribution packages.

### Added — Internationalisation
- Full Polish translation (`pl_PL`): `languages/dip-pl_PL.po` + compiled `dip-pl_PL.mo`.
- All user-facing strings use `__()` / `esc_html__()` with text domain `dip`.

[1.0.0]: https://github.com/example/WP-Dropshipping-Import-Products-dla-WooCommerce/releases/tag/1.0.0
