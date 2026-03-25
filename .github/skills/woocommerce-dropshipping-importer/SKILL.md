---
name: woocommerce-dropshipping-importer
description: "Use when building, reviewing, or extending the WooCommerce Dropshipping Import Products plugin. Covers architecture patterns (Action Scheduler, WC CRUD, HPOS), required features (XML/CSV import, field mapping, price rules, category/attribute/image import, scheduled sync, conditional logic), mandatory WordPress and WooCommerce standards (security, i18n, performance, accessibility, coding standards), and acceptance checklist. CRITICAL: plugin must be multilingual by architecture (Polish and English)."
argument-hint: "Describe the feature or module you are working on"
---

# WooCommerce Dropshipping Import Products — Development Skill

## Plugin Identity

- **Plugin**: Dropshipping Import Products dla WooCommerce
- **Purpose**: Bulk import and scheduled synchronisation of products from XML and CSV feeds into WooCommerce. Targets dropshipping stores, integrators, and agencies.
- **Language requirement (CRITICAL)**: All user-facing strings must be wrapped in WordPress i18n functions. The plugin is multilingual by architecture — Polish and English at minimum. Never hardcode Polish or English text directly.
- **PHP prefix**: Use `dip_` (or a variant) consistently for all functions, hooks, option names, CSS classes, JS globals, REST routes, and database table names.

---

## Architecture Decisions

### Core Stack

| Concern | Solution |
|---|---|
| Scheduled / background jobs | **Action Scheduler** (bundled with WooCommerce) — scalable, traceable, retry-safe |
| Product persistence | **WooCommerce CRUD objects** (`WC_Product`, `WC_Product_Variable`, etc.) — never write product meta directly |
| Order compatibility | **HPOS** (`woocommerce_feature_hpos` declaration) — required if the plugin touches orders |
| Custom tables | Add only when justified by scale or query complexity; version schema with `dbDelta()` |
| Options | WordPress Options API and Settings API where appropriate; avoid autoloading large blobs |
| File parsing | Stream or chunk large XML/CSV files; never load the entire file into memory |

### Module Separation

Split responsibilities into distinct classes or files loaded conditionally:

```
admin/          — Settings pages, import UI, logs viewer
importer/       — Feed fetchers, parsers (XML, CSV), field mapper
processor/      — Product creator/updater (WC CRUD), image handler, category handler
scheduler/      — Action Scheduler job registration and management
sync/           — Matching logic (SKU, EAN, name, custom ID), update rules
api/            — REST endpoints if needed
uninstall.php   — Cleanup on uninstall
```

Load each module only when its functionality is needed (admin-only on `is_admin()`, frontend-only when required).

---

## Required Features

Implement and maintain all of the following:

### Import Sources
- [ ] Import from **XML** files
- [ ] Import from **CSV** files
- [ ] Support remote URLs and locally uploaded files

### Product Types
- [ ] Simple products
- [ ] Variable products (variants)
- [ ] Affiliate products

### Field Mapping
- [ ] Drag-and-drop field mapping UI in admin
- [ ] Map feed fields to WooCommerce product fields (name, SKU, price, stock, description, categories, attributes, images, tags)
- [ ] Support mapping to custom fields (ACF, Yoast SEO meta, arbitrary meta keys)
- [ ] Selective field updates — user chooses **which fields are created vs. updated**

### Product Matching
- [ ] Match existing products by **SKU**
- [ ] Match by **EAN / GTIN**
- [ ] Match by **product name**
- [ ] Match by **custom unique identifier**
- [ ] Configurable match priority

### Import Logic and Rules
- [ ] **Conditional import**: include/skip/deactivate records based on field values or expressions
- [ ] **Price rules**: apply margin, percentage markup, fixed addition, rounding rules to imported prices
- [ ] Create new products as **drafts** optionally
- [ ] Optionally deactivate or delete products missing from the feed

### Taxonomy and Media
- [ ] Import and create **product categories** (tree mapping supported)
- [ ] Import and assign **product attributes** and terms
- [ ] Import and assign **product tags**
- [ ] Import and attach **product images** (main image + gallery); cache remote images locally

### Scheduling and Synchronisation
- [ ] Configure **scheduled sync** intervals via Action Scheduler
- [ ] Selective sync: prices only, stock only, or full sync
- [ ] Idempotent runs — safe to run repeatedly without side effects

### UX and Diagnostics
- [ ] **File preview** before executing import
- [ ] **Import logs** with per-record status (created / updated / skipped / error)
- [ ] Progress indication for long-running imports
- [ ] Actionable error messages in import log

---

## Mandatory Standards (from project Standards.md)

### Security — Every Entry Point
```php
// 1. Capability check first
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have permission to do this.', 'dip' ) );
}

// 2. Nonce verification for all state-changing actions
check_admin_referer( 'dip_import_action', 'dip_nonce' );

// 3. Validate ➜ Sanitize ➜ Escape at render
$source_url = esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) );

// 4. Prepared SQL when raw queries are unavoidable
$wpdb->prepare( 'SELECT * FROM %i WHERE feed_id = %d', $table, $id );
```

