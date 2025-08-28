# WP FSM Autoloader

This plugin exposes the [winzou/state-machine](https://github.com/winzou/StateMachine) library to WordPress through a simple PSR-4 autoloader. Activate it to make the `SM` namespace available for other plugins.

## Installation

1. Copy this repository into `wp-content/plugins/wp-fsm-autoloader`.
2. From the plugin directory run `composer install` to fetch required Symfony components.
3. Activate **WP FSM Autoloader** from the WordPress admin panel.

## Usage

Other plugins can now instantiate and configure state machines using the classes under the `SM` namespace. The helper function `wp_fsm_factory()` returns a shared instance of `SM\Factory\Factory` that can be reused across plugins. See `WP-GENERIC-INTEGRATION.md` for a detailed guide on integrating the library with custom post types, REST endpoints and UI elements.

After activation a small proof-of-concept page is available under **Tools → FSM Demo**. It showcases a simple state machine controlled with radio buttons and a checkbox.

## Developer Notes

- The autoloader maps the `SM\` namespace to `src/SM/`.
- If Composer is available, `vendor/autoload.php` will be loaded automatically.
- Ensure dependencies are installed before using the state machine in production.
