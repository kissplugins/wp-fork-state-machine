# Integrating `winzou/state-machine` into a WordPress Plugin

This guide provides an actionable checklist and detailed examples for integrating the `winzou/state-machine` library into a WordPress plugin. It also covers potential challenges and how to interact with the state machine from your JavaScript functions.

-----

## ✅ Actionable To-Do Checklist

  - [ ] **1. Set up Your Development Environment:**

      - [ ] Ensure you have a local WordPress installation.
      - [ ] Install Composer on your development machine.

  - [ ] **2. Initialize Composer in Your Plugin:**

      - [ ] Navigate to your plugin's directory in the terminal.
      - [ ] Run `composer init` to create a `composer.json` file.
      - [ ] Run `composer require winzou/state-machine` to add the library as a dependency.

  - [ ] **3. Include the Composer Autoloader:**

      - [ ] In your main plugin file, add `require_once __DIR__ . '/vendor/autoload.php';`.

  - [ ] **4. Design Your State Machine:**

      - [ ] Identify the object you want to manage (e.g., a custom post type).
      - [ ] Define all possible states for that object.
      - [ ] Map out the transitions that can occur between states.
      - [ ] Plan any guards (conditions) or callbacks (actions) for your transitions.

  - [ ] **5. Create a Centralized State Machine Service:**

      - [ ] Create a PHP class to manage your state machine's configuration and initialization.
      - [ ] This class should be responsible for returning a configured state machine instance for a given object.

  - [ ] **6. Integrate with WordPress:**

      - [ ] Choose how to store the state (e.g., as post meta for a custom post type).
      - [ ] Create the user interface elements (e.g., buttons in a metabox) that will trigger transitions.
      - [ ] Handle the form submissions or AJAX requests to apply transitions.

  - [ ] **7. Implement JavaScript Interaction (Optional):**

      - [ ] If you need to trigger transitions from the frontend, create custom WordPress REST API endpoints.
      - [ ] Write JavaScript functions that make requests to these endpoints.
      - [ ] Ensure proper security with nonces and capability checks in your REST API endpoints.

-----

## Sample Code and Approaches

Let's imagine we're building a plugin to manage a "Job Application" custom post type.

### 1️⃣ **State Machine Configuration**

First, create a class to manage your state machine. This keeps your configuration organized and easy to reuse.

```php
// in your-plugin/includes/class-application-state-machine.php

use SM\Factory\Factory;
use SM\StateMachine\StateMachineInterface;

class Application_State_Machine {

    private static $factory;

    public static function get_config() {
        return [
            'graph' => 'application_workflow',
            'property_path' => 'post_meta[_application_status][0]',
            'states' => [
                'new',
                'screening',
                'interviewing',
                'rejected',
                'hired',
            ],
            'transitions' => [
                'screen' => ['from' => ['new'], 'to' => 'screening'],
                'invite' => ['from' => ['screening'], 'to' => 'interviewing'],
                'reject' => ['from' => ['screening', 'interviewing'], 'to' => 'rejected'],
                'hire' => ['from' => ['interviewing'], 'to' => 'hired'],
            ],
            'callbacks' => [
                'after' => [
                    'notify_applicant' => [
                        'on' => ['reject', 'hire'],
                        'do' => [__CLASS__, 'notify_applicant'],
                    ],
                ],
            ],
        ];
    }

    public static function get_factory() {
        if (!self::$factory) {
            $configs = [self::get_config()];
            self::$factory = new Factory($configs);
        }
        return self::$factory;
    }

    public static function get_state_machine(WP_Post $application): StateMachineInterface {
        return self::get_factory()->get($application, 'application_workflow');
    }

    // Callback method
    public static function notify_applicant($event) {
        $state_machine = $event->getStateMachine();
        $application = $state_machine->getObject();
        $applicant_email = get_post_meta($application->ID, '_applicant_email', true);
        $new_state = $state_machine->getState();

        // In a real plugin, you would send a nicely formatted email
        wp_mail(
            $applicant_email,
            'Your Application Status Update',
            "Your application status has been updated to: " . $new_state
        );
    }
}
```

### 2️⃣ **Integrating with the WordPress Admin**

Now, let's add a metabox to the "Job Application" post type to show the current state and possible actions.

