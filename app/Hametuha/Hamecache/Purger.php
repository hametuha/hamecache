<?php

namespace Hametuha\Hamecache;


use Hametuha\Hamecache\Pattern\Singleton;

/**
 * Purge controller
 *
 * @package hamecache
 * @property Option $option
 */
class Purger extends Singleton {

	/**
	 * Constructor.
	 */
	protected function init() {
		// If post is edited, purge cache.
		add_action( 'save_post', [ $this, 'save_post' ], 100, 2 );
		add_action( 'transition_post_status', [ $this, 'purge_scheduled' ], 100, 3 );
		// Add REST API.
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
		// Add button for admin_bar.
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 998 );
		// Register assets.
		add_action( 'init', [ $this, 'register_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register scripts.
	 */
	public function register_assets() {
		wp_register_script( 'hamecache-controller', $this->option->url . 'assets/js/purge.js', [ 'jquery-effects-highlight', 'wp-api-fetch', 'wp-i18n' ], $this->option->version, true );
	}

	/**
	 * Enqueue assets if admin bar exits.
	 */
	public function enqueue_scripts() {
		if ( is_admin_bar_showing() && current_user_can( 'edit_posts' ) ) {
			wp_enqueue_script( 'hamecache-controller' );
			wp_enqueue_style( 'hamecache-admin' );
		}
	}

	/**
	 * Purge post on upddate.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( $this->option->is_supported( $post ) ) {
			$this->purge( $post );
		}
	}

	/**
	 * Purge cache of scheduled posts.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Post $post
	 */
	public function purge_scheduled( $new_status, $old_status, $post ) {
		if ( $this->option->is_supported( $post ) && 'future' === $old_status && 'publish' === $new_status ) {
			$this->purge( $post );
		}
	}

	/**
	 * Purge post's cache.
	 *
	 * @param \WP_Post $post
	 * @return \WP_Error|bool
	 */
	public function purge( $post ) {
		$urls = $this->get_purge_url( $post );
		$code = 500;
		$err  = '';
		try {
			if ( ! $urls ) {
				throw new \Exception( __( 'No URL to be purged.', 'hamecache' ) );
			}
			$urls = array_values( $urls );
			$result = hamecache_purge( $urls );
			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message() );
			}
			if ( ! $result->success) {
				$errors = [ 'Error' ];
				foreach ( $result->errors as $index => $error ) {
					$errors[] = sprintf( "#%d\t%s\t%s", $index + 1, $error->code, $error->message );
				}
				throw new \Exception( implode( "\n", $errors ) );
			}
			$message = [ 'Purged!' ];
			foreach ( $urls as $index => $url ) {
				$num = $index + 1;
				$message[] = "#{$num}\t{$url}";
			}
			$code = 200;
			throw new \Exception( implode( "\n", $message ) );
		} catch ( \Exception $e ) {
			if ( ! $this->option->do_not_log ) {
				error_log( "HAMECACHE\t{$code}\tPOST:{$post->ID}\t" . trim( $e->getMessage() ) );
			}
			$err = $e->getMessage();
		}
		return 200 === $code ? true : new \WP_Error( 'failed_purge', $err);
	}

