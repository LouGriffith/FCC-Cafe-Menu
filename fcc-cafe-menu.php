<?php
/**
 * Plugin Name: FCC Cafe Menu
 * Plugin URI:  https://lougriffith.com
 * Description: Manage the Fancy Cat Cafe menu. Organize items across menus and categories, set sizes and pricing, flag happy hour items, and display the full menu or individual sections anywhere on your site using shortcodes.
 * Version:     1.1.0
 * Author:      Lou Griffith
 * Author URI:  https://lougriffith.com
 * Text Domain: fcc-cafe-menu
 * GitHub Plugin URI: https://github.com/LouGriffith/fcc-cafe-menu
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FCC_MENU_VERSION', '1.1.0' );
define( 'FCC_MENU_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCC_MENU_URL', plugin_dir_url( __FILE__ ) );

require_once FCC_MENU_DIR . 'includes/class-github-updater.php';
require_once FCC_MENU_DIR . 'includes/class-post-type.php';
require_once FCC_MENU_DIR . 'includes/class-taxonomy.php';
require_once FCC_MENU_DIR . 'includes/class-meta-boxes.php';
require_once FCC_MENU_DIR . 'includes/class-shortcodes.php';
require_once FCC_MENU_DIR . 'admin/class-admin-columns.php';
require_once FCC_MENU_DIR . 'admin/class-quick-edit.php';

// GitHub auto-updater
new FCC_GitHub_Updater( __FILE__, 'LouGriffith', 'fcc-cafe-menu' );

FCC_Menu_Post_Type::get_instance();
FCC_Menu_Taxonomy::get_instance();
FCC_Menu_Meta_Boxes::get_instance();
FCC_Menu_Shortcodes::get_instance();
FCC_Menu_Admin_Columns::get_instance();
FCC_Menu_Quick_Edit::get_instance();
