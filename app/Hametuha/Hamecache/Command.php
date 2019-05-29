<?php

namespace Hametuha\Hamecache;

use cli\Table;

/**
 * CLI utility for hamecache.
 *
 * @package hamecache
 */
class Command extends \WP_CLI_Command {

	/**
	 * Get registered rules.
	 *
	 */
	public function list_rules() {

	}

	/**
	 * Get zone id
	 */
	public function zone_ids() {
		$this->stop_if_error( true );
		$option = Option::get_instance();
		$result = hamecache_list_zones();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		if ( ! $result->result_info->count ) {
			\WP_CLI::error( sprintf( __( 'No zone found for domain %s.', 'hamecache' ), $option->domain ) );
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
}
