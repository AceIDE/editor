<?php

namespace AceIDE\Editor\Modules;

use AceIDE\Editor\IDE;

class SettingsPage implements Module
{
	private $slug = null, $ide = null;

	public function setup_hooks(IDE $ide) {
		$this->ide = $ide;
		$ide->add_actions( array(
			array( 'admin_init', array( &$this, 'register_settings' ) ),
			array( 'admin_init', array( &$this, 'admin_init' ) ),
			array( 'admin_menu', array( &$this, 'add_menu_item' ) ),
		) );
	}

	public function admin_init() {
		$this->ide->add_actions( array(
			array( 'admin_print_scripts-' . $this->ide->get_menu_hook(), array( &$this, 'add_admin_js' ) ),
		) );
	}

	public function add_admin_js() {
		$themes = $this->get_theme_list();
		$default_theme = array_keys( $themes );
		$default_theme = reset( $default_theme );

		wp_localize_script( 'aceide-load-editor', 'aceide_options', array (
			'theme' => get_option( 'aceide.theme', $default_theme ),
			'folding' => get_option( 'aceide.folding', 'markbegin' ),
			'font_size' => get_option( 'aceide.font-size', 12 ),
			'wrap_limit' => get_option( 'aceide.wrap-limit', 120 ),
			'wrap_long_text' => get_option( 'aceide.wrap-long-text', 0 ),
			'fade_folders' => get_option( 'aceide.fade-folders', 0 ),
			'highlight_current_line' => get_option( 'aceide.highlight-current-line', 1 ),
			'show_invisibles' => get_option( 'aceide.show-invisibles', 0 ),
			'show_indent_guides' => get_option( 'aceide.show-indent-guides', 1 ),
			'animate_scrollbar' => get_option( 'aceide.animate-scrollbar', 1 ),
			'show_gutter' => get_option( 'aceide.show-gutter', 1 ),
			'use_tabs' => get_option( 'aceide.use-tabs', 1 ),
			'highlight-words' => get_option( 'aceide.highlight-words', 1 ),
			'show-behaviours' => get_option( 'aceide.show-behaviours', 0 ),
		) );
	}

	protected function add_field($id, $name, $callback = null, $sanitization = null) {
		if (is_null($callback)) {
			$callback = "display_{$id}_element";
			$callback = str_replace('-', '_', $callback);
		}

		add_settings_field( 'aceide.' . $id, __( $name, 'aceide' ), array( &$this, $callback ), $this->slug, 'aceide-settings-section' );
		register_setting( 'aceide-settings-group', $id, $sanitization );
	}

	public function register_settings() {
		add_settings_section( 'aceide-settings-section', __( 'All Settings', 'aceide' ), null, $this->slug );

		$this->add_field( 'theme', 'Theme', null, array( &$this, 'filter_theme_option' ) );
		$this->add_field( 'folding', 'Folding', null, array( &$this, 'filter_folding' ) );
		$this->add_field( 'font-size', 'Font Size', null, array( &$this, 'filter_font_size' ) );
		$this->add_field( 'wrap-limit', 'Wrap Limit', null, array( &$this, 'filter_wrap_limit' ) );
		$this->add_field( 'wrap-long-text', 'Wrap Long Text', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'fade-folders', 'Fade Indent Folders', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'highlight-current-line', 'Highlight Current Line', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'show-invisibles', 'Show Invisible Characters', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'show-indent-guides', 'Show Indent Guidelines', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'animate-scrollbar', 'Animate Scrollbar', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'show-gutter', 'Show Gutter', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'use-tabs', 'Use Tabs', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'highlight-words', 'Highlight Same Words', null, array( &$this, 'filter_checkbox' ) );
		$this->add_field( 'show-behaviours', 'Show Behaviours', null, array( &$this, 'filter_checkbox' ) );
	}

	public function add_menu_item() {
		// Our capability changes for multisite installs
		$capability = ( is_multisite() ? 'manage_network_themes' : 'create_users' );
		$this->slug = add_options_page( 'AceIDE', 'AceIDE', $capability, 'aceide-settings', array( &$this, 'render_page' ) );
	}

	/**
	 * Validation functions
	 */
	public function filter_theme_option($theme) {
		$themes = array_keys( $this->get_theme_list() );

		// Returns old value if the proposed value is invalid
		if ( !in_array( $theme, $themes, true ) ) {
			return get_option( 'aceide.theme', reset( $themes ) );
		}

		return $theme;
	}

