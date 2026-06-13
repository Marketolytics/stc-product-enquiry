<?php
/**
 * Uninstall routine for STC Product Enquiry.
 *
 * Removes the custom database table and all plugin options.
 *
 * @package STC_Product_Enquiry
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'stc_product_enquiries';

// Drop the custom table.
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

// Remove plugin options.
delete_option( 'stc_pe_notification_email' );
delete_option( 'stc_pe_db_version' );

// Clean up any transients used by the plugin.
delete_transient( 'stc_pe_cache' );

// Multisite cleanup.
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );

		$blog_table = $wpdb->prefix . 'stc_product_enquiries';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS `{$blog_table}`" );

		delete_option( 'stc_pe_notification_email' );
		delete_option( 'stc_pe_db_version' );

		restore_current_blog();
	}
}
