<?php
/**
 * Runs only when the plugin is deleted via WordPress admin.
 * Removes all plugin data: options, custom tables, scheduled actions.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'dip_global_settings', [] );

// Only delete data if the merchant has opted in (privacy-first default)
if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove all plugin options
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dip\_%'" );

// Drop custom tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dip_feeds" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dip_runs" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dip_logs" );

// Cancel all scheduled actions
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'dip_run_sync', [], 'dip' );
}
