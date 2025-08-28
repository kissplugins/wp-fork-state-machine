# WP FSM Autoloader

This plugin exposes the [winzou/state-machine](https://github.com/winzou/StateMachine) library to WordPress through a simple PSR-4 autoloader. Activate it to make the `SM` namespace available for other plugins.

## Installation

1. Copy this repository into `wp-content/plugins/wp-fsm-autoloader`.
2. From the plugin directory run `composer install` to fetch required Symfony components.
3. Activate **WP FSM Autoloader** from the WordPress admin panel.

## Usage

Other plugins can now instantiate and configure state machines using the classes under the `SM` namespace. See `WP-GENERIC-INTEGRATION.md` for a detailed guide on integrating the library with custom post types, REST endpoints and UI elements.

### Service Function

The plugin exposes a helper function `wp_fsm_factory()` that returns a shared
`SM\Factory\Factory` instance. Use the `wp_fsm_factory_configs` filter to add
your own graphs before calling the function:

```php
add_filter( 'wp_fsm_factory_configs', function( $configs ) {
    $configs[] = array(
        'class'         => My_Object::class,
        'graph'         => 'my_graph',
        'property_path' => 'state',
        // states and transitions here
    );
    return $configs;
} );

$factory = wp_fsm_factory();
$sm      = $factory->get( $object, 'my_graph' );
```

### Proof of Concept Admin Page

A demonstration plugin lives in `examples/wp-fsm-poc.php`. Activate it alongside
the autoloader to add an **FSM Demo** page to the WordPress admin, showcasing
transitions with radio buttons and a confirmation checkbox.

## Developer Notes

- The autoloader maps the `SM\` namespace to `src/SM/`.
- If Composer is available, `vendor/autoload.php` will be loaded automatically.
- Ensure dependencies are installed before using the state machine in production.
