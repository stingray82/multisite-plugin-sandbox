<?php
/*
Plugin Name: Wordpress Multi-site API
Description: A minimal plugin to allow multisite creating and deleting a multisite sandbox site with configurable settings via automator of your choice
Version: 1.0
Author: Stingray82
Network: true
*/

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
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    
    $secret_key = get_site_option('sandbox_secret_key', 'default-secret-key');
    $base_domain = get_site_option('sandbox_base_domain', 'wpdemo.uk');
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
            </table>
            <p class="submit"><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}

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

    // Create user if it doesn't exist
    $user = get_user_by('email', $admin_email);
    if (!$user) {
        $random_password = wp_generate_password(12, false);
        $user_id = wp_create_user($admin_email, $random_password, $admin_email);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['error' => 'User creation failed.'], 500);
        }
        
        // Send reset password email
        wp_new_user_notification($user_id, null, 'user');
    } else {
        $user_id = $user->ID;
    }

    // Create the site without assigning the super admin
    $new_blog_id = wpmu_create_blog($new_site_domain, '/', $site_title, $user_id, ['public' => 1], 1);
    if (is_wp_error($new_blog_id)) {
        return new WP_REST_Response(['error' => $new_blog_id->get_error_message()], 500);
    }
    
    // Ensure the new user is added only to their site
    add_user_to_blog($new_blog_id, $user_id, 'administrator');
    remove_user_from_blog($user_id, 1); // Ensure they are not added to the main network site

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