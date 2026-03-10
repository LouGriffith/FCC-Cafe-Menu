<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Quick_Edit {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'quick_edit_custom_box', [ $this, 'render_fields' ], 10, 2 );
        add_action( 'save_post_fcc_menu', [ $this, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'edit.php' ) return;

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'fcc_menu' ) return;

        wp_enqueue_script(
            'fcc-menu-quick-edit',
            FCC_MENU_URL . 'assets/quick-edit.js',
            [ 'jquery', 'inline-edit-post' ],
            FCC_MENU_VERSION,
            true
        );
    }

    public function render_fields( $column_name, $post_type ) {
        if ( $post_type !== 'fcc_menu' || $column_name !== 'fcc_price' ) return;
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <span class="title">Quick Prices</span>
                <?php wp_nonce_field( 'fcc_menu_quick_edit', 'fcc_quick_edit_nonce' ); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
                    <input type="text" name="fcc_size_option_1" placeholder="Size 1" style="width:100%;" />
                    <input type="number" name="fcc_price_option_1" placeholder="Price 1" step="0.01" style="width:100%;" />
                    <input type="text" name="fcc_size_option_2" placeholder="Size 2" style="width:100%;" />
                    <input type="number" name="fcc_price_option_2" placeholder="Price 2" step="0.01" style="width:100%;" />
                </div>
            </div>
        </fieldset>
        <?php
    }

    public function save( $post_id ) {
        // Only run on quick edit (not full save handled by meta boxes)
        if ( ! isset( $_POST['fcc_quick_edit_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['fcc_quick_edit_nonce'], 'fcc_menu_quick_edit' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            'fcc_size_option_1'  => '_fcc_size_option_1',
            'fcc_price_option_1' => '_fcc_price_option_1',
            'fcc_size_option_2'  => '_fcc_size_option_2',
            'fcc_price_option_2' => '_fcc_price_option_2',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( array_key_exists( $post_key, $_POST ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }
    }
}
