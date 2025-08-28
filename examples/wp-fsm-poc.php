<?php
/**
 * Plugin Name: WP FSM Demo
 * Description: Demonstrates the state machine library with a simple admin page.
 * Version: 0.1.0
 * Author: WP FSM
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPFSM_Demo_Object {
    public $state;

    public function __construct( $state = 'start' ) {
        $this->state = $state;
    }
}

// Provide the configuration for our demo object's state machine via the factory filter.
add_filter( 'wp_fsm_factory_configs', function( $configs ) {
    $configs[] = array(
        'class'         => WPFSM_Demo_Object::class,
        'graph'         => 'demo_graph',
        'property_path' => 'state',
        'states'        => array( 'start', 'middle', 'end' ),
        'transitions'   => array(
            'to_middle' => array(
                'from' => array( 'start' ),
                'to'   => 'middle',
            ),
            'to_end' => array(
                'from' => array( 'middle' ),
                'to'   => 'end',
            ),
            'reset' => array(
                'from' => array( 'middle', 'end' ),
                'to'   => 'start',
            ),
        ),
    );

    return $configs;
} );

// Register the demo admin page.
add_action( 'admin_menu', function() {
    add_menu_page( 'FSM Demo', 'FSM Demo', 'manage_options', 'wp-fsm-demo', 'wp_fsm_demo_render' );
} );

/**
 * Render the admin demo page.
 */
function wp_fsm_demo_render() {
    $state = get_option( 'wp_fsm_demo_state', 'start' );
    $obj   = new WPFSM_Demo_Object( $state );
    $sm    = wp_fsm_factory()->get( $obj, 'demo_graph' );

    if ( isset( $_POST['wp_fsm_demo_nonce'] ) && wp_verify_nonce( $_POST['wp_fsm_demo_nonce'], 'wp_fsm_demo' ) ) {
        $transition = isset( $_POST['transition'] ) ? sanitize_text_field( wp_unslash( $_POST['transition'] ) ) : '';
        $confirm    = ! empty( $_POST['confirm'] );

        if ( $confirm && $sm->can( $transition ) ) {
            $sm->apply( $transition );
            update_option( 'wp_fsm_demo_state', $obj->state );
            echo '<div class="updated"><p>Transition applied.</p></div>';
        } else {
            echo '<div class="error"><p>Transition blocked.</p></div>';
        }
    }

    $state = get_option( 'wp_fsm_demo_state', 'start' );
    $obj   = new WPFSM_Demo_Object( $state );
    ?>
    <div class="wrap">
        <h1>FSM Demo</h1>
        <p>Current state: <strong><?php echo esc_html( $obj->state ); ?></strong></p>
        <form method="post">
            <?php wp_nonce_field( 'wp_fsm_demo', 'wp_fsm_demo_nonce' ); ?>
            <p><label><input type="radio" name="transition" value="to_middle"> To Middle</label></p>
            <p><label><input type="radio" name="transition" value="to_end"> To End</label></p>
            <p><label><input type="radio" name="transition" value="reset"> Reset</label></p>
            <p><label><input type="checkbox" name="confirm" value="1"> Confirm transition</label></p>
            <p><input type="submit" class="button button-primary" value="Apply"></p>
        </form>
    </div>
    <?php
}
