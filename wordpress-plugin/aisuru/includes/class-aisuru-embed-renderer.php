<?php
/**
 * Build the memori-client markup.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Embed_Renderer {
	/**
	 * Build web component attributes for an agent record.
	 *
	 * @param object $agent Agent database record.
	 * @return array<string, string>
	 */
	public function build_attributes( $agent ) {
		$settings  = Aisuru_Database::get_settings();
		$languages = Aisuru_Locale_Resolver::resolve();
		$tenant    = trim( (string) $agent->tenant_id );
		$base_url  = trim( (string) $agent->base_url );

		if ( ! $base_url ) {
			$base_url = preg_match( '#^https?://#i', $tenant )
				? $tenant
				: 'https://' . $tenant;
		}

		$attributes = array(
			'memoriName'   => (string) $agent->memori_name,
			'ownerUserName'=> (string) $agent->owner_user_name,
			'memoriID'     => (string) $agent->memori_id,
			'ownerUserID'  => (string) $agent->owner_user_id,
			'tenantID'     => $tenant,
			'apiURL'       => $agent->api_url ?: $settings['api_url'],
			'engineURL'    => $agent->engine_url ?: $settings['engine_url'],
			'baseURL'      => $base_url,
			'integrationID'=> (string) $agent->integration_id,
			'layout'       => (string) $agent->layout,
			'uiLang'       => $languages['ui_lang'],
			'spokenLang'   => $languages['spoken_lang'],
			'multilingual' => 'true',
			'height'       => (string) $agent->height,
		);

		if ( '' !== (string) $agent->context ) {
			$attributes['context'] = (string) $agent->context;
		}
		if ( '' !== (string) $agent->initial_question ) {
			$attributes['initialQuestion'] = (string) $agent->initial_question;
		}
		if ( null !== $agent->max_total_message_payload && 0 < (int) $agent->max_total_message_payload ) {
			$attributes['maxTotalMessagePayload'] = (string) (int) $agent->max_total_message_payload;
		}
		if ( '' !== (string) $agent->tag ) {
			$attributes['tag'] = (string) $agent->tag;
		}
		if ( '' !== (string) $agent->pin ) {
			$attributes['pin'] = (string) $agent->pin;
		}

		$boolean_attributes = array(
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

		foreach ( $boolean_attributes as $property => $attribute ) {
			if ( null !== $agent->{$property} ) {
				$attributes[ $attribute ] = (int) $agent->{$property} ? 'true' : 'false';
			}
		}

		return apply_filters( 'aisuru_embed_attributes', $attributes, $agent, $languages );
	}

	/**
	 * Render one widget.
	 *
	 * @param object $agent Agent record.
	 * @param bool   $global Whether this is the footer assistant.
	 * @return string
	 */
	public function render( $agent, $global = false ) {
		$attributes = $this->build_attributes( $agent );
		$markup     = '<memori-client';

		foreach ( $attributes as $name => $value ) {
			if ( '' === $value && in_array( $name, array( 'memoriID', 'ownerUserID', 'integrationID' ), true ) ) {
				continue;
			}
			$markup .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}
		$markup .= '></memori-client>';

		if ( $global ) {
			return $markup;
		}

		$height = preg_match( '/^\d+(?:\.\d+)?(?:px|%|vh|vw|rem|em)$/', (string) $agent->height )
			? (string) $agent->height
			: '100%';

		return sprintf(
			'<div class="aisuru-embed aisuru-layout-%1$s" style="height:%2$s">%3$s</div>',
			esc_attr( strtolower( (string) $agent->layout ) ),
			esc_attr( $height ),
			$markup
		);
	}
}