```php
// In your main plugin file or a dedicated admin class

add_action('add_meta_boxes', 'add_application_status_metabox');
function add_application_status_metabox() {
    add_meta_box(
        'application_status',
        'Application Status',
        'render_application_status_metabox',
        'job_application', // Your custom post type slug
        'side',
        'high'
    );
}

function render_application_status_metabox($post) {
    wp_nonce_field('application_transition', 'application_nonce');
    $state_machine = Application_State_Machine::get_state_machine($post);
    $current_state = $state_machine->getState();
    $possible_transitions = $state_machine->getPossibleTransitions();

    echo "<strong>Current Status:</strong> " . esc_html($current_state);

    if (!empty($possible_transitions)) {
        echo '<h4>Actions:</h4>';
        foreach ($possible_transitions as $transition) {
            echo '<button type="submit" name="sm_transition" value="' . esc_attr($transition) . '">'
               . esc_html(ucfirst($transition))
               . '</button>';
        }
    } else {
        echo "<p>No further actions available.</p>";
    }
}

add_action('save_post_job_application', 'handle_application_transition');
function handle_application_transition($post_id) {
    if (!isset($_POST['application_nonce']) || !wp_verify_nonce($_POST['application_nonce'], 'application_transition')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['sm_transition'])) {
        $post = get_post($post_id);
        $state_machine = Application_State_Machine::get_state_machine($post);
        $transition = sanitize_text_field($_POST['sm_transition']);

        if ($state_machine->can($transition)) {
            $state_machine->apply($transition);
            // The state is automatically saved due to the 'property_path'
        }
    }
}
```

-----

## 🤯 Possible Difficulties and Solutions

  * **Difficulty:** The `property_path` is not working with `WP_Post`.

      * **Solution:** The `symfony/property-access` component, which this library uses, can't directly access WordPress meta values. You need a way to bridge this. In the example above, `post_meta[_application_status][0]` is a workaround that relies on how `WP_Post`'s magic `__get` works. A more robust solution is to create a wrapper class for your `WP_Post` object that has explicit getter and setter methods for the state, and use that wrapper as the object for the state machine.

  * **Difficulty:** I get a "class not found" error.

      * **Solution:** This almost always means the Composer autoloader is not being included correctly. Double-check that `require_once __DIR__ . '/vendor/autoload.php';` is at the top of your main plugin file and the path is correct.

  * **Difficulty:** Managing state machine configurations for multiple custom post types becomes messy.

      * **Solution:** Abstract your state machine logic. Create a base state machine class and extend it for each custom post type. This allows you to have a common structure while keeping configurations separate and organized.

-----

## 🤖 How This Works with JS Functions

To interact with your state machine from the frontend with JavaScript (e.g., in a React-based admin interface or a frontend user dashboard), you'll need to create custom REST API endpoints.

### 1️⃣ **Create REST API Endpoints**

```php
// In your main plugin file or a dedicated API class

add_action('rest_api_init', function () {
    register_rest_route('my-plugin/v1', '/applications/(?P<id>\d+)/transition', [
        'methods' => 'POST',
        'callback' => 'handle_api_transition',
        'permission_callback' => function ($request) {
            return current_user_can('edit_post', $request['id']);
        },
    ]);
});

function handle_api_transition(WP_REST_Request $request) {
    $post_id = $request['id'];
    $transition = $request->get_param('transition');
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'job_application') {
        return new WP_Error('not_found', 'Application not found', ['status' => 404]);
    }

    $state_machine = Application_State_Machine::get_state_machine($post);

    if ($state_machine->can($transition)) {
        $state_machine->apply($transition);
        return new WP_REST_Response([
            'success' => true,
            'new_state' => $state_machine->getState()
        ], 200);
    } else {
        return new WP_Error(
            'transition_failed',
            "Cannot apply transition '{$transition}' from state '{$state_machine->getState()}'",
            ['status' => 400]
        );
    }
}
```

### 2️⃣ **JavaScript Example**

Now you can call this endpoint from your JavaScript. Here's a simple example using the Fetch API. You would need to make sure to include the WordPress REST API nonce.