### Internationalisation (CRITICAL)
- Wrap **every** user-facing string: `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`.
- Text domain: `dip` (consistent across all files).
- Do not concatenate translated strings; use `printf`/`sprintf` with placeholders.
- Load textdomain on `init`:
  ```php
  add_action( 'init', function() {
      load_plugin_textdomain( 'dip', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
  } );
  ```

### WooCommerce CRUD (required pattern)
```php
$product = new WC_Product_Simple();
$product->set_name( $data['name'] );
$product->set_sku( $data['sku'] );
$product->set_regular_price( $data['price'] );
$product->set_stock_quantity( $data['stock'] );
$product->save();
```
Never call `update_post_meta()` directly for WooCommerce product fields.

### HPOS Declaration
```php
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
```

### Asset Enqueueing
```php
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'woocommerce_page_dip-import' !== $hook ) return; // load only on own pages
    wp_enqueue_style( 'dip-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', [], DIP_VERSION );
    wp_enqueue_script( 'dip-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', [ 'jquery', 'wp-i18n' ], DIP_VERSION, true );
} );
```

### Action Scheduler Usage
```php
// Schedule recurring import
if ( ! as_has_scheduled_action( 'dip_run_sync', [ $feed_id ] ) ) {
    as_schedule_recurring_action( time(), $interval, 'dip_run_sync', [ $feed_id ], 'dip' );
}

// Handler
add_action( 'dip_run_sync', function( $feed_id ) {
    // process one feed; bounded, retry-safe
} );
```

### WooCommerce Detection
```php
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'dip_woocommerce_missing_notice' );
        return;
    }
    dip_init();
} );
```

---

## Performance Rules

- Never run import queries on every page load; always gate behind scheduled jobs or explicit admin actions.
- Do not autoload large serialised option values; use separate option keys or a custom table.
- Stream/chunk XML and CSV parsing; use `XMLReader` or line-by-line CSV for large files.
- Cache expensive lookups (category ID by name, attribute term by slug) within a single import run using in-memory arrays.
- Limit Action Scheduler batch size (e.g., 50 products per action) so tasks stay within PHP time limits.

---

## Accessibility Requirements

- All admin forms: proper `<label for="">` associations.
- Error and status notices: use `aria-live="polite"` regions.
- Drag-and-drop mapping UI: provide a keyboard-accessible fallback.
- Do not use colour alone to indicate required fields or error state.
- Import log table: use `<th scope="col">` headers.

---

## Coding Standards Checklist

Before committing any code, verify:

- [ ] PHP: WordPress PHP Coding Standards (WPCS via PHP_CodeSniffer)
- [ ] JavaScript: WordPress JS Coding Standards (ESLint `@wordpress/eslint-plugin`)
- [ ] CSS: WordPress CSS Coding Standards
- [ ] All strings wrapped in i18n functions with `dip` text domain
- [ ] No inline `<script>` or `<style>` blocks (use `wp_enqueue_*`)
- [ ] No direct `$wpdb->query()` without `$wpdb->prepare()`
- [ ] No `$_GET`/`$_POST` used without `wp_unslash()` + sanitisation
- [ ] Nonces verified before any state-changing action
- [ ] Capability checks on all admin callbacks and AJAX handlers

---

## Acceptance Checklist (from Standards.md §19)

A feature is complete only when all items below pass:

- [ ] Coding standards linting passes (PHP, JS, CSS)
- [ ] Security review passes (nonces, capabilities, sanitise, escape, prepared SQL)
- [ ] No prefix collisions with WordPress core or third-party plugins
- [ ] Compatibility declared: minimum PHP version, WordPress version, WooCommerce version
- [ ] All user-facing strings translatable; Polish and English translations present
- [ ] Accessibility review passed (WCAG AA intent)
- [ ] Performance impact reviewed (no expensive ops on page load, Action Scheduler batch size bounded)
- [ ] Automated unit tests pass for business logic (price rules, field mapping, matching)
- [ ] Integration tests pass for WooCommerce hooks and CRUD
- [ ] Manual critical-flow test: create new product, update existing product, skip product, deactivate missing product
- [ ] HPOS compatibility declared
- [ ] Import log captures created / updated / skipped / error with reasons
- [ ] Documentation updated (README, changelog, data storage, uninstall behaviour)
- [ ] Uninstall routine tested: cleans options, custom tables, scheduled actions

---

## Prohibited Patterns

- Direct modification of WooCommerce core or WordPress core files.
- Using `update_post_meta()` instead of WC CRUD setters for product fields.
- Running XML/CSV parsing on every page request.
- Storing XML/CSV content in `wp_options` as a single autoloaded blob.
- Hardcoding Polish or English text outside of i18n functions.
- Raw SQL without `$wpdb->prepare()`.
- Rendering unsanitised output from feed data.
- Loading admin assets on all admin pages (`$hook` check required).
- Scheduling unbounded Action Scheduler tasks (add `group` and `batch` parameters).
- Undisclosed remote HTTP requests during normal page rendering.

---

## References

- [Standards.md](../../../Standards.md) — Full mandatory engineering specification
- [description.md](../../../description.md) — Feature list and architecture rationale
- [WooCommerce CRUD docs](https://developer.woocommerce.com/docs/product-crud-functions/)
- [Action Scheduler docs](https://actionscheduler.org)
- [WooCommerce HPOS docs](https://developer.woocommerce.com/2022/09/14/high-performance-order-storage-progress-report/)
