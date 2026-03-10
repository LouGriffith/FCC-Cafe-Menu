<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Taxonomy {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        // ── Top-level Menu (e.g. "Drink Menu", "Pastries Menu") ──────────
        $menu_labels = [
            'name'          => 'Menus',
            'singular_name' => 'Menu',
            'menu_name'     => 'Menus',
            'all_items'     => 'All Menus',
            'edit_item'     => 'Edit Menu',
            'add_new_item'  => 'Add New Menu',
            'new_item_name' => 'New Menu Name',
            'search_items'  => 'Search Menus',
            'not_found'     => 'No menus found',
        ];

        register_taxonomy( 'fcc_menu_name', 'fcc_menu', [
            'labels'            => $menu_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'cafe-menu' ],
        ] );

        // ── Category within a menu (e.g. "Coffee", "Tea", "Pastries") ────
        $cat_labels = [
            'name'          => 'Menu Categories',
            'singular_name' => 'Menu Category',
            'menu_name'     => 'Categories',
            'all_items'     => 'All Menu Categories',
            'edit_item'     => 'Edit Menu Category',
            'add_new_item'  => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'search_items'  => 'Search Categories',
            'not_found'     => 'No categories found',
        ];

        register_taxonomy( 'fcc_menu_category', 'fcc_menu', [
            'labels'            => $cat_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'menu-category' ],
        ] );
    }
}