```javascript
// This assumes you've used wp_localize_script to pass the nonce and API URL to your script.

const applyTransition = async (postId, transition) => {
    try {
        const response = await fetch(`${myPlugin.apiUrl}my-plugin/v1/applications/${postId}/transition`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': myPlugin.nonce // This is crucial for security
            },
            body: JSON.stringify({ transition: transition })
        });

        const data = await response.json();

        if (response.ok) {
            console.log(`Successfully transitioned to: ${data.new_state}`);
            // You would update your UI here
        } else {
            console.error(`Failed to apply transition: ${data.message}`);
        }
    } catch (error) {
        console.error('An error occurred:', error);
    }
};

// Example usage:
// applyTransition(123, 'invite');
```

# Additional WordPress Integration Guidelines for winzou/state-machine

## 🔧 Essential WordPress Wrapper Class

The document mentions creating a wrapper class but doesn't provide a complete example. Here's a robust implementation:

```php
// in your-plugin/includes/class-wp-post-state-wrapper.php

class WP_Post_State_Wrapper {
    private $post;
    private $meta_key;
    private $default_state;

    public function __construct(WP_Post $post, string $meta_key = '_state', string $default_state = 'draft') {
        $this->post = $post;
        $this->meta_key = $meta_key;
        $this->default_state = $default_state;
        
        // Ensure the post has a state set
        if (!$this->getState()) {
            $this->setState($default_state);
        }
    }

    public function getState(): string {
        $state = get_post_meta($this->post->ID, $this->meta_key, true);
        return $state ?: $this->default_state;
    }

    public function setState(string $state): void {
        update_post_meta($this->post->ID, $this->meta_key, $state);
        
        // Optional: Clear any object caches
        clean_post_cache($this->post->ID);
    }

    public function getPost(): WP_Post {
        return $this->post;
    }

    public function getId(): int {
        return $this->post->ID;
    }
}
```

## 📊 Database Considerations

### Migration Helper for Existing Posts

```php
// Add to your plugin activation hook
function migrate_existing_posts_to_state_machine() {
    $posts = get_posts([
        'post_type' => 'job_application',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_application_status',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);

    foreach ($posts as $post) {
        // Set default state for existing posts
        update_post_meta($post->ID, '_application_status', 'new');
    }
}
register_activation_hook(__FILE__, 'migrate_existing_posts_to_state_machine');
```

### Database Indexes for Performance

```php
// Add to your plugin activation
function add_state_machine_indexes() {
    global $wpdb;
    
    // Add index for faster state queries
    $wpdb->query("
        CREATE INDEX idx_postmeta_state 
        ON {$wpdb->postmeta} (meta_key, meta_value) 
        WHERE meta_key LIKE '%_status'
    ");
}
```

## 🎯 Advanced WordPress Integration Patterns

### 1. WordPress Query Integration

```php
// Add custom query vars for filtering by state
add_filter('query_vars', function($vars) {
    $vars[] = 'application_state';
    return $vars;
});

// Modify main query to filter by state
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query()) {
        $state = get_query_var('application_state');
        if ($state) {
            $query->set('meta_key', '_application_status');
            $query->set('meta_value', $state);
        }
    }
});
```

### 2. WordPress Admin List Table Integration

```php
// Add state column to admin list
add_filter('manage_job_application_posts_columns', function($columns) {
    $columns['application_state'] = 'Status';
    return $columns;
});

add_action('manage_job_application_posts_custom_column', function($column, $post_id) {
    if ($column === 'application_state') {
        $post = get_post($post_id);
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
        
        echo '<span class="state-badge state-' . esc_attr($state_machine->getState()) . '">'
           . esc_html(ucfirst($state_machine->getState()))
           . '</span>';
        
        // Show quick actions
        $transitions = $state_machine->getPossibleTransitions();
        if (!empty($transitions)) {
            echo '<div class="row-actions">';
            foreach ($transitions as $transition) {
                $url = wp_nonce_url(
                    add_query_arg(['action' => 'sm_transition', 'transition' => $transition, 'post' => $post_id]),
                    'sm_transition_' . $post_id
                );
                echo '<span><a href="' . esc_url($url) . '">' . esc_html(ucfirst($transition)) . '</a> | </span>';
            }
            echo '</div>';
        }
    }
}, 10, 2);
```

### 3. Bulk Actions Support

