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

require_once __DIR__ . '/vendor/autoload.php';

$aceide = new AceIDE\Editor\AceIDE;
