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

// -----------------------------------------------------------------------------
//  Service helper
// -----------------------------------------------------------------------------

if ( ! function_exists( 'wp_fsm_factory' ) ) {
    /**
     * Retrieve a shared State Machine Factory instance.
     *
     * Other plugins can call this function to register state machine
     * configurations and fetch state machines for their objects.
     *
     * @return \SM\Factory\Factory
     */
    function wp_fsm_factory() {
        static $factory = null;

        if ( null === $factory ) {
            $factory = new \SM\Factory\Factory( [] );
        }

        return $factory;
    }
}

// -----------------------------------------------------------------------------
//  Demo admin page
// -----------------------------------------------------------------------------

// Simple object used for the demo state machine.
class WP_FSM_Demo_Object {
    /** @var string */
    public $state;

    public function __construct( $state = 'closed' ) {
        $this->state = $state;
    }
}

add_action( 'admin_menu', 'wp_fsm_demo_admin_menu' );

/**
 * Register the demo page under Tools.
 */
function wp_fsm_demo_admin_menu() {
    add_management_page(
        __( 'FSM Demo', 'wp-fsm' ),
        __( 'FSM Demo', 'wp-fsm' ),
        'manage_options',
        'wp-fsm-demo',
        'wp_fsm_demo_page'
    );
}

/**
 * Render the demo admin page with a simple state machine and form controls.
 */
function wp_fsm_demo_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $state = get_option( 'wp_fsm_demo_state', 'closed' );

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['transition'] ) ) {
        check_admin_referer( 'wp_fsm_demo' );

        $transition = sanitize_text_field( wp_unslash( $_POST['transition'] ) );
        $allow      = ! empty( $_POST['allow_guard'] );

        $config = [
            'class'         => WP_FSM_Demo_Object::class,
            'graph'         => 'demo',
            'property_path' => 'state',
            'states'        => [ 'closed', 'opened' ],
            'transitions'   => [
                'open'  => [ 'from' => [ 'closed' ], 'to' => 'opened' ],
                'close' => [ 'from' => [ 'opened' ], 'to' => 'closed' ],
            ],
        ];

        $factory = wp_fsm_factory();
        $factory->addConfig( $config, 'demo' );

        $object       = new WP_FSM_Demo_Object( $state );
        $stateMachine = $factory->get( $object, 'demo' );

        if ( $allow && $stateMachine->can( $transition ) ) {
            $stateMachine->apply( $transition );
            $state = $object->state;
            update_option( 'wp_fsm_demo_state', $state );

            echo '<div class="updated"><p>' . esc_html__( 'Transition applied.', 'wp-fsm' ) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__( 'Transition not allowed.', 'wp-fsm' ) . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'FSM Demo', 'wp-fsm' ); ?></h1>
        <p><?php echo esc_html__( 'Current State:', 'wp-fsm' ) . ' ' . esc_html( $state ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'wp_fsm_demo' ); ?>
            <p>
                <label>
                    <input type="radio" name="transition" value="open" />
                    <?php esc_html_e( 'Open', 'wp-fsm' ); ?>
                </label><br />
                <label>
                    <input type="radio" name="transition" value="close" />
                    <?php esc_html_e( 'Close', 'wp-fsm' ); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="allow_guard" value="1" />
                    <?php esc_html_e( 'Allow transition', 'wp-fsm' ); ?>
                </label>
            </p>
            <?php submit_button( __( 'Apply Transition', 'wp-fsm' ) ); ?>
        </form>
    </div>
    <?php
}