```php
// Add bulk state transitions
add_filter('bulk_actions-edit-job_application', function($actions) {
    $actions['bulk_approve'] = 'Bulk Approve';
    $actions['bulk_reject'] = 'Bulk Reject';
    return $actions;
});

add_filter('handle_bulk_actions-edit-job_application', function($redirect_to, $action, $post_ids) {
    if (strpos($action, 'bulk_') === 0) {
        $transition = str_replace('bulk_', '', $action);
        $processed = 0;
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
            $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
            
            if ($state_machine->can($transition)) {
                $state_machine->apply($transition);
                $processed++;
            }
        }
        
        $redirect_to = add_query_arg('bulk_processed', $processed, $redirect_to);
    }
    
    return $redirect_to;
}, 10, 3);
```

## 🔒 Security & Permissions

### Capability-Based Transitions

```php
class Application_State_Machine {
    // Add capability checking to your state machine
    public static function can_user_apply_transition($post_id, $transition, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Define capability requirements per transition
        $capabilities = [
            'screen' => 'edit_applications',
            'invite' => 'manage_interviews', 
            'reject' => 'manage_applications',
            'hire' => 'hire_candidates'
        ];
        
        $required_cap = $capabilities[$transition] ?? 'edit_post';
        
        return current_user_can($required_cap, $post_id);
    }
}

// Modify your transition handlers to check capabilities
function handle_application_transition($post_id) {
    // ... existing nonce and autosave checks ...
    
    if (isset($_POST['sm_transition'])) {
        $transition = sanitize_text_field($_POST['sm_transition']);
        
        // Check if user can apply this specific transition
        if (!Application_State_Machine::can_user_apply_transition($post_id, $transition)) {
            wp_die(__('You do not have permission to perform this action.'));
        }
        
        // ... rest of the transition logic ...
    }
}
```

## 📈 Logging & Audit Trail

### State Change Logging

```php
// Add to your state machine callbacks
public static function log_state_change($event) {
    $state_machine = $event->getStateMachine();
    $object = $state_machine->getObject();
    
    if ($object instanceof WP_Post_State_Wrapper) {
        $post = $object->getPost();
        $user_id = get_current_user_id();
        
        // Log the state change
        add_post_meta($post->ID, '_state_change_log', [
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'user_name' => get_userdata($user_id)->display_name ?? 'System',
            'from_state' => $event->getState(),
            'to_state' => $state_machine->getState(),
            'transition' => $event->getTransition(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], false); // false = don't overwrite, create multiple entries
    }
}
```

## 🎨 Frontend Integration

### Shortcode Support

```php
// Register shortcode for displaying state information
add_shortcode('application_state', function($atts) {
    $atts = shortcode_atts([
        'post_id' => get_the_ID(),
        'show_transitions' => false,
        'class' => 'application-state'
    ], $atts);
    
    $post = get_post($atts['post_id']);
    if (!$post) return '';
    
    $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
    $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
    
    $output = '<div class="' . esc_attr($atts['class']) . '">';
    $output .= '<span class="current-state">Status: ' . esc_html(ucfirst($state_machine->getState())) . '</span>';
    
    if ($atts['show_transitions'] && current_user_can('edit_post', $post->ID)) {
        $transitions = $state_machine->getPossibleTransitions();
        if (!empty($transitions)) {
            $output .= '<div class="available-transitions">';
            foreach ($transitions as $transition) {
                $output .= '<button class="transition-btn" data-transition="' . esc_attr($transition) . '" data-post="' . esc_attr($post->ID) . '">';
                $output .= esc_html(ucfirst($transition));
                $output .= '</button>';
            }
            $output .= '</div>';
        }
    }
    
    $output .= '</div>';
    return $output;
});
```

## 🛠 Debugging & Development Tools

### Debug Information

```php
// Add debug information to WordPress admin bar
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    global $post;
    if ($post && $post->post_type === 'job_application') {
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
        
        $wp_admin_bar->add_node([
            'id' => 'sm_debug',
            'title' => 'SM: ' . $state_machine->getState(),
            'meta' => [
                'title' => 'Current State: ' . $state_machine->getState() . 
                          ' | Possible: ' . implode(', ', $state_machine->getPossibleTransitions())
            ]
        ]);
    }
}, 100);
```

## 📝 Configuration Management

### Centralized Configuration

```php
// Create a configuration manager
class SM_Config_Manager {
    private static $configs = [];
    
    public static function register_config($post_type, $config) {
        self::$configs[$post_type] = $config;
    }
    
    public static function get_config($post_type) {
        return self::$configs[$post_type] ?? null;
    }
    
    // Load configs from files or database
    public static function load_configs() {
        $config_dir = plugin_dir_path(__FILE__) . 'configs/';
        foreach (glob($config_dir . '*.php') as $file) {
            $post_type = basename($file, '.php');
            self::$configs[$post_type] = include $file;
        }
    }
}
```

