<?php

namespace AceIDE\Editor;

use phpseclib\Crypt\RSA as Crypt_RSA;
use PHPParser_Lexer;
use PHPParser_Parser;

class AceIDE
{
	public $site_url, $plugin_url, $git, $git_repo_path;
    private $menu_hook;

	function __construct() {
    	// add AceIDE to the menu
		add_action( 'admin_menu', array( &$this, 'add_my_menu_page' ) );
		add_action( 'admin_head', array( &$this, 'add_my_menu_icon' ) );

		// hook for processing incoming image saves
		if ( isset( $_GET['aceide_save_image'] ) ) {
			// force local file method for testing - you could force other methods 'direct', 'ssh', 'ftpext' or 'ftpsockets'
			$this->override_fs_method( 'direct' );

			add_action('admin_init', array( &$this, 'save_image') );
		}

		add_action( 'admin_init', array( &$this, 'setup_hooks' ) );

		$this->site_url = get_bloginfo('url');
	}

    public function override_fs_method( $method='direct' ) {
        if ( defined('FS_METHOD') ) {
            define( 'ACEIDE_FS_METHOD_FORCED_ELSEWHERE', FS_METHOD ); // make a note of the forced method
        } else {
            define( 'FS_METHOD', $method ); // force direct
        }
    }

	public function setup_hooks() {
		// force local file method until I've worked out how to implement the other methods
		// main problem being password wouldn't/isn't saved between requests
		// you could force other methods 'direct', 'ssh', 'ftpext' or 'ftpsockets'
		$this->override_fs_method( 'direct' );

		// Uncomment any of these calls to add the functionality that you need.
		// Will only enqueue on AceIDE page
		add_action( 'admin_print_scripts-' . $this->menu_hook, array( &$this, 'add_admin_js' ) );
		add_action( 'admin_print_styles-'  . $this->menu_hook, array( &$this, 'add_admin_styles' ) );

		add_action( 'admin_print_footer_scripts', array( &$this, 'print_find_dialog' ) );
		add_action( 'admin_print_footer_scripts', array( &$this, 'print_settings_dialog' ) );

		// s1etup jqueryFiletree list callback
		add_action( 'wp_ajax_jqueryFileTree', array( &$this, 'jqueryFileTree_get_list' ) );
		// setup ajax function to get file contents for editing
		add_action( 'wp_ajax_aceide_get_file', array( &$this, 'get_file' ) );
		// setup ajax function to save file contents and do automatic backup if needed
		add_action( 'wp_ajax_aceide_save_file', array( &$this, 'save_file' ) );
		// setup ajax function to rename file/folder
		add_action( 'wp_ajax_aceide_rename_file', array( &$this, 'rename_file' ) );
		// setup ajax function to delete file/folder
		add_action( 'wp_ajax_aceide_delete_file', array( &$this, 'delete_file' ) );
		// setup ajax function to handle upload
		add_action( 'wp_ajax_aceide_upload_file', array( &$this, 'upload_file' ) );
		// setup ajax function to handle download
		add_action( 'wp_ajax_aceide_download_file', array( &$this, 'download_file' ) );
		// setup ajax function to unzip file
		add_action( 'wp_ajax_aceide_unzip_file', array( &$this, 'unzip_file' ) );
		// setup ajax function to zip file
		add_action( 'wp_ajax_aceide_zip_file', array( &$this, 'zip_file' ) );
		// setup ajax function to create new item (folder, file etc)
		add_action( 'wp_ajax_aceide_create_new', array( &$this, 'create_new' ) );
		// setup ajax function to show local git repo changes
		add_action( 'wp_ajax_aceide_git_status', array( &$this, 'git_status' ) );
		// setup ajax function to show diff
		add_action( 'wp_ajax_aceide_git_diff', array( &$this, 'git_diff' ) );
		// setup ajax function to commit changes
		add_action( 'wp_ajax_aceide_git_commit', array( &$this, 'git_commit' ) );
		// setup ajax function to view the git log
		add_action( 'wp_ajax_aceide_git_log', array( &$this, 'git_log' ) );
		// setup ajax function to initiate a git repo
		add_action( 'wp_ajax_aceide_git_init', array( &$this, 'git_init' ) );
		// setup ajax function to clone a remote
		add_action( 'wp_ajax_aceide_git_clone', array( &$this, 'git_clone' ) );
		// setup ajax function to push to remote
		add_action( 'wp_ajax_aceide_git_push', array( &$this, 'git_push' ) );
		// setup ajax function to view/generate ssh key and known host file
		add_action( 'wp_ajax_aceide_git_ssh_gen', array( &$this, 'git_ssh_gen' ) );
		// setup ajax function to create new item (folder, file etc)
		add_action( 'wp_ajax_aceide_image_edit_key', array( &$this, 'image_edit_key' )  );
		// setup ajax function for startup to get some debug info, checking permissions etc
		add_action( 'wp_ajax_aceide_startup_check', array( &$this, 'startup_check' ) );

		// add a warning when navigating away from AceIDE
		// it has to go after WordPress scripts otherwise WP clears the binding
		// This has been implemented in load-editor.js
		// add_action('admin_print_footer_scripts', array( &$this, 'add_admin_nav_warning' ), 99 );

		// Add body class to collapse the wp sidebar nav
		add_filter( 'admin_body_class', array( &$this, 'hide_wp_sidebar_nav' ), 11);

		// hide the update nag
		add_action( 'admin_menu', array( &$this, 'hide_wp_update_nag' ) );
	}


