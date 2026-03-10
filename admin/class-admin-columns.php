<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Admin_Columns {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'manage_fcc_menu_posts_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_fcc_menu_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_filter( 'manage_edit-fcc_menu_sortable_columns', [ $this, 'sortable_columns' ] );
    }

    public function add_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['fcc_menu_name']  = 'Menu';
                $new['fcc_category']   = 'Category';
                $new['fcc_price']      = 'Price';
                $new['fcc_happy_hour'] = 'Happy Hour';
            }
        }
        return $new;
    }

    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'fcc_menu_name':
                $terms = get_the_terms( $post_id, 'fcc_menu_name' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $names = wp_list_pluck( $terms, 'name' );
                    echo esc_html( implode( ', ', $names ) );
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'fcc_category':
                $terms = get_the_terms( $post_id, 'fcc_menu_category' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $names = wp_list_pluck( $terms, 'name' );
                    echo esc_html( implode( ', ', $names ) );
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;

            case 'fcc_price':
                $size1  = get_post_meta( $post_id, '_fcc_size_option_1', true );
                $price1 = get_post_meta( $post_id, '_fcc_price_option_1', true );
                $size2  = get_post_meta( $post_id, '_fcc_size_option_2', true );
                $price2 = get_post_meta( $post_id, '_fcc_price_option_2', true );

                $parts = [];
                if ( $price1 ) {
                    $label = $size1 ? esc_html( $size1 ) . ': ' : '';
                    $parts[] = $label . '$' . number_format( (float) $price1, 2 );
                }
                if ( $price2 ) {
                    $label = $size2 ? esc_html( $size2 ) . ': ' : '';
                    $parts[] = $label . '$' . number_format( (float) $price2, 2 );
                }
                echo ! empty( $parts ) ? esc_html( implode( ' / ', $parts ) ) : '<span style="color:#999;">—</span>';
                break;

            case 'fcc_happy_hour':
                $is_happy = get_post_meta( $post_id, '_fcc_is_happy_hour', true );
                echo $is_happy === '1'
                    ? '<span style="color:#2ea44f;font-weight:600;">✓ Yes</span>'
                    : '<span style="color:#999;">—</span>';
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['fcc_category'] = 'fcc_category';
        return $columns;
    }
}