## ⚡ Performance Optimizations

### Caching State Machines

```php
class Cached_State_Machine_Factory {
    private static $cache = [];
    
    public static function get_state_machine($post, $config_name = null) {
        $cache_key = $post->ID . '_' . ($config_name ?? $post->post_type);
        
        if (!isset(self::$cache[$cache_key])) {
            // Create and cache the state machine
            $wrapper = new WP_Post_State_Wrapper($post);
            self::$cache[$cache_key] = new StateMachine($wrapper, $config);
        }
        
        return self::$cache[$cache_key];
    }
    
    public static function clear_cache($post_id = null) {
        if ($post_id) {
            // Clear specific post's cache
            self::$cache = array_filter(self::$cache, function($key) use ($post_id) {
                return strpos($key, $post_id . '_') !== 0;
            }, ARRAY_FILTER_USE_KEY);
        } else {
            // Clear all cache
            self::$cache = [];
        }
    }
}

// Clear cache when post is updated
add_action('save_post', function($post_id) {
    Cached_State_Machine_Factory::clear_cache($post_id);
});
```

## 🧪 Testing Helpers

### Unit Testing Support

```php
// Testing helper class
class SM_Test_Helper {
    public static function create_test_post($post_type = 'job_application', $initial_state = 'new') {
        $post_id = wp_insert_post([
            'post_type' => $post_type,
            'post_title' => 'Test Application',
            'post_status' => 'publish'
        ]);
        
        update_post_meta($post_id, '_application_status', $initial_state);
        
        return get_post($post_id);
    }
    
    public static function assert_state($post, $expected_state) {
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $actual_state = $wrapper->getState();
        
        if ($actual_state !== $expected_state) {
            throw new Exception("Expected state '{$expected_state}', got '{$actual_state}'");
        }
        
        return true;
    }
}
```

These additions would provide a much more complete foundation for implementing the winzou/state-machine in WordPress, covering enterprise-level concerns like security, performance, debugging, and maintainability that the original document touches on but doesn't fully flesh out.

# Additional WordPress Integration Guidelines for winzou/state-machine

## 🔧 Essential WordPress Wrapper Class

The document mentions creating a wrapper class but doesn't provide a complete example. Here's a robust implementation:

```php
// in your-plugin/includes/class-wp-post-state-wrapper.php

class WP_Post_State_Wrapper {
    private $post;
    private $meta_key;
    private $default_state;

    public function __construct(WP_Post $post, string $meta_key = '_state', string $default_state = 'draft') {
        $this->post = $post;
        $this->meta_key = $meta_key;
        $this->default_state = $default_state;
        
        // Ensure the post has a state set
        if (!$this->getState()) {
            $this->setState($default_state);
        }
    }

    public function getState(): string {
        $state = get_post_meta($this->post->ID, $this->meta_key, true);
        return $state ?: $this->default_state;
    }

    public function setState(string $state): void {
        update_post_meta($this->post->ID, $this->meta_key, $state);
        
        // Optional: Clear any object caches
        clean_post_cache($this->post->ID);
    }

    public function getPost(): WP_Post {
        return $this->post;
    }

    public function getId(): int {
        return $this->post->ID;
    }
}
```

## 📊 Database Considerations

### Migration Helper for Existing Posts

```php
// Add to your plugin activation hook
function migrate_existing_posts_to_state_machine() {
    $posts = get_posts([
        'post_type' => 'job_application',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_application_status',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ]);

    foreach ($posts as $post) {
        // Set default state for existing posts
        update_post_meta($post->ID, '_application_status', 'new');
    }
}
register_activation_hook(__FILE__, 'migrate_existing_posts_to_state_machine');
```

### Database Indexes for Performance

```php
// Add to your plugin activation
function add_state_machine_indexes() {
    global $wpdb;
    
    // Add index for faster state queries
    $wpdb->query("
        CREATE INDEX idx_postmeta_state 
        ON {$wpdb->postmeta} (meta_key, meta_value) 
        WHERE meta_key LIKE '%_status'
    ");
}
```

## 🎯 Advanced WordPress Integration Patterns

