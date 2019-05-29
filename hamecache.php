<?php
/**
 * Plugin Name:     Hamecache
 * Plugin URI:
 * Description:     A page cache plugin for WordPress by Hametuha. Cache stored on cloud front.
 * Author:          Hametuha
 * Author URI:      https://hametuha.co.jp
 * Text Domain:     hamecache
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         hamecache
 */

defined( 'ABSPATH' ) || die();

/**
 * Initialize hamecache.
 */
function hamecache_init() {
	load_plugin_textdomain( 'hamecache', false, basename( __DIR__ ) );
	require_once  __DIR__ . '/vendor/autoload.php';
	$info = get_file_data( __FILE__, [
		'version' => 'Version',
	] );
	// Init options.
	\Hametuha\Hamecache\Option::get_instance()->version = $info['version'];
	\Hametuha\Hamecache\Option::get_instance()->dir = __DIR__;
	// Init purge controller.
	\Hametuha\Hamecache\Purger::get_instance();
	// Register CLI if possible.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'hamecache', \Hametuha\Hamecache\Command::class );
	}
	foreach ( [ 'functions' ] as $dir ) {
		$dir_path = __DIR__ . '/' . $dir;
		if ( ! is_dir( $dir_path ) ) {
			continue;
		}
		// Load all PHP files.
		foreach ( scandir( $dir_path ) as $file ) {
			if ( preg_match( '/^[^._].*\.php$/u', $file ) ) {
				require $dir_path . '/' . $file;
			}
		}
	}
}
add_action( 'plugins_loaded', 'hamecache_init' );
