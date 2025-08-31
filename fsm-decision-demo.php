<?php
/**
 * Plugin Name: FSM Decision Demo
 * Description: An interactive FSM Decision Maker demo as a WordPress plugin.
 * Version: 0.1.0
 * Author: Gemini
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FSM_DEMO_VERSION', '0.1.0');
define('FSM_DEMO_DIR', plugin_dir_path(__FILE__));
define('FSM_DEMO_URL', plugin_dir_url(__FILE__));

require_once FSM_DEMO_DIR . 'vendor/autoload.php';
require_once FSM_DEMO_DIR . 'inc/Shortcodes.php';
require_once FSM_DEMO_DIR . 'inc/Rest.php';

function fsm_demo_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fsm_demo_jobs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        state VARCHAR(64) NOT NULL,
        version BIGINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        log LONGTEXT NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'fsm_demo_activate');

KissPlugins\FsmDemo\Shortcodes::init();