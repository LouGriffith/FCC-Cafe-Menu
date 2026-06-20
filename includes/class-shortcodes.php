<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Shortcodes {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'fcc_menu', [ $this, 'render_menu' ] );
        add_shortcode( 'fcc_menu_category', [ $this, 'render_category' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'fcc-menu-frontend',
            FCC_MENU_URL . 'assets/menu-frontend.css',
            [],
            FCC_MENU_VERSION
        );
    }

    /**
     * [fcc_menu] — render items grouped by category
     *
     * Attributes:
     *   menu            = comma-separated fcc_menu_name slugs  (default: all)
     *   category        = comma-separated fcc_menu_category slugs (default: all)
     *   columns         = 1 or 2  (default: 1)
     *   show_happy_hour = "only" | "false" (all)  default: false
     *
     * Examples:
     *   [fcc_menu]
     *   [fcc_menu category="coffee" columns="2"]
     *   [fcc_menu menu="drink-menu" category="coffee,tea" columns="2"]
     *   [fcc_menu show_happy_hour="only"]
     */
    public function render_menu( $atts ) {
        $atts = shortcode_atts( [
            'menu'            => '',
            'category'        => '',
            'columns'         => '1',
            'show_happy_hour' => 'false',
        ], $atts, 'fcc_menu' );

        $columns     = intval( $atts['columns'] ) === 2 ? 2 : 1;
        $items_class = $columns === 2 ? 'fcc-menu-items fcc-menu-items--two-col' : 'fcc-menu-items';

        // Determine which categories to loop over
        $cat_args = [
            'taxonomy'   => 'fcc_menu_category',
            'hide_empty' => true,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ];

        if ( ! empty( $atts['category'] ) ) {
            $cat_args['slug'] = array_map( 'trim', explode( ',', $atts['category'] ) );
        }

        // If scoped to a menu, limit categories to those that have items in it
        if ( ! empty( $atts['menu'] ) ) {
            $menu_slugs = array_map( 'trim', explode( ',', $atts['menu'] ) );
            $scoped_ids = get_posts( [
                'post_type'      => 'fcc_menu',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => [ [
                    'taxonomy' => 'fcc_menu_name',
                    'field'    => 'slug',
                    'terms'    => $menu_slugs,
                ] ],
            ] );

            $valid_cat_ids = [];
            foreach ( $scoped_ids as $id ) {
                $cats = wp_get_post_terms( $id, 'fcc_menu_category', [ 'fields' => 'ids' ] );
                $valid_cat_ids = array_merge( $valid_cat_ids, (array) $cats );
            }

            if ( empty( $valid_cat_ids ) ) return '';
            $cat_args['include'] = array_unique( $valid_cat_ids );
        }

        $categories = get_terms( $cat_args );
        if ( is_wp_error( $categories ) || empty( $categories ) ) return '';

        ob_start();
        echo '<div class="fcc-menu-wrap">';

        foreach ( $categories as $cat ) {
            $tax_query = [ 'relation' => 'AND', [
                'taxonomy' => 'fcc_menu_category',
                'field'    => 'term_id',
                'terms'    => $cat->term_id,
            ] ];

            if ( ! empty( $atts['menu'] ) ) {
                $tax_query[] = [
                    'taxonomy' => 'fcc_menu_name',
                    'field'    => 'slug',
                    'terms'    => array_map( 'trim', explode( ',', $atts['menu'] ) ),
                ];
            }

            $query_args = [
                'post_type'      => 'fcc_menu',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'tax_query'      => $tax_query,
            ];

            if ( $atts['show_happy_hour'] === 'only' ) {
                $query_args['meta_query'] = [ [ 'key' => '_fcc_is_happy_hour', 'value' => '1' ] ];
            }

            $items = new WP_Query( $query_args );
            if ( ! $items->have_posts() ) continue;

            echo '<div class="fcc-menu-category">';
            echo '<h2 class="fcc-category-title">' . esc_html( $cat->name ) . '</h2>';
            if ( ! empty( $cat->description ) ) {
                echo '<p class="fcc-category-desc">' . esc_html( $cat->description ) . '</p>';
            }
            echo '<div class="' . esc_attr( $items_class ) . '">';

            while ( $items->have_posts() ) {
                $items->the_post();
                $this->render_item( get_the_ID() );
            }

            echo '</div></div>';
            wp_reset_postdata();
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * [fcc_menu_category category="coffee"] — shortcut to render one category
     */
    public function render_category( $atts ) {
        $atts = shortcode_atts( [
            'category' => '',
            'menu'     => '',
            'columns'  => '1',
        ], $atts, 'fcc_menu_category' );
        return $this->render_menu( $atts );
    }

    private function render_item( $post_id ) {
        $title       = get_the_title( $post_id );
        $description = get_post_meta( $post_id, '_fcc_description', true );
        $size1       = get_post_meta( $post_id, '_fcc_size_option_1', true );
        $price1      = get_post_meta( $post_id, '_fcc_price_option_1', true );
        $size2       = get_post_meta( $post_id, '_fcc_size_option_2', true );
        $price2      = get_post_meta( $post_id, '_fcc_price_option_2', true );
        $allergens   = get_post_meta( $post_id, '_fcc_allergen_info', true );
        $is_happy    = get_post_meta( $post_id, '_fcc_is_happy_hour', true );

        $has_price1  = $price1 !== '' && $price1 !== false;
        $has_price2  = $price2 !== '' && $price2 !== false;
        $has_pricing = $size1 || $has_price1 || $size2 || $has_price2;
        ?>
        <div class="fcc-menu-item<?php echo $is_happy ? ' fcc-happy-hour' : ''; ?>">

            <?php if ( $has_pricing ) : ?>
                <div class="fcc-item-header">
                    <span class="fcc-item-title">
                        <?php echo esc_html( $title ); ?>
                        <?php if ( $is_happy ) : ?>
                            <span class="fcc-badge-happy">Happy Hour</span>
                        <?php endif; ?>
                    </span>
                    <span class="fcc-dot-leader"></span>
                    <span class="fcc-item-pricing">
                        <?php if ( $size1 || $has_price1 ) : ?>
                            <span class="fcc-price-group">
                                <?php if ( $size1 ) : ?>
                                    <span class="fcc-size"><?php echo esc_html( $size1 ); ?></span>
                                <?php endif; ?>
                                <?php if ( $has_price1 ) : ?>
                                    <span class="fcc-price">$<?php echo number_format( (float) $price1, 2 ); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $size2 || $has_price2 ) : ?>
                            <span class="fcc-price-group">
                                <?php if ( $size2 ) : ?>
                                    <span class="fcc-size"><?php echo esc_html( $size2 ); ?></span>
                                <?php endif; ?>
                                <?php if ( $has_price2 ) : ?>
                                    <span class="fcc-price">$<?php echo number_format( (float) $price2, 2 ); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php else : ?>
                <div class="fcc-item-header fcc-item-header--no-price">
                    <span class="fcc-item-title">
                        <?php echo esc_html( $title ); ?>
                        <?php if ( $is_happy ) : ?>
                            <span class="fcc-badge-happy">Happy Hour</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ( $description ) : ?>
                <p class="fcc-item-desc"><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>

            <?php if ( $allergens ) : ?>
                <p class="fcc-item-allergens"><strong>Allergens:</strong> <?php echo esc_html( $allergens ); ?></p>
            <?php endif; ?>

        </div>
        <?php
    }
}