<?php
/**
 * Database schema and legacy migration.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Database {
	const VERSION_OPTION   = 'aisuru_db_version';
	const MIGRATION_OPTION = 'aisuru_legacy_migration';
	const SETTINGS_OPTION  = 'aisuru_settings';

	/**
	 * Run installation on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Upgrade the schema when the plugin version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== AISURU_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Install the current schema and migrate legacy records.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL,
			memori_name varchar(191) NOT NULL,
			memori_id varchar(64) NOT NULL DEFAULT '',
			owner_user_name varchar(191) NOT NULL,
			owner_user_id varchar(64) NOT NULL DEFAULT '',
			tenant_id varchar(255) NOT NULL,
			integration_id varchar(64) NOT NULL DEFAULT '',
			layout varchar(32) NOT NULL DEFAULT 'WEBSITE_ASSISTANT',
			placement varchar(16) NOT NULL DEFAULT 'shortcode',
			height varchar(32) NOT NULL DEFAULT '100%',
			api_url varchar(255) NOT NULL DEFAULT '',
			engine_url varchar(255) NOT NULL DEFAULT '',
			base_url varchar(255) NOT NULL DEFAULT '',
			context text NULL,
			initial_question text NULL,
			show_settings tinyint(1) NULL DEFAULT NULL,
			show_share tinyint(1) NULL DEFAULT NULL,
			show_clear tinyint(1) NULL DEFAULT NULL,
			show_typing_text tinyint(1) NULL DEFAULT NULL,
			show_only_last_messages tinyint(1) NULL DEFAULT NULL,
			show_message_consumption tinyint(1) NULL DEFAULT NULL,
			avatar_3d_hidden tinyint(1) NULL DEFAULT NULL,
			show_login tinyint(1) NULL DEFAULT NULL,
			enable_audio tinyint(1) NULL DEFAULT NULL,
			max_total_message_payload bigint(20) unsigned NULL DEFAULT NULL,
			tag varchar(64) NOT NULL DEFAULT '',
			pin varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY integration_id (integration_id),
			KEY placement (placement)
		) {$charset_collate};";

		dbDelta( $sql );
		self::ensure_settings();
		self::upgrade_defaults();
		self::migrate_legacy_table();
		update_option( self::VERSION_OPTION, AISURU_DB_VERSION, false );
	}

	/**
	 * Get the prefixed v2 table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aisuru_agents';
	}

	/**
	 * Plugin defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		return array(
			'api_url'          => 'https://backend.memori.ai/api/v2',
			'engine_url'       => 'https://engine.memori.ai/memori/v2',
			'webcomponent_url' => 'https://cdn.jsdelivr.net/npm/@memori.ai/memori-webcomponent@8.3.1/dist/memori-webcomponent.js',
			'styles_url'       => 'https://cdn.jsdelivr.net/npm/@memori.ai/memori-react@8.45.2/dist/styles.min.css',
			'remove_data'      => 0,
		);
	}

	/**
	 * Return defaults merged with saved settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Create settings on first install without overwriting custom values.
	 *
	 * @return void
	 */
	private static function ensure_settings() {
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::default_settings(), '', false );
		}
	}

	/**
	 * Move installations using previous defaults to current endpoints and assets.
	 *
	 * Explicitly customized URLs are never overwritten.
	 *
	 * @return void
	 */
	private static function upgrade_defaults() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$old_defaults = array(
			'api_url'          => 'https://backend.memori.ai',
			'engine_url'       => 'https://engine.memori.ai',
			'webcomponent_url' => 'https://cdn.jsdelivr.net/npm/@memori.ai/memori-webcomponent@8.3.0/dist/memori-webcomponent.js',
			'styles_url'       => 'https://cdn.jsdelivr.net/npm/@memori.ai/memori-react@8.45.1/dist/styles.min.css',
		);
		$new_defaults = self::default_settings();
		$changed      = false;

		foreach ( $old_defaults as $key => $old_url ) {
			if ( isset( $settings[ $key ] ) && $old_url === $settings[ $key ] ) {
				$settings[ $key ] = $new_defaults[ $key ];
				$changed          = true;
			}
		}

		if ( $changed ) {
			update_option( self::SETTINGS_OPTION, $settings, false );
		}
	}

	/**
	 * Continue migration from the legacy hard-coded wp_ table after confirmation.
	 *
	 * @return void
	 */
	public static function confirm_legacy_migration() {
		$migration = get_option( self::MIGRATION_OPTION, array() );
		if ( ! is_array( $migration ) || 'confirmation_required' !== ( $migration['status'] ?? '' ) ) {
			return;
		}

		self::migrate_legacy_table( true );
	}

	/**
	 * Migrate the old hard-coded table while preserving shortcode IDs.
	 *
	 * @param bool $allow_hardcoded_table Whether an admin confirmed the wp_ table.
	 * @return void
	 */
	private static function migrate_legacy_table( $allow_hardcoded_table = false ) {
		global $wpdb;

		$migration = get_option( self::MIGRATION_OPTION, false );
		if ( false !== $migration
			&& ! ( $allow_hardcoded_table
				&& is_array( $migration )
				&& 'confirmation_required' === ( $migration['status'] ?? '' ) )
		) {
			return;
		}

		$prefixed_table  = $wpdb->prefix . 'plugin_memory_configuration';
		$hardcoded_table = 'wp_plugin_memory_configuration';
		$legacy_table    = '';

		if ( self::table_exists( $prefixed_table ) ) {
			$legacy_table = $prefixed_table;
		} elseif ( $prefixed_table !== $hardcoded_table && self::table_exists( $hardcoded_table ) ) {
			if ( ! $allow_hardcoded_table ) {
				update_option(
					self::MIGRATION_OPTION,
					array(
						'status' => 'confirmation_required',
						'count'  => 0,
						'table'  => $hardcoded_table,
					),
					false
				);
				return;
			}

			$legacy_table = $hardcoded_table;
		}

		if ( ! $legacy_table ) {
			update_option(
				self::MIGRATION_OPTION,
				array(
					'status' => 'not_found',
					'count'  => 0,
				),
				false
			);
			return;
		}

		// Table name is selected from the two fixed names above.
		$rows     = $wpdb->get_results( "SELECT * FROM `{$legacy_table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$migrated = 0;
		$now      = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( ! $id ) {
				continue;
			}

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::table_name() . ' WHERE id = %d',
					$id
				)
			);
			if ( $exists ) {
				continue;
			}

			$title = sanitize_text_field( $row['title'] ?? '' );
			$data  = array(
				'id'                      => $id,
				'title'                   => $title ?: sprintf( 'Agente %d', $id ),
				'memori_name'             => $title,
				'memori_id'               => sanitize_text_field( $row['memori_id'] ?? '' ),
				'owner_user_name'         => sanitize_text_field( $row['owner_user_name'] ?? '' ),
				'owner_user_id'           => sanitize_text_field( $row['owner_user_id'] ?? '' ),
				'tenant_id'               => sanitize_text_field( $row['tenant_id'] ?? '' ),
				'integration_id'          => '',
				'layout'                  => self::sanitize_legacy_layout( $row['layout'] ?? '' ),
				'placement'               => 'shortcode',
				'height'                  => '100%',
				'context'                 => sanitize_textarea_field( $row['context'] ?? '' ),
				'initial_question'        => sanitize_textarea_field( $row['question'] ?? '' ),
				'show_settings'           => self::legacy_boolean( $row['adminpanel'] ?? null ),
				'show_share'              => self::legacy_boolean( $row['social'] ?? null ),
				'show_clear'              => self::legacy_boolean( $row['admindelete'] ?? null ),
				'show_typing_text'        => self::legacy_boolean( $row['loadmessage'] ?? null ),
				'show_only_last_messages' => self::legacy_boolean( $row['lastmessage'] ?? null ),
				'show_message_consumption' => null,
				'avatar_3d_hidden'         => null,
				'show_login'               => null,
				'enable_audio'             => null,
				'max_total_message_payload' => null,
				'tag'                     => '',
				'pin'                     => '',
				'created_at'              => $now,
				'updated_at'              => $now,
			);

			if ( false !== $wpdb->insert( self::table_name(), $data ) ) {
				++$migrated;
			}
		}

		update_option(
			self::MIGRATION_OPTION,
			array(
				'status' => 'completed',
				'count'  => $migrated,
				'table'  => $legacy_table,
			),
			false
		);
	}

	/**
	 * Check whether an exact table name exists.
	 *
	 * @param string $table_name Exact table name.
	 * @return bool
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);
		return $found === $table_name;
	}

	/**
	 * Parse misspelled legacy boolean values.
	 *
	 * @param mixed $value Legacy value.
	 * @return int|null
	 */
	public static function legacy_boolean( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
	}

	/**
	 * Restrict migrated layouts to the supported public set.
	 *
	 * @param string $layout Legacy layout.
	 * @return string
	 */
	private static function sanitize_legacy_layout( $layout ) {
		$layout  = strtoupper( sanitize_text_field( $layout ) );
		$allowed = array( 'FULLPAGE', 'CHAT', 'WEBSITE_ASSISTANT', 'TOTEM', 'ZOOMED_FULL_BODY' );
		return in_array( $layout, $allowed, true ) ? $layout : 'FULLPAGE';
	}
}
