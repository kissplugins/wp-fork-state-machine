# WP FSM Autoloader

This plugin exposes the [winzou/state-machine](https://github.com/winzou/StateMachine) library to WordPress through a simple PSR-4 autoloader. Activate it to make the `SM` namespace available for other plugins.

## Installation

1. Copy this repository into `wp-content/plugins/wp-fsm-autoloader`.
2. From the plugin directory run `composer install` to fetch required Symfony components.
3. Activate **WP FSM Autoloader** from the WordPress admin panel.

## Usage

Other plugins can now instantiate and configure state machines using the classes under the `SM` namespace. See `WP-GENERIC-INTEGRATION.md` for a detailed guide on integrating the library with custom post types, REST endpoints and UI elements.

## Developer Notes

- The autoloader maps the `SM\` namespace to `src/SM/`.
- If Composer is available, `vendor/autoload.php` will be loaded automatically.
- Ensure dependencies are installed before using the state machine in production.