	public function filter_folding($folding) {
		$options = array ('manual', 'markbegin', 'markbeginend');

		if ( !in_array( $folding, $options ) ) {
			return get_option( 'aceide.folding', 'markbegin' );
		}

		return $folding;
	}

	public function filter_font_size($font_size) {
		$font_size = inval( $font_size );

		if ( $font_size < 8 || $font_size > 40 ) {
			return get_option( 'aceide.font-size', 12 );
		}

		return $font_size;
	}

	public function filter_wrap_limit($limit) {
		$limit = intval( $limit );

		if ( $limit < 40 || $limit > 400 ) {
			return get_option( 'aceide.wrap-limit', 120 );
		}

		return $limit;
	}

	public function filter_checkbox($value) {
		return (!!$value ? 1 : 0);
	}

	/**
	 * Output functions
	 */
	public function render_page() {
		?>
			<form method="POST">
				<h2>AceIDE</h2>

				<?php
					do_settings_sections( $this->slug );
					submit_button();
				?>
			</form>
		<?php
	}

	public function display_theme_element() {
		$themes = $this->get_theme_list();
		$default_theme = array_keys( $themes );
		$default_theme = reset( $default_theme );
		$selected = get_option( 'aceide.theme', $default_theme );

		echo '<select name="aceide-theme" id="aceide-theme">';

		foreach ( $themes as $path=>$theme ) {
			$fmt = '<option value="%s" ' . selected($path, $selected, false) . '>%s</option>';
			printf( $fmt, esc_attr( $path ), esc_html( $theme ) );
		}

		echo '</select>';
	}

	public function display_folding_element() {
		$options = array (
			'manual' => __( 'Manual', 'aceide' ),
			'markbegin' => __( 'Beginning', 'aceide' ),
			'markbeginend' => __( 'Beginning and End', 'aceide' )
		);
		$selected = get_option( 'aceide.folding', 'markbegin' );

		echo '<select name="aceide-folding" id="aceide-folding">';

		foreach ( $options as $value=>$label ) {
			$fmt = '<option value="%s" ' . selected($value, $selected, false) . '>%s</option>';
			printf( $fmt, esc_attr( $value ), esc_html( $label ) );
		}

		echo '</select>';
	}

	public function display_font_size_element() {
		$value = esc_attr( get_option( 'aceide.font-size', 12 ) );
		echo '<input type="number" min="8" max="40" name="aceide-font-size" id="aceide-font-size" value="' . $value . '" />';
	}

	public function display_wrap_limit_element() {
		$value = esc_attr( get_option( 'aceide.wrap-limit', 120 ) );
		echo '<input type="number" min="40" max="400" name="aceide-wrap-limit" id="aceide-wrap-limit" value="' . $value . '" />';
	}

	public function display_wrap_long_text_element() {
		$this->output_checkbox( 'wrap-long-text', 0 );
	}

	public function display_fade_folders_element() {
		$this->output_checkbox( 'fade-folders', 0 );
	}

	public function display_highlight_current_line_element() {
		$this->output_checkbox( 'highlight-current-line', 1 );
	}

	public function display_show_invisibles_element() {
		$this->output_checkbox('show-invisibles', 0);
	}

	public function display_show_indent_guides_element() {
		$this->output_checkbox('show-indent-guides', 1);
	}

	public function display_animate_scrollbar_element() {
		$this->output_checkbox('animate-scrollbar', 1);
	}

	public function display_show_gutter_element() {
		$this->output_checkbox('show-gutter', 1);
	}

	public function display_use_tabs_element() {
		$this->output_checkbox('use-tabs', 1);
	}

	public function display_highlight_words_element() {
		$this->output_checkbox('highlight-words', 1);
	}

	public function display_show_behaviours_element() {
		$this->output_checkbox('show-behaviours', 0);
	}

	protected function get_theme_list() {
		$files = glob( dirname( __FILE__ ) . '/../js/ace-*/theme-*.js' );

		$themes = [];

		foreach ($files as $file) {
			$index = basename( $file );
			$value = substr( $index, 6, -3 );
			$value = preg_replace( '/[\\-_]+/', ' ', $value );
			$value = ucwords( $value );

			$themes[$index] = $value;
		}

		return $themes;
	}

	protected function output_checkbox($name, $default_value) {
		$value = get_option( 'aceide.' . $name, $default_value );
		$checked = checked( '1', $value, false );
		printf('<input type="checkbox" name="aceide-%1$s" id="aceide-%1$s" %2$s />', $name, $checked);
	}
}
