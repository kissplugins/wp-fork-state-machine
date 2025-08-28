# State Machine Loader & Demo

This WordPress plugin bundles the [winzou/state-machine](https://github.com/winzou/StateMachine) library and exposes a service accessor and interactive admin page for experimenting with finite state machines.

## Installation

1. Copy or clone this repository into `wp-content/plugins/wp-state-machine`.
2. From the plugin directory run `composer install` to fetch dependencies.
3. Activate **State Machine Loader & Demo** from the WordPress Plugins screen.

## Service Accessor

The global `wp_sm_get_factory()` function returns a shared `SM\Factory\Factory` instance. Other plugins can call it to obtain state machines without handling boilerplate setup.

```php
$config = [
    'graph'         => 'orderProcessing',
    'class'         => \WpStateMachine\DomainObject::class,
    'property_path' => 'state',
    'states'        => ['checkout', 'pending', 'confirmed', 'shipped', 'cancelled'],
    'transitions'   => [
        'create'  => ['from' => ['checkout'], 'to' => 'pending'],
        'confirm' => ['from' => ['pending'], 'to' => 'confirmed'],
        'ship'    => ['from' => ['confirmed'], 'to' => 'shipped'],
        'cancel'  => ['from' => ['pending', 'confirmed'], 'to' => 'cancelled'],
    ],
];

$domain_object = new \WpStateMachine\DomainObject();
$state_machine = wp_sm_get_factory([$config])->get($domain_object, 'orderProcessing');
```

## Demo Admin Page

Activating the plugin adds an **FSM Test** entry in the WordPress admin menu. The page lets you:

- View the current state of a demo `DomainObject` stored in the options table.
- Apply available transitions using buttons.
- Reset the state back to the initial value.

This interface showcases how the library manages transitions within WordPress.

## Development

Run the following commands inside the plugin directory:

```bash
composer install
vendor/bin/phpspec run
```

The first command installs dependencies and the second runs the library's test suite.
