<?php
/**
 * FCC Cafe Menu — Bricks Dynamic Tags
 *
 * Registers custom dynamic data tags for use in Bricks Builder query loops.
 *
 * Available tags:
 *   {fcc_item_row}          — Full name + dotted leader + size/price row HTML
 *   {fcc_item_description}  — Item description text
 *   {fcc_item_price_1}      — Formatted price option 1 (e.g. "$6.25")
 *   {fcc_item_price_2}      — Formatted price option 2 (e.g. "$8.25")
 *   {fcc_item_size_1}       — Size label option 1 (e.g. "16oz")
 *   {fcc_item_size_2}       — Size label option 2 (e.g. "24oz")
 *   {fcc_item_is_happy_hour} — Returns "1" if happy hour, "" if not
 *
 * USAGE IN BRICKS:
 *   1. Add a Query Loop, set post type to fcc_menu, filter by fcc_menu_category term
 *   2. Inside the loop add a Basic Text or Rich Text element
 *   3. Type { to open the dynamic data picker and search "FCC"
 *   4. Select {fcc_item_row} for the name+price line
 *   5. Add a separate Basic Text element using {fcc_item_description}
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Bricks_Tags {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only load if Bricks is active
        add_action( 'init', [ $this, 'register' ], 20 );
    }

    public function register() {
        if ( ! function_exists( 'bricks_is_builder' ) && ! defined( 'BRICKS_VERSION' ) ) return;

        add_filter( 'bricks/dynamic_tags_list', [ $this, 'register_tags' ] );
        add_filter( 'bricks/dynamic_data/render_tag', [ $this, 'render_tag' ], 20, 3 );
        add_filter( 'bricks/dynamic_data/render_content', [ $this, 'render_content' ], 20, 3 );
        add_filter( 'bricks/frontend/render_data', [ $this, 'render_content' ], 20, 2 );
    }

    // ── Register tags so they appear in the Bricks dynamic data picker ────────

    public function register_tags( $tags ) {
        $group = 'FCC Menu';

        $tags[] = [
            'name'  => 'fcc_item_row',
            'label' => 'Item Row (Name + Price)',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_description',
            'label' => 'Item Description',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_price_1',
            'label' => 'Item Price 1',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_price_2',
            'label' => 'Item Price 2',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_size_1',
            'label' => 'Item Size 1',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_size_2',
            'label' => 'Item Size 2',
            'group' => $group,
        ];
        $tags[] = [
            'name'  => 'fcc_item_is_happy_hour',
            'label' => 'Item Is Happy Hour',
            'group' => $group,
        ];

        return $tags;
    }

    // ── Render a tag when Bricks encounters it ────────────────────────────────

    public function render_tag( $tag, $post, $context = 'text' ) {
        $post_id = isset( $post->ID ) ? $post->ID : get_the_ID();
        if ( ! $post_id ) return $tag;

        switch ( $tag ) {
            case 'fcc_item_row':
                return $this->get_item_row( $post_id );

            case 'fcc_item_description':
                return esc_html( get_post_meta( $post_id, '_fcc_description', true ) );

            case 'fcc_item_price_1':
                $price = get_post_meta( $post_id, '_fcc_price_option_1', true );
                return $price !== '' ? '$' . number_format( (float) $price, 2 ) : '';

            case 'fcc_item_price_2':
                $price = get_post_meta( $post_id, '_fcc_price_option_2', true );
                return $price !== '' ? '$' . number_format( (float) $price, 2 ) : '';

            case 'fcc_item_size_1':
                return esc_html( get_post_meta( $post_id, '_fcc_size_option_1', true ) );

            case 'fcc_item_size_2':
                return esc_html( get_post_meta( $post_id, '_fcc_size_option_2', true ) );

            case 'fcc_item_is_happy_hour':
                return get_post_meta( $post_id, '_fcc_is_happy_hour', true ) ? '1' : '';
        }

        return $tag;
    }

    // ── Parse tags within content strings (e.g. Rich Text elements) ──────────

    public function render_content( $content, $post, $context = 'text' ) {
        $post_id = isset( $post->ID ) ? $post->ID : get_the_ID();
        if ( ! $post_id ) return $content;

        $tags = [
            'fcc_item_row',
            'fcc_item_description',
            'fcc_item_price_1',
            'fcc_item_price_2',
            'fcc_item_size_1',
            'fcc_item_size_2',
            'fcc_item_is_happy_hour',
        ];

        foreach ( $tags as $tag ) {
            if ( strpos( $content, '{' . $tag . '}' ) !== false ) {
                $value   = $this->render_tag( $tag, $post, $context );
                $content = str_replace( '{' . $tag . '}', $value, $content );
            }
        }

        return $content;
    }

    // ── Build the name + dotted leader + price row HTML ───────────────────────

    private function get_item_row( $post_id ) {
        $title      = get_the_title( $post_id );
        $size1      = get_post_meta( $post_id, '_fcc_size_option_1', true );
        $price1     = get_post_meta( $post_id, '_fcc_price_option_1', true );
        $size2      = get_post_meta( $post_id, '_fcc_size_option_2', true );
        $price2     = get_post_meta( $post_id, '_fcc_price_option_2', true );
        $is_happy   = get_post_meta( $post_id, '_fcc_is_happy_hour', true );

        $has_price1 = $price1 !== '' && $price1 !== false;
        $has_price2 = $price2 !== '' && $price2 !== false;
        $has_pricing = $size1 || $has_price1 || $size2 || $has_price2;

        ob_start();
        ?>
        <div class="fcc-item-header">
            <span class="fcc-item-title">
                <?php echo esc_html( $title ); ?>
                <?php if ( $is_happy ) : ?>
                    <span class="fcc-badge-happy">Happy Hour</span>
                <?php endif; ?>
            </span>

            <?php if ( $has_pricing ) : ?>
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
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
