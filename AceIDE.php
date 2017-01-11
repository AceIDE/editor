<?php
/**
 * Plugin Name: AceIDE
 * Plugin URI: https://github.com/AceIDE/AceIDE
 * Description: WordPress code editor with auto completion of both WordPress and PHP functions with reference, syntax highlighting, line numbers, tabbed editing, automatic backup.
 * Version: 2.5.4
 * Author: AceIDE
 **/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !file_exists( __DIR__ . '/vendor/' ) ) {
	trigger_error( 'Composer "vendor/" directory missing.', E_USER_ERROR );
}

require_once __DIR__ . '/vendor/autoload.php';

$fileops = new AceIDE\Editor\Modules\FileOps;
$ide = new AceIDE\Editor\IDE;
$settings_page = new AceIDE\Editor\Modules\SettingsPage;

$ide->extend($fileops);
$ide->extend($settings_page);

// As long as the GitOps module is under development, let's only make it
// available to WordPress users with WP_DEBUG enabled.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	$gitops = new AceIDE\Editor\Modules\GitOps;
	$ide->extend($gitops);
}
