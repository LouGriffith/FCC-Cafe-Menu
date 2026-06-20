<?php
/**
 * FCC Cafe Menu — Google Sheets Sync
 *
 * Syncs menu items from a Google Sheet into WordPress on an hourly cron schedule.
 * The Sheet is treated as the source of truth:
 *   - New rows  → new fcc_menu posts created
 *   - Matching rows (case-insensitive name match) → existing posts updated
 *   - Rows removed from the Sheet → matching posts moved to trash
 *
 * Requires:
 *   - A Google Cloud service account with Sheets API enabled
 *   - The service account's JSON key file stored OUTSIDE the web root,
 *     or in a directory protected by .htaccess (Deny from all)
 *   - The target Sheet shared with the service account's client_email as Viewer
 *
 * Sheet column layout (row 1 = headers, exact names):
 *   name | category | description | size_1 | price_1 | size_2 | price_2 | is_happy_hour | allergen_info
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FCC_Menu_Sheets_Sync {

    private static $instance = null;

    const CRON_HOOK = 'fcc_menu_sheets_sync_cron';
    const OPTION_KEY = 'fcc_menu_sheets_sync_settings';
    const LOG_OPTION = 'fcc_menu_sheets_sync_log';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',           [ $this, 'register_page' ] );
        add_action( 'admin_init',           [ $this, 'handle_settings_save' ] );
        add_action( 'admin_init',           [ $this, 'handle_manual_sync' ] );
        add_action( self::CRON_HOOK,        [ $this, 'run_sync' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        // Register/clear the cron schedule based on whether sync is enabled
        add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
    }

    public function enqueue_styles( $hook ) {
        if ( strpos( $hook, 'fcc-menu-sheets-sync' ) === false ) return;
        wp_enqueue_style( 'fcc-menu-export-import', FCC_MENU_URL . 'assets/export-import.css', [], FCC_MENU_VERSION );
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    private function get_settings() {
        $defaults = [
            'enabled'          => false,
            'sheet_id'         => '',
            'sheet_range'      => 'Sheet1',
            'credentials_path' => WP_CONTENT_DIR . '/fcc-credentials/google-service-account.json',
        ];
        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $saved, $defaults );
    }

    private function save_settings( $settings ) {
        update_option( self::OPTION_KEY, $settings );
    }

    public function maybe_schedule_cron() {
        $settings = $this->get_settings();
        $is_scheduled = wp_next_scheduled( self::CRON_HOOK );

        if ( $settings['enabled'] && ! $is_scheduled ) {
            wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
        } elseif ( ! $settings['enabled'] && $is_scheduled ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
        }
    }

    // ── Admin page ───────────────────────────────────────────────────────────

    public function register_page() {
        add_submenu_page(
            'edit.php?post_type=fcc_menu',
            'Google Sheets Sync',
            'Sheets Sync',
            'manage_options',
            'fcc-menu-sheets-sync',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        $settings   = $this->get_settings();
        $log        = get_option( self::LOG_OPTION, [] );
        $next_run   = wp_next_scheduled( self::CRON_HOOK );
        $manual_msg = isset( $_GET['fcc_sync_done'] ) ? sanitize_text_field( $_GET['fcc_sync_done'] ) : '';
        ?>
        <div class="wrap fcc-export-import">
            <h1>Google Sheets Sync</h1>

            <?php if ( $manual_msg === '1' && ! empty( $log ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Sync complete.</strong> See results below.</p>
                </div>
            <?php elseif ( $manual_msg === 'error' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Sync failed.</strong> See the log below for details.</p>
                </div>
            <?php endif; ?>

            <div class="fcc-card">
                <h2>Settings</h2>
                <form method="post">
                    <?php wp_nonce_field( 'fcc_sheets_sync_settings', 'fcc_sheets_settings_nonce' ); ?>
                    <input type="hidden" name="fcc_action" value="save_settings" />
                    <table class="form-table">
                        <tr>
                            <th><label for="fcc_enabled">Enable Hourly Sync</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="fcc_enabled" id="fcc_enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
                                    Automatically sync from Google Sheets every hour
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fcc_sheet_id">Sheet ID</label></th>
                            <td>
                                <input type="text" name="fcc_sheet_id" id="fcc_sheet_id" class="regular-text" value="<?php echo esc_attr( $settings['sheet_id'] ); ?>" placeholder="1W34-GoJ0KYolVLPRsMp69zQVPeJ0jIRz6SVLoniUYyw" />
                                <p class="description">The long ID found in the Sheet's URL between <code>/d/</code> and <code>/edit</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fcc_sheet_range">Sheet/Tab Name</label></th>
                            <td>
                                <input type="text" name="fcc_sheet_range" id="fcc_sheet_range" class="regular-text" value="<?php echo esc_attr( $settings['sheet_range'] ); ?>" placeholder="Sheet1" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fcc_credentials_path">Credentials File Path</label></th>
                            <td>
                                <input type="text" name="fcc_credentials_path" id="fcc_credentials_path" class="regular-text" value="<?php echo esc_attr( $settings['credentials_path'] ); ?>" />
                                <p class="description">Full server path to the service account JSON file. Must be outside the public web root or protected by .htaccess.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save Settings' ); ?>
                </form>
            </div>

            <div class="fcc-card">
                <h2>Status</h2>
                <table class="fcc-preview-table">
                    <tr><th>Sync Enabled</th><td><?php echo $settings['enabled'] ? '✅ Yes' : '❌ No'; ?></td></tr>
                    <tr><th>Next Scheduled Run</th><td><?php echo $next_run ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), 'M j, Y g:i A' ) ) : 'Not scheduled'; ?></td></tr>
                    <tr><th>Credentials File</th><td><?php echo file_exists( $settings['credentials_path'] ) ? '✅ Found' : '❌ Not found at this path'; ?></td></tr>
                </table>

                <form method="post" style="margin-top:16px;">
                    <?php wp_nonce_field( 'fcc_sheets_manual_sync', 'fcc_manual_sync_nonce' ); ?>
                    <input type="hidden" name="fcc_action" value="manual_sync" />
                    <?php submit_button( 'Sync Now', 'primary', 'submit', false ); ?>
                </form>
            </div>

            <?php if ( ! empty( $log ) ) : ?>
                <div class="fcc-card">
                    <h2>Last Sync Log</h2>
                    <p>
                        Run at <?php echo esc_html( $log['timestamp'] ?? '—' ); ?> &nbsp;|&nbsp;
                        Created: <?php echo intval( $log['created'] ?? 0 ); ?> &nbsp;|&nbsp;
                        Updated: <?php echo intval( $log['updated'] ?? 0 ); ?> &nbsp;|&nbsp;
                        Trashed: <?php echo intval( $log['trashed'] ?? 0 ); ?> &nbsp;|&nbsp;
                        Errors: <?php echo intval( $log['errors'] ?? 0 ); ?>
                    </p>
                    <?php if ( ! empty( $log['messages'] ) ) : ?>
                        <pre style="background:#f1f1f1;padding:12px;max-height:300px;overflow:auto;"><?php echo esc_html( implode( "\n", $log['messages'] ) ); ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    public function handle_settings_save() {
        if ( ! isset( $_POST['fcc_action'] ) || $_POST['fcc_action'] !== 'save_settings' ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        if ( ! wp_verify_nonce( $_POST['fcc_sheets_settings_nonce'] ?? '', 'fcc_sheets_sync_settings' ) ) wp_die( 'Invalid nonce.' );

        $settings = [
            'enabled'          => isset( $_POST['fcc_enabled'] ),
            'sheet_id'         => sanitize_text_field( $_POST['fcc_sheet_id'] ?? '' ),
            'sheet_range'      => sanitize_text_field( $_POST['fcc_sheet_range'] ?? 'Sheet1' ),
            'credentials_path' => sanitize_text_field( $_POST['fcc_credentials_path'] ?? '' ),
        ];

        $this->save_settings( $settings );
        $this->maybe_schedule_cron();

        wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-sheets-sync&saved=1' ) );
        exit;
    }

    public function handle_manual_sync() {
        if ( ! isset( $_POST['fcc_action'] ) || $_POST['fcc_action'] !== 'manual_sync' ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        if ( ! wp_verify_nonce( $_POST['fcc_manual_sync_nonce'] ?? '', 'fcc_sheets_manual_sync' ) ) wp_die( 'Invalid nonce.' );

        $result = $this->run_sync();

        wp_redirect( admin_url( 'edit.php?post_type=fcc_menu&page=fcc-menu-sheets-sync&fcc_sync_done=' . ( $result ? '1' : 'error' ) ) );
        exit;
    }

    // ── Core sync logic ──────────────────────────────────────────────────────

    public function run_sync() {
        $settings = $this->get_settings();
        $messages = [];
        $created = $updated = $trashed = $errors = 0;

        if ( empty( $settings['sheet_id'] ) ) {
            $this->write_log( [ 'timestamp' => current_time( 'mysql' ), 'errors' => 1, 'messages' => [ 'No Sheet ID configured.' ] ] );
            return false;
        }

        if ( ! file_exists( $settings['credentials_path'] ) ) {
            $this->write_log( [ 'timestamp' => current_time( 'mysql' ), 'errors' => 1, 'messages' => [ 'Credentials file not found at: ' . $settings['credentials_path'] ] ] );
            return false;
        }

        $access_token = $this->get_access_token( $settings['credentials_path'] );
        if ( is_wp_error( $access_token ) ) {
            $this->write_log( [ 'timestamp' => current_time( 'mysql' ), 'errors' => 1, 'messages' => [ 'Auth error: ' . $access_token->get_error_message() ] ] );
            return false;
        }

        $rows = $this->fetch_sheet_rows( $settings['sheet_id'], $settings['sheet_range'], $access_token );
        if ( is_wp_error( $rows ) ) {
            $this->write_log( [ 'timestamp' => current_time( 'mysql' ), 'errors' => 1, 'messages' => [ 'Sheet fetch error: ' . $rows->get_error_message() ] ] );
            return false;
        }

        if ( empty( $rows ) ) {
            $messages[] = 'Sheet returned no data rows.';
            $this->write_log( [ 'timestamp' => current_time( 'mysql' ), 'errors' => 0, 'messages' => $messages ] );
            return true;
        }

        // Build lookup of existing posts by lowercase title
        $existing_posts = get_posts( [
            'post_type'      => 'fcc_menu',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => -1,
        ] );
        $existing_by_title = [];
        foreach ( $existing_posts as $p ) {
            $existing_by_title[ strtolower( trim( $p->post_title ) ) ] = $p;
        }

        $seen_titles = [];

        foreach ( $rows as $i => $row ) {
            $name = trim( $row['name'] ?? '' );
            if ( $name === '' ) continue;

            $key = strtolower( $name );
            $seen_titles[ $key ] = true;

            $item = [
                'post_title'           => sanitize_text_field( $name ),
                '_fcc_description'     => sanitize_textarea_field( $row['description'] ?? '' ),
                '_fcc_size_option_1'   => sanitize_text_field( $row['size_1'] ?? '' ),
                '_fcc_price_option_1'  => $this->sanitize_price( $row['price_1'] ?? '' ),
                '_fcc_size_option_2'   => sanitize_text_field( $row['size_2'] ?? '' ),
                '_fcc_price_option_2'  => $this->sanitize_price( $row['price_2'] ?? '' ),
                '_fcc_is_happy_hour'   => $this->sanitize_bool( $row['is_happy_hour'] ?? '' ),
                '_fcc_allergen_info'   => sanitize_textarea_field( $row['allergen_info'] ?? '' ),
                'category'             => sanitize_text_field( $row['category'] ?? '' ),
            ];

            if ( isset( $existing_by_title[ $key ] ) ) {
                $this->update_post( $existing_by_title[ $key ]->ID, $item );
                $updated++;
                $messages[] = "Updated: {$name}";
            } else {
                $new_id = $this->create_post( $item );
                if ( $new_id ) {
                    $created++;
                    $messages[] = "Created: {$name}";
                } else {
                    $errors++;
                    $messages[] = "ERROR creating: {$name}";
                }
            }
        }

        // Trash posts that exist in WP but are no longer in the Sheet
        foreach ( $existing_by_title as $key => $post ) {
            if ( ! isset( $seen_titles[ $key ] ) ) {
                wp_trash_post( $post->ID );
                $trashed++;
                $messages[] = "Trashed (removed from sheet): {$post->post_title}";
            }
        }

        $this->write_log( [
            'timestamp' => current_time( 'mysql' ),
            'created'   => $created,
            'updated'   => $updated,
            'trashed'   => $trashed,
            'errors'    => $errors,
            'messages'  => $messages,
        ] );

        return true;
    }

    private function write_log( $log ) {
        update_option( self::LOG_OPTION, $log, false );
    }

    private function sanitize_price( $value ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return '';
        return number_format( (float) preg_replace( '/[^0-9.\-]/', '', $value ), 2, '.', '' );
    }

    private function sanitize_bool( $value ) {
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, [ '1', 'true', 'yes', 'y' ], true ) ? '1' : '0';
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

        if ( ! empty( $item['category'] ) ) {
            $term = term_exists( $item['category'], 'fcc_menu_category' );
            if ( ! $term ) {
                $term = wp_insert_term( $item['category'], 'fcc_menu_category' );
            }
            if ( ! is_wp_error( $term ) ) {
                $term_id = is_array( $term ) ? $term['term_id'] : $term;
                wp_set_object_terms( $post_id, (int) $term_id, 'fcc_menu_category' );
            }
        }
    }

    private function create_post( $item ) {
        $post_id = wp_insert_post( [
            'post_title'  => $item['post_title'],
            'post_type'   => 'fcc_menu',
            'post_status' => 'publish',
        ] );
        if ( is_wp_error( $post_id ) || ! $post_id ) return 0;
        $this->write_meta( $post_id, $item );
        return $post_id;
    }

    private function update_post( $post_id, $item ) {
        // Restore from trash if it was previously removed and reappeared in the sheet
        $post = get_post( $post_id );
        if ( $post && $post->post_status === 'trash' ) {
            wp_untrash_post( $post_id );
            wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        }
        $this->write_meta( $post_id, $item );
    }

    // ── Google Auth (JWT, no external libraries) ──────────────────────────────

    private function get_access_token( $credentials_path ) {
        $cached = get_transient( 'fcc_sheets_access_token' );
        if ( $cached ) return $cached;

        $json = file_get_contents( $credentials_path );
        $creds = json_decode( $json, true );

        if ( ! $creds || empty( $creds['private_key'] ) || empty( $creds['client_email'] ) ) {
            return new WP_Error( 'fcc_bad_credentials', 'Invalid credentials file format.' );
        }

        $now = time();
        $header = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
        $claims = [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $segments = [
            $this->base64url_encode( wp_json_encode( $header ) ),
            $this->base64url_encode( wp_json_encode( $claims ) ),
        ];

        $signing_input = implode( '.', $segments );

        $signature = '';
        $success = openssl_sign( $signing_input, $signature, $creds['private_key'], 'SHA256' );

        if ( ! $success ) {
            return new WP_Error( 'fcc_jwt_sign_failed', 'Failed to sign JWT — check that the private key is valid.' );
        }

        $segments[] = $this->base64url_encode( $signature );
        $jwt = implode( '.', $segments );

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            $err = $body['error_description'] ?? $body['error'] ?? 'Unknown error fetching access token.';
            return new WP_Error( 'fcc_token_failed', $err );
        }

        $token = $body['access_token'];
        $expires_in = isset( $body['expires_in'] ) ? intval( $body['expires_in'] ) : 3600;

        // Cache for slightly less than the token's actual lifetime
        set_transient( 'fcc_sheets_access_token', $token, max( 60, $expires_in - 120 ) );

        return $token;
    }

    private function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    // ── Sheets fetch ─────────────────────────────────────────────────────────

    private function fetch_sheet_rows( $sheet_id, $range, $access_token ) {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            rawurlencode( $sheet_id ),
            rawurlencode( $range )
        );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $err = $body['error']['message'] ?? 'HTTP ' . $code;
            return new WP_Error( 'fcc_sheets_fetch_failed', $err );
        }

        if ( empty( $body['values'] ) || count( $body['values'] ) < 2 ) {
            return [];
        }

        $values  = $body['values'];
        $headers = array_map( 'trim', array_map( 'strtolower', $values[0] ) );
        $rows    = [];

        for ( $i = 1; $i < count( $values ); $i++ ) {
            $row_values = $values[ $i ];
            $row = [];
            foreach ( $headers as $col_index => $header_name ) {
                $row[ $header_name ] = $row_values[ $col_index ] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
