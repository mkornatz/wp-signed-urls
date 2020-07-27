<?php
// Don't call this file directly.
if ( ! class_exists( 'WP' ) ) {
	die();
}

if ( !class_exists( 'Signed_Urls_Options' ) ) {
	/**
	 * This class sets up WP admin options for the plugin
	 */
	class Signed_Urls_Options {

		public $wp_options_name = 'signed_urls_options';

		private $defaults = [
			'enabled' => false,
			'session_timeout' => '30 minutes',
			'secret_signing_key' => null,
			'redirect_url' => null,
			'protected_parent' => null,
		];

		private $session_timeout_options = [
			'5 minutes',
			'15 minutes',
			'30 minutes',
			'45 minutes',
			'1 hour',
			'1 day',
			'2 days',
			'1 week',
			'2 weeks',
			'1 month'
		];

		private $options;

		public function register_with_wordpress_admin() {
			add_action( 'admin_menu', array( $this, 'add_plugin_options_page' ) );
			add_action( 'admin_init', array( $this, 'init_plugin_options' ) );
			add_filter( 'plugin_action_links_' . SIGNED_URLS_PLUGIN_BASENAME, array($this, 'add_settings_link_on_plugins_page') );
		}

		public function add_settings_link_on_plugins_page( $links ) {
			$url = admin_url( 'options-general.php?page=signed-urls' );
			return array_merge(
				$links,
				["<a href='$url'>" . __( 'Settings' ) . '</a>']
			);
		}

		public function delete_from_wordpress() {
			delete_option( $this->wp_options_name );
		}

		public function load_options() {
			if ( empty($this->options) ) {
				$this->options = get_option( $this->wp_options_name );
			}
		}

		public function get($name) {
			$this->load_options();
			return array_key_exists($name, $this->options) ? $this->options[$name] : $this->defaults[$name];
		}

		/**
		 * Plugin is located in admin Settings menu.
		 */
		public function add_plugin_options_page() {
			add_options_page(
				'Signed URLs', // page_title
				'Signed URLs', // menu_title
				'manage_options', // required capability
				'signed-urls', // menu_slug
				array( $this, 'options_html' ) // callback for settings HTML
			);
		}

		public function options_html() {
			$this->load_options(); ?>

			<div class="wrap" style="max-width: 80%;">
				<h2>Signed URLs</h2>
				<p>
					Signed URLs are a way to protect access to the content of this website without using a password.
					When enabling signed URLs, to view any content on the site, users will be required to either be
					logged into the WordPress site or have come to the site using a URL with a special embedded signature.
				</p>
				<p>
					When a user visits this site with a Signed URL, the site will remember the user for a period of time.
					You can change the length of this time period using the "Remember User For" option below.
				</p>

				<h3>How does it work?</h3>
				<p>
					Say your site hosts private resources only available to certain people, https://private-resources.example.com/dashboard.</p>
					Without Signed URLs enabled, any user with the link to your dashboard can see it.
					With Signed URLs enabled, only users with the private link can access the dashboard, https://private-resources.example.com/dashboard?<em>signature=726b6174686a6e613834686d6e</em>.
				</p>

				<p>Technical documentation can be found at <a href="https://github.com/mkornatz/wp-signed-urls" target="_blank">https://github.com/mkornatz/wp-signed-urls</a>.</p>

				<p>&nbsp;</p>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'signed_urls_option_group' );
						do_settings_sections( 'signed-urls-admin' );
						submit_button();
					?>
				</form>
			</div>
		<?php
		}

		public function init_plugin_options() {
			register_setting(
				'signed_urls_option_group', // option_group
				$this->wp_options_name, // option_name
				array( $this, 'sanitize_options' ) // callback
			);

			add_settings_section(
				'signed_urls_setting_section', // id
				'Settings', // title
				array( $this, 'options_section_html' ), // callback
				'signed-urls-admin' // page
			);

			add_settings_field(
				'enabled', // id
				'Signed URL Protection', // title
				array( $this, 'enabled_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);

			add_settings_field(
				'secret_signing_key', // id
				'Secret Signing Key', // title
				array( $this, 'secret_signing_key_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);

			add_settings_field(
				'session_timeout', // id
				'Remember User For', // title
				array( $this, 'session_timeout_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);

			add_settings_field(
				'redirect_url', // id
				'Redirect URL', // title
				array( $this, 'redirect_url_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);

			add_settings_field(
				'protected_parent', // id
				'Parent Page to Protect', // title
				array( $this, 'protected_parent_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);

			add_settings_field(
				'go_back_menu_item_title', // id
				'"Go Back" Menu Item Title', // title
				array( $this, 'go_back_menu_item_title_html' ), // callback
				'signed-urls-admin', // page
				'signed_urls_setting_section' // section
			);
		}

		public function sanitize_options($input) {
			$sanitary_values = array();
			if ( isset( $input['secret_signing_key'] ) ) {
				$sanitary_values['secret_signing_key'] = sanitize_text_field( $input['secret_signing_key'] );
			}

			if ( isset( $input['enabled'] ) ) {
				$sanitary_values['enabled'] = $input['enabled'];
			}

			if ( isset( $input['session_timeout'] ) ) {
				if ( strtotime($input['session_timeout']) ) {
					$sanitary_values['session_timeout'] = $input['session_timeout'];
				} else {
					add_settings_error('session_timeout', 'session_timeout', 'The session timeout you selected is not valid.');
				}
			}

			if ( isset( $input['redirect_url'] ) ) {
				if ( empty($input['redirect_url']) ) {
					$sanitary_values['redirect_url'] = null;
				} else {
					$filtered_url = filter_var($input['redirect_url'], FILTER_VALIDATE_URL);
					if ( $filtered_url ) {
						$sanitary_values['redirect_url'] = $filtered_url;
					} else {
						add_settings_error('redirect_url', 'redirect_url', 'The Redirect URL is not a valid URL.');
					}
				}
			}

			if ( isset( $input['protected_parent'] ) ) {
				$sanitary_values['protected_parent'] = $input['protected_parent'];
			}

			if ( isset( $input['go_back_menu_item_title'] ) ) {
				$sanitary_values['go_back_menu_item_title'] = $input['go_back_menu_item_title'];
			}

			return $sanitary_values;
		}

		public function options_section_html() {}

		public function secret_signing_key_html() {
			printf(
				'<label for="secret_signing_key">
					<input class="regular-text" type="text" name="signed_urls_options[secret_signing_key]" id="secret_signing_key" value="%s">
					<br><em>This key should remain private and be used to generate signed URLs.</em>
				</label>
				',
				isset( $this->options['secret_signing_key'] ) ? esc_attr( $this->options['secret_signing_key']) : ''
			);
		}

		public function enabled_html() {
			?> <fieldset><?php $checked = ( isset( $this->options['enabled'] ) && $this->options['enabled'] === '1' ) ? 'checked' : '' ; ?>
			<label for="enabled-0"><input type="radio" name="signed_urls_options[enabled]" id="enabled-0" value="1" <?php echo $checked; ?>> Enabled</label><br>
			<?php $checked = ( isset( $this->options['enabled'] ) && $this->options['enabled'] === '0' ) ? 'checked' : '' ; ?>
			<label for="enabled-1"><input type="radio" name="signed_urls_options[enabled]" id="enabled-1" value="0" <?php echo $checked; ?>> Disabled</label></fieldset> <?php
		}

		public function session_timeout_html() {
			?><label for="session_timeout"><select name="signed_urls_options[session_timeout]" id="session_timeout"><?php

			foreach ($this->session_timeout_options as $option) {
				$selected = (isset( $this->options['session_timeout'] ) && $this->options['session_timeout'] === $option) ? 'selected' : '' ;
				?><option value="<?php echo $option ?>" <?php echo $selected; ?>><?php echo $option ?></option><?php
			}

			?></select><br><em>How long to remember a user when they view the site via a signed URL. This time period is measured from the last time the user loads a page during their session.</em></label><?php
		}

		public function redirect_url_html() {
			printf(
				'<label for="redirect_url">
					<input class="regular-text" type="text" name="signed_urls_options[redirect_url]" id="redirect_url" value="%s" placeholder="https://example.com/login">
					<br><em>Where to redirect when a user uses an invalid URL or their session ends. Leave blank to show the default error message.</em>
				 </label>
				',
				isset( $this->options['redirect_url'] ) ? esc_attr( $this->options['redirect_url']) : ''
			);
		}

		public function protected_parent_html() {
			?><label for="protected_parent">
					<select name="signed_urls_options[protected_parent]" id="protected_parent">
						<option value="">All Pages</option>
			<?php

			foreach (get_pages() as $page) {
				$selected = (isset( $this->options['protected_parent'] ) && $this->options['protected_parent'] == $page->ID) ? 'selected' : '' ;
				?><option value="<?php echo $page->ID ?>" <?php echo $selected; ?>><?php echo $page->post_title ?></option><?php
			}

			?></select><br><em>If you only want to protect a certain set of pages, select a page to limit the scope of which pages are protected by Signed URLs. All child pages of the selected parent page will be protected.</em></label><?php
		}

		public function go_back_menu_item_title_html() {
			printf(
				'<label for="redirect_url">
					<input class="regular-text" type="text" name="signed_urls_options[go_back_menu_item_title]" id="go_back_menu_item_title" value="%s">
					<br><em>When not empty, this adds a menu item to "Go Back" to the site that initiated the Signed URL session. This uses the HTTP Referrer value, and if that doesn\'t exist, it falls back to the "Redirect URL" above. Note: this only works when a menu is selected in your theme options. Auto-generated menus are not affected.</em>
				 </label>
				',
				isset( $this->options['go_back_menu_item_title'] ) ? esc_attr( $this->options['go_back_menu_item_title']) : ''
			);
		}
	}
}
