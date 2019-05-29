<?php

namespace Hametuha\Hamecache;

use cli\Table;

/**
 * CLI utility for hamecache.
 *
 * @package hamecache
 * @property-read Option $option
 * @property-read Purger $purge
 */
class Command extends \WP_CLI_Command {

	/**
	 * Get registered rules.
	 *
	 */
	public function list_rules() {

	}

	/**
	 * Get URL to purge if post is updated.
	 *
	 * These URL is
	 *
	 * ## OPTIONS
	 *
	 * : <post_id>
	 *   Post id to purge.
	 *
	 * @synopsis <post_id>
	 * @param array $args
	 */
	public function urls( $args ) {
		list( $post_id ) = $args;
		$post = get_post( $post_id );
		if ( ! $this->option->is_supported( $post ) ) {
			\WP_CLI::error( __( 'This post is not supported.', 'hamecache' ) );
		}
		$purge = $this->purge->get_purge_url( $post );
		if ( ! $purge ) {
			\WP_CLI::error( __( 'This post has no url to be purged.', 'hamecache' ) );
		}
		$table = new Table( );
		$table->setHeaders( [ '#', 'Type', 'URL' ] );
		$counter = 0;
		foreach ( $purge as $type => $url ) {
			$counter++;
			$table->addRow( [ $counter, rawurldecode( $type ), rawurldecode( $url ) ] );
		}
		$table->display();
		$count = count( $purge );
		\WP_CLI::success( sprintf(
			__( '%s has %s to be purged.', 'hamecache' ),
			get_the_title( $post ),
			sprintf( _n( '%d URL', '%d URLS', $count, 'hamecache' ), $count )
		) );
	}

	/**
	 * Get zone id
	 */
	public function zone_ids() {
		$this->stop_if_error( true );
		$result = hamecache_list_zones();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		if ( ! $result->result_info->count ) {
			\WP_CLI::error( sprintf( __( 'No zone found for domain %s.', 'hamecache' ), $this->option->domain ) );
		}
		$table = new Table();
		$table->setHeaders( [ 'Zone ID', 'Name', 'Registrar' ] );
		foreach ( $result->result as $item ) {
			$table->addRow( [ $item->id, $item->name, $item->original_registrar ] );
		}
		$table->display();
	}

	/**
	 * Stop process if this is WP_Error.
	 *
	 * @param bool $allow_zone_id If true, allow empty zone id.
	 */
	private function stop_if_error( $allow_zone_id = false ) {
		$wp_error = hamecache_service_available( true, $allow_zone_id );
		if ( is_wp_error( $wp_error ) ) {
			\WP_CLI::error( $wp_error->get_error_message() );
		}
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'option':
				return Option::get_instance();
			case 'purge':
				return Purger::get_instance();
			default:
				return null;
		}
	}
}
