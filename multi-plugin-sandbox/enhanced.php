<?php

if (!defined('ABSPATH')) exit;

// Ensure plugin only loads in the Network Admin, but allow REST API calls
if (!is_multisite()) {
    return;
}

if (!is_network_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
    return;
}


// 1. Disable Theme & Plugin File Editing
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
if (!defined('DISALLOW_FILE_MODS')) {
    define('DISALLOW_FILE_MODS', true);
}

// 2. Prevent Subsite Admins from Editing Functions.php
function restrict_functions_edit($caps, $cap, $user_id, $args) {
    if ($cap === 'edit_theme_options') {
        $file = $_SERVER['REQUEST_URI'];
        if (strpos($file, 'functions.php') !== false) {
            $caps[] = 'do_not_allow';
        }
    }
    return $caps;
}
add_filter('map_meta_cap', 'restrict_functions_edit', 10, 4);

// 3. Prevent PHP File Uploads via Media Library
function block_php_uploads($file) {
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (in_array($ext, ['php', 'php3', 'php4', 'php5', 'phtml'])) {
        $file['error'] = 'File type not allowed.';
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'block_php_uploads');

// 4. Block Direct PHP Access in wp-content/uploads
function block_php_execution_in_uploads() {
    $htaccess_path = WP_CONTENT_DIR . '/uploads/.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, "<Files *.php>\nDeny from all\n</Files>");
    }
}
register_activation_hook(__FILE__, 'block_php_execution_in_uploads');

function disable_password_change_admin_emails_multisite() {
    if (!is_main_site()) {
        return; // Exit for subsites
    }
    add_filter( 'wp_password_change_notification_email', '__return_false' ); // Stops admin notification
}
add_action( 'init', 'disable_password_change_admin_emails_multisite' );
