<?php
/**
 * Plugin Name:       Wordpress Multi-site API
 * Tested up to:      6.7.2
 * Description:       A minimal plugin to allow multisite creating and deleting a multisite sandbox site with configurable settings via automator of your choice.
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.12
 * Author:            reallyusefulplugins.com
 * Author URI:        https://reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordpress-multisite-api
 * Website:           https://reallyusefulplugins.com
 * */


if (!defined('ABSPATH')) exit;

// Include additional security settings from enhanced.php if available
if (file_exists(plugin_dir_path(__FILE__) . 'enhanced.php')) {
    include_once plugin_dir_path(__FILE__) . 'enhanced.php';
}

// --- Network-only admin menu + settings save ---
function sandbox_admin_menu() {
    // Only show in Network Admin (main network dashboard), not subsites.
    if ( ! is_multisite() || ! is_network_admin() ) {
        return;
    }

    add_menu_page(
        'Sandbox Settings',
        'Sandbox Settings',
        'manage_network_options',
        'sandbox-settings',
        'sandbox_settings_page'
    );
}
add_action('network_admin_menu', 'sandbox_admin_menu');

function sandbox_settings_page() {
    if ( ! current_user_can('manage_network_options') ) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Defaults
    $defaults = [
        'sandbox_secret_key'            => 'default-secret-key',
        'sandbox_base_domain'           => 'wpdemo.uk',
        'disable_password_change_email' => 1,

        // Auto update delay defaults
        'sandbox_delay_core_days'       => 14, // major core only
        'sandbox_delay_plugins_hours'   => 36,
        'sandbox_delay_themes_hours'    => 36,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('sandbox_settings_save');

        update_site_option('sandbox_secret_key', sanitize_text_field($_POST['sandbox_secret_key'] ?? $defaults['sandbox_secret_key']));
        update_site_option('sandbox_base_domain', sanitize_text_field($_POST['sandbox_base_domain'] ?? $defaults['sandbox_base_domain']));
        update_site_option('disable_password_change_email', isset($_POST['disable_password_change_email']) ? 1 : 0);

        // New: update delay settings
        update_site_option('sandbox_delay_core_days', max(0, (int) ($_POST['sandbox_delay_core_days'] ?? $defaults['sandbox_delay_core_days'])));
        update_site_option('sandbox_delay_plugins_hours', max(0, (int) ($_POST['sandbox_delay_plugins_hours'] ?? $defaults['sandbox_delay_plugins_hours'])));
        update_site_option('sandbox_delay_themes_hours', max(0, (int) ($_POST['sandbox_delay_themes_hours'] ?? $defaults['sandbox_delay_themes_hours'])));

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $secret_key = get_site_option('sandbox_secret_key', $defaults['sandbox_secret_key']);
    $base_domain = get_site_option('sandbox_base_domain', $defaults['sandbox_base_domain']);
    $disable_password_change_email = (int) get_site_option('disable_password_change_email', $defaults['disable_password_change_email']);

    $delay_core_days     = (int) get_site_option('sandbox_delay_core_days', $defaults['sandbox_delay_core_days']);
    $delay_plugins_hours = (int) get_site_option('sandbox_delay_plugins_hours', $defaults['sandbox_delay_plugins_hours']);
    $delay_themes_hours  = (int) get_site_option('sandbox_delay_themes_hours', $defaults['sandbox_delay_themes_hours']);
    ?>
    <div class="wrap">
        <h1>Sandbox API Settings</h1>

        <form method="post">
            <?php wp_nonce_field('sandbox_settings_save'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sandbox_secret_key">Secret Key</label></th>
                    <td><input type="text" id="sandbox_secret_key" name="sandbox_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sandbox_base_domain">Base Domain</label></th>
                    <td><input type="text" id="sandbox_base_domain" name="sandbox_base_domain" value="<?php echo esc_attr($base_domain); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Disable Admin Password Change Emails</th>
                    <td>
                        <input type="checkbox" id="disable_password_change_email" name="disable_password_change_email" value="1" <?php checked(1, $disable_password_change_email, true); ?>>
                        <label for="disable_password_change_email">Prevent admin notifications for password changes</label>
                    </td>
                </tr>
            </table>

            <hr />

            <h2>Auto Update Delays (reduce risk)</h2>
            <p>Delays are counted from when this network first detects an update (first seen), not the vendor release date.</p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sandbox_delay_core_days">Delay major WordPress core updates (days)</label></th>
                    <td>
                        <input type="number" min="0" id="sandbox_delay_core_days" name="sandbox_delay_core_days" value="<?php echo esc_attr($delay_core_days); ?>" />
                        <p class="description">Minor/security core updates apply immediately; major updates are delayed by this many days.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sandbox_delay_plugins_hours">Delay plugin updates (hours)</label></th>
                    <td>
                        <input type="number" min="0" id="sandbox_delay_plugins_hours" name="sandbox_delay_plugins_hours" value="<?php echo esc_attr($delay_plugins_hours); ?>" />
                        <p class="description">Set 0 to update plugins immediately.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sandbox_delay_themes_hours">Delay theme updates (hours)</label></th>
                    <td>
                        <input type="number" min="0" id="sandbox_delay_themes_hours" name="sandbox_delay_themes_hours" value="<?php echo esc_attr($delay_themes_hours); ?>" />
                        <p class="description">Set 0 to update themes immediately.</p>
                    </td>
                </tr>
            </table>

            <p class="submit"><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}

// --- Delayed Auto Updates (network-wide) ---
class Sandbox_Delayed_Auto_Updates {
    const CRON_HOOK = 'sandbox_delayed_update_checks';

    public static function init() {
        add_action('init', [__CLASS__, 'schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run_checks']);

        add_filter('auto_update_core',   [__CLASS__, 'maybe_auto_update_core'],   10, 2);
        add_filter('auto_update_plugin', [__CLASS__, 'maybe_auto_update_plugin'], 10, 2);
        add_filter('auto_update_theme',  [__CLASS__, 'maybe_auto_update_theme'],  10, 2);
    }

    public static function schedule() {
        if ( ! is_multisite() ) return;

        if ( ! wp_next_scheduled(self::CRON_HOOK) ) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    public static function run_checks() {
        wp_update_plugins();
        wp_update_themes();
        wp_version_check();
    }

    private static function get_core_delay_seconds() : int {
        $days = (int) get_site_option('sandbox_delay_core_days', 14);
        return max(0, $days) * DAY_IN_SECONDS;
    }

    private static function get_plugin_delay_seconds() : int {
        $hours = (int) get_site_option('sandbox_delay_plugins_hours', 36);
        return max(0, $hours) * HOUR_IN_SECONDS;
    }

    private static function get_theme_delay_seconds() : int {
        $hours = (int) get_site_option('sandbox_delay_themes_hours', 36);
        return max(0, $hours) * HOUR_IN_SECONDS;
    }

    private static function seen_key($type, $slug, $new_version) : string {
        return 'sandbox_seen_' . sanitize_key($type) . '_' . md5($slug . '|' . $new_version);
    }

    private static function seen_time_or_set_now($type, $slug, $new_version) : int {
        $k = self::seen_key($type, $slug, $new_version);
        $t = (int) get_site_option($k, 0);
        if ( ! $t ) {
            $t = time();
            update_site_option($k, $t);
        }
        return $t;
    }

    private static function delay_passed($type, $slug, $new_version, $delay_seconds) : bool {
        if ( (int) $delay_seconds <= 0 ) return true;
        $seen = self::seen_time_or_set_now($type, $slug, $new_version);
        return (time() - $seen) >= (int) $delay_seconds;
    }

    public static function maybe_auto_update_plugin($update, $item) {
        if ( empty($item->plugin) || empty($item->new_version) ) return $update;
        return self::delay_passed('plugin', $item->plugin, $item->new_version, self::get_plugin_delay_seconds());
    }

    public static function maybe_auto_update_theme($update, $item) {
        if ( empty($item->theme) || empty($item->new_version) ) return $update;
        return self::delay_passed('theme', $item->theme, $item->new_version, self::get_theme_delay_seconds());
    }

    public static function maybe_auto_update_core($update, $item) {
        $current = get_bloginfo('version');
        $target  = (is_object($item) && ! empty($item->current)) ? (string) $item->current : '';
        if ( ! $target ) return $update;

        // Allow minor/security immediately; delay major line updates.
        if ( ! self::is_major_wp_change($current, $target) ) {
            return true;
        }

        return self::delay_passed('core', 'wordpress', $target, self::get_core_delay_seconds());
    }

    private static function is_major_wp_change($current, $target) : bool {
        $cur = explode('.', (string) $current);
        $tar = explode('.', (string) $target);

        $cur_major = ($cur[0] ?? '0') . '.' . ($cur[1] ?? '0');
        $tar_major = ($tar[0] ?? '0') . '.' . ($tar[1] ?? '0');

        return $cur_major !== $tar_major;
    }
}
Sandbox_Delayed_Auto_Updates::init();

// Disable Email Functionality for Password Changes Based on Setting
function conditionally_disable_password_change_email() {
    $disable_email = get_site_option('disable_password_change_email', 1); // Default is checked

    if ($disable_email) {
        remove_action('after_password_reset', 'wp_password_change_notification');
        remove_action('password_reset', 'wp_password_change_notification');
    }
}
add_action('plugins_loaded', 'conditionally_disable_password_change_email');

// Register REST API routes
function register_multisite_creation_api() {
    register_rest_route('sandbox/v1', '/create/', array(
        'methods'  => 'POST',
        'callback' => 'create_multisite_sandbox',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_multisite_creation_api');

function create_multisite_sandbox(WP_REST_Request $request) {
    $secret_key = get_site_option('sandbox_secret_key', 'default-secret-key');
    $base_domain = get_site_option('sandbox_base_domain', 'wpdemo.uk');
    $provided_key = $request->get_param('secret');

    if (!$provided_key || $provided_key !== $secret_key) {
        return new WP_REST_Response(['error' => 'Unauthorized'], 403);
    }

    $site_slug = sanitize_title($request->get_param('slug'));
    $site_title = sanitize_text_field($request->get_param('title'));
    $admin_email = sanitize_email($request->get_param('email'));

    if (empty($site_slug) || empty($site_title) || empty($admin_email)) {
        return new WP_REST_Response(['error' => 'Missing parameters'], 400);
    }

    $new_site_domain = $site_slug . '.' . $base_domain;

    $user = get_user_by('email', $admin_email);
    if (!$user) {
        $random_password = wp_generate_password(12, false);
        $user_id = wp_create_user($admin_email, $random_password, $admin_email);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['error' => 'User creation failed.'], 500);
        }
        wp_new_user_notification($user_id, null, 'user');
    } else {
        $user_id = $user->ID;
    }

    $new_blog_id = wpmu_create_blog($new_site_domain, '/', $site_title, $user_id, ['public' => 1], 1);
    if (is_wp_error($new_blog_id)) {
        return new WP_REST_Response(['error' => $new_blog_id->get_error_message()], 500);
    }
    
    add_user_to_blog($new_blog_id, $user_id, 'administrator');
    remove_user_from_blog($user_id, 1);

    return new WP_REST_Response(['success' => true, 'site_id' => $new_blog_id, 'url' => 'https://' . $new_site_domain], 200);
}



function register_multisite_deletion_api() {
    register_rest_route('sandbox/v1', '/delete/', array(
        'methods'  => 'POST',
        'callback' => 'delete_multisite_sandbox',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_multisite_deletion_api');

function delete_multisite_sandbox(WP_REST_Request $request) {
    $secret_key = get_site_option('sandbox_secret_key', 'default-secret-key');
    $provided_key = $request->get_param('secret');
    
    if (!$provided_key || $provided_key !== $secret_key) {
        return new WP_REST_Response(['error' => 'Unauthorized'], 403);
    }

    $blog_id = intval($request->get_param('site_id'));
    if (!$blog_id) {
        return new WP_REST_Response(['error' => 'Missing site_id'], 400);
    }

    // Get the site's admin user
    $users = get_users(array(
        'blog_id' => $blog_id,
        'role'    => 'administrator',
        'fields'  => 'ID',
    ));

    if (!empty($users)) {
        $user_id = $users[0];
    }

    require_once(ABSPATH . 'wp-admin/includes/ms.php');
    wpmu_delete_blog($blog_id, true);

    // Ensure the user is completely removed if they are not assigned to other sites
    if (!empty($user_id)) {
        global $wpdb;
        $user_blogs = get_blogs_of_user($user_id);
        if (empty($user_blogs) || count($user_blogs) === 0) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wpmu_delete_user($user_id);
        }
    }

    return new WP_REST_Response(['success' => true, 'deleted' => $blog_id, 'deleted_user' => $user_id ?? null], 200);
}
