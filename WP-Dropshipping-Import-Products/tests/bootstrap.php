<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Unit tests do not require a full WordPress install — they use stubs
 * for WP/WC functions so the plugin classes can be tested in isolation.
 */

define( 'ABSPATH', __DIR__ . '/stubs/' );
define( 'DIP_VERSION', '1.0.0' );
define( 'DIP_FILE', dirname( __DIR__ ) . '/dropshipping-import-products.php' );
define( 'DIP_DIR', dirname( __DIR__ ) . '/' );
define( 'DIP_URL', 'https://example.com/wp-content/plugins/dropshipping-import-products/' );
define( 'DIP_BASENAME', 'dropshipping-import-products/dropshipping-import-products.php' );

// WP/WC stubs — only the functions used by the classes under test.
require_once __DIR__ . '/stubs/wp-functions.php';
require_once __DIR__ . '/stubs/wc-functions.php';

// Plugin classes under test (no WordPress bootstrap needed for unit tests)
require_once DIP_DIR . 'includes/processor/class-dip-price-rules.php';
require_once DIP_DIR . 'includes/importer/class-dip-field-mapper.php';
require_once DIP_DIR . 'includes/importer/class-dip-csv-parser.php';
require_once DIP_DIR . 'includes/importer/class-dip-xml-parser.php';
