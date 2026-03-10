<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Post_Type {

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
        $labels = [
            'name'               => 'Menu Items',
            'singular_name'      => 'Menu Item',
            'menu_name'          => 'Cafe Menu',
            'all_items'          => 'All Menu Items',
            'edit_item'          => 'Edit Menu Item',
            'view_item'          => 'View Menu Item',
            'add_new_item'       => 'Add New Menu Item',
            'add_new'            => 'Add New Item',
            'new_item'           => 'New Menu Item',
            'search_items'       => 'Search Menu',
            'not_found'          => 'No menu items found',
            'not_found_in_trash' => 'No menu items found in Trash',
        ];

        register_post_type( 'fcc_menu', [
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'menu_position'     => 25,
            'menu_icon'         => 'dashicons-coffee',
            'supports'          => [ 'title' ],
            'has_archive'       => false,
            'publicly_queryable'=> true,
            'rewrite'           => [ 'slug' => 'menu-item' ],
        ] );
    }
}
