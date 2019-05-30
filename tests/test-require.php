<?php
/**
 * Class SampleTest
 *
 * @package Hamecache
 */

/**
 * Sample test case.
 */
class RequireTest extends WP_UnitTestCase {

	/**
	 * Detect if file exists.
	 */
	public function test_function() {
		$this->assertTrue( function_exists( 'hamecache_purge' ) );
	}

	/**
	 * Detect if class exists.
	 */
	public function test_class() {
		$this->assertTrue( class_exists( 'Hametuha\\Hamecache\\Purger' ) );
	}
}
