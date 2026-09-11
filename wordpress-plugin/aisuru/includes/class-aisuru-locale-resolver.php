<?php
/**
 * Resolve WordPress locales to widget languages.
 *
 * @package Aisuru
 */

defined( 'ABSPATH' ) || exit;

final class Aisuru_Locale_Resolver {
	const SUPPORTED_SPOKEN_LANGUAGES = array(
		'AR',
		'BG',
		'CS',
		'DA',
		'DE',
		'EL',
		'EN',
		'ES',
		'ET',
		'FI',
		'FR',
		'HR',
		'HU',
		'IT',
		'JA',
		'LT',
		'LV',
		'NL',
		'PL',
		'PT',
		'RO',
		'RU',
		'SK',
		'SL',
		'SV',
		'UK',
		'ZH',
	);

	/**
	 * Resolve the current site language.
	 *
	 * get_locale() is filtered by common multilingual plugins on frontend requests.
	 *
	 * @param string|null $locale Optional explicit locale for tests/filters.
	 * @return array{locale:string,language:string,ui_lang:string,spoken_lang:string}
	 */
	public static function resolve( $locale = null ) {
		if ( null === $locale ) {
			$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		}

		$locale   = (string) apply_filters( 'aisuru_site_locale', $locale );
		$language = strtoupper( (string) strtok( str_replace( '-', '_', $locale ), '_' ) );
		$language = preg_replace( '/[^A-Z]/', '', $language );

		if ( ! $language ) {
			$language = 'EN';
		}

		$ui_lang     = 'IT' === $language ? 'IT' : 'EN';
		$spoken_lang = in_array( $language, self::SUPPORTED_SPOKEN_LANGUAGES, true )
			? $language
			: 'EN';

		$result = array(
			'locale'       => $locale,
			'language'     => $language,
			'ui_lang'      => $ui_lang,
			'spoken_lang'  => $spoken_lang,
		);

		return apply_filters( 'aisuru_resolved_languages', $result, $locale );
	}
}
