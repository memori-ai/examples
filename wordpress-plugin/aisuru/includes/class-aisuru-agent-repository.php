<?php
/**
 * Persistence for configured AIsuru agents.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Agent_Repository {
	/**
	 * Get all agents.
	 *
	 * @return array<int, object>
	 */
	public function all() {
		global $wpdb;
		$table = Aisuru_Database::table_name();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Find an agent by internal numeric ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = Aisuru_Database::table_name();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Return the configured site-wide Website Assistant.
	 *
	 * @return object|null
	 */
	public function global_assistant() {
		global $wpdb;
		$table = Aisuru_Database::table_name();
		return $wpdb->get_row(
			"SELECT * FROM {$table} WHERE layout = 'WEBSITE_ASSISTANT' AND placement = 'global' ORDER BY updated_at DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Create or update an agent.
	 *
	 * @param array<string, mixed> $data Sanitized record data.
	 * @param int                  $id Existing record ID.
	 * @return int|false
	 */
	public function save( array $data, $id = 0 ) {
		global $wpdb;
		$table = Aisuru_Database::table_name();
		$now   = current_time( 'mysql' );

		if ( 'global' === $data['placement'] ) {
			$wpdb->query(
				"UPDATE {$table} SET placement = 'shortcode' WHERE placement = 'global'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		$data['updated_at'] = $now;
		if ( $id ) {
			$result = $wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
			return false === $result ? false : absint( $id );
		}

		$data['created_at'] = $now;
		$result             = $wpdb->insert( $table, $data );
		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete(
			Aisuru_Database::table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}
}
