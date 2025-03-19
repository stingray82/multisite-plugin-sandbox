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

// Add Admin Menu for Settings
function sandbox_admin_menu() {
    add_menu_page('Sandbox Settings', 'Sandbox Settings', 'manage_options', 'sandbox-settings', 'sandbox_settings_page');
}
add_action('network_admin_menu', 'sandbox_admin_menu');

// Settings Page
function sandbox_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        update_site_option('sandbox_secret_key', sanitize_text_field($_POST['sandbox_secret_key']));
        update_site_option('sandbox_base_domain', sanitize_text_field($_POST['sandbox_base_domain']));
        update_site_option('disable_password_change_email', isset($_POST['disable_password_change_email']) ? 1 : 0);

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    
    $secret_key = get_site_option('sandbox_secret_key', 'default-secret-key');
    $base_domain = get_site_option('sandbox_base_domain', 'wpdemo.uk');
    $disable_password_change_email = get_site_option('disable_password_change_email', 1); // Default is checked
    ?>
    <div class="wrap">
        <h1>Sandbox API Settings</h1>
        <form method="post">
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
            <p class="submit"><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}

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