    public function hide_wp_sidebar_nav( $classes ) {
        global $hook_suffix;

		if ( apply_filters( 'aceide_sidebar_folded', $hook_suffix === $this->menu_hook ) ) {
			return  str_replace( "auto-fold", "", $classes ) . ' folded';
		}
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
		$plugin_path =  plugin_dir_url( __FILE__ );

		// include file tree
		wp_enqueue_script( 'jquery-file-tree', plugins_url( 'jqueryFileTree.js', __FILE__ ) );
		// include ace
		wp_enqueue_script( 'ace', plugins_url( 'js/ace-1.2.0/ace.js', __FILE__ ) );
		// include ace modes for css, javascript & php
		wp_enqueue_script( 'ace-mode-css', $plugin_path . 'js/ace-1.2.0/mode-css.js' );
		wp_enqueue_script( 'ace-mode-less', $plugin_path . 'js/ace-1.2.0/mode-less.js' );
		wp_enqueue_script( 'ace-mode-javascript', $plugin_path . 'js/ace-1.2.0/mode-javascript.js' );
		wp_enqueue_script( 'ace-mode-php', $plugin_path . 'js/ace-1.2.0/mode-php.js' );
		// include ace theme
		wp_enqueue_script( 'ace-theme', plugins_url( 'js/ace-1.2.0/theme-dawn.js', __FILE__ ) ); // ambiance looks really nice for high contrast
		// wordpress-completion tags
		wp_enqueue_script( 'aceide-wordpress-completion', plugins_url( 'js/autocomplete/wordpress.js', __FILE__ ) );
		// php-completion tags
		wp_enqueue_script( 'aceide-php-completion', plugins_url('js/autocomplete/php.js', __FILE__ ) );
		// load editor
		wp_enqueue_script( 'aceide-load-editor', plugins_url( 'js/load-editor.js', __FILE__ ) );
		// load filetree menu
		wp_enqueue_script( 'aceide-load-filetree-menu', plugins_url( 'js/load-filetree-menu.js', __FILE__ ) );
		// load autocomplete dropdown
		wp_enqueue_script( 'aceide-dd', plugins_url( 'js/jquery.dd.js', __FILE__ ) );

		// load jquery ui
		wp_enqueue_script( 'jquery-ui', plugins_url( 'js/jquery-ui-1.9.2.custom.min.js', __FILE__ ), array( 'jquery' ),  '1.9.2' );

		// load color picker
		wp_enqueue_script( 'ImageColorPicker', plugins_url( 'js/ImageColorPicker.js', __FILE__ ), array( 'jquery' ),  '0.3' );
	}

	public function add_admin_styles() {
		// main AceIDE styles
		wp_register_style( 'aceide_style', plugins_url( 'aceide.css', __FILE__ ) );
		wp_enqueue_style( 'aceide_style' );
		// filetree styles
		wp_register_style( 'aceide_filetree_style', plugins_url( 'jqueryFileTree.css', __FILE__ ) );
		wp_enqueue_style( 'aceide_filetree_style' );
		// autocomplete dropdown styles
		wp_register_style( 'aceide_dd_style', plugins_url( 'dd.css', __FILE__ ) );
		wp_enqueue_style( 'aceide_dd_style' );

		// jquery ui styles
		wp_register_style( 'aceide_jqueryui_style', plugins_url( 'css/flick/jquery-ui-1.8.20.custom.css', __FILE__ ) );
		wp_enqueue_style( 'aceide_jqueryui_style' );
	}

	public function jqueryFileTree_get_list() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

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

	public function get_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		// setup wp_filesystem api
        $url         = wp_nonce_url( 'admin.php?page=aceide','plugin-name-action_aceidenonce' );
        $form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

        if ( false === $creds ) {
             // no credentials yet, just produced a form for the user to fill in
            return true; // stop the normal page form from displaying
        }

		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes($_POST['filename']);

