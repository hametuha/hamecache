<?php

namespace Hametuha\Hamecache;


use Hametuha\Hamecache\Pattern\Singleton;

/**
 * Option class
 *
 * @package hamecache
 * @property string $version
 * @property-read  string $domain
 * @property-read  string $mail
 * @property-read  string $token
 * @property-read  string $zone_id
 * @property-read  string $url
 * @property-read  string $extra_pages
 * @property-read  bool   $do_not_log
 * @property-read  bool   $purge_top_page
 * @property-write string $dir
 */
class Option extends Singleton {

	/**
	 * @var string
	 */
	private $_version = '0.0.0';

	/**
	 * @var string
	 */
	private $_dir = '';

	/**
	 * Constructor
	 */
	protected function init() {
		// Register setting page.
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		// Register items.
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		// Register css.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Enqueue style and JS
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'hamecache-admin', $this->url . 'assets/css/admin.css', [], $this->version );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu() {
		$menu_title = __( 'Cache Setting', 'hamecache' );
		$title = $menu_title . ' - Hamecache';
		add_options_page( $title, $menu_title, 'manage_options', 'hamecache', function() use ( $title ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html( $title ) ?></h1>

				<hr class="hamecache-divider" />

				<form method="POST" action="options.php">
					<?php
					settings_fields( 'hamecache' );
					do_settings_sections( 'hamecache' );
					submit_button();
					?>
				</form>

				<hr class="hamecache-divider" />

				<h2><?php esc_html_e( 'Page Rules', 'hamecache' ) ?></h2>
				<p class="description"><?php echo wp_kses_post( sprintf( __( 'Here are your page rules. You can manage them at <a href="%s" target="_blank">cloudflare dashboard</a>.', 'hamecache' ), 'https://dash.cloudflare.com' ) ) ?></p>

				<?php
				$rules = hamecache_get_page_rules();
				if ( is_wp_error( $rules ) ) :
					?>
				<div class="hamecache-error">
					<p><?php echo esc_html( $rules->get_error_message() ) ?></p>
				</div>
				<?php else: ?>
				<ol class="hamecache-rule-list">
					<?php foreach ( $rules->result as $rule ) : ?>
					<li class="hamecache-rule-item">

						<div class="hamecache-rule-url">

							<input onclick="this.select();" readonly type="text" class="widefat" value="<?php echo esc_attr( $rule->targets[0]->constraint->value ) ?>" />

							<span class="hamecache-rule-status">
								<?php if ( 'active' === $rule->status ) : ?>
									<span class="dashicons dashicons-yes"></span>
								<?php else : ?>
									<span class="dashicons dashicons-no"></span>
								<?php endif; ?>
							</span>
						</div>

						<div class="hamecache-rule-actions">
							<?php foreach ( $rule->actions as $action ) : ?>
							<span class="hamecache-rule-action">
								<span><?php echo esc_html( $action->id ) ?>: </span>
								<code><?php echo esc_html( $action->value ) ?></code>
							</span>
							<?php endforeach; ?>
						</div>
					</li>
					<?php endforeach; ?>
				</ol>
				<?php endif; ?>
			</div>
			<?php
		} );
	}

	/**
	 * Register settings.
	 */
	public function admin_init() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		// Add setting section for credentials.
		add_settings_section( 'hamecache_setting', __( 'Credentials', 'hamecache' ), function() {
			printf( '<p class="description">%s</p>', esc_html__( 'Enter credentials to enable page cache.', 'hamecache' ) );
		}, 'hamecache' );
		foreach ( [
			[ 'mail', __( 'Email', 'hamecache' ), __( 'Your email address registered at cloudflare.', 'hamecache' ) ],
			[ 'token', __( 'Token', 'hamecache' ), sprintf( __( 'API token which you can get on <a href="%s" target="_blank">profile page</a>.', 'hamecache' ), 'https://dash.cloudflare.com/' ) ],
			[ 'zone_id', __( 'Zone ID', 'hamecache' ), sprintf( __( 'Zone ID is assigned to your domain <code>%s</code>.', 'hamecache' ), $this->domain ) ],
		] as list( $key, $label, $desc ) ) {
			add_settings_field( 'hamecache_' . $key, $label, function() use ( $key, $desc ) {
				$name         = 'hamecache_' . $key;
				$const_name   = strtoupper( 'CF_' . $key );
				$place_holder = '';
				if ( defined( $const_name ) ) {
					$const = get_defined_constants()[ $const_name ];
					$desc .= '<br />' . sprintf(
						__( '<code>%1$s</code> is defined as <code>%2$s</code>. This value takes priority.', 'hamecache' ),
						$const,
						$const_name
					);
					$place_holder = $const;
				}
				printf(
					'<input type="text" id="%1$s" name="%1$s" class="widefat" value="%2$s" placeholder="%3$s" />',
					esc_attr( $name ),
					esc_attr( get_option( $name ) ),
					esc_attr( $place_holder )
				);
				?>
				<p class="description">
					<?php echo wp_kses_post( $desc ) ?>
				</p>
				<?php
				if ( 'zone_id' === $key ) {
					$this->zone_id_list();
				}
			}, 'hamecache', 'hamecache_setting' );
			register_setting( 'hamecache', 'hamecache_' . $key );
		}

