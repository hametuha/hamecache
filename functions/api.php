<?php

/**
 * Detect if cloud flare is available.
 *
 * @param bool $wp_error            If true, returns WP_Error.
 * @param bool $allow_empty_zone_id If true, allow empty zone id.
 * @return bool|WP_Error
 */
function hamecache_service_available( $wp_error = false, $allow_empty_zone_id = false ) {
	$option = \Hametuha\Hamecache\Option::get_instance();
	$available = $option->mail && $option->token;
	if ( ! $allow_empty_zone_id ) {
		$available = $available && $option->zone_id;
	}
	return $wp_error && ! $available ?  new WP_Error( 'hamecache_invalid_credentials', __( 'No credentials set.', 'hamecache' ) ) : $available;
}

/**
 * Make request to CloudFlare
 *
 * @param string $endpoint
 * @param array  $params
 * @param string $method
 * @param array  $args     Additional arguments.
 *
 * @return array|object|WP_Error
 */
function hamecache_make_request( $endpoint, $params, $method = 'GET', $args = [] ) {
	if ( is_wp_error( $valid = hamecache_service_available( true, true ) ) ) {
		return $valid;
	}
	$option   = \Hametuha\Hamecache\Option::get_instance();
	$endpoint = 'https://api.cloudflare.com/client/v4/' . ltrim( $endpoint, '/' );
	$args     = [
		'timeout'    => 10,
		'user-agent' => 'Hamecache/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		'sslverify'  => false,
		'method'     => strtoupper( $method ),
		'headers'    => [
			'X-Auth-Email' => $option->mail,
			'X-Auth-Key'   => $option->token,
			'Content-Type' => 'application/json',
		],
	];
	array_walk($params, function( &$value, $key ) {
		$a = rawurlencode( $value );
	} );
	switch ( $method ) {
		case 'GET':
			$endpoint = add_query_arg( $params, $endpoint );
			break;
		default:
			$args['body'] = json_encode( $params );
			break;
	}
	$result = wp_remote_request( $endpoint, $args );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$response = json_decode( $result['body'] );
	if ( ! $response ) {
		return new WP_Error( 'hamecache_parse_result_failure', __( 'Failed to parse result.', 'hamecache' ) );
	} else {
		return $response;
	}
}

/**
 * Purge all URLs.
 *
 * @param string[] $urls
 * @return stdClass|WP_Error
 */
function hamecache_purge( $urls ) {
	$valid = hamecache_service_available( true );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$option = \Hametuha\Hamecache\Option::get_instance();
	return hamecache_make_request( sprintf( '/zones/%s/purge_cache', $option->zone_id ), [
		'files' => $urls,
	], 'DELETE' );
}

/**
 * Purge everything.
 *
 * @return stdClass|WP_Error
 */
function hamecache_purge_everything() {
	$valid = hamecache_service_available( true );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$option = \Hametuha\Hamecache\Option::get_instance();
	return hamecache_make_request( sprintf( '/zones/%s/purge_cache', $option->zone_id ), [
		'purge_everything' => true,
	], 'DELETE' );
}

/**
 * Get page rules.
 *
 * @return stdClass|WP_Error
 */
function hamecache_get_page_rules() {
	$valid = hamecache_service_available( true );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	return hamecache_make_request( sprintf( 'zones/%s/pagerules', \Hametuha\Hamecache\Option::get_instance()->zone_id ), [], 'GET' );
}

/**
 * Get zone ids.
 *
 * @param int $page
 * @param int $per_page
 * @return array|WP_Error
 */
function hamecache_list_zones( $page = 1, $per_page = 20 ) {
	$valid = hamecache_service_available( true, true );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	return hamecache_make_request( 'zones', [
		'name' => \Hametuha\Hamecache\Option::get_instance()->domain,
		'status' => 'active',
		'page'     => max( 1, $page ),
		'per_page' => $per_page,
		'order'    => 'status',
		'direction' => 'desc',
		'match'     => 'all',
	], 'GET' );
}
