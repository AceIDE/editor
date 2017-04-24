<?php

namespace AceIDE\Editor\Modules;

use WP_Error;
use AceIDE\Editor\IDE;
use PHPParser_Lexer;
use PHPParser_Parser;
use PclZip;
use ZipArchive;

class FileOps implements Module
{
	public function setup_hooks() {
		return array (
			array( 'wp_ajax_aceide_get_file',      array( &$this, 'get_file' ) ),
			array( 'wp_ajax_aceide_save_file',     array( &$this, 'save_file' ) ),
			array( 'wp_ajax_aceide_rename_file',   array( &$this, 'rename_file' ) ),
			array( 'wp_ajax_aceide_delete_file',   array( &$this, 'delete_file' ) ),
			array( 'wp_ajax_aceide_upload_file',   array( &$this, 'upload_file' ) ),
			array( 'wp_ajax_aceide_download_file', array( &$this, 'download_file' ) ),
			array( 'wp_ajax_aceide_unzip_file',    array( &$this, 'unzip_file' ) ),
			array( 'wp_ajax_aceide_zip_file',      array( &$this, 'zip_file' ) ),
			array( 'wp_ajax_aceide_create_new',    array( &$this, 'create_new' ) ),
			array( 'wp_ajax_aceide_move_file',     array( &$this, 'move_file' ) ),
		);
	}

	public function get_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

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

		if (ob_get_level()) {
			ob_end_clean();
		}

		echo $wp_filesystem->get_contents($file_name);
		die(); // this is required to return a proper result
	}

	public function save_file() {
		global $wp_filesystem, $current_user;

		// check the user has the permissions
		IDE::check_perms();

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
				if (ob_get_level()) {
					ob_end_clean();
				}

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
			exit;
		}

		// save a copy of the file and create a backup just in case
		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		// set backup filename
		$backup_path = 'backups' . preg_replace( "#\.php$#i", "_" . date( "Y-m-d-H" ) . ".php", $_POST['filename'] );
		$backup_path_full = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . $backup_path;
		// create backup directory if not there
		$new_file_info = pathinfo( $backup_path_full );

		if ( ! $wp_filesystem->is_dir( $new_file_info['dirname'] ) ) {
			wp_mkdir_p( $new_file_info['dirname'] ); // should use the filesytem api here but there isn't a comparable command right now
		}

		if ( $is_php ) {
			// create the backup file adding some php to the file to enable direct restore
			$current_user = wp_get_current_user();
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
			$current_user = wp_get_current_user();
			$user_md5 = md5( serialize( $current_user ) );

			$result = "\"" . $backup_path . ":::" . $user_md5 . "\"";
		} else {
			$result = __( 'Could not save file' );
		}

		if (ob_get_level()) {
			ob_end_clean();
		}

		die( $result ); // this is required to return a proper result
	}

	public function create_new() {
		// check the user has the permissions
		IDE::check_perms();

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

				if (ob_get_level()) {
					ob_end_clean();
				}

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

				if (ob_get_level()) {
					ob_end_clean();
				}

				if ( $write_result ) {
					die( "1" ); // created
				} else {
					printf( __( "Problem creating file %s" ), $root . $path . $filename );
				}
			}
		}

		if (ob_get_level()) {
			ob_end_clean();
		}

		echo "An error has occurred creating the file.";
		die(); // this is required to return a proper result
	}

	public function rename_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );
		$new_name  = dirname( $file_name ) . '/' . stripslashes( $_POST['newname'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}
    
			echo __( 'The target file doesn\'t exist!' );
			exit;
		}

		if ( $wp_filesystem->exists( $new_name ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The destination file exists!' );
			exit;
		}

		// Move instead of rename
		$renamed = $wp_filesystem->move( $file_name, $new_name );

		if ( !$renamed ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The file could not be renamed!' );
		}

		exit;
	}

	public function delete_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		$root      = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name = $root . stripslashes( $_POST['filename'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The file doesn\'t exist!' );
			exit;
		}

		$deleted = $wp_filesystem->delete( $file_name, true );

		if ( ! $deleted ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The file couldn\'t be deleted.' );
		}

		exit;
	}

	public function move_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		$root        = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$source      = $root . stripslashes( $_POST['source'] );
		$destination = $root . stripslashes( $_POST['destination'] );

		if ( ! $wp_filesystem->exists( $source ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The source file doesn\'t exist!' );
			exit;
		}

		if ( !$wp_filesystem->exists( $destination ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The destination directory does not exist!' );
			exit;
		}

		if ( !$wp_filesystem->is_dir( $destination ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'The destination is not a directory!' );
			exit;
		}

		$destination .= '/' . basename( $source );

		// Move instead of rename
		$moved = $wp_filesystem->move( $source, $destination );

		if (ob_get_level()) {
			ob_end_clean();
		}

		if ( !$moved ) {
			echo __( 'The file could not be renamed!' );
		} else {
			echo '1';
		}

		exit;
	}

	public function upload_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		$root = apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$destination_folder = $root . stripslashes( $_POST['destination'] );

		foreach ( $_FILES as $file ) {
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				continue;
			}

			$destination = $destination_folder . $file['name'];

			if ( $wp_filesystem->exists( $destination ) ) {
				if (ob_get_level()) {
					ob_end_clean();
				}

				exit( $file['name'] . ' already exists!' );
			}

			if ( ! $wp_filesystem->move( $file['tmp_name'], $destination ) ) {
				if (ob_get_level()) {
					ob_end_clean();
				}

				exit( $file['name'] . ' could not be moved.' );
			}

			if ( ! $wp_filesystem->chmod( $destination ) ) {
				if (ob_get_level()) {
					ob_end_clean();
				}

				exit( $file['name'] . ' could not be chmod.' );
			}
		}

		exit;
	}

	public function download_file() {
		global $wp_filesystem;

		// check the user has the permissions
		IDE::check_perms();

		$url         = wp_nonce_url( 'admin.php?page=aceide', 'plugin-name-action_aceidenonce' );
		$form_fields = null; // for now, but at some point the login info should be passed in here
		$creds       = request_filesystem_credentials( $url, FS_METHOD, false, false, $form_fields );

		if ( false === $creds ) {
			// no credentials yet, just produced a form for the user to fill in
			return true; // stop the normal page form from displaying
		}

		if ( ! WP_Filesystem( $creds ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		$root		= apply_filters( 'aceide_filesystem_root', WP_CONTENT_DIR );
		$file_name	= $root . stripslashes( $_POST['filename'] );

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo 'The file doesn\'t exist!';
			exit;
		}

		if (ob_get_level()) {
			ob_end_clean();
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
		IDE::check_perms();

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
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( "Cannot initialise the WP file system API" );
			exit;
		}

		if ( ! $wp_filesystem->exists( $file_name ) ) {
			if (ob_get_level()) {
				ob_end_clean();
			}

			echo __( 'Error: target file does not exist!' );
			exit;
		}

		$method = apply_filters( 'aceide_compression_method', 'zip' );

		switch ( $method ) {
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
			if (ob_get_level()) {
				ob_end_clean();
			}

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
				} */

				break;
			case 'b2z':
				exit('B2Z!');
			case 'zip':
			default:
				if ( $method !== 'zip' ) {
					return new WP_Error( 'invalid-compression', sprintf( '"%s" is not a valid compression mechanism.', $method ) );
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

			if ( false === $file ) {
				break;
			}

			if ( ( $file != '.' ) && ( $file != '..' ) ) {
				$filePath = "$folder/$file";
				$filePathRel = str_replace( $zip_base, '', $filePath );

				if ( $filePathRel{0} === '/' ) {
					$filePathRel = substr( $filePathRel, 1 );
				}

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
		IDE::check_perms();

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
			exit;
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
}
