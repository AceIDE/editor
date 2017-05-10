<?php

namespace AceIDE\Editor;

use WP_Error;
use AceIDE\Editor\Modules\Module;

class IDE
{
	private $ace_version = '1.2.4';

	public $site_url, $plugin_url;
	private $menu_hook;

	private $modules = array();

	public function __construct() {
		$this->site_url = get_bloginfo('url');

		$actions = array (
			array( 'admin_menu', array( &$this, 'add_my_menu_page' ) ),
			array( 'admin_head', array( &$this, 'add_my_menu_icon' ) ),
			array( 'admin_init', array( &$this, 'init_hooks' ) ),
			array( 'network_admin_menu', array( &$this, 'add_my_menu_page' ) ),
		);

		// hook for processing incoming image saves
		if ( isset( $_GET['aceide_save_image'] ) ) {
			// force local file method for testing
			$this->override_fs_method( 'direct' );

			$actions[] = array('admin_init', array( &$this, 'save_image') );
		}

		$this->add_actions($actions);
	}

	public function add_actions(array $actions) {
		foreach ($actions as $action_params) {
			call_user_func_array('add_action', $action_params);
		}
	}

	public function override_fs_method( $method='direct' ) {
		if ( defined('FS_METHOD') ) {
			define( 'ACEIDE_FS_METHOD_FORCED_ELSEWHERE', FS_METHOD ); // make a note of the forced method
		} else {
			define( 'FS_METHOD', $method ); // force direct
		}
	}

	public function extend(Module $module) {
		if (did_action('admin_init')) {
			return new WP_Error( 'editor_extend', 'Can\'t extend after admin_init!' );
		}

		$this->modules[get_class($module)] = $module;
	}

	public function init_hooks() {
		// force local file method until I've worked out how to implement the other methods
		// main problem being password wouldn't/isn't saved between requests
		// you could force other methods 'direct', 'ssh', 'ftpext' or 'ftpsockets'
		$this->override_fs_method( 'direct' );

		$hooks = $this->setup_hooks();
		$this->add_actions($hooks);

		foreach ($this->modules as $module) {
			$hooks = $module->setup_hooks();
			$this->add_actions($hooks);
		}
	}

	public function setup_hooks() {
		// Uncomment any of these calls to add the functionality that you need.
		// Will only enqueue on AceIDE page
		return array (
			array( 'admin_print_scripts-' . $this->menu_hook, array( &$this, 'add_admin_js' ) ),
			array( 'admin_print_styles-'  . $this->menu_hook, array( &$this, 'add_admin_styles' ) ),
			array( 'wp_ajax_jqueryFileTree',        array( &$this, 'jqueryFileTree_get_list' ) ),

			array( 'wp_ajax_aceide_image_edit_key', array( &$this, 'image_edit_key' )  ),
			array( 'wp_ajax_aceide_startup_check',  array( &$this, 'startup_check' ) ),

			// add a warning when navigating away from AceIDE
			// it has to go after WordPress scripts otherwise WP clears the binding
			// This has been implemented in load-editor.js
			// add_action('admin_print_footer_scripts', array( &$this, 'add_admin_nav_warning' ), 99 );

			// Add body class to collapse the wp sidebar nav
			array( 'admin_body_class', array( &$this, 'hide_wp_sidebar_nav' ), 11),

			// hide the update nag
			array( 'admin_menu', array( &$this, 'hide_wp_update_nag' ) ),
		);
	}

