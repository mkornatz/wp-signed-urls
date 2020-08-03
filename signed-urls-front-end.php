<?php
// Don't call this file directly.
if ( ! class_exists( 'WP' ) ) {
	die();
}

require_once( SIGNED_URLS_PLUGIN_PATH . 'signed-url.php' );

if ( !class_exists( 'Signed_Urls_Front_End' ) ) {
	/**
	 * This hooks into the wordpress front end to implement signed url protection.
	 */
	class Signed_Urls_Front_End {

		private $options;

		public function __construct($options) {
			$this->options = $options;

			$this->register_session();
			$this->disable_caching();

			// Don't use signed URLs for the login page
			if (home_url($_SERVER['SCRIPT_NAME']) == wp_login_url()) {
				return;
			}

			add_action( 'init', [$this, 'validate_session_or_signed_url'], 2 );
			add_action( 'wp_logout', [$this, 'remove_session_data'] );

			add_filter( 'wp_get_nav_menu_items', [$this, 'add_menu_item'], 10, 2 );
		}

		public function disable_caching() {
			if ( !$this->is_active() ) {
				return;
			}
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			// Sets a cookie to pretend like we're "signed in" (Liquid Web hosting provider - Varnish issue)
			setcookie('wp-settings-signed-urls', '1');
		}

		/**
		 * Starts the PHP session if it hasn't already been started.
		 */
		public function register_session() {
			if ( !session_id() ) {
				session_start();
			}
		}

		public function remove_session_data() {
			if ($this->has_session()) {
				unset($_SESSION['signed_urls_session_valid_until']);
				unset($_SESSION['signed_urls_referrer']);
			}
		}

		private function has_session() {
			return is_array($_SESSION) && array_key_exists('signed_urls_session_valid_until', $_SESSION);
		}

		private function is_active() {
			if (!$this->options->get('enabled')) {
				return false;
			}

			// Always allow access to robots.txt
			if ( isset( $wp_query ) && is_robots() ) {
				return false;
			}

			return true;
		}

		/**
		 * Every user gets an initial session set up for the "Session Length" option and every page
		 * view after that, the session is extend to an hour from "now" in order to avoid issues
		 * with sessions ending in the middle of someone using the site.
		 */
		public function validate_session_or_signed_url() {
			if ( !$this->is_active() ) {
				return;
			}

			// If they can edit posts, they are logged in and don't need a signed URL
			if ( current_user_can( 'edit_posts' ) ) {
				return $this->extend_session();
			}

			// If they're using a new signed URL, ignore the current session in order to reset the session
			if ( empty($_GET['signature']) && $this->session_is_valid() ) {
				return $this->extend_session();
			}

			$signed_url = new Signed_Url($this->options->get('secret_signing_key'));

			// Check to see if this page should be protected by signed URLs
			$post_id = url_to_postid($signed_url->url_without_signature_param());

			if ( ! $this->post_is_protected($post_id) ) {
				return;
			}

			if ( $signed_url->is_valid() ) {
				$this->remember_referrer();
				$this->extend_session();

				// Redirect to the URL without the signature and expires params
				wp_redirect( $signed_url->url_without_signature_param() , 302 );

			} else {
				if ( $this->options->get('redirect_url') ) {
					wp_redirect( $this->options->get('redirect_url') , 302 );
				} else if ( $this->has_session() ) {
					$this->render_session_expired();
				} else {
					$this->render_invalid_url();
				}
				exit;
			}
		}

		private function post_is_protected( $post_id ) {
			$protected_parent = $this->options->get('protected_parent');

			if ( empty($protected_parent) ) {
				return true;
			}

			if ($protected_parent == $post_id) {
				return true;
			}

			$ancestors = get_post_ancestors($post_id);
			$ultimate_parent = array_pop($ancestors);
			return $protected_parent == $ultimate_parent->ID;
		}

		private function extend_session() {
			$session_timeout = $this->options->get('session_timeout');

			if ( strtotime($_SESSION['signed_urls_session_valid_until']) ) {
				$existing_valid_until = new DateTime( $_SESSION['signed_urls_session_valid_until'] );
			}
			$valid_until = new DateTime( '+' . $session_timeout, new DateTimeZone('UTC') );

			if ( empty($existing_valid_until) || $existing_valid_until < $valid_until) {
				$_SESSION['signed_urls_session_valid_until'] = $valid_until->format('c');
			}
		}

		// Save referrer in case we need to send the user back to where they came from
		private function remember_referrer() {
			if ( !empty($_GET['returnTo']) ) {
				$_SESSION['signed_urls_referrer'] = $_GET['returnTo'];

			} else if ( !empty($_SERVER['HTTP_REFERER']) ) {
				$_SESSION['signed_urls_referrer'] = $_SERVER['HTTP_REFERER'];
			}
		}

		private function session_is_valid() {
			if ( !$this->has_session() ) {
				return false;
			}

			$valid_until = new DateTime( $_SESSION['signed_urls_session_valid_until'], new DateTimeZone('UTC') );
			$now = new DateTime( 'now', new DateTimeZone('UTC') );
			return $valid_until > $now ? true : false;
		}

		// // https://www.daggerhart.com/dynamically-add-item-to-wordpress-menus/
		function add_menu_item( $items, $menu )  {
			if( is_admin() || empty($this->options->get('go_back_menu_item_title')) ) {
				return $items;
			}

			if ( !empty($_SESSION['signed_urls_referrer']) ) {
				$url = $_SESSION['signed_urls_referrer'];
			} else if ( !empty($this->options->get('redirect_url')) ) {
				$url = $this->options->get('redirect_url');
			}

			if ( !empty($url) ) {
				$item = new stdClass();
				$item->ID = 98006000 + rand(1, 100);
				$item->db_id = $item->ID;
				$item->title = $this->options->get('go_back_menu_item_title');
				$item->url = $url;
				$item->menu_order = 0;
				$item->menu_item_parent = 0;
				$item->type = '';
				$item->object = '';
				$item->object_id = '';
				$item->classes = array();
				$item->target = '';
				$item->attr_title = '';
				$item->description = '';
				$item->xfn = '';
				$item->status = 'publish';
				$items[] = $item;
			}

			return $items;
		}

		private function render_invalid_url() {
			?>
			<html>
				<head>
					<title>This Site is Protected</title>
					<style type="text/css">
						body {
							text-align: center;
							padding-top: 100px;
							font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
						}
						.message {
							border: 1px solid #ccc;
							width: 50%;
							margin: 0 auto;
							padding: 20px;
							-webkit-box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
							-moz-box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
							box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
						}
						p {
							font-size: 18px;
						}
					</style>
				</head>
				<body>
					<div class="message">
						<h1>Invalid URL</h1>
						<p>The URL you entered is invalid. Please try again with a new link.</p>
						<p>Session ID <?php echo session_id(); ?></p>
						<code><?php echo print_r($_SESSION, true); ?></code>
						<code><?php echo print_r($_COOKIE, true); ?></code>
					</div>
				</body>
			</html>
			<?php
			exit;
		}

		private function render_session_expired() {
			?>
			<html>
				<head>
					<title>Your Session has Expired</title>
					<style type="text/css">
						body {
							text-align: center;
							padding-top: 100px;
							font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
						}
						.message {
							border: 1px solid #ccc;
							width: 50%;
							margin: 0 auto;
							padding: 20px;
							-webkit-box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
							-moz-box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
							box-shadow: 2px 10px 21px -7px rgba(0,0,0,0.62);
						}
						p {
							font-size: 18px;
						}
					</style>
				</head>
				<body>
					<div class="message">
						<h1>Your Session Has Expired</h1>
						<p>To view this page, please try again with a new link.</p>
						<p><?php echo $_SESSION['signed_urls_session_valid_until']; ?></p>
					</div>
				</body>
			</html>
			<?php
			exit;
		}
	}
}

