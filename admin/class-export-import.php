<?php
/**
 * FCC Cafe Menu — Export / Import
 *
 * Adds a Cafe Menu → Export / Import admin page.
 * Export: downloads all fcc_menu posts as a JSON file.
 * Import: uploads a JSON file; prompts per-item when a duplicate name is found.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Export_Import {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',  [ $this, 'register_page' ] );
        add_action( 'admin_init',  [ $this, 'handle_export' ] );
        add_action( 'admin_init',  [ $this, 'handle_import' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    // ── Admin page ────────────────────────────────────────────────────────────

    public function register_page() {
        add_submenu_page(
            'edit.php?post_type=fcc_menu',
            'Export / Import',
            'Export / Import',
            'manage_options',
            'fcc-menu-export-import',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'fcc-menu-export-import' ) === false ) return;
        wp_enqueue_style( 'fcc-menu-export-import', FCC_MENU_URL . 'assets/export-import.css', [], FCC_MENU_VERSION );
    }

    public function render_page() {
        $import_results = get_transient( 'fcc_import_results' );
        if ( $import_results ) {
            delete_transient( 'fcc_import_results' );
        }

        // Pending duplicates waiting for user decision
        $pending = get_transient( 'fcc_import_pending_' . get_current_user_id() );
        ?>
        <div class="wrap fcc-export-import">
            <h1>Export / Import Menu Items</h1>

            <?php if ( $import_results ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong>Import complete.</strong>
                        Created: <?php echo intval( $import_results['created'] ); ?> &nbsp;|&nbsp;
                        Updated: <?php echo intval( $import_results['updated'] ); ?> &nbsp;|&nbsp;
                        Skipped: <?php echo intval( $import_results['skipped'] ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $pending ) : ?>
                <?php $this->render_duplicate_review( $pending ); ?>
            <?php else : ?>

                <!-- ── Export ─────────────────────────────────────────────── -->
                <div class="fcc-card">
                    <h2>Export</h2>
                    <p>Download all menu items as a JSON file. Includes all meta fields and taxonomy assignments.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'fcc_menu_export', 'fcc_export_nonce' ); ?>
                        <input type="hidden" name="fcc_action" value="export" />
                        <?php submit_button( 'Download JSON', 'primary', 'submit', false ); ?>
                    </form>
                </div>

                <!-- ── Import ─────────────────────────────────────────────── -->
                <div class="fcc-card">
                    <h2>Import</h2>
                    <p>Upload a JSON file previously exported from this plugin. You will be asked what to do with any duplicate item names.</p>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'fcc_menu_import', 'fcc_import_nonce' ); ?>
                        <input type="hidden" name="fcc_action" value="import" />
                        <table class="form-table">
                            <tr>
                                <th><label for="fcc_import_file">JSON File</label></th>
                                <td><input type="file" name="fcc_import_file" id="fcc_import_file" accept=".json" required /></td>
                            </tr>
                        </table>
                        <?php submit_button( 'Upload & Review', 'primary', 'submit', false ); ?>
                    </form>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }

    private function render_duplicate_review( $pending ) {
        $total     = count( $pending['items'] );
        $current   = $pending['current_index'];
        $item      = $pending['items'][ $current ];
        $remaining = $total - $current - 1;
        ?>
        <div class="fcc-card fcc-duplicate-review">
            <h2>Duplicate Found — Item <?php echo ( $current + 1 ); ?> of <?php echo $total; ?></h2>
            <p>The item <strong><?php echo esc_html( $item['post_title'] ); ?></strong> already exists. What would you like to do?</p>

            <div class="fcc-diff">
                <div class="fcc-diff-col">
                    <h3>Existing</h3>
                    <?php $this->render_item_preview( $this->get_existing_item( $item['post_title'] ) ); ?>
                </div>
                <div class="fcc-diff-col">
                    <h3>Incoming</h3>
                    <?php $this->render_item_preview( $item ); ?>
                </div>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'fcc_menu_import_decision', 'fcc_decision_nonce' ); ?>
                <input type="hidden" name="fcc_action" value="import_decision" />
                <div class="fcc-decision-buttons">
                    <button type="submit" name="fcc_decision" value="update" class="button button-primary">Update Existing</button>
                    <button type="submit" name="fcc_decision" value="skip" class="button">Skip This Item</button>
                    <?php if ( $remaining > 0 ) : ?>
                        <button type="submit" name="fcc_decision" value="update_all" class="button button-secondary">Update All Remaining</button>
                        <button type="submit" name="fcc_decision" value="skip_all" class="button button-secondary">Skip All Remaining</button>
                    <?php endif; ?>
                </div>
                <?php if ( $remaining > 0 ) : ?>
                    <p class="description"><?php echo $remaining; ?> more item(s) to review after this.</p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    private function render_item_preview( $item ) {
        if ( ! $item ) { echo '<p><em>Not found.</em></p>'; return; }
        $meta = is_array( $item ) ? $item : [];
        if ( is_object( $item ) ) {
            // Existing WP post — pull meta
            $id   = $item->ID;
            $meta = [
                'post_title'           => $item->post_title,
                '_fcc_description'     => get_post_meta( $id, '_fcc_description', true ),
                '_fcc_size_option_1'   => get_post_meta( $id, '_fcc_size_option_1', true ),
                '_fcc_price_option_1'  => get_post_meta( $id, '_fcc_price_option_1', true ),
                '_fcc_size_option_2'   => get_post_meta( $id, '_fcc_size_option_2', true ),
                '_fcc_price_option_2'  => get_post_meta( $id, '_fcc_price_option_2', true ),
                '_fcc_is_happy_hour'   => get_post_meta( $id, '_fcc_is_happy_hour', true ),
                '_fcc_allergen_info'   => get_post_meta( $id, '_fcc_allergen_info', true ),
                'fcc_menu_category'    => implode( ', ', wp_get_post_terms( $id, 'fcc_menu_category', [ 'fields' => 'names' ] ) ),
                'fcc_menu_name'        => implode( ', ', wp_get_post_terms( $id, 'fcc_menu_name',     [ 'fields' => 'names' ] ) ),
            ];
        }

        echo '<table class="fcc-preview-table">';
        $fields = [
            'post_title'          => 'Name',
            '_fcc_description'    => 'Description',
            '_fcc_size_option_1'  => 'Size 1',
            '_fcc_price_option_1' => 'Price 1',
            '_fcc_size_option_2'  => 'Size 2',
            '_fcc_price_option_2' => 'Price 2',
            '_fcc_is_happy_hour'  => 'Happy Hour',
            '_fcc_allergen_info'  => 'Allergens',
            'fcc_menu_category'   => 'Category',
            'fcc_menu_name'       => 'Menu',
        ];
        foreach ( $fields as $key => $label ) {
            $val = isset( $meta[ $key ] ) ? $meta[ $key ] : '';
            if ( $key === '_fcc_is_happy_hour' ) $val = $val ? 'Yes' : 'No';
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
        }
        echo '</table>';
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function handle_export() {
        if ( ! isset( $_POST['fcc_action'] ) || $_POST['fcc_action'] !== 'export' ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        if ( ! wp_verify_nonce( $_POST['fcc_export_nonce'] ?? '', 'fcc_menu_export' ) ) wp_die( 'Invalid nonce.' );

        $posts = get_posts( [
            'post_type'      => 'fcc_menu',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $export = [
            'version'    => FCC_MENU_VERSION,
            'exported_at' => current_time( 'c' ),
            'items'      => [],
        ];

        foreach ( $posts as $post ) {
            $export['items'][] = [
                'post_title'          => $post->post_title,
                'post_status'         => $post->post_status,
                '_fcc_description'    => get_post_meta( $post->ID, '_fcc_description',    true ),
                '_fcc_size_option_1'  => get_post_meta( $post->ID, '_fcc_size_option_1',  true ),
                '_fcc_price_option_1' => get_post_meta( $post->ID, '_fcc_price_option_1', true ),
                '_fcc_size_option_2'  => get_post_meta( $post->ID, '_fcc_size_option_2',  true ),
                '_fcc_price_option_2' => get_post_meta( $post->ID, '_fcc_price_option_2', true ),
                '_fcc_is_happy_hour'  => get_post_meta( $post->ID, '_fcc_is_happy_hour',  true ),
                '_fcc_allergen_info'  => get_post_meta( $post->ID, '_fcc_allergen_info',  true ),
                'fcc_menu_category'   => wp_get_post_terms( $post->ID, 'fcc_menu_category', [ 'fields' => 'names' ] ),
                'fcc_menu_name'       => wp_get_post_terms( $post->ID, 'fcc_menu_name',     [ 'fields' => 'names' ] ),
            ];
        }

        $filename = 'fcc-menu-export-' . date( 'Y-m-d' ) . '.json';
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    // ── Import — Step 1: parse file, handle non-duplicates, queue duplicates ──

    public function handle_import() {
        if ( ! isset( $_POST['fcc_action'] ) ) return;

        if ( $_POST['fcc_action'] === 'import' ) {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
            if ( ! wp_verify_nonce( $_POST['fcc_import_nonce'] ?? '', 'fcc_menu_import' ) ) wp_die( 'Invalid nonce.' );

            if ( empty( $_FILES['fcc_import_file']['tmp_name'] ) ) {
                wp_die( 'No file uploaded.' );
            }

            $json = file_get_contents( $_FILES['fcc_import_file']['tmp_name'] );
            $data = json_decode( $json, true );

            if ( ! $data || empty( $data['items'] ) ) {
                wp_die( 'Invalid JSON file or no items found.' );
            }

            $results   = [ 'created' => 0, 'updated' => 0, 'skipped' => 0 ];
            $duplicates = [];

            foreach ( $data['items'] as $item ) {
                $existing = $this->get_existing_item( $item['post_title'] );
                if ( $existing ) {
                    $duplicates[] = $item;
                } else {
                    $this->create_item( $item );
                    $results['created']++;
                }
            }

            if ( ! empty( $duplicates ) ) {
                set_transient( 'fcc_import_pending_' . get_current_user_id(), [
                    'items'          => $duplicates,
                    'current_index'  => 0,
                    'results'        => $results,
                    'bulk_decision'  => null,
                ], HOUR_IN_SECONDS );
                wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-export-import' ) );
                exit;
            }

            set_transient( 'fcc_import_results', $results, 30 );
            wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-export-import' ) );
            exit;
        }

        // ── Step 2: handle per-duplicate decision ─────────────────────────────
        if ( $_POST['fcc_action'] === 'import_decision' ) {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
            if ( ! wp_verify_nonce( $_POST['fcc_decision_nonce'] ?? '', 'fcc_menu_import_decision' ) ) wp_die( 'Invalid nonce.' );

            $decision = sanitize_text_field( $_POST['fcc_decision'] ?? 'skip' );
            $pending  = get_transient( 'fcc_import_pending_' . get_current_user_id() );

            if ( ! $pending ) {
                wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-export-import' ) );
                exit;
            }

            $current = $pending['current_index'];
            $item    = $pending['items'][ $current ];
            $results = $pending['results'];

            // Bulk decisions — process all remaining
            if ( in_array( $decision, [ 'update_all', 'skip_all' ], true ) ) {
                $do_update = $decision === 'update_all';
                for ( $i = $current; $i < count( $pending['items'] ); $i++ ) {
                    if ( $do_update ) {
                        $this->update_item( $pending['items'][ $i ] );
                        $results['updated']++;
                    } else {
                        $results['skipped']++;
                    }
                }
                delete_transient( 'fcc_import_pending_' . get_current_user_id() );
                set_transient( 'fcc_import_results', $results, 30 );
                wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-export-import' ) );
                exit;
            }

            // Single decision
            if ( $decision === 'update' ) {
                $this->update_item( $item );
                $results['updated']++;
            } else {
                $results['skipped']++;
            }

            $next = $current + 1;

            if ( $next >= count( $pending['items'] ) ) {
                // All done
                delete_transient( 'fcc_import_pending_' . get_current_user_id() );
                set_transient( 'fcc_import_results', $results, 30 );
            } else {
                // Next duplicate
                set_transient( 'fcc_import_pending_' . get_current_user_id(), [
                    'items'         => $pending['items'],
                    'current_index' => $next,
                    'results'       => $results,
                    'bulk_decision' => null,
                ], HOUR_IN_SECONDS );
            }

            wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-export-import' ) );
            exit;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function get_existing_item( $title ) {
        $existing = get_posts( [
            'post_type'   => 'fcc_menu',
            'post_status' => 'any',
            'title'       => $title,
            'numberposts' => 1,
        ] );
        return ! empty( $existing ) ? $existing[0] : null;
    }

    private function write_meta( $post_id, $item ) {
        $meta_keys = [
            '_fcc_description',
            '_fcc_size_option_1',
            '_fcc_price_option_1',
            '_fcc_size_option_2',
            '_fcc_price_option_2',
            '_fcc_is_happy_hour',
            '_fcc_allergen_info',
        ];
        foreach ( $meta_keys as $key ) {
            if ( isset( $item[ $key ] ) ) {
                update_post_meta( $post_id, $key, $item[ $key ] );
            }
        }

        // Taxonomies — get or create terms
        foreach ( [ 'fcc_menu_category', 'fcc_menu_name' ] as $taxonomy ) {
            if ( ! empty( $item[ $taxonomy ] ) ) {
                $term_ids = [];
                foreach ( (array) $item[ $taxonomy ] as $term_name ) {
                    $term = term_exists( $term_name, $taxonomy );
                    if ( ! $term ) {
                        $term = wp_insert_term( $term_name, $taxonomy );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
                    }
                }
                wp_set_object_terms( $post_id, $term_ids, $taxonomy );
            }
        }
    }

    private function create_item( $item ) {
        $post_id = wp_insert_post( [
            'post_title'  => sanitize_text_field( $item['post_title'] ),
            'post_type'   => 'fcc_menu',
            'post_status' => $item['post_status'] ?? 'publish',
        ] );
        if ( ! is_wp_error( $post_id ) ) {
            $this->write_meta( $post_id, $item );
        }
    }

    private function update_item( $item ) {
        $existing = $this->get_existing_item( $item['post_title'] );
        if ( $existing ) {
            $this->write_meta( $existing->ID, $item );
        }
    }
}