	public static function check_perms( $check_referrer=true ) {
		$capability = ( is_multisite() ? 'manage_network_themes' : 'edit_plugins' );

		if ( defined( 'DISALLOW_FILE_EDIT' ) && ! apply_filters( 'aceide_override_disallow_file_edit', ! DISALLOW_FILE_EDIT ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		if ( $check_referrer ) {
			check_admin_referer( 'plugin-name-action_aceidenonce' );
		}

		if ( ! current_user_can( $capability ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}
	}

	public function hide_wp_sidebar_nav( $classes ) {
		global $hook_suffix;

		if ( apply_filters( 'aceide_sidebar_folded', $hook_suffix === $this->menu_hook ) ) {
			$classes = str_replace( "auto-fold", "", $classes ) . ' folded';
		}

		return $classes;
	}

	public function hide_wp_update_nag() {
		remove_action( 'admin_notices', 'update_nag', 3 );
	}

	public function add_admin_nav_warning()
	{
		?>
			<script type="text/javascript">

				jQuery(document).ready(function($) {
					window.onbeforeunload = function() {
					  return <?php _e( 'You are attempting to navigate away from AceIDE. Make sure you have saved any changes made to your files otherwise they will be forgotten.' ); ?>;
					}
				});

			</script>
		<?php
	}

	public function add_admin_js() {
		$plugin_path = trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'AceIDE.php';
		$plugin_url  = trailingslashit( plugin_dir_url( $plugin_path ) );

		$acever = $this->ace_version;
		$idever = get_plugin_data( $plugin_path, false, false );
		$idever = $idever['Version'];

		// include file tree
		wp_enqueue_script( 'jquery-file-tree', plugins_url( 'js/jqueryFileTree.js', __FILE__ ), array( 'jquery' ), $idever );
		// include ace
		wp_enqueue_script( 'ace', "{$plugin_url}src/js/ace-{$acever}/ace.js", array(), $idever );
		// include ace modes for css, javascript & php
		wp_enqueue_script( 'ace-mode-css', "{$plugin_url}src/js/ace-{$acever}/mode-css.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'ace-mode-scss', "{$plugin_url}src/js/ace-{$acever}/mode-scss.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'ace-mode-less', "{$plugin_url}src/js/ace-{$acever}/mode-less.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'ace-mode-javascript', "{$plugin_url}src/js/ace-{$acever}/mode-javascript.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'ace-mode-php', "{$plugin_url}src/js/ace-{$acever}/mode-php.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'ace-mode-twig', "{$plugin_url}src/js/ace-{$acever}/mode-twig.js", array( 'ace' ), $idever );
		// include ace theme
		wp_enqueue_script( 'ace-theme', "{$plugin_url}src/js/ace-{$acever}/theme-dawn.js", array( 'ace' ), $idever ); // ambiance looks really nice for high contrast
		// load emmet
		wp_enqueue_script( 'aceide-ext-emmet', "{$plugin_url}src/js/ace-{$acever}/ext-emmet.js", array( 'ace' ), $idever );
		wp_enqueue_script( 'aceide-emmet', "{$plugin_url}src/js/emmet.js", array( 'aceide-ext-emmet' ), $idever );
		// wordpress-completion tags
		wp_enqueue_script( 'aceide-wordpress-completion', "{$plugin_url}src/js/autocomplete/wordpress.js", array( 'ace' ), $idever );
		// php-completion tags
		wp_enqueue_script( 'aceide-php-completion', "{$plugin_url}src/js/autocomplete/php.js", array( 'ace' ), $idever );
		// load editor
		wp_enqueue_script( 'aceide-load-editor', "{$plugin_url}src/js/load-editor.js", array( 'ace', 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ), $idever );
		// load filetree menu
		wp_enqueue_script( 'aceide-load-filetree-menu', "{$plugin_url}src/js/load-filetree-menu.js", array( 'ace', 'jquery' ), $idever );
		// load autocomplete dropdown
		wp_enqueue_script( 'aceide-dd', "{$plugin_url}src/js/jquery.dd.js", array( 'ace', 'jquery' ), $idever );

		// Specify custom AJAX handler
		wp_localize_script( 'aceide-load-editor', 'aceajax', array(
			'url' => plugins_url( 'Ajax.php', __FILE__ )
		) );

		// load color picker
		wp_enqueue_script( 'ImageColorPicker', "{$plugin_url}src/js/ImageColorPicker.js", array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget' ),  $idever );
	}

	public function add_admin_styles() {
		$plugin_path = trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'AceIDE.php';
		$plugin_url  = trailingslashit( plugin_dir_url( $plugin_path ) );

		$acever = $this->ace_version;
		$idever = get_plugin_data( $plugin_path, false, false );
		$idever = $idever['Version'];

		// main AceIDE styles
		wp_register_style( 'aceide_style', "{$plugin_url}src/aceide.css", array(), $idever );
		wp_enqueue_style( 'aceide_style' );
		// filetree styles
		wp_register_style( 'aceide_filetree_style', "{$plugin_url}src/css/jqueryFileTree.css", array( 'aceide_style' ), $idever );
		wp_enqueue_style( 'aceide_filetree_style' );
		// autocomplete dropdown styles
		wp_register_style( 'aceide_dd_style', "{$plugin_url}src/dd.css", array( 'aceide_style' ), $idever );
		wp_enqueue_style( 'aceide_dd_style' );

		// jquery ui styles
		wp_register_style( 'aceide_jqueryui_style', "{$plugin_url}src/css/flick/jquery-ui-1.8.20.custom.css", array( 'aceide_style' ), $idever );
		wp_enqueue_style( 'aceide_jqueryui_style' );
	}

	public function jqueryFileTree_get_list() {
		global $wp_filesystem;

		// check the user has the permissions
		self::check_perms();

		// setup wp_filesystem api
		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			 // no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}

		$_POST['dir'] = urldecode( $_POST['dir'] );
		$root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		if (ob_get_level()) {
			ob_end_clean();
		}

		if ( $wp_filesystem->exists( $root . $_POST['dir'] ) ) {
			$files = $wp_filesystem->dirlist( $root . $_POST['dir'] );

			echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
			if( count( $files ) > 0 ) {
				// build seperate arrays for folders and files
				$dir_array = array();
				$file_array = array();
				foreach ( $files as $file => $file_info ) {
					if ( $file != '.' && $file != '..' && $file_info['type'] == 'd' ) {
						$file_string = strtolower( preg_replace( "[._-]", "", $file ) );
						$dir_array[$file_string] = $file_info;
					} elseif ( $file != '.' && $file != '..' &&  $file_info['type'] == 'f' ){
						$file_string = strtolower( preg_replace( "[._-]", "", $file ) );
						$file_array[$file_string] = $file_info;
					}
				}

				// shot those arrays
				ksort( $dir_array );
				ksort( $file_array );

				// All dirs
				foreach ( $dir_array as $file => $file_info ) {
					echo "<li class=\"directory collapsed\" draggable=\"true\"><a href=\"#\" rel=\"" . esc_attr( $_POST['dir'] . $file_info['name'] ) . "/\" draggable=\"false\">" . esc_html( $file_info['name'] ) . "</a></li>";
				}
				// All files
				foreach ( $file_array as $file => $file_info ) {
					$ext = preg_replace( '/^.*\./', '', $file_info['name'] );
					echo "<li class=\"file ext_$ext\" draggable=\"true\"><a href=\"#\" rel=\"" . esc_attr( $_POST['dir'] . $file_info['name'] ) . "\" draggable=\"false\">" . esc_html( $file_info['name'] ) . "</a></li>";
				}
			}
			// output toolbar for creating new file, folder etc
			echo "<li class=\"create_new\"><a class='new_directory' title='" . __( 'Create a new directory here' ) . "' href=\"#\" rel=\"{type: 'directory', path: '" . esc_attr( $_POST['dir'] ) . "'}\"></a> <a class='new_file' title='" . __( 'Create a new file here' ) . "' href=\"#\" rel=\"{type: 'file', path: '" . esc_attr( $_POST['dir'] ) . "'}\"></a><br style='clear:both;' /></li>";
			echo "</ul>";
		}

		die(); // this is required to return a proper result
	}

	public function image_edit_key() {
		// check the user has the permissions
		self::check_perms();

		if (ob_get_level()) {
			ob_end_clean();
		}

		// create a nonce based on the image path
		echo wp_create_nonce( 'aceide_image_edit' . $_POST['file'] );
	}

	public function save_image() {
		global $wp_filesystem;

		self::check_perms(false);

		$filename = explode( '::', $_POST['opt'] );
		$filename = reset( $filename ); // filename

		// setup wp_filesystem api
		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			echo __( "Cannot initialise the WP file system API" );
		}

		// save a copy of the file and create a backup just in case
		$backup_path = 'backups' . preg_replace( "#\.php$#i", "_" . date( "Y-m-d-H" ) . ".php", $filename );
		$backup_path = plugin_dir_path( __FILE__ ) . $backup_path;

		// create backup directory if not there
		$new_file_info = pathinfo( $backup_path );
		if ( ! $wp_filesystem->is_dir( $new_file_info['dirname'] ) ) {
			wp_mkdir_p( $new_file_info['dirname'] ); // should use the filesytem api here but there isn't a comparable command right now
		}

		// Find absolute path to save to
		$root     = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$filename = $root . stripslashes( $filename );

		// do backup
		$wp_filesystem->move( $filename, $backup_path );

		// retrieve new file
		$edited = wp_remote_get( $_POST['url'] );
		$edited = wp_remote_retrieve_body( $edited );

		// save file
		if ( $wp_filesystem->put_contents( $filename, $edited ) ) {
			wp_die( sprintf(
				'<p><strong>%s</strong> <br /><a href="JavaScript:window.close();">%s</a>.</p>',
				__( 'Image saved.' ),
				__( 'You may close this window / tab' )
			) );
		} else {
			wp_die( sprintf(
				'<p><strong>%s</strong> <br /><a href="JavaScript:window.close();">%s</a></p>',
				__( 'Problem saving image.' ),
				__( 'Close this window / tab and try editing the image again.' )
			) );
		}
	}

	public function startup_check() {
		global $wp_filesystem, $wp_version;

		// check the user has the permissions
		self::check_perms();

		if (ob_get_level()) {
			ob_end_clean();
		}

		echo "\n\n\n\n" . __( 'ACEIDE STARTUP CHECKS' ) . "\n";
		echo "___________________ \n\n";

		// WordPress version
		if ($wp_version > 3){
			printf( __( 'WordPress version = %s' ), $wp_version );
		}else{
			printf( __( 'WordPress version = %s (which is too old to run AceIDE)' ), $wp_version );
		}
		echo "\n\n";

		if ( defined( 'ACEIDE_FS_METHOD_FORCED_ELSEWHERE' ) ) {
			echo __( sprintf(
				"WordPress filesystem API has been forced to use the %s method by another plugin/WordPress",
				ACEIDE_FS_METHOD_FORCED
			) ) . "\n";
		}

		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			echo __( 'WordPress constant "DISALLOW_FILE_EDIT" defined but overridden by the "aceide_override_disallow_file_edit" filter.' ) . "\n";
		}
		echo "\n";

		// setup wp_filesystem api
		$aceide_filesystem_before = $wp_filesystem;

		$url         = wp_nonce_url( 'admin.php?page=aceide','plugin-name-action_acepidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials($url, FS_METHOD, false, false, $form_fields);

		ob_start();
		if ( false === $creds ) {
			// if we get here, then we don't have credentials yet,
			// but have just produced a form for the user to fill in,
			// so stop processing for now
			// return true; // stop the normal page form from displaying
		}
		ob_end_clean();

		if ( ! WP_Filesystem( $creds ) ) {
			echo __( "There has been a problem initialising the filesystem API" ) . "\n\n";
			echo __( "Filesystem API before this plugin ran:" ) . " \n\n" . print_r( $aceide_filesystem_before, true );
			echo __( "Filesystem API now:" ) . " \n\n" . print_r( $wp_filesystem, true );
		}
		unset( $aceide_filesystem_before );

		$root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		if ( isset( $wp_filesystem ) ) {
			// Running webservers user and group
			printf(
				__( 'Web server user/group = %s:%s' ),
				getenv( 'APACHE_RUN_USER' ),
				getenv( 'APACHE_RUN_GROUP' )
			);
			echo "\n";

			// wp-content user and group
			printf(
				__( 'Root folder owner/group = %s:%s' ),
				$wp_filesystem->owner( $root ),
				$wp_filesystem->group( $root )
			);
			echo "\n\n";

			// check we can list wp-content files
			if ( $wp_filesystem->exists( $root ) ) {
				$files = $wp_filesystem->dirlist( $root );
				if ( count( $files ) > 0) {
					printf(
						__( 'Root folder exists and contains %d files' ),
						count( $files )
					);
				} else {
					echo __( 'Root folder exists but we cannot read it\'s contents' );
				}

				echo "\n";
			}

			echo "\n" . __( "Using the {$wp_filesystem->method} method of the WP filesystem API" ) . "\n";

			$is_readable = $wp_filesystem->is_readable( $root ) == 1;
			$is_writable = $wp_filesystem->is_writable( $root ) == 1;

			if ( $is_readable  && $is_writable ) {
				echo __( "The root folder IS readable and IS writable by this method" );
			} elseif ( $is_readable && ! $is_writable ) {
				echo __( "The root folder IS readable but IS NOT writable by this method" );
			} elseif ( ! $is_readable && $is_writable ) {
				echo __( "The root folder IS NOT readable but IS writable by this method" );
			} else {
				echo __( "The root folder IS NOT readable and IS NOT writable by this method" );
			}
			echo "\n";

			if ($is_readable || $is_writable) {
				$real_root = trailingslashit(realpath($root));
				$real_wpc  = trailingslashit(WP_CONTENT_DIR);
				$wp_content_accessible = (strpos($real_wpc, $real_root) !== false);

				if ($wp_content_accessible) {
					$is_readable = $wp_filesystem->is_readable( $real_wpc . 'plugins' ) == 1;
					$is_writable = $wp_filesystem->is_writable( $real_wpc . 'plugins' ) == 1;

					// plugins folder editable
					if ( $is_readable  && $is_writable ) {
						echo __( "The wp-content/plugins folder IS readable and IS writable by this method" );
					} elseif ( $is_readable && ! $is_writable ) {
						echo __( "The wp-content/plugins folder IS readable but IS NOT writable by this method" );
					} elseif ( ! $is_readable && $is_writable ) {
						echo __( "The wp-content/plugins folder IS NOT readable but IS writable by this method" );
					} else {
						echo __( "The wp-content/plugins folder IS NOT readable and IS NOT writable by this method" );
					}
					echo "\n";

					// themes folder editable
					$is_readable = $wp_filesystem->is_readable( $real_wpc . 'themes' ) == 1;
					$is_writable = $wp_filesystem->is_writable( $real_wpc . 'themes' ) == 1;

					// plugins folder editable
					if ( $is_readable  && $is_writable ) {
						echo __( "The wp-content/themes folder IS readable and IS writable by this method" );
					} elseif ( $is_readable && ! $is_writable ) {
						echo __( "The wp-content/themes folder IS readable but IS NOT writable by this method" );
					} elseif ( ! $is_readable && $is_writable ) {
						echo __( "The wp-content/themes folder IS NOT readable but IS writable by this method" );
					} else {
						echo __( "The wp-content/themes folder IS NOT readable and IS NOT writable by this method" );
					}
				} else {
					echo __( "The wp-content folder is not accessible from the current AceIDE root directory" );
				}
				echo "\n";
			}
		}

		echo "___________________ \n\n\n\n";

		echo __( " If the file tree to the right is empty there is a possibility that your server permissions are not compatible with this plugin. \n The startup information above may shed some light on things. \n Paste that information into the support forum for further assistance." );

		die();
	}

	public function add_my_menu_page() {
		global $wp_version;

		// We should not even display a menu item if we can't edit files
		if ( defined( 'DISALLOW_FILE_EDIT' ) && ! apply_filters( 'aceide_override_disallow_file_edit', ! DISALLOW_FILE_EDIT ) ) {
			return;
		}

		if ( version_compare( $wp_version, '3.8', '<' ) ) {
			$this->menu_hook = add_menu_page( 'AceIDE', 'AceIDE', 'edit_plugins', "aceide", array( &$this, 'my_menu_page' ) );
		} else {
			$this->menu_hook = add_menu_page( 'AceIDE', 'AceIDE', 'edit_plugins', "aceide", array( &$this, 'my_menu_page' ), 'dashicons-editor-code' );
		}
	}

	public function add_my_menu_icon() {
		global $wp_version;

		// We should not even display a menu icon if we can't edit files
		if ( defined( 'DISALLOW_FILE_EDIT' ) && ! apply_filters( 'aceide_override_disallow_file_edit', ! DISALLOW_FILE_EDIT ) ) {
			return;
		}

		if ( version_compare( $wp_version, '3.8', '<' ) ):
?>
	<style type="text/css">
		#toplevel_page_aceide .wp-menu-image {
			background-image: url( '<?php echo plugins_url( 'images/aceide_icon.png', __FILE__ ); ?>' );
			background-position: 6px -18px !important;
		}
		#toplevel_page_aceide:hover .wp-menu-image,
		#toplevel_page_aceide.current .wp-menu-image {
			background-position: 6px 6px !important;
		}
	</style>
<?php
		endif;
	}

	public function my_menu_page() {
		// Do not check for a nonce, however check other permissions
		self::check_perms( false );

		$app_url = get_bloginfo('url'); // need to make this https if we are currently looking on the site using https (even though https for admin might not be forced it can still cause issues)
		if ( is_ssl() ) {
			$app_url = str_replace( "http:", "https:", $app_url );
		}

		$url    = trailingslashit( site_url() );
		$root   = apply_filters( "aceide_filesystem_root", WP_CONTENT_DIR );
		$root   = trailingslashit( $root );
		$root   = str_replace( ABSPATH, $url, $root );
		$root   = untrailingslashit( $root );

		do_action('pre_output_aceide_menu_page');
		do_action('pre_output_aceide_menu_page_scripts');
		?>
		<script>

			var aceide_app_path = "<?php echo plugin_dir_url( __FILE__ ); ?>";
			// dont think this is needed any more.. var aceide_file_root_url = "<?php echo $root ?>";
			var user_nonce_addition = '';

			function the_filetree() {
				jQuery('#aceide_file_browser').fileTree({ script: ajaxurl }, function(parent, file) {
					if ( jQuery(parent).hasClass("create_new") ) { // create new file/folder
						// to create a new item we need to know the name of it so show input

						var item = eval('('+file+')');

						// hide all inputs just incase one is selected
						jQuery(".new_item_inputs").hide();
						// show the input form for this
						jQuery("div.new_" + item.type).show();
						jQuery("div.new_" + item.type + " input[name='new_" + item.type + "']").focus();
						jQuery("div.new_" + item.type + " input[name='new_" + item.type + "']").attr("rel", file);
					} else if ( jQuery(".aceide_tab[rel='"+file+"']").length > 0) {  // focus existing tab
						jQuery(".aceide_tab[sessionrel='"+ jQuery(".aceide_tab[rel='"+file+"']").attr("sessionrel") +"']").click();// focus the already open tab
					} else { // open file
						var image_pattern = new RegExp("\\.(jpg|gif|png|bmp)$");
						if (image_pattern.test(file)) {
							// it's an image so open it for editing

							// using modal+iframe
							if ("lets not" == "use the modal for now") {

								var NewDialog = jQuery('<div id="MenuDialog">\
									<iframe src="http://www.sumopaint.com/app/?key=ebcdaezjeojbfgih&target=<?php echo get_bloginfo( 'url' ); ?>?action=aceide_image_save&url=<?php echo $root; ?>' + file + '&title=Edit image&service=Save back to AceIDE" width="100%" height="600px"> </iframe>\
									</div>');
								NewDialog.dialog({
									modal: true,
									title: "title",
									show: 'clip',
									hide: 'clip',
									width:'800',
									height:'600'
								});
							} else { // open in new tab/window
								var data = { action: 'aceide_image_edit_key', file: file, _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val() };
								var image_data = '';
								var image_title = encodeURIComponent(file.match(/(^|\/)([^\/]*$)/)[2]);
								jQuery.ajaxSetup({async:false}); // we need to wait until we get the response before opening the window
								jQuery.post(ajaxurl, data, function(response) {
									// with the response (which is a nonce), build the json data to pass to the image editor. The edit key (nonce) is only valid to edit this image
									image_data = file+'::'+response;
								});
								jQuery.ajaxSetup({async:true});// enable async again

								window.open('http://www.sumopaint.com/app/?key=ebcdaezjeojbfgih&url=<?php echo $root; ?>' + file + '&opt=' + image_data + '&title=' + image_title + '&service=Save back to AceIDE&target=<?php echo urlencode( $app_url . "/wp-admin/admin.php?aceide_save_image=yes" ) ; ?>');
							}
						} else {
							jQuery(parent).addClass('wait');

							aceide_set_file_contents(file, function(){
								// once file loaded remove the wait class/indicator
								jQuery(parent).removeClass('wait');
							});

							jQuery('#filename').val(file);
						}
					}

				});
			}

			jQuery(document).ready(function($) {
//				$("#fancyeditordiv").css("height", ($('body').height()-120) + 'px' );

				$(document).trigger('aceide:prefiletree');

				// Handler for .ready() called.
				the_filetree() ;

				$(document).trigger('aceide:postfiletree');

				// inialise the color assist
				$("#aceide_color_assist img").ImageColorPicker({
		  			afterColorSelected: function(event, color){
						jQuery("#aceide_color_assist_input").val(color);
			  		}
	  			});
				$("#aceide_color_assist").hide(); // hide it until it's needed

				$("#aceide_color_assist_send").click(function(e){
					e.preventDefault();
					editor.insert( jQuery("#aceide_color_assist_input").val().replace('#', '') );

					$("#aceide_color_assist").hide(); // hide it until it's needed again
				});

				$(".close_color_picker a").click(function(e){
					e.preventDefault();
					$("#aceide_color_assist").hide(); // hide it until it's needed again
				});

				$("#aceide_toolbar_buttons").on('click', "a.restore", function(e){
					e.preventDefault();
					var file_path = jQuery(".aceide_tab.active", "#aceide_toolbar").data( "backup" );

					jQuery("#aceide_message").hide(); // might be shortly after a save so a message may be showing, which we don't need
					jQuery("#aceide_message").html('<span><strong><?php _e( 'File available for restore' ); ?></strong><p> ' + file_path + '</p><a class="button red restore now" href="'+ aceide_app_path + file_path +'"><?php _e( 'Restore this file now &#10012;' ); ?></a><a class="button restore cancel" href="#"><?php _e( 'Cancel &#10007;' ); ?></a><br /><em class="note"><strong>note: </strong><?php _e( 'You can browse all file backups if you navigate to the backups folder (plugins/AceIDE/backups/..) using the filetree.' ); ?></em></span>');
					jQuery("#aceide_message").show();
				});
				$("#aceide_toolbar_buttons").on('click', "a.restore.now", function(e){
					e.preventDefault();

					var data = { restorewpnonce: user_nonce_addition + jQuery('#_wpnonce').val() };
					jQuery.post( aceide_app_path + jQuery(".aceide_tab.active", "#aceide_toolbar").data( "backup" )
								, data, function(response) {

						if (response == -1){
							alert("<?php _e( 'Problem restoring file.' ); ?>");
						}else{
							alert( response);
							jQuery("#aceide_message").hide();
						}

					});

				});
				$("#aceide_toolbar_buttons" ).on('click', "a.cancel", function(e){
					e.preventDefault();

					jQuery("#aceide_message").hide(); // might be shortly after a save so a message may be showing, which we don't need
				});
			});
		</script>
		<?php
			do_action('post_output_aceide_menu_page_scripts');
			do_action('pre_output_aceide_menu_page_html');
		?>
		<div id="poststuff" class="metabox-holder has-right-sidebar">
			<div id="side-info-column" class="inner-sidebar">
				<img src="<?php echo plugins_url( 'images/logo.png', __FILE__ ); ?>" id="aceidelogo" />
				<div id="aceide_info">
					<div id="aceide_info_content"></div>
				</div>
				<br style="clear:both;" />
				<div id="aceide_color_assist">
					<div class="close_color_picker"><a href="close-color-picker">x</a></div>
					<h3><?php _e( 'Colour Assist' ); ?></h3>
					<img src='<?php echo plugins_url( "images/color-wheel.png", __FILE__ ); ?>' />
					<input type="button" class="button" id="aceide_color_assist_send" value="<?php _e( '&lt; Send to editor' ); ?>" />
					<input type="text" id="aceide_color_assist_input" name="aceide_color_assist_input" value="" />
				</div>

				<div id="submitdiv" class="postbox ">
					<h3 class="hndle"><span>Files</span></h3>
					<div class="inside">
						<ul id="drag_coverup" class="jqueryFileTree" style="position: absolute; background-color: white; z-index: -1;"></ul>
						<div class="submitbox" id="submitpost">
							<div id="minor-publishing"></div>
							<div id="major-publishing-actions">
								<div id="aceide_file_browser"></div>
								<br style="clear:both;" />
								<div class="new_file new_item_inputs">
									<label for="new_folder"><?php _e( 'File name' ); ?></label>
									<input class="has_data" name="new_file" type="text" rel="" value="" placeholder="<?php esc_attr_e( 'Filename' ); ?>" />
									<a href="#" id="aceide_create_new_file" class="button-primary"><?php _e( 'CREATE' ); ?></a>
								</div>
								<div class="new_directory new_item_inputs">
									<label for="new_directory"><?php _e( 'Directory name' ); ?></label><input class="has_data" name="new_directory" type="text" rel="" value="" placeholder="<?php esc_attr_e( 'Folder' ); ?>" />
									<a href="#" id="aceide_create_new_directory" class="button-primary"><?php esc_html_e( 'CREATE' ); ?></a>
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="post-body">
				<div id="aceide_container">
					<div id="aceide_toolbar" class="quicktags-toolbar">
						<div id="aceide_toolbar_tabs"> </div>
						<div id="dialog_window_minimized_container"></div>
					</div>
	
					<div id="aceide_toolbar_buttons">
						<div id="aceide_message"></div>
						<a class="button restore" style="display:none;" title="<?php esc_attr_e( 'Restore the active tab' ); ?>" href="#"><?php _e( 'Restore &#10012;' ); ?></a>
					</div>
					<div id='fancyeditordiv'></div>
					<form id="aceide_save_container" action="" method="get">
						<div id="aceide_footer_message"></div>
						<div id="aceide_footer_message_last_saved"></div>
						<div id="aceide_footer_message_unsaved"></div>
	
					  	<a href="#" id="aceide_save" alt="<?php esc_attr_e( 'Keyboard shortcut to save [Ctrl/Cmd + S]' ); ?>" title="<?php esc_attr_e( 'Keyboard shortcut to save [Ctrl/Cmd + S]' ); ?>" class="button-primary"><?php esc_html_e( 'SAVE FILE' ); ?></a>
						<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
						<a href="#" id="aceide_git" alt="Open the Git overlay" title="Open the Git overlay" class="button-secondary"><?php esc_html_e( 'Git' ); ?></a>
						<?php endif; ?>
						<input type="hidden" id="filename" name="filename" value="" />
						<?php
							if ( function_exists( 'wp_nonce_field' ) ) {
								wp_nonce_field('plugin-name-action_aceidenonce');
							}
						?>
					</form>
				</div>
			</div>
		</div>
		<div id="editor_find_dialog" title="<?php esc_attr_e( 'Find...' ); ?>" style="padding: 0px; display: none;">
			<?php if ( false ): ?>
			<ul>
				<li><a href="#find-inline"><?php esc_html_e( 'Text' ); ?></a></li>
				<li><a href="#find-func"><?php esc_html_e( 'Function' ); ?></a></li>
			</ul>
			<?php endif; ?>
			<form id="find-inline" style="position: relative; padding: 4px; margin: 0px; height: 100%; overflow: hidden; width: 400px;">
				<label class="left"> <?php esc_html_e( 'Find' ); ?><input type="search" name="find" /></label>
				<label class="left"> <?php esc_html_e( 'Replace' ); ?><input type="search" name="replacement" /></label>
				<div class="clear" style="height: 33px;"></div>

				<label><input type="checkbox" name="wrap" checked="checked" /> <?php esc_html_e( 'Wrap Around' ); ?></label>
				<label><input type="checkbox" name="case" /> <?php esc_html_e( 'Case Sensitive' ); ?></label>
				<label><input type="checkbox" name="whole" /> <?php esc_html_e( 'Match Whole Word' ); ?></label>
				<label><input type="checkbox" name="regexp" /> <?php esc_html_e( 'Regular Expression' ); ?></label>

				<div class="search_direction">
					<?php esc_html_e( 'Direction:' ); ?>
					<label><input type="radio" name="direction" value="0" /> <?php esc_html_e( 'Up' ); ?></label>
					<label><input type="radio" name="direction" value="1" checked="checked" /> <?php esc_html_e( 'Down' ); ?></label>
				</div>
				<div class="right">
					<input type="submit" name="submit" value="<?php esc_attr_e( 'Find' ); ?>" class="action_button" />
					<input type="button" name="replace" value="<?php esc_attr_e( 'Replace' ); ?>" class="action_button" />
					<input type="button" name="replace_all" value="<?php esc_attr_e( 'Replace All' ); ?>" class="action_button" />
					<input type="button" name="cancel" value="<?php esc_attr_e( 'Cancel' ); ?>" class="action_button" />
				</div>
			</form>
			<?php if ( false ): ?>
			<form id="find-func">
				<label class="left"> <?php esc_html_e( 'Function' ); ?><input type="search" name="find" /></label>
				<div class="right">
					<input type="submit" name="submit" value="<?php esc_attr_e( 'Find Function' ); ?>" class="action_button" />
				</div>
			</form>
			<?php endif; ?>
		</div>
		<div id="editor_goto_dialog" title="<?php esc_attr_e( 'Go to...' ); ?>" style="padding: 0px; display: none;"></div>
		<div id="editor_settings_dialog" style="padding: 10px 10px 5px; display: none;">
			<div class="left">
				<label><?php _e( 'Theme:' ); ?> <input type="text" id="editor_theme_setting" class="ipt-sel"></label>
				<label><?php _e( 'Folding:' ); ?> <select id="editor_folding_setting" class="ipt-sel">
					<option value="manual"><?php _e( 'Manual' ); ?></option>
					<option value="markbegin" selected="selected"><?php _e( 'Beginning' ); ?></option>
					<option value="markbeginend"><?php _e( 'Beginning And End' ); ?></option>
				</select></label>
				<label><?php _e( 'Font Size:' ); ?> <input type="number" id="editor_font_size_setting" class="number"></label>
				<label><?php _e( 'Wrap Limit:' ); ?> <input type="number" id="editor_wrap_limit_setting" class="number"></label>
				<label><?php _e( 'Wrap Long Text:' ); ?> <input type="checkbox" id="editor_wrap_setting"></label>
				<label><?php _e( 'Fade Folders:' ); ?> <input type="checkbox" id="editor_fade_fold_setting"></label>
			</div>
			<div class="right">
				<label><?php _e( 'Highlight Current Line:' ); ?> <input type="checkbox" id="editor_highlight_line_setting"></label>
				<label><?php _e( 'Show Invisible Characters:' ); ?> <input type="checkbox" id="editor_show_invisibles"></label>
				<label><?php _e( 'Show Indent Guidelines:' ); ?> <input type="checkbox" id="editor_display_indent_guides_setting"></label>
				<label><?php _e( 'Animate Scrollbar:' ); ?> <input type="checkbox" id="editor_animate_scroll_setting"></label>
				<label><?php _e( 'Show Gutter:' ); ?> <input type="checkbox" id="editor_show_gutter_setting"></label>
				<label><?php _e( 'Use Tabs:' ); ?> <input type="checkbox" id="editor_use_tabs_setting"></label>
				<label><?php _e( 'Highlight Same Words:' ); ?> <input type="checkbox" id="editor_word_highlight"></label>
				<label><?php _e( 'Show Behaviours:' ); ?> <input type="checkbox" id="editor_behaviours_setting"></label>
			</div>
		</div>
<?php
		do_action('post_output_aceide_menu_page_html');
	}
}
