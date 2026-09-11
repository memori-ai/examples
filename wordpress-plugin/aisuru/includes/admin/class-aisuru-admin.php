<?php
/**
 * WordPress-native administration screens.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Admin {
	/** @var Aisuru_Agent_Repository */
	private $repository;

	/**
	 * @param Aisuru_Agent_Repository $repository Agent repository.
	 */
	public function __construct( Aisuru_Agent_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_aisuru_preview_integration', array( $this, 'preview_integration' ) );
	}

	/**
	 * Register menu pages.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			__( 'AIsuru', 'aisuru' ),
			__( 'AIsuru', 'aisuru' ),
			'manage_options',
			'aisuru',
			array( $this, 'agents_page' ),
			AISURU_PLUGIN_URL . 'assets/memori-icon.svg',
			58
		);
		add_submenu_page(
			'aisuru',
			__( 'Agenti', 'aisuru' ),
			__( 'Agenti', 'aisuru' ),
			'manage_options',
			'aisuru',
			array( $this, 'agents_page' )
		);
		add_submenu_page(
			'aisuru',
			__( 'Aggiungi agente', 'aisuru' ),
			__( 'Aggiungi agente', 'aisuru' ),
			'manage_options',
			'aisuru-agent',
			array( $this, 'agent_page' )
		);
		add_submenu_page(
			'aisuru',
			__( 'Impostazioni', 'aisuru' ),
			__( 'Impostazioni', 'aisuru' ),
			'manage_options',
			'aisuru-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Load assets only on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'aisuru' ) ) {
			return;
		}

		wp_enqueue_style(
			'aisuru-admin',
			AISURU_PLUGIN_URL . 'assets/admin.css',
			array(),
			AISURU_VERSION
		);
		wp_enqueue_script(
			'aisuru-admin',
			AISURU_PLUGIN_URL . 'assets/admin.js',
			array(),
			AISURU_VERSION,
			true
		);
		wp_localize_script(
			'aisuru-admin',
			'aisuruAdmin',
			array(
				'ajaxURL' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aisuru_preview_integration' ),
				'labels'  => array(
					'loading'   => __( 'Caricamento valori da AIsuru…', 'aisuru' ),
					'loaded'    => __( 'Valori AIsuru caricati. Puoi lasciarli in eredità o sovrascriverli.', 'aisuru' ),
					'error'     => __( 'Impossibile caricare i valori AIsuru.', 'aisuru' ),
					'missing'   => __( 'Compila prima Tenant ID, Nome agente, Owner username e Integration ID.', 'aisuru' ),
					'inherit'   => __( 'Eredita da AIsuru', 'aisuru' ),
					'inherited' => __( 'Ereditato da AIsuru', 'aisuru' ),
					'on'        => __( 'Attivo', 'aisuru' ),
					'off'       => __( 'Disattivo', 'aisuru' ),
				),
			)
		);
	}

	/**
	 * Resolve the selected Landing Experience for the admin prefill.
	 *
	 * Values are fetched server-side so the admin UI does not depend on browser
	 * CORS rules. wp_safe_remote_get blocks local/private targets.
	 *
	 * @return void
	 */
	public function preview_integration() {
		$this->guard();
		check_ajax_referer( 'aisuru_preview_integration', 'nonce' );

		$required = array( 'tenant_id', 'memori_name', 'owner_user_name', 'integration_id' );
		foreach ( $required as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Compila Tenant ID, Nome agente, Owner username e Integration ID.', 'aisuru' ) ),
					400
				);
			}
		}

		$settings       = Aisuru_Database::get_settings();
		$tenant_id      = sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) );
		$memori_name    = sanitize_text_field( wp_unslash( $_POST['memori_name'] ) );
		$owner_user_name = sanitize_text_field( wp_unslash( $_POST['owner_user_name'] ) );
		$integration_id = sanitize_text_field( wp_unslash( $_POST['integration_id'] ) );
		$api_url        = $this->sanitize_optional_url( $_POST['api_url'] ?? '' );
		$api_url        = $api_url ?: $settings['api_url'];
		$api_path       = (string) wp_parse_url( $api_url, PHP_URL_PATH );
		$api_endpoint   = $api_url;
		if ( '' === trim( $api_path, '/' ) ) {
			$api_endpoint = trailingslashit( $api_endpoint ) . 'api/v2';
		}
		$cache_key = 'aisuru_integration_' . md5( $api_endpoint . $tenant_id . $owner_user_name . $memori_name . $integration_id );
		$cached         = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$url      = trailingslashit( $api_endpoint ) . 'Memori/' . implode(
			'/',
			array_map( 'rawurlencode', array( $tenant_id, $owner_user_name, $memori_name ) )
		) . '/';
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 502 );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $body ) || empty( $body['memori']['integrations'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Agente o Landing Experience non trovati.', 'aisuru' ) ),
				404
			);
		}

		$selected = null;
		foreach ( $body['memori']['integrations'] as $integration ) {
			if ( isset( $integration['integrationID'] ) && $integration_id === (string) $integration['integrationID'] ) {
				$selected = $integration;
				break;
			}
		}

		if ( ! $selected ) {
			wp_send_json_error(
				array( 'message' => __( 'L’Integration ID non appartiene a questo agente.', 'aisuru' ) ),
				404
			);
		}

		$custom_data = $selected['customData'] ?? array();
		if ( is_string( $custom_data ) ) {
			$custom_data = json_decode( $custom_data, true );
		}
		if ( ! is_array( $custom_data ) ) {
			$custom_data = array();
		}

		$layout = $custom_data['layout'] ?? '';
		if ( is_array( $layout ) ) {
			$layout = $layout['name'] ?? '';
		}
		$person      = isset( $custom_data['person'] ) && is_array( $custom_data['person'] )
			? $custom_data['person']
			: array();
		$max_payload = isset( $custom_data['maxTotalMessagePayload'] ) && is_scalar( $custom_data['maxTotalMessagePayload'] )
			? (string) $custom_data['maxTotalMessagePayload']
			: '';
		if ( ! preg_match( '/^[1-9][0-9]*$/', $max_payload ) ) {
			$max_payload = '';
		}
		$tag = $custom_data['tag'] ?? ( $person['tag'] ?? '' );
		$pin = $custom_data['pin'] ?? ( $person['pin'] ?? '' );

		$result = array(
			'layout'                 => sanitize_text_field( (string) $layout ),
			'contextVars'            => sanitize_textarea_field( (string) ( $custom_data['contextVars'] ?? '' ) ),
			'initialQuestion'        => sanitize_textarea_field( (string) ( $custom_data['initialQuestion'] ?? '' ) ),
			'maxTotalMessagePayload' => $max_payload,
			'tag'                    => is_scalar( $tag ) ? sanitize_text_field( (string) $tag ) : '',
			'pin'                    => is_scalar( $pin ) ? sanitize_text_field( (string) $pin ) : '',
			'options'                => array(),
		);
		$option_map = array(
			'show_settings'           => 'showSettings',
			'show_share'              => 'showShare',
			'show_clear'              => 'showClear',
			'show_typing_text'        => 'showTypingText',
			'show_only_last_messages' => 'showOnlyLastMessages',
			'show_message_consumption' => 'showMessageConsumption',
			'avatar_3d_hidden'         => 'avatar3dHidden',
			'show_login'               => 'showLogin',
			'enable_audio'             => 'enableAudio',
		);
		foreach ( $option_map as $field => $key ) {
			if ( array_key_exists( $key, $custom_data ) ) {
				$result['options'][ $field ] = rest_sanitize_boolean( $custom_data[ $key ] );
			}
		}

		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( $result );
	}

	/**
	 * Handle save/delete operations.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['aisuru_action'] ) && 'save_agent' === $_POST['aisuru_action'] ) {
			$this->save_agent();
		}

		if ( isset( $_POST['aisuru_action'] ) && 'save_settings' === $_POST['aisuru_action'] ) {
			$this->save_settings();
		}

		if ( isset( $_GET['page'], $_GET['action'] )
			&& 'aisuru' === $_GET['page']
			&& 'migrate-legacy' === $_GET['action']
		) {
			check_admin_referer( 'aisuru_migrate_legacy' );
			delete_option( 'aisuru_migration_notice_shown' );
			Aisuru_Database::confirm_legacy_migration();
			wp_safe_redirect( $this->admin_url( 'aisuru' ) );
			exit;
		}

		if ( isset( $_GET['page'], $_GET['action'], $_GET['id'] )
			&& 'aisuru' === $_GET['page']
			&& 'delete' === $_GET['action']
		) {
			$id = absint( $_GET['id'] );
			check_admin_referer( 'aisuru_delete_agent_' . $id );
			$this->repository->delete( $id );
			wp_safe_redirect( $this->admin_url( 'aisuru', array( 'message' => 'deleted' ) ) );
			exit;
		}
	}

	/**
	 * Render the agents list.
	 *
	 * @return void
	 */
	public function agents_page() {
		$this->guard();
		$agents = $this->repository->all();
		?>
		<div class="wrap aisuru-admin">
			<div class="aisuru-page-header">
				<div>
					<span class="aisuru-eyebrow"><?php esc_html_e( 'Plugin WordPress', 'aisuru' ); ?></span>
					<h1><?php esc_html_e( 'Agenti AIsuru', 'aisuru' ); ?></h1>
					<p><?php esc_html_e( 'Gestisci gli agenti incorporati nel sito e copia gli shortcode pronti all’uso.', 'aisuru' ); ?></p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $this->admin_url( 'aisuru-agent' ) ); ?>">
					<?php esc_html_e( 'Aggiungi agente', 'aisuru' ); ?>
				</a>
			</div>

			<?php $this->render_notice(); ?>

			<?php if ( ! $agents ) : ?>
				<div class="aisuru-empty-state">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<h2><?php esc_html_e( 'Nessun agente configurato', 'aisuru' ); ?></h2>
					<p><?php esc_html_e( 'Aggiungi il primo agente usando i dati disponibili nella sezione Condividi di AIsuru.', 'aisuru' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( $this->admin_url( 'aisuru-agent' ) ); ?>">
						<?php esc_html_e( 'Configura il primo agente', 'aisuru' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="aisuru-agent-grid">
					<?php foreach ( $agents as $agent ) : ?>
						<article class="aisuru-agent-card">
							<div class="aisuru-agent-card__top">
								<span class="aisuru-layout-badge"><?php echo esc_html( $this->layout_label( $agent->layout ) ); ?></span>
								<?php if ( 'global' === $agent->placement ) : ?>
									<span class="aisuru-global-badge"><?php esc_html_e( 'Globale', 'aisuru' ); ?></span>
								<?php endif; ?>
							</div>
							<h2><?php echo esc_html( $agent->title ); ?></h2>
							<p class="aisuru-agent-meta">
								<?php echo esc_html( $agent->memori_name ); ?> · <?php echo esc_html( $agent->tenant_id ); ?>
							</p>
							<div class="aisuru-pii-status <?php echo $agent->integration_id ? 'is-ready' : 'is-warning'; ?>">
								<span class="dashicons <?php echo $agent->integration_id ? 'dashicons-shield-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
								<?php echo $agent->integration_id ? esc_html__( 'PII collegata alla Landing Experience', 'aisuru' ) : esc_html__( 'Integration ID mancante', 'aisuru' ); ?>
							</div>
							<div class="aisuru-shortcode-row">
								<code>[aisuru id="<?php echo esc_attr( $agent->id ); ?>"]</code>
								<button type="button" class="button aisuru-copy" data-copy='[aisuru id="<?php echo esc_attr( $agent->id ); ?>"]'>
									<?php esc_html_e( 'Copia', 'aisuru' ); ?>
								</button>
							</div>
							<div class="aisuru-card-actions">
								<a class="button" href="<?php echo esc_url( $this->admin_url( 'aisuru-agent', array( 'id' => $agent->id ) ) ); ?>">
									<?php esc_html_e( 'Modifica', 'aisuru' ); ?>
								</a>
								<a
									class="button button-link-delete"
									href="<?php echo esc_url( wp_nonce_url( $this->admin_url( 'aisuru', array( 'action' => 'delete', 'id' => $agent->id ) ), 'aisuru_delete_agent_' . $agent->id ) ); ?>"
									data-confirm="<?php esc_attr_e( 'Eliminare questa configurazione?', 'aisuru' ); ?>"
								>
									<?php esc_html_e( 'Elimina', 'aisuru' ); ?>
								</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render create/edit form.
	 *
	 * @return void
	 */
	public function agent_page() {
		$this->guard();
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$agent = $id ? $this->repository->find( $id ) : null;
		$agent = $agent ?: $this->empty_agent();
		$lang  = Aisuru_Locale_Resolver::resolve();
		?>
		<div class="wrap aisuru-admin">
			<div class="aisuru-page-header">
				<div>
					<a class="aisuru-back-link" href="<?php echo esc_url( $this->admin_url( 'aisuru' ) ); ?>">← <?php esc_html_e( 'Tutti gli agenti', 'aisuru' ); ?></a>
					<h1><?php echo $id ? esc_html__( 'Modifica agente', 'aisuru' ) : esc_html__( 'Aggiungi agente', 'aisuru' ); ?></h1>
					<p><?php esc_html_e( 'I dati si trovano in AIsuru nella sezione Condividi. Le regole PII restano gestite dalla Landing Experience selezionata.', 'aisuru' ); ?></p>
				</div>
			</div>

			<?php $this->render_notice(); ?>

			<form method="post" class="aisuru-editor">
				<?php wp_nonce_field( 'aisuru_save_agent' ); ?>
				<input type="hidden" name="aisuru_action" value="save_agent">
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">

				<div class="aisuru-editor__main">
					<section class="aisuru-panel">
						<div class="aisuru-panel__heading">
							<span>1</span>
							<div>
								<h2><?php esc_html_e( 'Identità agente', 'aisuru' ); ?></h2>
								<p><?php esc_html_e( 'Collega questa configurazione all’agente e alla sua Landing Experience.', 'aisuru' ); ?></p>
							</div>
						</div>
						<div class="aisuru-form-grid">
							<?php $this->text_field( 'title', __( 'Nome interno', 'aisuru' ), $agent->title, __( 'Usato solo in WordPress.', 'aisuru' ), true ); ?>
							<?php $this->text_field( 'tenant_id', __( 'Tenant ID', 'aisuru' ), $agent->tenant_id, 'www.aisuru.com', true ); ?>
							<?php $this->text_field( 'memori_name', __( 'Nome agente', 'aisuru' ), $agent->memori_name, '', true ); ?>
							<?php $this->text_field( 'memori_id', __( 'Memori ID', 'aisuru' ), $agent->memori_id, '', true ); ?>
							<?php $this->text_field( 'owner_user_name', __( 'Owner username', 'aisuru' ), $agent->owner_user_name, '', true ); ?>
							<?php $this->text_field( 'owner_user_id', __( 'Owner user ID', 'aisuru' ), $agent->owner_user_id, '', true ); ?>
						</div>
						<div class="aisuru-integration-field">
							<?php $this->text_field( 'integration_id', __( 'Integration ID', 'aisuru' ), $agent->integration_id, __( 'Obbligatorio: permette al widget di caricare PII, colori e impostazioni della Landing Experience.', 'aisuru' ), true ); ?>
						</div>
						<div class="aisuru-callout aisuru-callout--security">
							<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
							<div>
								<strong><?php esc_html_e( 'PII detection', 'aisuru' ); ?></strong>
								<p><?php esc_html_e( 'Il plugin usa l’Integration ID per caricare le regole PII salvate in AIsuru. Assicurati che la rilevazione PII sia attiva su quella Landing Experience.', 'aisuru' ); ?></p>
								<button type="button" class="button aisuru-sync-integration" data-integration-sync>
									<?php esc_html_e( 'Carica valori da AIsuru', 'aisuru' ); ?>
								</button>
								<span class="aisuru-sync-status" data-integration-status aria-live="polite"></span>
							</div>
						</div>
					</section>

					<section class="aisuru-panel">
						<div class="aisuru-panel__heading">
							<span>2</span>
							<div>
								<h2><?php esc_html_e( 'Esperienza e layout', 'aisuru' ); ?></h2>
								<p><?php esc_html_e( 'Scegli il formato più adatto alla pagina WordPress.', 'aisuru' ); ?></p>
							</div>
						</div>
						<?php $this->layout_cards( $agent->layout ); ?>

						<div class="aisuru-placement" data-website-assistant-options>
							<label for="placement"><?php esc_html_e( 'Posizionamento Website Assistant', 'aisuru' ); ?></label>
							<select id="placement" name="placement">
								<option value="shortcode" <?php selected( $agent->placement, 'shortcode' ); ?>><?php esc_html_e( 'Solo tramite shortcode', 'aisuru' ); ?></option>
								<option value="global" <?php selected( $agent->placement, 'global' ); ?>><?php esc_html_e( 'Automaticamente su tutto il sito', 'aisuru' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Può esserci un solo Website Assistant globale attivo.', 'aisuru' ); ?></p>
						</div>
						<p class="aisuru-inherited-layout" data-inherited-layout hidden></p>

						<div class="aisuru-form-grid aisuru-form-grid--compact">
							<?php $this->text_field( 'height', __( 'Altezza', 'aisuru' ), $agent->height, '600px / 80vh / 100%', true ); ?>
							<div class="aisuru-field aisuru-field--readonly">
								<label><?php esc_html_e( 'Lingua risolta', 'aisuru' ); ?></label>
								<div>
									<strong>UI: <?php echo esc_html( $lang['ui_lang'] ); ?></strong>
									<span>Agent: <?php echo esc_html( $lang['spoken_lang'] ); ?></span>
								</div>
								<p class="description"><?php echo esc_html( sprintf( __( 'Derivata dal locale WordPress corrente: %s', 'aisuru' ), $lang['locale'] ) ); ?></p>
							</div>
						</div>
					</section>

					<section class="aisuru-panel">
						<div class="aisuru-panel__heading">
							<span>3</span>
							<div>
								<h2><?php esc_html_e( 'Contenuto iniziale', 'aisuru' ); ?></h2>
								<p><?php esc_html_e( 'Facoltativo. Se vuoto, vengono usati i valori della Landing Experience.', 'aisuru' ); ?></p>
							</div>
						</div>
						<div class="aisuru-field">
							<label for="context"><?php esc_html_e( 'Variabili di contesto', 'aisuru' ); ?></label>
							<textarea id="context" name="context" rows="3" placeholder="LOCATION:Milano,CHANNEL:WordPress" data-inherited-field="contextVars"><?php echo esc_textarea( $agent->context ); ?></textarea>
							<p class="description" data-inherited-value="contextVars" hidden></p>
						</div>
						<div class="aisuru-field">
							<label for="initial_question"><?php esc_html_e( 'Domanda iniziale', 'aisuru' ); ?></label>
							<textarea id="initial_question" name="initial_question" rows="3" data-inherited-field="initialQuestion"><?php echo esc_textarea( $agent->initial_question ); ?></textarea>
							<p class="description" data-inherited-value="initialQuestion" hidden></p>
						</div>
						<div class="aisuru-form-grid aisuru-form-grid--compact">
							<div class="aisuru-field">
								<label for="max_total_message_payload"><?php esc_html_e( 'Payload totale massimo', 'aisuru' ); ?></label>
								<input
									type="number"
									id="max_total_message_payload"
									name="max_total_message_payload"
									min="1"
									step="1"
									value="<?php echo esc_attr( $agent->max_total_message_payload ); ?>"
									data-inherited-field="maxTotalMessagePayload"
								>
								<p class="description" data-inherited-value="maxTotalMessagePayload" hidden></p>
							</div>
							<div class="aisuru-field">
								<label for="tag"><?php esc_html_e( 'Tag persona', 'aisuru' ); ?></label>
								<input type="text" id="tag" name="tag" value="<?php echo esc_attr( $agent->tag ); ?>" data-inherited-field="tag">
								<p class="description" data-inherited-value="tag" hidden></p>
							</div>
							<div class="aisuru-field">
								<label for="pin"><?php esc_html_e( 'PIN persona', 'aisuru' ); ?></label>
								<input type="text" id="pin" name="pin" value="<?php echo esc_attr( $agent->pin ); ?>" data-inherited-field="pin">
								<p class="description"><?php esc_html_e( 'Non è la password dell’agente: se impostato viene pubblicato nel markup del widget.', 'aisuru' ); ?></p>
								<p class="description" data-inherited-value="pin" hidden></p>
							</div>
						</div>
					</section>

					<section class="aisuru-panel">
						<div class="aisuru-panel__heading">
							<span>4</span>
							<div>
								<h2><?php esc_html_e( 'Opzioni widget', 'aisuru' ); ?></h2>
								<p><?php esc_html_e( '“Eredita” mantiene il valore configurato nella Landing Experience.', 'aisuru' ); ?></p>
							</div>
						</div>
						<div class="aisuru-form-grid">
							<?php $this->tri_state_field( 'show_settings', __( 'Pannello impostazioni', 'aisuru' ), $agent->show_settings ); ?>
							<?php $this->tri_state_field( 'show_share', __( 'Condivisione', 'aisuru' ), $agent->show_share ); ?>
							<?php $this->tri_state_field( 'show_clear', __( 'Cancella conversazione', 'aisuru' ), $agent->show_clear ); ?>
							<?php $this->tri_state_field( 'show_typing_text', __( 'Testo di caricamento', 'aisuru' ), $agent->show_typing_text ); ?>
							<?php $this->tri_state_field( 'show_only_last_messages', __( 'Solo ultimi messaggi', 'aisuru' ), $agent->show_only_last_messages ); ?>
							<?php $this->tri_state_field( 'show_message_consumption', __( 'Consumo messaggi', 'aisuru' ), $agent->show_message_consumption ); ?>
							<?php $this->tri_state_field( 'avatar_3d_hidden', __( 'Nascondi avatar 3D', 'aisuru' ), $agent->avatar_3d_hidden ); ?>
							<?php $this->tri_state_field( 'show_login', __( 'Mostra accesso', 'aisuru' ), $agent->show_login ); ?>
							<?php $this->tri_state_field( 'enable_audio', __( 'Audio', 'aisuru' ), $agent->enable_audio ); ?>
						</div>
					</section>

					<details class="aisuru-panel aisuru-panel--advanced">
						<summary><?php esc_html_e( 'Impostazioni avanzate URL', 'aisuru' ); ?></summary>
						<p><?php esc_html_e( 'Lascia vuoto per usare i valori globali del plugin.', 'aisuru' ); ?></p>
						<div class="aisuru-form-grid">
							<?php $this->url_field( 'api_url', 'apiURL', $agent->api_url ); ?>
							<?php $this->url_field( 'engine_url', 'engineURL', $agent->engine_url ); ?>
							<?php $this->url_field( 'base_url', 'baseURL', $agent->base_url ); ?>
						</div>
					</details>
				</div>

				<aside class="aisuru-editor__sidebar">
					<div class="aisuru-save-card">
						<h2><?php esc_html_e( 'Pubblica', 'aisuru' ); ?></h2>
						<?php if ( $id ) : ?>
							<div class="aisuru-shortcode-preview">
								<span><?php esc_html_e( 'Shortcode', 'aisuru' ); ?></span>
								<code>[aisuru id="<?php echo esc_attr( $id ); ?>"]</code>
							</div>
						<?php endif; ?>
						<button type="submit" class="button button-primary button-hero">
							<?php echo $id ? esc_html__( 'Salva modifiche', 'aisuru' ) : esc_html__( 'Crea agente', 'aisuru' ); ?>
						</button>
					</div>
				</aside>
			</form>
		</div>
		<?php
	}

	/**
	 * Render global settings.
	 *
	 * @return void
	 */
	public function settings_page() {
		$this->guard();
		$settings = Aisuru_Database::get_settings();
		?>
		<div class="wrap aisuru-admin">
			<div class="aisuru-page-header">
				<div>
					<span class="aisuru-eyebrow"><?php esc_html_e( 'Configurazione globale', 'aisuru' ); ?></span>
					<h1><?php esc_html_e( 'Impostazioni AIsuru', 'aisuru' ); ?></h1>
					<p><?php esc_html_e( 'Valori predefiniti usati da tutte le configurazioni, salvo override sul singolo agente.', 'aisuru' ); ?></p>
				</div>
			</div>
			<?php $this->render_notice(); ?>
			<form method="post" class="aisuru-settings-form">
				<?php wp_nonce_field( 'aisuru_save_settings' ); ?>
				<input type="hidden" name="aisuru_action" value="save_settings">
				<section class="aisuru-panel">
					<h2><?php esc_html_e( 'Endpoint', 'aisuru' ); ?></h2>
					<?php $this->url_field( 'api_url', 'apiURL', $settings['api_url'], true ); ?>
					<?php $this->url_field( 'engine_url', 'engineURL', $settings['engine_url'], true ); ?>
				</section>
				<section class="aisuru-panel">
					<h2><?php esc_html_e( 'Asset versionati', 'aisuru' ); ?></h2>
					<p><?php esc_html_e( 'Le versioni sono fissate per evitare cambiamenti imprevisti e garantire il supporto PII.', 'aisuru' ); ?></p>
					<?php $this->url_field( 'webcomponent_url', __( 'Web component JavaScript', 'aisuru' ), $settings['webcomponent_url'], true ); ?>
					<?php $this->url_field( 'styles_url', __( 'Widget CSS', 'aisuru' ), $settings['styles_url'], true ); ?>
				</section>
				<section class="aisuru-panel">
					<label class="aisuru-checkbox">
						<input type="checkbox" name="remove_data" value="1" <?php checked( ! empty( $settings['remove_data'] ) ); ?>>
						<span>
							<strong><?php esc_html_e( 'Elimina i dati alla disinstallazione', 'aisuru' ); ?></strong>
							<small><?php esc_html_e( 'Se disattivato, configurazioni e impostazioni restano nel database.', 'aisuru' ); ?></small>
						</span>
					</label>
				</section>
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Salva impostazioni', 'aisuru' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Save one agent after validation.
	 *
	 * @return void
	 */
	private function save_agent() {
		check_admin_referer( 'aisuru_save_agent' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$required = array( 'title', 'tenant_id', 'memori_name', 'memori_id', 'owner_user_name', 'owner_user_id', 'integration_id' );
		foreach ( $required as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				$this->redirect_agent( $id, 'missing' );
			}
		}

		$memori_id      = sanitize_text_field( wp_unslash( $_POST['memori_id'] ) );
		$owner_user_id  = sanitize_text_field( wp_unslash( $_POST['owner_user_id'] ) );
		$integration_id = sanitize_text_field( wp_unslash( $_POST['integration_id'] ) );
		foreach ( array( $memori_id, $owner_user_id, $integration_id ) as $uuid ) {
			if ( ! $this->is_uuid( $uuid ) ) {
				$this->redirect_agent( $id, 'uuid' );
			}
		}

		$layout   = sanitize_text_field( wp_unslash( $_POST['layout'] ?? '' ) );
		$layouts  = array_keys( $this->layouts() );
		$layout   = in_array( $layout, $layouts, true ) ? $layout : 'WEBSITE_ASSISTANT';
		$placement = sanitize_text_field( wp_unslash( $_POST['placement'] ?? 'shortcode' ) );
		if ( 'WEBSITE_ASSISTANT' !== $layout || 'global' !== $placement ) {
			$placement = 'shortcode';
		}

		$height = sanitize_text_field( wp_unslash( $_POST['height'] ?? '100%' ) );
		if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|%|vh|vw|rem|em)$/', $height ) ) {
			$height = '100%';
		}

		$max_total_message_payload = $this->sanitize_optional_positive_int(
			$_POST['max_total_message_payload'] ?? ''
		);
		if ( false === $max_total_message_payload ) {
			$this->redirect_agent( $id, 'payload' );
		}

		$data = array(
			'title'                   => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
			'tenant_id'               => sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ),
			'memori_name'             => sanitize_text_field( wp_unslash( $_POST['memori_name'] ) ),
			'memori_id'               => $memori_id,
			'owner_user_name'         => sanitize_text_field( wp_unslash( $_POST['owner_user_name'] ) ),
			'owner_user_id'           => $owner_user_id,
			'integration_id'          => $integration_id,
			'layout'                  => $layout,
			'placement'               => $placement,
			'height'                  => $height,
			'context'                 => sanitize_textarea_field( wp_unslash( $_POST['context'] ?? '' ) ),
			'initial_question'        => sanitize_textarea_field( wp_unslash( $_POST['initial_question'] ?? '' ) ),
			'max_total_message_payload' => $max_total_message_payload,
			'tag'                     => $this->sanitize_short_text( $_POST['tag'] ?? '' ),
			'pin'                     => $this->sanitize_short_text( $_POST['pin'] ?? '' ),
			'api_url'                 => $this->sanitize_optional_url( $_POST['api_url'] ?? '' ),
			'engine_url'              => $this->sanitize_optional_url( $_POST['engine_url'] ?? '' ),
			'base_url'                => $this->sanitize_optional_url( $_POST['base_url'] ?? '' ),
			'show_settings'           => $this->sanitize_tri_state( $_POST['show_settings'] ?? '' ),
			'show_share'              => $this->sanitize_tri_state( $_POST['show_share'] ?? '' ),
			'show_clear'              => $this->sanitize_tri_state( $_POST['show_clear'] ?? '' ),
			'show_typing_text'        => $this->sanitize_tri_state( $_POST['show_typing_text'] ?? '' ),
			'show_only_last_messages' => $this->sanitize_tri_state( $_POST['show_only_last_messages'] ?? '' ),
			'show_message_consumption' => $this->sanitize_tri_state( $_POST['show_message_consumption'] ?? '' ),
			'avatar_3d_hidden'         => $this->sanitize_tri_state( $_POST['avatar_3d_hidden'] ?? '' ),
			'show_login'               => $this->sanitize_tri_state( $_POST['show_login'] ?? '' ),
			'enable_audio'             => $this->sanitize_tri_state( $_POST['enable_audio'] ?? '' ),
		);

		$saved_id = $this->repository->save( $data, $id );
		if ( ! $saved_id ) {
			$this->redirect_agent( $id, 'error' );
		}

		wp_safe_redirect( $this->admin_url( 'aisuru-agent', array( 'id' => $saved_id, 'message' => 'saved' ) ) );
		exit;
	}

	/**
	 * Save global settings.
	 *
	 * @return void
	 */
	private function save_settings() {
		check_admin_referer( 'aisuru_save_settings' );
		$defaults = Aisuru_Database::default_settings();
		$settings = array(
			'api_url'          => $this->sanitize_required_url( $_POST['api_url'] ?? '', $defaults['api_url'] ),
			'engine_url'       => $this->sanitize_required_url( $_POST['engine_url'] ?? '', $defaults['engine_url'] ),
			'webcomponent_url' => $this->sanitize_required_url( $_POST['webcomponent_url'] ?? '', $defaults['webcomponent_url'] ),
			'styles_url'       => $this->sanitize_required_url( $_POST['styles_url'] ?? '', $defaults['styles_url'] ),
			'remove_data'      => isset( $_POST['remove_data'] ) ? 1 : 0,
		);

		update_option( Aisuru_Database::SETTINGS_OPTION, $settings, false );
		wp_safe_redirect( $this->admin_url( 'aisuru-settings', array( 'message' => 'settings-saved' ) ) );
		exit;
	}

	/**
	 * Render layout radio cards.
	 *
	 * @param string $selected_layout Current layout.
	 * @return void
	 */
	private function layout_cards( $selected_layout ) {
		?>
		<div class="aisuru-layout-grid" data-layout-grid>
			<?php foreach ( $this->layouts() as $value => $layout ) : ?>
				<label class="aisuru-layout-option">
					<input type="radio" name="layout" value="<?php echo esc_attr( $value ); ?>" <?php checked( $selected_layout, $value ); ?>>
					<span class="dashicons <?php echo esc_attr( $layout['icon'] ); ?>" aria-hidden="true"></span>
					<strong><?php echo esc_html( $layout['label'] ); ?></strong>
					<small><?php echo esc_html( $layout['description'] ); ?></small>
					<?php if ( ! empty( $layout['tag'] ) ) : ?>
						<em><?php echo esc_html( $layout['tag'] ); ?></em>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Public layouts safe enough for WordPress.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function layouts() {
		return array(
			'WEBSITE_ASSISTANT' => array(
				'label'       => 'Website Assistant',
				'description' => __( 'Pulsante flottante che apre la chat. Ideale per un sito completo.', 'aisuru' ),
				'icon'        => 'dashicons-format-chat',
				'tag'         => __( 'Consigliato', 'aisuru' ),
			),
			'CHAT' => array(
				'label'       => 'Chat',
				'description' => __( 'Solo conversazione, adatta dentro pagine e articoli.', 'aisuru' ),
				'icon'        => 'dashicons-testimonial',
				'tag'         => '',
			),
			'FULLPAGE' => array(
				'label'       => 'Full Page',
				'description' => __( 'Avatar e chat affiancati per pagine ampie.', 'aisuru' ),
				'icon'        => 'dashicons-layout',
				'tag'         => '',
			),
			'TOTEM' => array(
				'label'       => 'Totem',
				'description' => __( 'Esperienza immersiva a tutta altezza.', 'aisuru' ),
				'icon'        => 'dashicons-format-image',
				'tag'         => __( 'Pagina dedicata', 'aisuru' ),
			),
			'ZOOMED_FULL_BODY' => array(
				'label'       => 'Zoomed Full Body',
				'description' => __( 'Avatar 3D in primo piano con chat.', 'aisuru' ),
				'icon'        => 'dashicons-businessperson',
				'tag'         => __( 'Pagina dedicata', 'aisuru' ),
			),
		);
	}

	/**
	 * Render a standard text input.
	 *
	 * @param string $name Field name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param string $help Help/placeholder.
	 * @param bool   $required Required flag.
	 * @return void
	 */
	private function text_field( $name, $label, $value, $help = '', $required = false ) {
		?>
		<div class="aisuru-field">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
			<input
				type="text"
				id="<?php echo esc_attr( $name ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php echo $required ? 'required' : ''; ?>
				<?php echo $help && false === strpos( $help, ' ' ) ? 'placeholder="' . esc_attr( $help ) . '"' : ''; ?>
			>
			<?php if ( $help && false !== strpos( $help, ' ' ) ) : ?>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a URL input.
	 *
	 * @param string $name Field name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param bool   $required Required.
	 * @return void
	 */
	private function url_field( $name, $label, $value, $required = false ) {
		?>
		<div class="aisuru-field">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
			<input type="url" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $required ? 'required' : ''; ?>>
		</div>
		<?php
	}

	/**
	 * Render an inherit/on/off select.
	 *
	 * @param string   $name Field name.
	 * @param string   $label Label.
	 * @param int|null $value Current value.
	 * @return void
	 */
	private function tri_state_field( $name, $label, $value ) {
		$current = null === $value ? '' : (string) (int) $value;
		?>
		<div class="aisuru-field">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="" <?php selected( '', $current ); ?>><?php esc_html_e( 'Eredita da AIsuru', 'aisuru' ); ?></option>
				<option value="1" <?php selected( '1', $current ); ?>><?php esc_html_e( 'Attivo', 'aisuru' ); ?></option>
				<option value="0" <?php selected( '0', $current ); ?>><?php esc_html_e( 'Disattivo', 'aisuru' ); ?></option>
			</select>
			<p class="description" data-inherited-option="<?php echo esc_attr( $name ); ?>" hidden></p>
		</div>
		<?php
	}

	/**
	 * Blank agent model.
	 *
	 * @return object
	 */
	private function empty_agent() {
		return (object) array(
			'id'                      => 0,
			'title'                   => '',
			'memori_name'             => '',
			'memori_id'               => '',
			'owner_user_name'         => '',
			'owner_user_id'           => '',
			'tenant_id'               => 'www.aisuru.com',
			'integration_id'          => '',
			'layout'                  => 'WEBSITE_ASSISTANT',
			'placement'               => 'shortcode',
			'height'                  => '600px',
			'api_url'                 => '',
			'engine_url'              => '',
			'base_url'                => '',
			'context'                 => '',
			'initial_question'        => '',
			'max_total_message_payload' => null,
			'tag'                     => '',
			'pin'                     => '',
			'show_settings'           => null,
			'show_share'              => null,
			'show_clear'              => null,
			'show_typing_text'        => null,
			'show_only_last_messages' => null,
			'show_message_consumption' => null,
			'avatar_3d_hidden'         => null,
			'show_login'               => null,
			'enable_audio'             => null,
		);
	}

	/**
	 * Render status messages and migration information.
	 *
	 * @return void
	 */
	private function render_notice() {
		$message = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '';
		$notices = array(
			'saved'          => array( 'success', __( 'Configurazione salvata.', 'aisuru' ) ),
			'deleted'        => array( 'success', __( 'Configurazione eliminata.', 'aisuru' ) ),
			'settings-saved' => array( 'success', __( 'Impostazioni salvate.', 'aisuru' ) ),
			'missing'        => array( 'error', __( 'Compila tutti i campi obbligatori.', 'aisuru' ) ),
			'uuid'           => array( 'error', __( 'Memori ID, Owner user ID e Integration ID devono essere UUID validi.', 'aisuru' ) ),
			'payload'        => array( 'error', __( 'Il payload totale massimo deve essere un numero intero positivo.', 'aisuru' ) ),
			'error'          => array( 'error', __( 'Salvataggio non riuscito. Controlla il database e riprova.', 'aisuru' ) ),
		);

		if ( isset( $notices[ $message ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notices[ $message ][0] ),
				esc_html( $notices[ $message ][1] )
			);
		}

		$migration = get_option( Aisuru_Database::MIGRATION_OPTION );
		if ( is_array( $migration ) && 'confirmation_required' === ( $migration['status'] ?? '' ) ) {
			$migration_url = wp_nonce_url(
				$this->admin_url( 'aisuru', array( 'action' => 'migrate-legacy' ) ),
				'aisuru_migrate_legacy'
			);
			printf(
				'<div class="notice notice-warning"><p>%1$s <a class="button button-secondary" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'È stata trovata una tabella legacy con prefisso wp_ diverso da quello del sito. Importala solo se appartiene a questa installazione.', 'aisuru' ),
				esc_url( $migration_url ),
				esc_html__( 'Conferma importazione legacy', 'aisuru' )
			);
		} elseif ( is_array( $migration ) && 'completed' === ( $migration['status'] ?? '' ) && ! get_option( 'aisuru_migration_notice_shown' ) ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( __( 'Migrazione completata: %d configurazioni legacy importate. Aggiungi l’Integration ID per abilitare la PII.', 'aisuru' ), (int) $migration['count'] ) )
			);
			update_option( 'aisuru_migration_notice_shown', 1, false );
		}
	}

	/**
	 * Redirect back to editor with an error.
	 *
	 * @param int    $id Record ID.
	 * @param string $message Message code.
	 * @return void
	 */
	private function redirect_agent( $id, $message ) {
		$args = array( 'message' => $message );
		if ( $id ) {
			$args['id'] = $id;
		}
		wp_safe_redirect( $this->admin_url( 'aisuru-agent', $args ) );
		exit;
	}

	/**
	 * Build plugin admin URLs.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra args.
	 * @return string
	 */
	private function admin_url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Check page access.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'aisuru' ) );
		}
	}

	/**
	 * Sanitize an optional positive integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null|false
	 */
	private function sanitize_optional_positive_int( $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( '' === $value ) {
			return null;
		}

		return filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array( 'options' => array( 'min_range' => 1 ) )
		);
	}

	/**
	 * Sanitize a short optional widget value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_short_text( $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, 64 )
			: substr( $value, 0, 64 );
	}

	/**
	 * Validate the canonical UUID shape without restricting its version.
	 *
	 * @param string $value UUID value.
	 * @return bool
	 */
	private function is_uuid( $value ) {
		return 1 === preg_match(
			'/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
			(string) $value
		);
	}

	/**
	 * Sanitize a nullable tri-state field.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private function sanitize_tri_state( $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return in_array( $value, array( '0', '1' ), true ) ? (int) $value : null;
	}

	/**
	 * Sanitize an optional HTTP URL.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private function sanitize_optional_url( $value ) {
		$value = esc_url_raw( wp_unslash( $value ), array( 'http', 'https' ) );
		return is_string( $value ) ? untrailingslashit( $value ) : '';
	}

	/**
	 * Sanitize a required URL or return its default.
	 *
	 * @param mixed  $value Raw URL.
	 * @param string $default Default.
	 * @return string
	 */
	private function sanitize_required_url( $value, $default ) {
		$value = $this->sanitize_optional_url( $value );
		return $value ?: $default;
	}

	/**
	 * Friendly layout label.
	 *
	 * @param string $layout Layout code.
	 * @return string
	 */
	private function layout_label( $layout ) {
		$layouts = $this->layouts();
		return isset( $layouts[ $layout ] ) ? $layouts[ $layout ]['label'] : $layout;
	}
}
