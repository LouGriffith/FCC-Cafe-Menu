<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Meta_Boxes {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register' ] );
        add_action( 'save_post_fcc_menu', [ $this, 'save' ] );
    }

    public function register() {
        add_meta_box(
            'fcc_menu_details',
            'Menu Item Details',
            [ $this, 'render' ],
            'fcc_menu',
            'normal',
            'high'
        );
    }

    public function render( $post ) {
        wp_nonce_field( 'fcc_menu_save', 'fcc_menu_nonce' );

        $description  = get_post_meta( $post->ID, '_fcc_description', true );
        $size1        = get_post_meta( $post->ID, '_fcc_size_option_1', true );
        $price1       = get_post_meta( $post->ID, '_fcc_price_option_1', true );
        $size2        = get_post_meta( $post->ID, '_fcc_size_option_2', true );
        $price2       = get_post_meta( $post->ID, '_fcc_price_option_2', true );
        $is_happy_hour = get_post_meta( $post->ID, '_fcc_is_happy_hour', true );
        $allergen_info = get_post_meta( $post->ID, '_fcc_allergen_info', true );
        ?>
        <style>
            .fcc-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .fcc-meta-field { margin-bottom: 16px; }
            .fcc-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; }
            .fcc-meta-field input[type="text"],
            .fcc-meta-field input[type="number"],
            .fcc-meta-field textarea { width: 100%; }
            .fcc-meta-section { border: 1px solid #ddd; border-radius: 4px; padding: 16px; margin-bottom: 16px; }
            .fcc-meta-section h4 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; color: #666; letter-spacing: .5px; }
            .fcc-price-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        </style>

        <div class="fcc-meta-field">
            <label for="fcc_description">Description</label>
            <textarea id="fcc_description" name="fcc_description" rows="3" placeholder="Tell us about this menu item"><?php echo esc_textarea( $description ); ?></textarea>
        </div>

        <div class="fcc-meta-section">
            <h4>Size &amp; Price Options</h4>
            <div class="fcc-price-row" style="margin-bottom:12px;">
                <div class="fcc-meta-field">
                    <label for="fcc_size_option_1">Size — Option 1</label>
                    <input type="text" id="fcc_size_option_1" name="fcc_size_option_1" value="<?php echo esc_attr( $size1 ); ?>" placeholder="e.g. 16oz" />
                </div>
                <div class="fcc-meta-field">
                    <label for="fcc_price_option_1">Price — Option 1</label>
                    <input type="number" id="fcc_price_option_1" name="fcc_price_option_1" value="<?php echo esc_attr( $price1 ); ?>" step="0.01" min="0" placeholder="0.00" />
                </div>
            </div>
            <div class="fcc-price-row">
                <div class="fcc-meta-field">
                    <label for="fcc_size_option_2">Size — Option 2</label>
                    <input type="text" id="fcc_size_option_2" name="fcc_size_option_2" value="<?php echo esc_attr( $size2 ); ?>" placeholder="e.g. 24oz" />
                </div>
                <div class="fcc-meta-field">
                    <label for="fcc_price_option_2">Price — Option 2</label>
                    <input type="number" id="fcc_price_option_2" name="fcc_price_option_2" value="<?php echo esc_attr( $price2 ); ?>" step="0.01" min="0" placeholder="0.00" />
                </div>
            </div>
        </div>

        <div class="fcc-meta-grid">
            <div class="fcc-meta-field">
                <label>
                    <input type="checkbox" name="fcc_is_happy_hour" value="1" <?php checked( $is_happy_hour, '1' ); ?> />
                    Happy Hour Item
                </label>
            </div>
        </div>

        <div class="fcc-meta-field">
            <label for="fcc_allergen_info">Allergen Info</label>
            <textarea id="fcc_allergen_info" name="fcc_allergen_info" rows="3" placeholder="List any allergens..."><?php echo esc_textarea( $allergen_info ); ?></textarea>
        </div>
        <?php
    }

    public function save( $post_id ) {
        if ( ! isset( $_POST['fcc_menu_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['fcc_menu_nonce'], 'fcc_menu_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            'fcc_description'    => '_fcc_description',
            'fcc_size_option_1'  => '_fcc_size_option_1',
            'fcc_price_option_1' => '_fcc_price_option_1',
            'fcc_size_option_2'  => '_fcc_size_option_2',
            'fcc_price_option_2' => '_fcc_price_option_2',
            'fcc_allergen_info'  => '_fcc_allergen_info',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        // Textarea fields - sanitize differently
        $textarea_fields = [
            'fcc_description'  => '_fcc_description',
            'fcc_allergen_info' => '_fcc_allergen_info',
        ];
        foreach ( $textarea_fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $_POST[ $post_key ] ) );
            }
        }

        // Checkbox
        $happy_hour = isset( $_POST['fcc_is_happy_hour'] ) ? '1' : '0';
        update_post_meta( $post_id, '_fcc_is_happy_hour', $happy_hour );
    }
}
