<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FCC GitHub Updater
 *
 * Hooks into WordPress's native update system to check a GitHub repository
 * for new releases. When a release zip is found with a higher version number,
 * WordPress will show the standard "Update Available" notice and allow
 * one-click updating from the Plugins admin screen.
 *
 * Usage (in main plugin file):
 *   new FCC_GitHub_Updater( __FILE__, 'your-github-username', 'your-repo-name' );
 *
 * Release workflow:
 *   1. Bump the Version header in the main plugin file.
 *   2. Push to GitHub.
 *   3. Create a GitHub Release tagged v{version} (e.g. v1.4.0).
 *   4. Attach the plugin .zip as a release asset.
 *   5. WordPress will detect the update within 12 hours (or force-check via Plugins → Check Again).
 */
class FCC_GitHub_Updater {

    private string $plugin_file;
    private string $plugin_slug;
    private string $github_user;
    private string $github_repo;
    private string $current_version;
    private string $api_url;
    private string $cache_key;

    public function __construct( string $plugin_file, string $github_user, string $github_repo ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->current_version = $this->get_plugin_version();
        $this->api_url         = "https://api.github.com/repos/{$github_user}/{$github_repo}/releases/latest";
        $this->cache_key       = 'fcc_gh_update_' . md5( $this->plugin_slug );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
    }

    /* ── Fetch latest release data from GitHub (cached 12h) ─── */

    private function get_release_data(): ?object {
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) return $cached;

        $response = wp_remote_get( $this->api_url, [
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $data->tag_name ) ) return null;

        set_transient( $this->cache_key, $data, 12 * HOUR_IN_SECONDS );
        return $data;
    }

    private function get_plugin_version(): string {
        $data = get_file_data( $this->plugin_file, [ 'Version' => 'Version' ] );
        return $data['Version'] ?? '0.0.0';
    }

    /* ── Resolve the download URL from release assets or zipball ── */

    private function get_download_url( object $release ): string {
        // Prefer an explicitly attached .zip asset
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( $asset->name, '.zip' ) ) {
                    return $asset->browser_download_url;
                }
            }
        }
        // Fall back to GitHub's auto-generated source zip
        return $release->zipball_url ?? '';
    }

    /* ── Tell WordPress there is an update available ─────────── */

    public function check_for_update( object $transient ): object {
        if ( empty( $transient->checked ) ) return $transient;

        // Clear our cache when WordPress forces a fresh check
        if ( isset( $_GET['force-check'] ) ) {
            delete_transient( $this->cache_key );
        }

        $release = $this->get_release_data();
        if ( ! $release ) return $transient;

        $remote_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'     => $this->get_download_url( $release ),
                'tested'      => get_bloginfo( 'version' ),
            ];
        }

        return $transient;
    }

    /* ── Populate the "View version details" lightbox ────────── */

    public function plugin_info( $result, string $action, object $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $result;

        $release = $this->get_release_data();
        if ( ! $release ) return $result;

        $remote_version = ltrim( $release->tag_name, 'v' );

        return (object) [
            'name'          => $this->github_repo,
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $remote_version,
            'author'        => '<a href="https://lougriffith.com">Lou Griffith</a>',
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'download_link' => $this->get_download_url( $release ),
            'sections'      => [
                'description' => $release->body ?? 'See GitHub for release notes.',
                'changelog'   => $release->body ?? '',
            ],
            'last_updated'  => $release->published_at ?? '',
            'tested'        => get_bloginfo( 'version' ),
        ];
    }

    /* ── Rename extracted folder to match the plugin slug ────── */

    public function after_install( $response, array $hook_extra, array $result ): array {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $result;
        }

        global $wp_filesystem;
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );

        $wp_filesystem->move( $result['destination'], $plugin_dir );
        $result['destination'] = $plugin_dir;

        // Re-activate the plugin if it was active before the update
        if ( is_plugin_active( $this->plugin_slug ) ) {
            activate_plugin( $this->plugin_slug );
        }

        return $result;
    }
}
