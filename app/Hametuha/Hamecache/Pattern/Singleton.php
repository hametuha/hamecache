<?php

namespace Hametuha\Hamecache\Pattern;


/**
 * Singleton pattern
 *
 * @package hamecache
 */
abstract class Singleton {

	/**
	 * @var array
	 */
	private static $instances = [];

	/**
	 * Constructor finalized.
	 */
	final protected function __construct() {
		$this->init();
	}

	/**
	 * Constructor.
	 */
	protected function init() {
		// Do something.
	}

	/**
	 * Get instance
	 *
	 * @return static
	 */
	final public static function get_instance() {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name();
		}
		return self::$instances[ $class_name ];
	}
}
