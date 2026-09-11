<?php
/**
 * Plugin Name: AIsuru
 * Plugin URI: https://aisuru.com
 * Description: Integra gli agenti AIsuru in WordPress tramite shortcode o assistente globale.
 * Version: 2.2.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Memori Srl
 * Author URI: https://memori.ai
 * License: Apache-2.0
 * Text Domain: aisuru
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AISURU_VERSION', '2.2.0' );
define( 'AISURU_DB_VERSION', '2.2.0' );
define( 'AISURU_PLUGIN_FILE', __FILE__ );
define( 'AISURU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISURU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AISURU_PLUGIN_DIR . 'includes/class-aisuru-database.php';
require_once AISURU_PLUGIN_DIR . 'includes/class-aisuru-agent-repository.php';
require_once AISURU_PLUGIN_DIR . 'includes/class-aisuru-locale-resolver.php';
require_once AISURU_PLUGIN_DIR . 'includes/class-aisuru-embed-renderer.php';
require_once AISURU_PLUGIN_DIR . 'includes/class-aisuru-plugin.php';
require_once AISURU_PLUGIN_DIR . 'includes/admin/class-aisuru-admin.php';

register_activation_hook( __FILE__, array( 'Aisuru_Database', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain(
			'aisuru',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		Aisuru_Database::maybe_upgrade();
		( new Aisuru_Plugin() )->register();
	}
);