	/**
	 * Get URL to purge.
	 *
	 * @param \WP_Post $post
	 * @return string[]
	 */
	public function get_purge_url( $post ) {
		$urls = [];
		if ( ! $this->option->is_supported( $post ) ) {
			return $urls;
		}
		// Permalink
		$urls['permalink'] = get_permalink( $post );
		// Pages.
		$has_permalink = get_option( 'rewrite_rules' );
		if ( preg_match_all( '/<\!--nextpage-->/u', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $index => $match ) {
				if ( $index ) {
					$pagenum = $index + 1;
					if ( $has_permalink ) {
						$urls[ 'paging-' . $pagenum ] = sprintf( '%s/page/%d/', untrailingslashit( get_permalink( $post ) ), $pagenum );
					} else {
						$urls[ 'paging-' . $pagenum ] = add_query_arg( [
							'paged' => $pagenum,
						], get_permalink( $post ) );
					}
				}
			}
		}
		// AMP URL.
		if ( function_exists( 'amp_get_permalink' ) ) {
			$urls['amp'] = amp_get_permalink( $post->ID );
		}
		// Author url.
		$urls['author'] = get_author_posts_url( $post->post_author );
		$urls['author_feed'] = get_author_feed_link( $post->post_author );
		// Post type archive.
		$post_type = get_post_type_object( $post->post_type );
		if ( $post_type->has_archive ) {
			$urls['archive'] = get_post_type_archive_link( $post->post_type );
			$urls['archive_feed'] = get_post_type_archive_feed_link( $post->post_type );
		}
		// Taxonomies.
		foreach ( (array) $post_type->taxonomies as $taxonomy ) {
			$taxonomy = get_taxonomy( $taxonomy );
			if ( !$taxonomy || !$taxonomy->public ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( !$terms || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$urls[ 'tax_' . $taxonomy->name . '_' . $term->slug ] = get_term_link( $term );
			}
		}
		// Front page.
		if ( $this->option->purge_top_page ) {
			$urls['top'] = home_url( '/' );
			$urls['feed'] = get_feed_link();
		}
		/**
		 * hamecache_urls_to_be_purged
		 *
		 * List of URLs to be purged.
		 *
		 * @param array    $urls List of URL.
		 * @param \WP_Post $post Post to be edited.
		 * @return array
		 */
		return apply_filters( 'hamecache_urls_to_be_purged', $urls, $post );
	}

	/**
	 * Add admin bar menu.
	 *
	 * @param \WP_Admin_Bar $wp_adminbar
	 */
	public function admin_bar( &$wp_adminbar ) {
		$values = hamecache_service_available( true );
		if ( is_wp_error( $values ) ) {
			return;
		}
		// Add root.
		$wp_adminbar->add_menu( [
			'id'     => 'hamecache-ab-label',
			'href'   => false,
			'title'  => '<span class="ab-icon dashicons-cloud"></span>' . __( 'Cache', 'hamecache' ),
		] );
		// Add purge everything.
		if ( current_user_can( 'edit_others_posts' ) ) {
			$wp_adminbar->add_node( [
				'id' => 'hamecache-ab-everything',
				'href' => '#all',
				'title' => __( 'Purge Everything', 'hamecache' ),
				'parent' => 'hamecache-ab-label',
				'meta' => [
					'class' => 'hamecache-ab-btn'
				],
			] );
		}
		// If this is post screen or single post page,
		// Remove it.
		$single_post = null;
		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( 'post' === $screen->base && in_array( $screen->post_type, $this->option->post_types ) ) {
				$single_post = (int) filter_input( INPUT_GET, 'post' );
			}
		} else {
			if ( is_singular() && $this->option->is_supported( get_queried_object() ) ) {
				$single_post = (int) get_queried_object_id();
			}
		}
		if ( $single_post && current_user_can( 'edit_post', $single_post ) ) {
			$wp_adminbar->add_node( [
				'id'     => 'hamecache-ab-single',
				'href'   => sprintf( '#post-%d', $single_post ),
				'title'  => __( 'Purge this post', 'hamecache' ),
				'parent' => 'hamecache-ab-label',
				'meta' => [
					'class' => 'hamecache-ab-btn'
				],
			] );
		}
		// Enqueue scripts.
		wp_enqueue_script( 'hamecache-controller' );
	}

	/**
	 * Register rest endpoint.
	 */
	public function register_rest() {
		// Purge everything.
		register_rest_route( 'hamecache/v1', 'cache/everything', [
			[
				'methods'     => 'DELETE',
				'description' => __( 'Purge everything from cloudfalre.', 'hamecache' ),
				'args'        => [],
				'callback'    => [ $this, 'rest_purge_everything' ],
				'permission_callback' => function() {
					return current_user_can( 'edit_others_posts' );
				}
			]
		] );
		// Purge post cache.
		register_rest_route( 'hamecache/v1', 'cache/post/(?P<post_id>\d+)', [
			[
				'methods'     => 'DELETE',
				'description' => __( 'Purge cache of specified post.', 'hamecache' ),
				'args'        => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( '', 'hamecache' ),
						'validate_callback' => function( $var ) {
							return $this->option->is_supported( $var );
						},
					],
				],
				'callback'    => [ $this, 'rest_purge_post' ],
				'permission_callback' => function( \WP_REST_Request $request ) {
					return current_user_can( 'edit_post', $request->get_param( 'post_id' ) );
				}
			]
		] );
	}

	/**
	 * Handle purge everything.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function rest_purge_everything( $request ) {
		$result = hamecache_purge_everything();
		$code = 200;
		if ( is_wp_error( $result ) ) {
			$code = 500;
			$message = $result->get_error_message();
		} else {
			$message = __( 'Every cache is purged.', 'hamecache' );
			$log     =  json_encode( $result );
			$result = new \WP_REST_Response( [
				'success' => true,
				'message' => $message
			] );
			$message .= "\n" . $log;
		}
		if ( ! $this->option->do_not_log ) {
			error_log( "HAMECACHE\t{$code}\tEverything\t{$message}" );
		}
		return $result;
	}

	/**
	 * Purge post cache.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function rest_purge_post( $request ) {
		$post = get_post( $request->get_param( 'post_id' ) );
		$result = $this->purge( $post );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( [
			'success' => true,
			'message' => sprintf( __( 'All caches are flushed: %s', 'hamecache' ), get_the_title( $post ) ),
		] );
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'option':
				return Option::get_instance();
			default:
				return null;
		}
	}
}