### 1. WordPress Query Integration

```php
// Add custom query vars for filtering by state
add_filter('query_vars', function($vars) {
    $vars[] = 'application_state';
    return $vars;
});

// Modify main query to filter by state
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query()) {
        $state = get_query_var('application_state');
        if ($state) {
            $query->set('meta_key', '_application_status');
            $query->set('meta_value', $state);
        }
    }
});
```

### 2. WordPress Admin List Table Integration

```php
// Add state column to admin list
add_filter('manage_job_application_posts_columns', function($columns) {
    $columns['application_state'] = 'Status';
    return $columns;
});

add_action('manage_job_application_posts_custom_column', function($column, $post_id) {
    if ($column === 'application_state') {
        $post = get_post($post_id);
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
        
        echo '<span class="state-badge state-' . esc_attr($state_machine->getState()) . '">'
           . esc_html(ucfirst($state_machine->getState()))
           . '</span>';
        
        // Show quick actions
        $transitions = $state_machine->getPossibleTransitions();
        if (!empty($transitions)) {
            echo '<div class="row-actions">';
            foreach ($transitions as $transition) {
                $url = wp_nonce_url(
                    add_query_arg(['action' => 'sm_transition', 'transition' => $transition, 'post' => $post_id]),
                    'sm_transition_' . $post_id
                );
                echo '<span><a href="' . esc_url($url) . '">' . esc_html(ucfirst($transition)) . '</a> | </span>';
            }
            echo '</div>';
        }
    }
}, 10, 2);
```

### 3. Bulk Actions Support

```php
// Add bulk state transitions
add_filter('bulk_actions-edit-job_application', function($actions) {
    $actions['bulk_approve'] = 'Bulk Approve';
    $actions['bulk_reject'] = 'Bulk Reject';
    return $actions;
});

add_filter('handle_bulk_actions-edit-job_application', function($redirect_to, $action, $post_ids) {
    if (strpos($action, 'bulk_') === 0) {
        $transition = str_replace('bulk_', '', $action);
        $processed = 0;
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
            $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
            
            if ($state_machine->can($transition)) {
                $state_machine->apply($transition);
                $processed++;
            }
        }
        
        $redirect_to = add_query_arg('bulk_processed', $processed, $redirect_to);
    }
    
    return $redirect_to;
}, 10, 3);
```

## 🔒 Security & Permissions

### Capability-Based Transitions

```php
class Application_State_Machine {
    // Add capability checking to your state machine
    public static function can_user_apply_transition($post_id, $transition, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Define capability requirements per transition
        $capabilities = [
            'screen' => 'edit_applications',
            'invite' => 'manage_interviews', 
            'reject' => 'manage_applications',
            'hire' => 'hire_candidates'
        ];
        
        $required_cap = $capabilities[$transition] ?? 'edit_post';
        
        return current_user_can($required_cap, $post_id);
    }
}

// Modify your transition handlers to check capabilities
function handle_application_transition($post_id) {
    // ... existing nonce and autosave checks ...
    
    if (isset($_POST['sm_transition'])) {
        $transition = sanitize_text_field($_POST['sm_transition']);
        
        // Check if user can apply this specific transition
        if (!Application_State_Machine::can_user_apply_transition($post_id, $transition)) {
            wp_die(__('You do not have permission to perform this action.'));
        }
        
        // ... rest of the transition logic ...
    }
}
```

## 📈 Logging & Audit Trail

### State Change Logging

```php
// Add to your state machine callbacks
public static function log_state_change($event) {
    $state_machine = $event->getStateMachine();
    $object = $state_machine->getObject();
    
    if ($object instanceof WP_Post_State_Wrapper) {
        $post = $object->getPost();
        $user_id = get_current_user_id();
        
        // Log the state change
        add_post_meta($post->ID, '_state_change_log', [
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'user_name' => get_userdata($user_id)->display_name ?? 'System',
            'from_state' => $event->getState(),
            'to_state' => $state_machine->getState(),
            'transition' => $event->getTransition(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], false); // false = don't overwrite, create multiple entries
    }
}
```

## 🎨 Frontend Integration

### Shortcode Support

