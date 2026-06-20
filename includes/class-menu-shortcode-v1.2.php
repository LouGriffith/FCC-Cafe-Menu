<?php
/**
 * Fancy Cat Cafe — Menu Shortcode
 * Version: 1.2
 *
 * Changes from v1.1:
 *   - Rebuilt item markup to match restaurant menu layout:
 *     Name ............. Size  $Price   Size  $Price
 *     Description text below
 *
 * USAGE:
 *   require_once plugin_dir_path( __FILE__ ) . 'includes/class-menu-shortcode-v1.2.php';
 *
 * ATTRIBUTES:
 *   category  — category name to display. Omit to show all categories.
 *   columns   — 1 or 2. Default: 1.
 *   exclude   — comma-separated category names to exclude.
 *
 * EXAMPLES:
 *   [fcc_menu]
 *   [fcc_menu category="Coffee" columns="2"]
 *   [fcc_menu category="Syrups" columns="1"]
 *   [fcc_menu exclude="Syrups,Add-ons" columns="2"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'fcc_menu', [ $this, 'render' ] );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( [
            'category' => '',
            'columns'  => '1',
            'exclude'  => '',
        ], $atts, 'fcc_menu' );

        $columns     = intval( $atts['columns'] ) === 2 ? 2 : 1;
        $items_class = $columns === 2 ? 'fcc-menu__items fcc-menu__items--two-col' : 'fcc-menu__items';

        $term_args = [
            'taxonomy'   => 'fcc_menu_category',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        if ( ! empty( $atts['category'] ) ) {
            $term_args['name'] = sanitize_text_field( $atts['category'] );
        }

        $terms = get_terms( $term_args );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '<p>No menu items found.</p>';
        }

        $excluded = [];
        if ( ! empty( $atts['exclude'] ) ) {
            $excluded = array_map( 'strtolower', array_map( 'trim', explode( ',', $atts['exclude'] ) ) );
        }

        ob_start();
        ?>
        <div class="fcc-menu">
            <?php foreach ( $terms as $term ) :

                if ( in_array( strtolower( $term->name ), $excluded, true ) ) continue;

                $posts = get_posts( [
                    'post_type'      => 'fcc_menu',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'tax_query'      => [ [
                        'taxonomy' => 'fcc_menu_category',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ] ],
                ] );

                if ( empty( $posts ) ) continue;
            ?>
                <div class="fcc-menu__category" id="menu-<?php echo esc_attr( $term->slug ); ?>">
                    <h2 class="fcc-menu__category-title"><?php echo esc_html( $term->name ); ?></h2>
                    <div class="<?php echo esc_attr( $items_class ); ?>">

                        <?php foreach ( $posts as $post ) :
                            $description = get_post_meta( $post->ID, '_fcc_description', true );
                            $size1       = get_post_meta( $post->ID, '_fcc_size_option_1', true );
                            $price1      = get_post_meta( $post->ID, '_fcc_price_option_1', true );
                            $size2       = get_post_meta( $post->ID, '_fcc_size_option_2', true );
                            $price2      = get_post_meta( $post->ID, '_fcc_price_option_2', true );
                            $happy_hour  = get_post_meta( $post->ID, '_fcc_is_happy_hour', true );

                            $has_price1  = $price1 !== '' && $price1 !== false;
                            $has_price2  = $price2 !== '' && $price2 !== false;
                            $has_pricing = $size1 || $has_price1 || $size2 || $has_price2;
                        ?>
                            <div class="fcc-menu__item<?php echo $happy_hour ? ' fcc-menu__item--happy-hour' : ''; ?>">

                                <?php if ( $has_pricing ) : ?>

                                    <?php
                                    // ── Row 1: Name .... Size1 $Price1  Size2 $Price2
                                    // ── Row 2 (if single size): just shown inline in row 1
                                    // We build one name+dotted row, then prices inline
                                    ?>
                                    <div class="fcc-menu__name-price-row">
                                        <span class="fcc-menu__item-name">
                                            <?php echo esc_html( $post->post_title ); ?>
                                            <?php if ( $happy_hour ) : ?>
                                                <span class="fcc-menu__happy-hour-badge">Happy Hour</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fcc-menu__dot-leader"></span>
                                        <span class="fcc-menu__prices">
                                            <?php if ( $size1 || $has_price1 ) : ?>
                                                <span class="fcc-menu__price-option">
                                                    <?php if ( $size1 ) : ?>
                                                        <span class="fcc-menu__size"><?php echo esc_html( $size1 ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $has_price1 ) : ?>
                                                        <span class="fcc-menu__price">$<?php echo number_format( (float) $price1, 2 ); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $size2 || $has_price2 ) : ?>
                                                <span class="fcc-menu__price-option">
                                                    <?php if ( $size2 ) : ?>
                                                        <span class="fcc-menu__size"><?php echo esc_html( $size2 ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $has_price2 ) : ?>
                                                        <span class="fcc-menu__price">$<?php echo number_format( (float) $price2, 2 ); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                <?php else : ?>

                                    <div class="fcc-menu__name-price-row fcc-menu__name-price-row--no-price">
                                        <span class="fcc-menu__item-name">
                                            <?php echo esc_html( $post->post_title ); ?>
                                            <?php if ( $happy_hour ) : ?>
                                                <span class="fcc-menu__happy-hour-badge">Happy Hour</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                <?php endif; ?>

                                <?php if ( $description ) : ?>
                                    <p class="fcc-menu__item-description"><?php echo esc_html( $description ); ?></p>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

FCC_Menu_Shortcode::get_instance();