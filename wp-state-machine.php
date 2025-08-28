<?php
/**
 * Plugin Name:       State Machine Loader & Demo
 * Plugin URI:        https://github.com/kissplugins/wp-fork-state-machine
 * Description:       A loader and proof-of-concept for a PHP state machine library in WordPress.
 * Version:           1.0.0
 * Author:            KISS Plugins
 * Author URI:        https://github.com/kissplugins
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Include the Composer autoloader (from your existing file).
require_once __DIR__ . '/vendor/autoload.php';

use SM\Factory\Factory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use WpStateMachine\DomainObject;

/**
 * Gets the configured State Machine Factory.
 *
 * This function acts as a centralized service locator, allowing other plugins
 * to easily access the state machine functionality without needing to handle
 * the setup and configuration themselves.
 *
 * @param array $configs An array of state machine graph configurations.
 * @return Factory
 */
function wp_sm_get_factory(array $configs): Factory
{
    static $factory = null;
    if ($factory === null) {
        $dispatcher = new EventDispatcher();
        $factory = new Factory($configs, $dispatcher);
    }
    // Note: In a real multi-config scenario, you might want to allow adding configs
    // to an existing factory, but for this purpose, creating it once is fine.
    return $factory;
}


// --- Proof of Concept / Admin Demo ---

/**
 * Adds the admin menu page for our POC.
 */
function wp_fsm_admin_menu(): void {
    add_menu_page(
        'FSM Test Page',
        'FSM Test',
        'manage_options',
        'fsm-test-page',
        'wp_fsm_settings_page_html'
    );
}
add_action( 'admin_menu', 'wp_fsm_admin_menu' );

/**
 * Renders the HTML for the admin settings page.
 */
function wp_fsm_settings_page_html(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Define the state machine configuration for our demo.
    $config = [
        'graph'         => 'orderProcessing',
        'class'         => DomainObject::class,
        'property_path' => 'state',
        'states'        => ['checkout', 'pending', 'confirmed', 'shipped', 'cancelled'],
        'transitions'   => [
            'create'  => ['from' => ['checkout'], 'to' => 'pending'],
            'confirm' => ['from' => ['pending'], 'to' => 'confirmed'],
            'ship'    => ['from' => ['confirmed'], 'to' => 'shipped'],
            'cancel'  => ['from' => ['pending', 'confirmed'], 'to' => 'cancelled'],
        ],
    ];

    // Get our domain object and the state machine instance using our new service function.
    $domain_object = new DomainObject();
    $state_machine = wp_sm_get_factory([$config])->get($domain_object, 'orderProcessing');

    // Handle form submissions to apply transitions.
    if ( isset( $_POST['fsm_action'] ) && check_admin_referer( 'fsm_poc_action' ) ) {
        $action = sanitize_text_field( $_POST['fsm_action'] );

        if ( $action === 'reset' ) {
            delete_option( DomainObject::OPTION_KEY );
            $domain_object = new DomainObject(); // Re-instantiate to get fresh state
            $state_machine = wp_sm_get_factory([$config])->get($domain_object, 'orderProcessing');
            echo '<div class="notice notice-success is-dismissible"><p>State has been reset.</p></div>';
        } elseif ( $state_machine->can( $action ) ) {
            $state_machine->apply( $action );
            echo '<div class="notice notice-success is-dismissible"><p>Transition <strong>' . esc_html( $action ) . '</strong> applied!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Could not apply transition <strong>' . esc_html( $action ) . '</strong>.</p></div>';
        }
    }

    $current_state        = $state_machine->getState();
    $possible_transitions = $state_machine->getPossibleTransitions();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p>This page demonstrates a Finite State Machine (FSM) for a simple order process.</p>

        <div style="border: 1px solid #ccd0d4; padding: 15px; background: #fff; margin-top: 20px; max-width: 500px;">
            <h2>Current Status</h2>
            <p style="font-size: 24px; font-weight: bold; margin: 0; padding: 10px 0;">
                <?php echo esc_html( ucfirst( $current_state ) ); ?>
            </p>
        </div>

        <div style="margin-top: 20px;">
            <h2>Available Actions</h2>
            <form method="post" action="">
                <?php
                wp_nonce_field( 'fsm_poc_action' );
                if ( ! empty( $possible_transitions ) ) {
                    foreach ( $possible_transitions as $transition ) {
                        submit_button(ucfirst( $transition ), 'primary', 'fsm_action', false, ['value' => $transition]);
                        echo '&nbsp;';
                    }
                } else {
                    echo '<p>No further transitions are possible.</p>';
                }
                submit_button('Reset State', 'secondary', 'fsm_action', false, ['value' => 'reset']);
                ?>
            </form>
        </div>
    </div>
    <?php
}
