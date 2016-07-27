<?php
/**
 * Plugin Name: AceIDE
 * Plugin URI: https://github.com/AceIDE/AceIDE
 * Description: WordPress code editor with auto completion of both WordPress and PHP functions with reference, syntax highlighting, line numbers, tabbed editing, automatic backup.
 * Version: 2.5.0
 * Author: AceIDE
 **/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !file_exists( __DIR__ . '/vendor/' ) ) {
	trigger_error( 'Composer "vendor/" directory missing.', E_USER_ERROR );
}

require_once __DIR__ . '/vendor/autoload.php';

$fileops = new AceIDE\Editor\Modules\FileOps;
$gitops = new AceIDE\Editor\Modules\GitOps;
$ide = new AceIDE\Editor\IDE;

$ide->extend($fileops);
$ide->extend($gitops);
