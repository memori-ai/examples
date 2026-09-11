<?php
/**
 * AIsuru uninstall routine.
 *
 * @package Aisuru
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'aisuru_settings', array() );
if ( empty( $settings['remove_data'] ) ) {
	return;
}

global $wpdb;
$table = $wpdb->prefix . 'aisuru_agents';

// The table name only contains WordPress' trusted prefix and a fixed suffix.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'aisuru_settings' );
delete_option( 'aisuru_db_version' );
delete_option( 'aisuru_legacy_migration' );
delete_option( 'aisuru_migration_notice_shown' );
