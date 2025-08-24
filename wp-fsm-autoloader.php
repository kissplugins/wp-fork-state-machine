<?php
/**
 * Plugin Name: WP FSM Autoloader
 * Description: Provides PSR-4 autoloading for the winzou/state-machine library so other plugins can use it.
 * Version: 0.1.0
 * Author: WP FSM
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load Composer dependencies if available.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Register autoloader for the SM namespace using PSR-4.
spl_autoload_register( function ( $class ) {
    $prefix   = 'SM\\';
    $base_dir = plugin_dir_path( __FILE__ ) . 'src/SM/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );
