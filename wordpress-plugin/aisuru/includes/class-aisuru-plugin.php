<?php
/**
 * Public plugin orchestration.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Plugin {
	const LOADER_HANDLE = 'aisuru-loader';

	/** @var Aisuru_Agent_Repository */
	private $repository;

	/** @var Aisuru_Embed_Renderer */
	private $renderer;

	/** @var array<int, bool> */
	private $rendered = array();

	/** @var object|null */
	private $global_assistant;

	public function __construct() {
		$this->repository = new Aisuru_Agent_Repository();
		$this->renderer   = new Aisuru_Embed_Renderer();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'aisuru', array( $this, 'shortcode' ) );
		add_shortcode( 'memori', array( $this, 'shortcode' ) );
		add_action( 'wp_footer', array( $this, 'render_global_assistant' ), 20 );

		if ( is_admin() ) {
			( new Aisuru_Admin( $this->repository ) )->register();
		}
	}

	/**
	 * Register the lightweight loader used to discover widgets anywhere.
	 *
	 * @return void
	 */
	public function register_assets() {
		$settings = Aisuru_Database::get_settings();

		wp_register_script(
			self::LOADER_HANDLE,
			AISURU_PLUGIN_URL . 'assets/loader.js',
			array(),
			AISURU_VERSION,
			true
		);
		wp_localize_script(
			self::LOADER_HANDLE,
			'aisuruLoader',
			array(
				'scriptURL'      => add_query_arg( 'ver', AISURU_VERSION, esc_url_raw( $settings['webcomponent_url'] ) ),
				'widgetStyleURL' => add_query_arg( 'ver', AISURU_VERSION, esc_url_raw( $settings['styles_url'] ) ),
				'publicStyleURL' => add_query_arg( 'ver', AISURU_VERSION, AISURU_PLUGIN_URL . 'assets/public.css' ),
			)
		);
		wp_enqueue_script( self::LOADER_HANDLE );

		$this->global_assistant = $this->repository->global_assistant();
	}

	/**
	 * Ensure the loader remains enqueued for programmatic shortcode rendering.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_script( self::LOADER_HANDLE );
	}

	/**
	 * Render [aisuru id="N"] and the legacy [memori] alias.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'aisuru' );
		$id   = absint( $atts['id'] );

		if ( ! $id ) {
			return current_user_can( 'manage_options' ) ? '<!-- AIsuru: shortcode id mancante -->' : '';
		}

		$agent = $this->repository->find( $id );
		if ( ! $agent ) {
			return current_user_can( 'manage_options' ) ? '<!-- AIsuru: configurazione non trovata -->' : '';
		}

		$this->enqueue_assets();
		$this->rendered[ $id ] = true;

		return $this->renderer->render( $agent );
	}

	/**
	 * Render the configured site-wide Website Assistant once.
	 *
	 * @return void
	 */
	public function render_global_assistant() {
		if ( null === $this->global_assistant ) {
			$this->global_assistant = $this->repository->global_assistant();
		}

		if ( ! $this->global_assistant || isset( $this->rendered[ (int) $this->global_assistant->id ] ) ) {
			return;
		}

		$this->enqueue_assets();
		$this->rendered[ (int) $this->global_assistant->id ] = true;
		echo $this->renderer->render( $this->global_assistant, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
