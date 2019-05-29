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
		add_action( 'save_post', [ $this, 'save_post' ], 100, 2 );
		add_action( 'transition_post_status', [ $this, 'purge_scheduled' ], 100, 3 );
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
			$urls = array_map( function( $url ) {
				return str_replace( '.info', '.com', $url );
			}, $urls );
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
		$has_permlink = get_option( 'rewrite_rules' );
		if ( preg_match_all( '/<\!--nextpage-->/u', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $index => $match ) {
				if ( $index ) {
					$pagenum = $index + 1;
					if ( $has_permlink ) {
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
		if ( function_exists( 'amp_activate' ) ) {
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
		return $urls;
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
