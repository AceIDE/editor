<?php

namespace AceIDE\Editor\Modules;

use AceIDE\Editor\IDE;

class GitOps implements Module
{
	protected $git, $git_repo_path;

	public function setup_hooks() {
		return array (
			array( 'post_output_aceide_menu_page_scripts', array( &$this, 'add_git_js' ) ),
			array( 'post_output_aceide_menu_page_html',    array( &$this, 'add_git_html') ),
			array( 'wp_ajax_aceide_git_status',  array( &$this, 'git_status' ) ),
			array( 'wp_ajax_aceide_git_diff',    array( &$this, 'git_diff' ) ),
			array( 'wp_ajax_aceide_git_commit',  array( &$this, 'git_commit' ) ),
			array( 'wp_ajax_aceide_git_log',     array( &$this, 'git_log' ) ),
			array( 'wp_ajax_aceide_git_init',    array( &$this, 'git_init' ) ),
			array( 'wp_ajax_aceide_git_clone',   array( &$this, 'git_clone' ) ),
			array( 'wp_ajax_aceide_git_push',    array( &$this, 'git_push' ) ),
			array( 'wp_ajax_aceide_git_ssh_gen', array( &$this, 'git_ssh_gen' ) ),
		);
	}

	public function add_git_js() {
		IDE::check_perms( false );
		?>
	<script type="text/javascript">
		(function($) {
			$(document).on('aceide:prefiletree', function() {
				// set up the git commit overlay
				$('#gitdiv').dialog({
					autoOpen: false,
					title: 'Git',
					width: 800,
					appendTo: jQuery("#wpbody-content")
				});
			});

			$(document).on('aceide:postfiletree', function() {
				$("#aceide_git" ).on('click', function(e){
					e.preventDefault();

					$('#gitdiv').dialog( "open" );
				});

				$("#gitdiv .show_changed_files" ).on('click', function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					var data = { action: 'aceide_git_status', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
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

					var base64_file = $(this).attr('href');
					var data = { action: 'aceide_git_diff', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									file: base64_file, gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
						$(".gitdivdiff."+ base64_file.replace(/=/g, '_' ) ).html( response );
					});

				});

				// commit selected files
				$("#gitdiv" ).on('click', "#gitdivcommit a.button-primary", function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					if ( $(".gitfilerow input:checked").length > 0 ){
						var files_for_commit = [];
						$(".gitfilerow input:checked").each(function( index ) {
							files_for_commit[index] = $(this).val();
						});
					} else {
						alert("<?php _e( 'You haven\'t selected any files to be committed!'); ?>");
						return;
					}

					var data = { action: 'aceide_git_commit', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									files: files_for_commit, gitmessage: $('#gitmessage').val(), gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
						$("#gitdivcontent").html( response );
					});
				});

				// git log
				$("#gitdiv" ).on('click', ".git_log", function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					var base64_file = $(this).attr('href');
					var data = { action: 'aceide_git_log', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									sshpath: $('#sshpath').val(), gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
						$("#gitdivcontent").html( response );
					});
				});

				// git init
				$("#gitdiv" ).on('click', ".git_init", function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					var data = { action: 'aceide_git_init', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									repo_path: $('#repo_path').val(), gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
						$("#gitdivcontent").html( response );
					});
				});

				// git clone
				$("#gitdiv" ).on('click', ".git_clone", function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					var data = { action: 'aceide_git_clone', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									repo_path: $('#repo_path').val(), sshpath: $('#sshpath').val(), gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
						$("#gitdivcontent").html( response );
					});
				});

				// git push
				$("#gitdiv" ).on('click', ".git_push", function(e){
					e.preventDefault();

					$(".git_settings_panel").hide();

					var data = { action: 'aceide_git_push', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									sshpath: $('#sshpath').val(), gitpath: $('#gitpath').val(), gitbinary: $('#gitbinary').val() };

					$.post(ajaxurl, data, function(response) {
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

					var data = { action: 'aceide_git_ssh_gen', _wpnonce: $('#_wpnonce').val(), _wp_http_referer: $('#_wp_http_referer').val(),
									sshpath: $('#sshpath').val() };

					$.post(ajaxurl, data, function(response) {
						alert('<?php _e( "Your SSH key: %s" ); ?>'.replace('%s', response));
					});
				});
			});
		})(jQuery);
	</script>
		<?php
	}

	public function add_git_html() {
		IDE::check_perms( false );
		?>
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
		<?php
	}

	public function git_ssh_gen() {
		// check the user has the permissions
		IDE::check_perms();

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
			$this->createRsaKey( 2048 );

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

	protected function git_open_repo() {
		// check the user has the permissions
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

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
		IDE::check_perms();

		$this->git_open_repo(); // make sure git repo is open

		$userinfo = wp_get_current_user();

		// committer name, shows under author on github
		putenv( sprintf(
			"GIT_COMMITTER_NAME=%s %s",
			$userinfo->user_firstname,
			$userinfo->user_lastname
		) );

		// committer email address
		putenv( sprintf(
			"GIT_COMMITTER_EMAIL=%s",
			$userinfo->user_email
		) );

		$files = array();
		foreach ( $_POST['files'] as $file ) {
			$files[] = base64_decode( $file );
		}

		// get the current user to be used for the commit
		$current_user = wp_get_current_user();

		$this->git->add( $files );
		$this->git->commit( sanitize_text_field( stripslashes( $_POST['gitmessage'] ) ) , $files, "{$current_user->user_firstname} {$current_user->user_lastname} <{$current_user->user_email}>" );

		// output our status
		$this->git_status();

		die(); // this is required to return a proper result
	}

	private function createRsaKey( $size ) {
		$config = array();

		$rsa = openssl_pkey_new( array_merge( array (
			'private_key_bits' => $size
		), $config ) );

		openssl_pkey_export($rsa, $privatekeystr, null, $config);
		$publickeyarr = openssl_pkey_get_details($rsa);

		return array (
			'privatekey' => $privatekeystr,
			'publickey'  => $publickeyarr['key']
		);
	}
}
