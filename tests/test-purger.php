<?php
/**
 * Test purge logging.
 *
 * @package Hamecache
 */

use Hametuha\Hamecache\Purger;

/**
 * Purger test case.
 */
class PurgerTest extends WP_UnitTestCase {

	/**
	 * Temporary log file.
	 *
	 * @var string
	 */
	protected $log_file = '';

	/**
	 * Original error_log ini value.
	 *
	 * @var string
	 */
	protected $original_error_log = '';

	/**
	 * Response body which mock API returns.
	 *
	 * @var array
	 */
	protected $response = [];

	/**
	 * Set up credentials, mock API and log file.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'hamecache_mail', 'test@example.com' );
		update_option( 'hamecache_token', 'dummy-token' );
		update_option( 'hamecache_zone_id', 'dummy-zone' );
		update_option( 'hamecache_post_types', [ 'post' ] );
		$this->response = [
			'success'  => true,
			'errors'   => [],
			'messages' => [],
			'result'   => [ 'id' => 'dummy-zone' ],
		];
		add_filter( 'pre_http_request', [ $this, 'mock_api' ], 10, 3 );
		$this->log_file           = wp_tempnam( 'hamecache-log' );
		$this->original_error_log = ini_set( 'error_log', $this->log_file );
	}

	/**
	 * Restore log setting.
	 */
	public function tear_down() {
		ini_set( 'error_log', $this->original_error_log );
		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}
		remove_filter( 'pre_http_request', [ $this, 'mock_api' ], 10 );
		parent::tear_down();
	}

	/**
	 * Mock CloudFlare API.
	 *
	 * @param false|array $preempt Preempt value.
	 * @param array       $args    Request args.
	 * @param string      $url     Request URL.
	 * @return false|array
	 */
	public function mock_api( $preempt, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.cloudflare.com/' ) ) {
			return $preempt;
		}
		return [
			'headers'  => [],
			'body'     => wp_json_encode( $this->response ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
	}

	/**
	 * Get log content.
	 *
	 * @return string
	 */
	protected function get_log() {
		clearstatcache();
		return file_exists( $this->log_file ) ? (string) file_get_contents( $this->log_file ) : '';
	}

	/**
	 * Create post without triggering purge on save.
	 *
	 * @return WP_Post
	 */
	protected function create_post() {
		$post = self::factory()->post->create_and_get();
		// Discard logs which save_post may have written.
		file_put_contents( $this->log_file, '' );
		return $post;
	}

	/**
	 * Success is not logged by default.
	 */
	public function test_success_not_logged() {
		$result = Purger::get_instance()->purge( $this->create_post() );
		$this->assertTrue( $result );
		$this->assertSame( '', $this->get_log() );
	}

	/**
	 * Success is logged when filter allows.
	 */
	public function test_success_logged_with_filter() {
		add_filter( 'hamecache_log_success', '__return_true' );
		$post   = $this->create_post();
		$result = Purger::get_instance()->purge( $post );
		remove_filter( 'hamecache_log_success', '__return_true' );
		$this->assertTrue( $result );
		$this->assertStringContainsString( "HAMECACHE\t200\tPOST:{$post->ID}\tPurged!", $this->get_log() );
	}

	/**
	 * Failure is logged by default.
	 */
	public function test_failure_logged() {
		$this->response = [
			'success' => false,
			'errors'  => [
				[
					'code'    => 1234,
					'message' => 'Something wrong.',
				],
			],
		];
		$post           = $this->create_post();
		$result         = Purger::get_instance()->purge( $post );
		$this->assertWPError( $result );
		$log = $this->get_log();
		$this->assertStringContainsString( "HAMECACHE\t500\tPOST:{$post->ID}\tError", $log );
		$this->assertStringContainsString( 'Something wrong.', $log );
	}

	/**
	 * Nothing is logged if "Do not log" is on.
	 */
	public function test_do_not_log() {
		update_option( 'hamecache_do_not_log', 1 );
		$this->response = [
			'success' => false,
			'errors'  => [],
		];
		$result         = Purger::get_instance()->purge( $this->create_post() );
		$this->assertWPError( $result );
		$this->assertSame( '', $this->get_log() );
	}
}