		echo $wp_filesystem->get_contents($file_name);
		die(); // this is required to return a proper result
	}

    public function git_ssh_gen() {
        // errors need to be on while experimental
        error_reporting( E_ALL );
        ini_set( "display_errors", 1 );

		$gitpath = preg_replace( "#/$#", "", sanitize_text_field( $_POST['sshpath'] ) );

        // create the folder if doesn't exist
        if ( ! file_exists( $gitpath ) ) {
            mkdir( $gitpath, 0700 );
        }

        // create known hosts if doesn't exist
        if ( ! file_exists( $gitpath . "/known_hosts" ) ) {
            touch( $gitpath . "/known_hosts" );
            chmod( $gitpath . "/known_hosts", 0700 );
        }

        // create keys if not exist
        if ( ! file_exists( $gitpath . "/id_rsa" ) || ! file_exists( $gitpath . "/id_rsa.pub" ) ) {
            $rsa = new Crypt_RSA();

            $rsa->setPublicKeyFormat( 'OPENSSH' );

            extract( $rsa->createKey() ); // == $rsa->createKey(1024) where 1024 is the key size - $privatekey and $publickey

            // create private key
            file_put_contents( $gitpath . "/id_rsa", $privatekey );
            chmod( $gitpath . "/id_rsa", 0700 );

            // create public key
            file_put_contents( $gitpath . "/id_rsa.pub", $publickey );
            chmod( $gitpath . "/id_rsa.pub", 0700 );
        }

        // return public key
        echo "\n\n" . file_get_contents( $gitpath . "/id_rsa.pub" ) . "\n\n";

        die();
    }

    public function git_open_repo() {
        // errors need to be on while experimental
        error_reporting( E_ALL );
        ini_set( "display_errors", 1 );

        $root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$root = trailingslashit($root);

        // check repo path entered or die
        if ( ! strlen( $_POST['gitpath'] ) ) {
            die( __( "Error: Path to your git repository is required! (see settings)" ) );
		}

        $this->git_repo_path = $root . sanitize_text_field( $_POST['gitpath'] );
        $gitbinary = sanitize_text_field( stripslashes( $_POST['gitbinary'] ) );
        /*
        if ( $gitbinary==="I'll guess.." ) { // the binary path
            $thebinary = TQ\Git\Cli\Binary::locateBinary();
            $this->git = TQ\Git\Repository\Repository::open( $this->git_repo_path, new TQ\Git\Cli\Binary( $thebinary ), 0755 );
        } else {
            $thebinary = $_POST['gitbinary'];
            $this->git = TQ\Git\Repository\Repository::open( $this->git_repo_path, new TQ\Git\Cli\Binary( $thebinary ), 0755 );
        }
        */
    }

    public function git_status() {
		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( !current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        // echo branch
        $branch = $this->git->getCurrentBranch();
        echo '<p><strong>' . __( 'Current branch:' ) . '</strong> ' . $branch . '</p>';

        //    [0] => Array
        // (
        //    [file] => AceIDE.php
        //    [x] =>
        //    [y] => M
        //    [renamed] =>
        // )
        $status = $this->git->getStatus();
        $i      = 0;// row counter

        if ( count( $status ) ) {
            // echo out rows of staged files
            foreach ( $status as $item ) {
                echo '<div class="gitfilerow ' . ( $i % 2 != 0 ? 'light' : '' )  ."\"><span class='filename'>{$item["file"]}</span> <input type='checkbox' name=\"" . str_replace( '=', '_', base64_encode( $item['file'] ) ) . '" value="' . base64_encode( $item['file'] ) . '" checked />
                <a href="' . base64_encode( $item['file'] ) . '" class="viewdiff">[view diff]</a> <div class="gitdivdiff ' . str_replace( '=', '_', base64_encode( $item['file'] ) ) . '"></div> </div>';
                $i++;
            }
        } else {
			echo '<p class="red">' . __( 'No changed files in this repo so nothing to commit.' ) . '</p>';
        }

        // output the commit message box
        echo '<div id="gitdivcommit"><label>Commit message</label><br /><input type="text" id="gitmessage" name="message" class="message" />
                <p><a href="#" class="button-primary">' . __( 'Commit the staged changes' ) . '</a></p></div>';

		die(); // this is required to return a proper result
	}



    public function git_log() {
        // check the user has the permissions
    	check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        $log = $this->git->getLog(50);

		echo '<div class="git_log">';
        foreach ( $log as $item ) {
            $matches   = array();
            $log_array = array();
            $bits      = explode( "\n", $item );

            foreach ( $bits as $bit ) {
				if ( preg_match_all( "#(.*): (.*)#iS", trim( $bit ), $matches ) ) {
					$key = $matches[1][0];

					if ( is_string( $key ) && trim( $key ) !== "" ) {
						$log_array[$key] = trim( $matches[2][0] );
					}
				}
            }

			$message = explode( end( $log_array ), $item );
			$commit  = explode( reset( $log_array ), $item );

			$log_array['message'] = trim( $message[2] );
            $log_array['commit']  = trim( str_replace( array( "commit ", "Author:" ), "", $commit[0] ) );

            echo '<span class="input_row">';
            echo "<span class='message'>{$log_array['message']}</span> {$log_array['AuthorDate']} <span style='float:right;'>ID: {$log_array['commit']}</span> ";
            echo "</span>";
        }
        echo "</div>";

		die(); // this is required to return a proper result
	}


    public function git_init() {
        // check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        // create the local repo path if it doesn't exist
        if ( ! file_exists( $this->git->getRepositoryPath() ) ) {
            mkdir( $this->git->getRepositoryPath() );
		}

        $result = $this->git->getBinary()->{'init'}( $this->git->getRepositoryPath(), array(
			// What do we put here?
        ) );

        // return $result->getStdOut(); // still not getting enough output from the push...
        if ( $result->getStdErr() === '' ) {
            echo $result->getStdOut();
        } else {
            echo $result->getStdErr();
        }

		die(); // this is required to return a proper result
	}


    public function git_clone() {
    	// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        // just incase it's a private repo we will setup the keys
        $sshpath = preg_replace( "#/$#", "", $_POST['sshpath'] ); // get path replacing end slash if entered

        putenv( 'GIT_SSH=' . plugin_dir_path( __FILE__ ) . 'git-wrapper-nohostcheck.sh' ); // tell Git about our wrapper script
        /* See note on git_push re wrapper */
        putenv( 'ACEIDE_SSH_PATH=' . $sshpath ); // no trailing slash - pass wp-content path to Git wrapper script
        putenv( 'HOME='. plugin_dir_path( __FILE__ ) . 'git' ); // no trailing slash - set home to the git directory (this may not be needed)

        if ( $_POST['repo_path'] === '' || is_null( $_POST['repo_path'] ) ) {

            echo '<span class="input_row">
                        <label>' . __( 'Clone a remote repository by entering it\'s remote path' ) . '</label>
                        <input type="text" name="repo_path" id="repo_path" value=""> <em>' . __( 'It will be cloned into the repository path/folder defined in the Git settings.' ) . '</em>
                        <p><a href="#" class="button-primary git_clone">' . __( 'Clone' ) . '</a></p>
                        </span>';
            die();

        }

        $path = sanitize_text_field( $_POST['repo_path'] );

        // create the local repo path if it doesn't exist
        if ( ! file_exists( $this->git->getRepositoryPath() ) ) {
            mkdir( $this->git->getRepositoryPath() );
		}

        $result = $this->git->getBinary()->{'clone'}( $this->git->getRepositoryPath(), array (
            $path,
            $this->git->getRepositoryPath(),
            '--recursive'
        ) );

        // return $result->getStdOut(); // still not getting enough output from the push...
        if ( $result->getStdErr() === '' ) {
            $result = $result->getStdOut();

            // format the output a little better
            $result = str_replace( '...', '...<br />', $result );

            echo $result;
        } else {
            echo $result->getStdErr();
        }

		die(); // this is required to return a proper result
	}

    public function git_push() {
    	// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can('edit_themes') ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        $sshpath = preg_replace( "#/$#", "", $_POST['sshpath'] ); // get path replacing end slash if entered

        putenv( 'GIT_SSH=' . plugin_dir_path( __FILE__ ) . 'git-wrapper-nohostcheck.sh' ); // tell Git about our wrapper script
        /*
            The wrapper we use above doesn't do a host check which means we can't guarentee the other side is who we think it is
            We have this other wrapper which does a host check which we should swap to after the initial push/connection has been made
            and the entry automatically added to known hosts but that logic isn't in place yet.
            putenv("GIT_SSH=". plugin_dir_path(__FILE__) . 'git/git-wrapper.sh');
        */
        putenv( 'ACEIDE_SSH_PATH=' . $sshpath ); // no trailing slash - pass wp-content path to Git wrapper script
        putenv( 'HOME=' . plugin_dir_path( __FILE__ ) . 'git' ); // no trailing slash - set home to the git directory (this may not be needed)

        echo '<pre>';
        $push_result = $this->git->push();
        echo '</pre>';

        if ( $push_result === '' ) {
            echo __( "Sucessfully pushed to your remote repo" );
        } else {
            echo $push_result;
        }

        echo __( "<p>Git push completed.</p>" );

		die(); // this is required to return a proper result
	}


	public function git_diff() {
    	// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( !current_user_can('edit_themes') ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        $file = sanitize_text_field( base64_decode( $_POST['file']) );

        $result = $this->git->getBinary()->{'diff'}( $this->git->getRepositoryPath(), array (
            $file
        ));

        // return $result->getStdOut(); // still not getting enough output from the push...
        if ( $result->getStdErr() === '' ) {
            $diff_lines = explode( "\n", $result->getStdOut() );
            foreach ( $diff_lines as $a_line ) {
                if ( preg_match( "#^\+#", $a_line ) ) {
                    $a_class = 'plus';
                } elseif ( preg_match("#^\-#", $a_line ) ) {
                     $a_class = 'minus';
                } else {
                     $a_class = '';
                }

                echo "<span class='diff_line {$a_class}'>{$a_line}</span>";
            }
        } else {
            echo $result->getStdErr();
        }

        echo '<strong>' . __( 'Diff' ) . '</strong>'  . $diff_table;

		die(); // this is required to return a proper result
	}

    public function git_commit() {
    	// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $this->git_open_repo(); // make sure git repo is open

        // putenv("GIT_AUTHOR_NAME=AceIDE"); // author can be set using env but for now we set it during the commit
        // putenv("GIT_AUTHOR_EMAIL=shanept@iinet.net.au");
        putenv( "GIT_COMMITTER_NAME=AceIDE" ); // commiter details, shows under author on github
        putenv( "GIT_COMMITTER_EMAIL=shanept@iinet.net.au" );

        $files = array();
        foreach ( $_POST['files'] as $file ) {
            $files[] = base64_decode( $file );
        }

        // get the current user to be used for the commit
        $current_user = wp_get_current_user();

        $this->git->add( $files );
        $this->git->commit( sanitize_text_field( stripslashes( $_POST['gitmessage'] ) ) , $files, "{$current_user->user_firstname} {$current_user->user_lastname} <{$current_user->user_email}>" );

        aceide::git_status();

		die(); // this is required to return a proper result
	}

	public function image_edit_key() {
		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		// create a nonce based on the image path
		echo wp_create_nonce( 'aceide_image_edit' . $_POST['file'] );
	}

	public function create_new() {
		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		// setup wp_filesystem api
		global $wp_filesystem;

        $url         = wp_nonce_url( 'admin.php?page=aceide','plugin-name-action_aceidenonce' );
        $form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

        if ( false === $creds ) {
            // no credentials yet, just produced a form for the user to fill in
            return true; // stop the normal page form from displaying
        }

		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}

		$root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );

		// check all required vars are passed
		if ( strlen( $_POST['path'] ) > 0 && strlen( $_POST['type'] ) > 0 && strlen( $_POST['file'] ) > 0 ) {
			$filename = $_POST['file'];
            $special_chars = array( "?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", chr( 0 ) );
            $filename = preg_replace( "#\x{00a0}#siu", ' ', $filename );
            $filename = str_replace( $special_chars, '', $filename );
            $filename = str_replace( array( '%20', '+' ), '-', $filename );
            $filename = preg_replace( '/[\r\n\t -]+/', '-', $filename );

			$path = $_POST['path'];

			if ( $_POST['type'] == "directory" ) {
				$write_result = $wp_filesystem->mkdir( $root . $path . $filename, FS_CHMOD_DIR );

				if ( $write_result ) {
					die( "1" ); // created
				} else {
					echo "Problem creating directory" . $root . $path . $filename;
				}
			} else if ( $_POST['type'] == "file" ) {
                // write the file
				$write_result = $wp_filesystem->put_contents(
					$root . $path . $filename,
					'',
					FS_CHMOD_FILE // predefined mode settings for WP files
				);

				if ( $write_result ) {
					die( "1" ); // created
				} else {
					printf( __( "Problem creating file %s" ), $root . $path . $filename );
				}
			}
		}

		echo "0";
		die(); // this is required to return a proper result
	}

	public function save_file() {
		global $wp_filesystem, $current_user;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

        $is_php = false;

        /*
         * Check file syntax of PHP files by parsing the PHP
         * If a site is running low on memory this PHP parser library could well tip memory usage over the edge
         * Especially if you are editing a large PHP file.
         * Might be worth either making this syntax check optional or it only running if memory is available.
         * Symptoms: no response on file save, and errors in your log like "Fatal error: Allowed memory size of 8388608 bytes exhausted…"
         */
        if ( preg_match( "#\.php$#i", $_POST['filename'] ) ) {
            $is_php = true;

            ini_set( 'xdebug.max_nesting_level', 2000 );

            $code = stripslashes( $_POST['content'] );

            $parser = new PHPParser_Parser( new PHPParser_Lexer );

            try {
                $stmts = $parser->parse( $code );
            } catch ( PHPParser_Error $e ) {
                echo 'Parse Error: ', $e->getMessage();
                die();
            }
        }

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
		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		// set backup filename
		$backup_path = 'backups' . preg_replace( "#\.php$#i", "_" . date( "Y-m-d-H" ) . ".php", $_POST['filename'] );
		$backup_path_full = plugin_dir_path( __FILE__ ) . $backup_path;
        // create backup directory if not there
		$new_file_info = pathinfo( $backup_path_full );
		if ( ! $wp_filesystem->is_dir( $new_file_info['dirname'] ) ) {
			wp_mkdir_p( $new_file_info['dirname'] ); // should use the filesytem api here but there isn't a comparable command right now
		}

        if ( $is_php ) {
            // create the backup file adding some php to the file to enable direct restore
            get_currentuserinfo();
            $user_md5 = md5( serialize( $current_user ) );

            $restore_php = '<?php /* start AceIDE restore code */
                                    if ( $_POST["restorewpnonce"] === "'.  $user_md5 . $_POST['_wpnonce'] . '" ) {
                                        if ( file_put_contents ( "' . $file_name . '" ,  preg_replace( "#<\?php /\* start AceIDE restore code(.*)end AceIDE restore code \* \?>/#s", "", file_get_contents( "' . $backup_path_full . '" ) ) ) ) {
                                            echo __( "Your file has been restored, overwritting the recently edited file! \n\n The active editor still contains the broken or unwanted code. If you no longer need that content then close the tab and start fresh with the restored file." );
                                        }
                                    } else {
                                        echo "-1";
                                    }
                                    die();
                            /* end AceIDE restore code */ ?>';

            file_put_contents( $backup_path_full ,  $restore_php . file_get_contents( $file_name ) );
        } else {
            // do normal backup
			$wp_filesystem->copy( $file_name, $backup_path_full );
        }

		// save file
		if ( $wp_filesystem->put_contents( $file_name, stripslashes( $_POST['content'] ) ) ) {
            // lets create an extra long nonce to make it less crackable
            get_currentuserinfo();
            $user_md5 = md5( serialize( $current_user ) );

			$result = "\"" . $backup_path . ":::" . $user_md5 . "\"";
		} else {
			$result = __( 'Could not save file' );
		}

		die( $result ); // this is required to return a proper result
	}


	public function rename_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to modify files for this site. SORRY' ) . '</p>' );
		}

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

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );
		$new_name  = dirname( $file_name ) . '/' . stripslashes( $_POST['newname'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			echo __( 'The target file doesn\'t exist!' );
			exit;
		}

		if ( $wp_filesystem->exists( $new_name ) ) {
			echo __( 'The destination file exists!' );
			exit;
		}

		// Move instead of rename
		$renamed = $wp_filesystem->move( $file_name, $new_name );

		if ( !$renamed ) {
			echo __( 'The file could not be renamed!' );
		}
		exit;
	}

	public function delete_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to modify files for this site. SORRY' ) . '</p>' );
		}

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

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			echo __( 'The file doesn\'t exist!' );
			exit;
		}

		$deleted = $wp_filesystem->delete( $file_name, true );

		if ( ! $deleted ) {
			echo __( 'The file couldn\'t be deleted.' );
		}

		exit;
	}

	public function upload_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die('<p>'.__('You do not have sufficient permissions to modify files for this site. SORRY').'</p>');
		}

		$url         = wp_nonce_url( 'admin.php?page=ace', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			echo __( "Cannot initialise the WP file system API" );
		}

		$root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$destination_folder = $root . stripslashes( $_POST['destination'] );

		foreach ( $_FILES as $file ) {
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				continue;
			}

			$destination = $destination_folder . $file['name'];

			if ( $wp_filesystem->exists( $destination ) ) {
				exit( $file['name'] . ' already exists!' );
			}

			if ( ! $wp_filesystem->move( $file['tmp_name'], $destination ) ) {
				exit( $file['name'] . ' could not be moved.' );
			}

			if ( ! $wp_filesystem->chmod( $destination ) ) {
				exit( $file['name'] . ' could not be chmod.' );
			}
		}

		exit;
	}

	public function download_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to modify files for this site. SORRY' ) . '</p>' );
		}

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

		$root		= apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name	= $root . stripslashes( $_POST['filename'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			echo 'The file doesn\'t exist!';
			exit;
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: filename="' . basename( $file_name ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		echo $wp_filesystem->get_contents( $file_name );
		exit;
	}

	public function zip_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to modify files for this site. SORRY' ) . '</p>' );
		}

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( !WP_Filesystem( $creds ) ) {
			echo __( "Cannot initialise the WP file system API" );
		}

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			echo __( 'Error: target file does not exist!' );
			exit;
		}

		$ext = '.zip';
		switch ( apply_filters( 'aceide_compression_method', 'zip' ) ) {
			case 'gz':
				$ext = '.tar.gz';
				break;
			case 'tar':
				$ext = '.tar';
				break;
			case 'b2z':
				$ext = '.b2z';
				break;
			case 'zip':
				$ext = '.zip';
				break;
		}

		// Unzip a file to its current directory.
		if ( $wp_filesystem->is_dir( $file_name ) ) {
			$output_path = dirname( $file_name ) . '/' . basename( $file_name ) . $ext;
		} else {
			$output_path = $file_name;
			$output_path = strstr( $file_name, '.', true ) . $ext;
		}

		$zipped = self::do_zip_file( $file_name, $output_path );

		if ( is_wp_error( $zipped ) ) {
			printf( '%s: %s', $zipped->get_error_code(), $zipped->get_error_message() );
		}

		exit;
	}

	protected static function do_zip_file( $file, $to ) {
		// Unzip can use a lot of memory, but not this much hopefully
		ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

		$method = apply_filters( 'aceide_compression_method', 'zip' );

		switch ( $method ) {
			case 'gz':
			case 'tar':
				if ( class_exists( 'PharData' ) && apply_filters( 'unzip_file_use_phardata', true ) ) {
					exit('yes');
					return self::_zip_archive_phardata( $file, $to );
				} else {
					exit( 'figure it out');
				}

/*				if ( $method === 'gz' ) {
					$gz = gzopen( $to );
				}
*/
				break;
			case 'b2z':
				exit('B2Z!');
			case 'zip':
			default:
				if ( $method !== 'zip' ) {
					trigger_error( sprintf( '"%s" is not a valid compression mechanism.', $method ) );
				}

				if ( class_exists( 'ZipArchive' ) && apply_filters( 'unzip_file_use_ziparchive', true ) ) {
					return self::_zip_file_ziparchive( $file, $to );
				} else {
					// Fall through to PclZip if ZipArchive is not available, or encountered an error opening the file.
					return self::_zip_file_pclzip( $file, $to );
				}
				break;
		}
	}

	protected static function _zip_file_ziparchive( $file, $to ) {
		$z = new ZipArchive;
		$opened = $z->open( $to, ZipArchive::CREATE );

		if ( $opened !== true ) {
			switch ( $opened ) {
				case ZipArchive::ER_EXISTS:
					return new WP_Error(
						'ZipArchive Error',
						'File already exists',
						ZipArchive::ER_EXISTS
					);
				case ZipArchive::ER_INCONS:
					return new WP_Error(
						'ZipArchive Error',
						'Archive inconsistent',
						ZipArchive::ER_INCONS
					);
				case ZipArchive::ER_INVAL:
					return new WP_Error(
						'ZipArchive Error',
						'Invalid argument',
						ZipArchive::ER_INVAL
					);
				case ZipArchive::ER_MEMORY:
					return new WP_Error(
						'ZipArchive Error',
						'Malloc failure',
						ZipArchive::ER_MEMORY
					);
				case ZipArchive::ER_NOENT:
					return new WP_Error(
						'ZipArchive Error',
						'No such file.',
						ZipArchive::ER_NOENT
					);
				case ZipArchive::ER_NOZIP:
					return new WP_Error(
						'ZipArchive Error',
						'Not a zip archive.',
						ZipArchive::ER_NOZIP
					);
				case ZipArchive::ER_OPEN:
					return new WP_Error(
						'ZipArchive Error',
						'Can\'t open file.',
						ZipArchive::ER_OPEN
					);
				case ZipArchive::ER_READ:
					return new WP_Error(
						'ZipArchive Error',
						'Read Error',
						ZipArchive::ER_READ
					);
				case ZipArchive::ER_SEEK:
					return new WP_Error(
						'ZipArchive Error',
						'Seek Error',
						ZipArchive::ER_SEEK
					);

				default:
					return new WP_Error(
						'ZipArchive Error',
						'Unknown Error',
						$opened
					);
					break;
			}
		}

		if ( is_dir( $file ) ) {
			$base = dirname( $file );
			$file = untrailingslashit( $file );

			$z = self::_zip_folder_ziparchive( $base, $file, $to, $z );
			if ( is_wp_error( $z ) ) {
				return $z;
			}
		} else {
			$z->addFile( $file, basename( $file ) );
		}

		$z->close();

		return true;
	}

	protected static function _zip_folder_ziparchive( $zip_base, $folder, $to, $z ) {
		$handle = opendir( $folder );
		while (1) {
			$file = readdir( $handle );

			if ( false === $file )
				break;

			if ( ( $file != '.' ) && ( $file != '..' ) ) {
				$filePath = "$folder/$file";
				$filePathRel = str_replace( $zip_base, '', $filePath );

				if ( $filePathRel{0} === '/' )
					$filePathRel = substr( $filePathRel, 1 );

				if ( is_file( $filePath ) ) {
					$z->addFile( $filePath, $filePathRel );
				} elseif ( is_dir( $filePath ) ) {
					// Add sub-directory.
					$z->addEmptyDir( $filePathRel );
					self::_zip_folder_ziparchive( $zip_base, $filePath, $to, $z );
				}
			}
		}

		closedir($handle);

		return $z;
	}

	protected static function _zip_file_pclzip( $file, $to ) {
		require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');

		$pz = new PclZip( $to );
		$created = $pz->create( $file, PCLZIP_OPT_REMOVE_PATH, dirname( $file ) );

		if ( !$created ) {
			return new WP_Error( 'PclZip Error', $pz->errorInfo( true ) );
		}

		return true;
	}

    protected static function _zip_file_phardata( $file, $to ) {
        $p = new PharData( $to );

        if ( is_dir( $file ) ) {
            $p->buildFromDirectory( $file );
        } else {
            $p->addFile( $file, basename( $file ) );
        }

        return true;
    }

	public function unzip_file() {
		global $wp_filesystem;

		// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to modify files for this site. SORRY' ) . '</p>' );
		}

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

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

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			echo 'Error: Extraction path doesn\'t exist!';
			exit;
		}

		$unzipped = self::do_unzip_file( $file_name, dirname( $file_name ) );

		if ( is_wp_error( $unzipped ) ) {
			printf( '%s: %s', $unzipped->get_error_code(), $unzipped->get_error_message() );
		}

		exit;
	}

	protected static function do_unzip_file( $from, $to ) {
		if ( ! file_exists( $from ) ) {
			return new WP_Error( 'file-missing', 'Archive missing.' );
		}

		$fp    = fopen( $from, 'rb' );
		$bytes = fread( $fp, 2 );
		fclose( $fp );

		switch ( $bytes ) {
			case "\37\213":
				// gz
			case 'BZ':
				return new WP_Error( 'unimplemented', 'That method is not yet implemented.' );
				break;
			case 'PK':
				return unzip_file( $from, $to );
			default:
				return new WP_Error( 'unknown', 'Unknown archive type' );
		}
	}

	public function save_image() {
		global $wp_filesystem;

		$filennonce = split( "::", $_POST["opt"] ); // file::nonce

		// check the user has a valid nonce
		// we are checking two variations of the nonce, one as-is and another that we have removed a trailing zero from
		// this is to get around some sort of bug where a nonce generated on another page has a trailing zero and a nonce generated/checked here doesn't have the zero
		if ( ! wp_verify_nonce( $filennonce[1], 'aceide_image_edit' . $filennonce[0] ) &&
			 ! wp_verify_nonce( rtrim($filennonce[1], "0") , 'aceide_image_edit' . $filennonce[0] ) ) {
			die( __( 'Security check' ) ); // die because both checks failed
		}
		// check the user has the permissions
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		$_POST['content']  = base64_decode( $_POST["data"] ); // image content
		$_POST['filename'] = $filennonce[0]; // filename

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
		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		// set backup filename
    	$backup_path = 'backups' . preg_replace( "#\.php$#i", "_" . date( "Y-m-d-H" ) . ".php", $_POST['filename'] );
		$backup_path = plugin_dir_path( __FILE__ ) . $backup_path;

        // create backup directory if not there
		$new_file_info = pathinfo( $backup_path );
		if ( ! $wp_filesystem->is_dir( $new_file_info['dirname'] ) ) {
			wp_mkdir_p( $new_file_info['dirname'] ); // should use the filesytem api here but there isn't a comparable command right now
		}

		// do backup
		$wp_filesystem->move( $file_name, $backup_path );

		// save file
		if ( $wp_filesystem->put_contents( $file_name, $_POST['content'] ) ) {
			$result = "success";
		}

		if ( $result == "success" ) {
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

        echo "\n\n\n\n" . __( 'ACEIDE STARTUP CHECKS' ) . "\n";
        echo "___________________ \n\n";

        // WordPress version
        if ($wp_version > 3){
        	printf( __( 'WordPress version = %s' ), $wp_version );
        }else{
            printf( __( 'WordPress version = %s (which is too old to run AceIDE)' ), $wp_version );
        }
		echo "\n\n";

    	// check the user has the permissions
		check_admin_referer( 'plugin-name-action_aceidenonce' );
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		if ( defined( 'ACEIDE_FS_METHOD_FORCED_ELSEWHERE' ) ) {
            echo __( sprintf(
				"WordPress filesystem API has been forced to use the %s method by another plugin/WordPress",
				ACEIDE_FS_METHOD_FORCED
			) );
        }
		echo "\n\n";

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
				__( 'wp-content owner/group = %s:%s' ),
				$wp_filesystem->owner( $root ),
				$wp_filesystem->group( $root )
			);
			echo "\n\n";

			// check we can list wp-content files
			if ( $wp_filesystem->exists( $root ) ) {
				$files = $wp_filesystem->dirlist( $root );
				if ( count( $files ) > 0) {
					printf(
						__( 'wp-content folder exists and contains %d files' ),
						count( $files )
					);
				} else {
					echo __( 'wp-content folder exists but we cannot read it\'s contents' );
				}

				echo "\n";
			}

			echo "\n" . __( "Using the {$wp_filesystem->method} method of the WP filesystem API" ) . "\n";

			$is_readable = $wp_filesystem->is_readable( $root ) == 1;
			$is_writable = $wp_filesystem->is_writable( $root ) == 1;

			if ( $is_readable  && $is_writable ) {
				echo __( "The wp-content folder IS readable and IS writable by this method" );
			} elseif ( $is_readable && ! $is_writable ) {
				echo __( "The wp-content folder IS readable but IS NOT writable by this method" );
			} elseif ( ! $is_readable && $is_writable ) {
				echo __( "The wp-content folder IS NOT readable but IS writable by this method" );
			} else {
				echo __( "The wp-content folder IS NOT readable and IS NOT writable by this method" );
			}
			echo "\n";

			if ($is_readable || $is_writable) {
				$is_readable = $wp_filesystem->is_readable( $root . '/plugins' ) == 1;
				$is_writable = $wp_filesystem->is_writable( $root . '/plugins' ) == 1;

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
				$is_readable = $wp_filesystem->is_readable( $root . '/themes' ) == 1;
				$is_writable = $wp_filesystem->is_writable( $root . '/themes' ) == 1;

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
				echo "\n";
			}
        }

        echo "___________________ \n\n\n\n";

        echo __( " If the file tree to the right is empty there is a possibility that your server permissions are not compatible with this plugin. \n The startup information above may shed some light on things. \n Paste that information into the support forum for further assistance." );

		die();
	}

	public function add_my_menu_page() {
		global $wp_version;

		if ( version_compare( $wp_version, '3.8', '<' ) ) {
			$this->menu_hook = add_menu_page( 'AceIDE', 'AceIDE', 'edit_themes', "aceide", array( &$this, 'my_menu_page' ) );
		} else {
			$this->menu_hook = add_menu_page( 'AceIDE', 'AceIDE', 'edit_themes', "aceide", array( &$this, 'my_menu_page' ), 'dashicons-editor-code' );
		}
	}

    public function add_my_menu_icon() {
		global $wp_version;

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
		if ( ! current_user_can( 'edit_themes' ) ) {
			wp_die( '<p>' . __( 'You do not have sufficient permissions to edit templates for this site. SORRY' ) . '</p>' );
		}

		$app_url = get_bloginfo('url'); // need to make this https if we are currently looking on the site using https (even though https for admin might not be forced it can still cause issues)
		if ( is_ssl() ) {
			$app_url = str_replace( "http:", "https:", $app_url );
		}

		?>
		<script>

			var aceide_app_path = "<?php echo plugin_dir_url( __FILE__ ); ?>";
            // dont think this is needed any more.. var aceide_file_root_url = "<?php echo apply_filters( "aceide_file_root_url", WP_CONTENT_URL ); ?>";
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
						var image_pattern = new RegExp("(\\.jpg$|\\.gif$|\\.png$|\\.bmp$)");
						if (image_pattern.test(file)) {
							// it's an image so open it for editing

							// using modal+iframe
							if ("lets not" == "use the modal for now") {

								var NewDialog = jQuery('<div id="MenuDialog">\
									<iframe src="http://www.sumopaint.com/app/?key=ebcdaezjeojbfgih&target=<?php echo get_bloginfo( 'url' ) . "?action=aceide_image_save";?>&url=<?php echo get_bloginfo( 'url' ) . "/wp-content";?>' + file + '&title=Edit image&service=Save back to AceIDE" width="100%" height="600px"> </iframe>\
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
								jQuery.ajaxSetup({async:false}); // we need to wait until we get the response before opening the window
								jQuery.post(ajaxurl, data, function(response) {
									// with the response (which is a nonce), build the json data to pass to the image editor. The edit key (nonce) is only valid to edit this image
									image_data = file+'::'+response;
								});
								jQuery.ajaxSetup({async:true});// enable async again

								window.open('http://www.sumopaint.com/app/?key=ebcdaezjeojbfgih&url=<?php echo $app_url. "/wp-content";?>' + file + '&opt=' + image_data + '&title=Edit image&service=Save back to AceIDE&target=<?php echo urlencode( $app_url . "/wp-admin/admin.php?aceide_save_image=yes" ) ; ?>');
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
//                $("#fancyeditordiv").css("height", ($('body').height()-120) + 'px' );

                // set up the git commit overlay
                $('#gitdiv').dialog({
                   autoOpen: false,
                   title: 'Git',
                   width: 800
                });

				// Handler for .ready() called.
				the_filetree() ;

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

                $("#aceide_git" ).on('click', function(e){
                    e.preventDefault();

                    $('#gitdiv').dialog( "open" );
                });

                $("#gitdiv .show_changed_files" ).on('click', function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                	var data = { action: 'aceide_git_status', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

					jQuery.post(ajaxurl, data, function(response) {
						$("#gitdivcontent").html( response );
					});
                });

                // view chosen diff
                $("#gitdiv" ).on('click', ".viewdiff", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    if ($(this).text() == '<?php _e( '[hide diff]' ); ?>') {
                        $(this).text('<?php _e( '[show diff]' ); ?>');
                        $(this).parent().find(".gitdivdiff").hide();
                    } else {
                        $(this).text('<?php _e( '[hide diff]' ); ?>');
                        $(this).parent().find(".gitdivdiff").show();
                    }

                    var base64_file = jQuery(this).attr('href');
                    var data = { action: 'aceide_git_diff', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    file: base64_file, gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

					jQuery.post(ajaxurl, data, function(response) {
                        $(".gitdivdiff."+ base64_file.replace(/=/g, '_' ) ).html( response );
					});

                });

                // commit selected files
                $("#gitdiv" ).on('click', "#gitdivcommit a.button-primary", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    if ( jQuery(".gitfilerow input:checked").length > 0 ){
                        var files_for_commit = [];
                        jQuery(".gitfilerow input:checked").each(function( index ) {
                            files_for_commit[index] = $(this).val();
                        });
                    } else {
                        alert("<?php _e( 'You haven\'t selected any files to be committed!'); ?>");
                        return;
                    }

                    var data = { action: 'aceide_git_commit', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    files: files_for_commit, gitmessage: jQuery('#gitmessage').val(), gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

    				jQuery.post(ajaxurl, data, function(response) {
                        $("#gitdivcontent").html( response );
					});
                });

                // git log
                $("#gitdiv" ).on('click', ".git_log", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    var base64_file = jQuery(this).attr('href');
                    var data = { action: 'aceide_git_log', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    sshpath: jQuery('#sshpath').val(), gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

        			jQuery.post(ajaxurl, data, function(response) {
                        $("#gitdivcontent").html( response );
					});
                });

                // git init
                $("#gitdiv" ).on('click', ".git_init", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    var data = { action: 'aceide_git_init', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    repo_path: jQuery('#repo_path').val(), gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

            		jQuery.post(ajaxurl, data, function(response) {
                        $("#gitdivcontent").html( response );
					});
                });

                // git clone
                $("#gitdiv" ).on('click', ".git_clone", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    var data = { action: 'aceide_git_clone', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    repo_path: jQuery('#repo_path').val(), sshpath: jQuery('#sshpath').val(), gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

        			jQuery.post(ajaxurl, data, function(response) {
                        $("#gitdivcontent").html( response );
					});
                });

                // git push
                $("#gitdiv" ).on('click', ".git_push", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").hide();

                    var data = { action: 'aceide_git_push', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    sshpath: jQuery('#sshpath').val(), gitpath: jQuery('#gitpath').val(), gitbinary: jQuery('#gitbinary').val() };

    				jQuery.post(ajaxurl, data, function(response) {
                        $("#gitdivcontent").html( response );
					});
                });

                // git show settings
                $("#gitdiv" ).on('click', ".git_settings", function(e){
                    e.preventDefault();

                    $(".git_settings_panel").toggle();
                });


                // git SSH key gen/view
                $("#gitdiv" ).on('click', ".git_ssh_gen", function(e){
                    e.preventDefault();

                    var data = { action: 'aceide_git_ssh_gen', _wpnonce: jQuery('#_wpnonce').val(), _wp_http_referer: jQuery('#_wp_http_referer').val(),
                                    sshpath: jQuery('#sshpath').val() };

        			jQuery.post(ajaxurl, data, function(response) {
						alert('<?php _e( "Your SSH key: %s" ); ?>'.replace('%s', response));
					});
                });
			});
		</script>

		<div id="poststuff" class="metabox-holder has-right-sidebar">
			<div id="side-info-column" class="inner-sidebar">
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
                    <a href="#" style="display:none;" id="aceide_git" alt="Open the Git overlay" title="Open the Git overlay" class="button-secondary"><?php esc_html_e( 'Git' ); ?></a>
                    <input type="hidden" id="filename" name="filename" value="" />
					<?php
						if ( function_exists( 'wp_nonce_field' ) ) {
							wp_nonce_field('plugin-name-action_aceidenonce');
						}
					?>
				</form>

                <div id="gitdiv">
                    <a class="button git_settings" href="#"><?php _e( 'GIT SETTINGS <em>setting local repo location, keys etc</em>' ); ?></a>
                    <a class="button git_clone" href="#"><?php _e( 'GIT CLONE <em>create or clone a repo</em>' ); ?></a>
                    <a class="button show_changed_files" href="#"><?php _e( 'GIT STATUS <em>show changed/staged files</em>' ); ?></a>
                    <a class="button git_log" href="#"><?php _e( 'GIT LOG <em>history of commits</em>' ); ?></a>
                    <a class="button git_push" href="#"><?php _e( 'GIT PUSH <em>push to remote repo</em>' ); ?></a>

                    <div class="git_settings_panel" style="display:none;">
                        <h2><?php esc_html_e( 'Git Settings' ); ?></h2>
                        <span class="input_row">
                          <label><?php esc_html_e( 'Local repository path' ); ?></label>
                          <input type="text" name="gitpath" id="gitpath" value="" />
                          <em><?php _e( 'The Git repository you want to work with. <br /> If it doesn\'t exist you can <a href="#" class="red git_init">initiate a blank repository by clicking here</a> or you can <a href="#" class="red git_clone">clone a remote repo over here</a>' ); ?></em>
                        </span>
                        <span class="input_row">
                          <label><?php esc_html_e( 'Git binary' ); ?></label>
                          <input type="text" name="gitbinary" id="gitbinary" value="<?php esc_attr_e( 'I\'ll guess...' ); ?>" /> <em><?php esc_html_e( 'Full path to the local Git binary on this server.' ); ?></em>
                        </span>
                        <span class="input_row">
                          <label><?php esc_html_e( 'SSH key path' ); ?></label>
                          <input type="text" name="sshpath" id="sshpath" value="<?php echo WP_CONTENT_DIR . '/ssh';?>" /> <em><?php esc_html_e( 'Full path to the folder that contains your SSH keys (both id_rsa and id_rsa.pub) and a known_hosts file.' ); ?></em>
                        </span>
                        <span class="input_row">
                          <?php _e( '<a href="#" class="git_ssh_gen red">Click here to view your SSH key</a>. If an SSH key cannot be found in the SSH path specified above, AceIDE will create this key for you. You\'ll need to pass this key to github or any other services/servers you need Git push access to.' ); ?>
                        </span>
                    </div>

                    <div id="gitdivcontent">
                     <h2><?php esc_html_e( 'Git functionality is currently experimental, so use at your own risk' ); ?></h2>
                     <p><?php  esc_html_e( 'Saying that, it does work. You can create new Git repositories, clone from remote repositories, push to remote repositories etc. BUT there are many Git features missing, errors aren\'t very tidy and the interface needs some serious attention but I just wanted to get it out there!' ); ?></p>
                     <p><?php  esc_html_e( 'For this functionality to work your Git binary needs to be accessible to the web server process/user and that user will probably need an ssh folder in the default place (~/.ssh) otherwise you will have trouble with remote repository access due to the SSH keys' ); ?></p>
                     <p><?php  esc_html_e( 'AceIDE will use it\'s own SSH key in a custom location which can then even be shared between different WordPress/AceIDE installs on the same server providing the SSH folder you set in settings is accessible to all installs.' ); ?></p>
                     <p><?php  esc_html_e( 'Don\'t be afraid to close this overlay. It will be in exactly the same state once you press the Git button again.' ); ?></p>
                    </div>
                 </div>
			</div>
		</div>
		<?php
	}

    public function print_find_dialog() {
		?>
	<div id="editor_find_dialog" title="<?php esc_attr_e( 'Find...' ); ?>" style="padding: 0px; display: none;">
		<?php if ( false ): ?>
		<ul>
			<li><a href="#find-inline"><?php esc_html_e( 'Text' ); ?></a></li>
        	<li><a href="#find-func"><?php esc_html_e( 'Function' ); ?></a></li>
        </ul>
		<?php endif; ?>
		<form id="find-inline" style="position: relative; padding: 4px; margin: 0px; height: 100%; overflow: hidden; width: 400px;">
			<label class="left"> <?php esc_html_e( 'Find' ); ?><input type="search" name="find" /></label>
			<label class="left"> <?php esc_html_e( 'Replace' ); ?><input type="search" name="replace" /></label>
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
<?php
    }

    public function print_settings_dialog() {

    }
}
