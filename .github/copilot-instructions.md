# Dropshipping Import Products dla WooCommerce — Copilot Instructions

WordPress + WooCommerce plugin for bulk XML/CSV product import and scheduled synchronisation. PHP 8.0+, WP 6.4+, WC 8.2+. **Multilingual by architecture — Polish and English are both mandatory.**

See [Standards.md](../Standards.md) for mandatory engineering/security/WooCommerce standards. See [CHANGELOG.md](../CHANGELOG.md) for feature inventory.

---

## Build & Test

All commands run from `WP-Dropshipping-Import-Products/`:

```bash
composer install           # install dev dependencies (PHPUnit, PHPCS, PHPStan)
composer test              # PHPUnit unit tests (no WP install required — stub-based)
composer lint              # PHPCS with WordPress standard
composer analyse           # PHPStan level 6

npm install                # install JS/CSS linting tools
npm run lint               # ESLint + Stylelint
npm run lint:js            # ESLint only  (assets/js/)
npm run lint:css           # Stylelint only (assets/css/)
```

**Unit tests need no WP or DB** — `tests/bootstrap.php` loads stubs from `tests/stubs/`. Run:
```bash
./vendor/bin/phpunit --testsuite unit --no-coverage
```

CI runs PHP 8.0–8.3 × WP 6.4/6.6/latest. See [.github/workflows/ci.yml](.github/workflows/ci.yml).

---

## Architecture

```
dropshipping-import-products.php   ← entry point, HPOS declaration, activation hooks
includes/
  class-dip-plugin.php             ← singleton orchestrator, loads all classes
  class-dip-logger.php             ← buffered DB logger (auto-flush at 50 entries)
  data/class-dip-db.php            ← custom tables: dip_feeds, dip_runs, dip_logs
  importer/                        ← XML (XMLReader streaming) + CSV (fgetcsv) parsers
  processor/                       ← price rules, category/image handling, WC CRUD
  sync/                            ← matcher (SKU/meta/name/custom) + sync orchestration
  scheduler/class-dip-scheduler.php ← Action Scheduler recurring sync + WP-Cron cleanup
  api/class-dip-rest-api.php       ← REST endpoints under dip/v1
  admin/                           ← admin screens (feeds, logs, settings)
assets/js/admin.js                 ← all UI interactions (mapping builder, AJAX)
assets/css/admin.css
languages/dip-pl_PL.po/.mo         ← Polish translation
```

Admin classes are loaded only when `is_admin()`. The REST API is always loaded (registered via `rest_api_init`).

---

## Conventions

### Naming
- PHP: prefix `DIP_` (classes), `dip_` (functions, hooks, option names, table names, REST routes)
- JS globals: `dipAdmin` object (localized via `wp_localize_script`)
- Text domain: `dip`
- Options key: `dip_global_settings` (array: `timeout`, `log_retention`, `batch_size`, `image_timeout`, `debug_mode`, `delete_on_uninstall`)

### WooCommerce CRUD — never bypass
```php
// ✅ correct
$product = new WC_Product_Simple();
$product->set_name( $name );
$product->save();

// ❌ wrong — never use update_post_meta() directly for WC product fields
```

### Security — every state-changing action needs both:
```php
check_admin_referer( 'dip_action_name', 'dip_nonce_field' );  // admin forms
check_ajax_referer( 'dip_admin_nonce' );                       // AJAX handlers
current_user_can( 'manage_woocommerce' );                      // capability check
```
REST endpoints use `permission_callback` returning `current_user_can( 'manage_woocommerce' )`.

Escape all output: `esc_html__()`, `esc_attr()`, `esc_url()`. Sanitize all input: `sanitize_text_field()`, `absint()`, `sanitize_key()`, `esc_url_raw()`. Use `$wpdb->prepare()` for all custom SQL.

### Internationalization
- **All** user-visible strings must use `__( 'string', 'dip' )` / `esc_html__( 'string', 'dip' )`
- Polish translation file: `languages/dip-pl_PL.po` — add Polish `msgstr` for every new `msgid`
- Compile after editing `.po`: `msgfmt languages/dip-pl_PL.po -o languages/dip-pl_PL.mo`

### Action Scheduler
Feed sync is scheduled with `as_schedule_recurring_action()` grouped under `'dip'`. Always guard with `function_exists( 'as_schedule_recurring_action' )`. Log cleanup uses WP-Cron (`dip_cleanup_logs`, daily).

### Parser generators
`DIP_XML_Parser::parse()` and `DIP_CSV_Parser::parse()` are PHP **generators** — iterate with `foreach`, never collect into arrays for large feeds.

### Database
Custom tables are created via `DIP_DB::create_tables()` using `dbDelta()` (idempotent). Never write directly to `wp_posts`/`wp_postmeta` for WC product data.

---

## Key Patterns to Follow

**Adding a new setting:** Update `DIP_Admin_Settings` form + `handle_save()`, the REST `settings_args()` and `update_settings()` in `DIP_REST_API`, and default in any consumer (e.g., sync runner reads `batch_size` from `dip_global_settings`).

**Adding a new REST endpoint:** Register in `DIP_REST_API::register_routes()`, add `check_permission()` as `permission_callback`, validate/sanitize all params, test with `DIP_REST_API::NAMESPACE` (`dip/v1`).

**Writing a unit test:** Extend `\PHPUnit\Framework\TestCase`. Use stubs from `tests/stubs/`. For private/protected methods use `ReflectionMethod`. See `tests/unit/ConditionsTest.php` for the `passes_conditions` private-method pattern.