```php
// Register shortcode for displaying state information
add_shortcode('application_state', function($atts) {
    $atts = shortcode_atts([
        'post_id' => get_the_ID(),
        'show_transitions' => false,
        'class' => 'application-state'
    ], $atts);
    
    $post = get_post($atts['post_id']);
    if (!$post) return '';
    
    $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
    $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
    
    $output = '<div class="' . esc_attr($atts['class']) . '">';
    $output .= '<span class="current-state">Status: ' . esc_html(ucfirst($state_machine->getState())) . '</span>';
    
    if ($atts['show_transitions'] && current_user_can('edit_post', $post->ID)) {
        $transitions = $state_machine->getPossibleTransitions();
        if (!empty($transitions)) {
            $output .= '<div class="available-transitions">';
            foreach ($transitions as $transition) {
                $output .= '<button class="transition-btn" data-transition="' . esc_attr($transition) . '" data-post="' . esc_attr($post->ID) . '">';
                $output .= esc_html(ucfirst($transition));
                $output .= '</button>';
            }
            $output .= '</div>';
        }
    }
    
    $output .= '</div>';
    return $output;
});
```

## 🛠 Debugging & Development Tools

### Debug Information

```php
// Add debug information to WordPress admin bar
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    global $post;
    if ($post && $post->post_type === 'job_application') {
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $state_machine = Application_State_Machine::get_state_machine_from_wrapper($wrapper);
        
        $wp_admin_bar->add_node([
            'id' => 'sm_debug',
            'title' => 'SM: ' . $state_machine->getState(),
            'meta' => [
                'title' => 'Current State: ' . $state_machine->getState() . 
                          ' | Possible: ' . implode(', ', $state_machine->getPossibleTransitions())
            ]
        ]);
    }
}, 100);
```

## 📝 Configuration Management

### Centralized Configuration

```php
// Create a configuration manager
class SM_Config_Manager {
    private static $configs = [];
    
    public static function register_config($post_type, $config) {
        self::$configs[$post_type] = $config;
    }
    
    public static function get_config($post_type) {
        return self::$configs[$post_type] ?? null;
    }
    
    // Load configs from files or database
    public static function load_configs() {
        $config_dir = plugin_dir_path(__FILE__) . 'configs/';
        foreach (glob($config_dir . '*.php') as $file) {
            $post_type = basename($file, '.php');
            self::$configs[$post_type] = include $file;
        }
    }
}
```

## ⚡ Performance Optimizations

### Caching State Machines

```php
class Cached_State_Machine_Factory {
    private static $cache = [];
    
    public static function get_state_machine($post, $config_name = null) {
        $cache_key = $post->ID . '_' . ($config_name ?? $post->post_type);
        
        if (!isset(self::$cache[$cache_key])) {
            // Create and cache the state machine
            $wrapper = new WP_Post_State_Wrapper($post);
            self::$cache[$cache_key] = new StateMachine($wrapper, $config);
        }
        
        return self::$cache[$cache_key];
    }
    
    public static function clear_cache($post_id = null) {
        if ($post_id) {
            // Clear specific post's cache
            self::$cache = array_filter(self::$cache, function($key) use ($post_id) {
                return strpos($key, $post_id . '_') !== 0;
            }, ARRAY_FILTER_USE_KEY);
        } else {
            // Clear all cache
            self::$cache = [];
        }
    }
}

// Clear cache when post is updated
add_action('save_post', function($post_id) {
    Cached_State_Machine_Factory::clear_cache($post_id);
});
```

## 🧪 Testing Helpers

### Unit Testing Support

```php
// Testing helper class
class SM_Test_Helper {
    public static function create_test_post($post_type = 'job_application', $initial_state = 'new') {
        $post_id = wp_insert_post([
            'post_type' => $post_type,
            'post_title' => 'Test Application',
            'post_status' => 'publish'
        ]);
        
        update_post_meta($post_id, '_application_status', $initial_state);
        
        return get_post($post_id);
    }
    
    public static function assert_state($post, $expected_state) {
        $wrapper = new WP_Post_State_Wrapper($post, '_application_status');
        $actual_state = $wrapper->getState();
        
        if ($actual_state !== $expected_state) {
            throw new Exception("Expected state '{$expected_state}', got '{$actual_state}'");
        }
        
        return true;
    }
}
```

---

These additions provide a much more complete foundation for implementing the winzou/state-machine in WordPress, covering enterprise-level concerns like security, performance, debugging, and maintainability that the original document touches on but doesn't fully flesh out.