		// Add sections for post types.
		add_settings_section( 'hamecache_rules', __( 'Pages to Cache', 'hamecache' ), function() {
			printf( '<p class="description">%s</p>', esc_html__( 'These post types will purge automatically.', 'hamecache' ) );
		}, 'hamecache' );
		// Post types.
		add_settings_field( 'hamecache_post_types', __( 'Post Types', 'hamecache' ), function() {
			$post_types = array_filter( get_post_types( [
				'public' => true,
			], 'objects' ), function( $post_type ) {
				return 'attachment' !== $post_type->name;
			} );
			?>
			<p>
				<?php foreach ( $post_types as $post_type ) : ?>
				<label class="hamecache-label">
					<input type="checkbox" name="hamecache_post_types[]" value="<?php echo esc_attr( $post_type->name ) ?>" <?php checked( in_array( $post_type->name, $this->post_types ) ) ?> />
					<?php echo esc_html( $post_type->label ) ?>
				</label>
				<?php endforeach; ?>
			</p>
			<?php
		}, 'hamecache', 'hamecache_rules' );
		register_setting( 'hamecache', 'hamecache_post_types' );
		// Top page and log.
		foreach ( [
			'purge_top_page' => __( 'Purge Top Page', 'hamecache' ),
			'do_not_log'     => __( 'Do not log on purge', 'hamecache' ),
		] as $key => $label ) {
			$option_key = 'hamecache_' . $key;
			add_settings_field( $option_key, $label, function() use ( $key, $option_key ) {
				?>
				<p>
					<?php foreach ( [ __( 'Yes', 'hamecache' ), __( 'No', 'hamecache' ) ] as $index => $str ) {
						$value = ! $index;
						printf(
							'<label class="hamecache-label"><input type="radio" name="%s" value="%s" %s /> %s</label>',
							$option_key,
							(int) $value,
							checked( $value, $this->{$key}, false ),
							esc_html( $str )
						);
					} ?>
				</p>
				<?php
			}, 'hamecache', 'hamecache_rules' );
			register_setting( 'hamecache', $option_key );
		}
		// Extra pages.
		add_settings_field( 'hamecache_extra_pages', __( 'Extra Pages', 'hamecache' ), function() {
			?>
			<textarea class="widefat" rows="5" id="hamecache_extra_pages" name="hamecache_extra_pages"><?php echo esc_textarea( $this->extra_pages ) ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Add extra pages to purge on post edit. 1 URL per 1 line.', 'hamecache' ) ?>
			</p>
			<?php
		}, 'hamecache', 'hamecache_rules' );
		register_setting( 'hamecache', 'hamecache_extra_pages' );
	}

	/**
	 * Display zone id.
	 */
	public function zone_id_list() {
		try {
			$valid = hamecache_service_available( true, true );
			if ( is_wp_error( $valid ) ) {
				throw new \Exception( $valid->get_error_message() );
			}
			$zone_ids = hamecache_list_zones();
			if ( is_wp_error( $zone_ids ) ) {
				throw new \Exception( $zone_ids->get_error_message() );
			}
			if ( ! $zone_ids->result_info->count ) {
				throw new \Exception( __( 'No zone id is found. Is this domain correct?', 'hamecache' ) );
			}
			$lines = [];
			foreach ( $zone_ids->result as $zone ) {
				$lines[] = sprintf( '%s <code onclick="this.select();">%s</code>', esc_html( $zone->name ), esc_html( $zone->id ) );
			}
			printf( '<p class="description">%s</p>', implode( '<br />', $lines ) );

		} catch ( \Exception $e ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $e->getMessage() ) );
		}
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'version':
				return $this->_version;
			case 'domain':
				if ( defined( 'CF_PSEUDO_DOMAIN' ) && CF_PSEUDO_DOMAIN ) {
					return CF_PSEUDO_DOMAIN;
				} else {
					// Remove protocol
					$url = preg_replace( '#^https?://#u', '', trailingslashit( home_url( '/' ) ) );
					// Get first part.
					$url = explode( '/', $url )[0];
					// Remove port no.
					$url = preg_replace( '/:\d+$/u', '', $url );
					return $url;
				}
				break;
			case 'mail':
			case 'token':
			case 'zone_id':
				$const = strtoupper( "cf_{$name}" );
				return defined( $const ) ? get_defined_constants()[ $const ] : get_option( "hamecache_{$name}", '' );
			case 'url':
				return plugin_dir_url( $this->_dir . '/assets' );
			case 'post_types':
				return array_filter( (array) get_option( 'hamecache_post_types', [ 'post' ] ) );
				break;
			case 'purge_top_page':
			case 'do_not_log':
				return (bool) get_option( 'hamecache_' . $name );
				break;
			case 'extra_pages':
				return get_option( 'hamecache_' . $name, '' );
			default:
				return null;
		}
	}

	/**
	 * Setter
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set( $name, $value ) {
		switch ( $name ) {
			case 'version':
				if ( '0.0.0' === $this->_version ) {
					$this->_version = $value;
				}
				break;
			case 'dir':
				if ( ! $this->_dir ) {
					$this->_dir = $value;
				}
				break;
			default:
				// Do nothing.
				break;
		}
	}
}
