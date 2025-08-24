# Integrating `winzou/state-machine` with ACF Pro in a WordPress Plugin

This guide provides an actionable checklist and detailed examples for integrating the `winzou/state-machine` library into a WordPress plugin using **Advanced Custom Fields (ACF) Pro** to manage the state field.

-----

## Why use ACF with the State Machine?

  * **Simplified UI:** ACF provides user-friendly field types like "Select" or "Radio" that are perfect for displaying and managing a list of defined states.
  * **Reliable Data Storage:** ACF handles the complexities of saving and retrieving metadata, making the integration cleaner and more reliable.
  * **Conditional Logic:** You can use ACF's powerful conditional logic to show or hide other custom fields based on the current state of your object.

## ✅ Actionable To-Do Checklist (with ACF)

  - [ ] **1. Set up Your Development Environment:**

      - [ ] Ensure you have a local WordPress installation.
      - [ ] Install and activate the **ACF Pro** plugin.
      - [ ] Install Composer on your development machine.

  - [ ] **2. Initialize Composer in Your Plugin:**

      - [ ] Navigate to your plugin's directory and run `composer require winzou/state-machine`.

  - [ ] **3. Include the Composer Autoloader:**

      - [ ] In your main plugin file, add `require_once __DIR__ . '/vendor/autoload.php';`.

  - [ ] **4. Create an ACF Field Group:**

      - [ ] In the WordPress admin, go to **ACF \> Field Groups \> Add New**.
      - [ ] Create a "Select" or "Radio Button" field to store the status. Let's name the field `application_status`.
      - [ ] In the "Choices" setting for the field, add your defined states (e.g., `new`, `screening`, `interviewing`).
      - [ ] Set the "Default Value" to your initial state (e.g., `new`).
      - [ ] **Important:** To make the field read-only for users, you can either set it to "Disabled" in the ACF field settings or use the code-based approach shown below.
      - [ ] Assign this field group to your custom post type (e.g., "Job Application").

  - [ ] **5. Design and Configure Your State Machine:**

      - [ ] Create a PHP class to manage your state machine's configuration.
      - [ ] **Crucially, modify the `property_path` to be compatible with ACF.** Since the state machine can't call ACF functions directly, we will create a custom object wrapper.

  - [ ] **6. Integrate with WordPress Admin:**

      - [ ] Create UI elements (e.g., admin bar links or buttons in a custom metabox) to trigger transitions.
      - [ ] Handle the logic to apply transitions upon user action.

  - [ ] **7. Implement JavaScript Interaction (Optional):**

      - [ ] Create custom WordPress REST API endpoints to allow frontend interactions.
      - [ ] Write JavaScript to call these endpoints, ensuring proper security.

-----

## Sample Code and Approaches (with ACF)

Let's continue with our "Job Application" custom post type example.

### 1️⃣ **The Post Wrapper (Key for ACF Integration)**

Because the state machine needs a standard PHP object with getters and setters, and `WP_Post` doesn't work directly with ACF's `get_field`/`update_field` functions, we create a simple wrapper.

```php
// in your-plugin/includes/class-acf-post-wrapper.php

class ACF_Post_Wrapper {
    private $post;
    private $state_field_name;

    public function __construct(WP_Post $post, string $state_field_name) {
        $this->post = $post;
        $this->state_field_name = $state_field_name;
    }

    public function getState() {
        return get_field($this->state_field_name, $this->post->ID);
    }

    public function setState($state) {
        update_field($this->state_field_name, $state, $this->post->ID);
    }

    public function getPost() {
        return $this->post;
    }
}
```

### 2️⃣ **State Machine Configuration (Modified for ACF)**

Now, our state machine class will use this wrapper.

```php
// in your-plugin/includes/class-application-state-machine.php

use SM\Factory\Factory;
use SM\StateMachine\StateMachineInterface;

class Application_State_Machine {

    private static $factory;

    public static function get_config() {
        return [
            'graph'         => 'application_workflow',
            'class'         => ACF_Post_Wrapper::class, // We target our wrapper class
            'property_path' => 'state', // This now calls getState() and setState() on our wrapper
            'states'        => [ /* ... same as before ... */ ],
            'transitions'   => [ /* ... same as before ... */ ],
            'callbacks'     => [ /* ... same as before ... */ ],
        ];
    }

    public static function get_factory() {
        if (!self::$factory) {
            self::$factory = new Factory([self::get_config()]);
        }
        return self::$factory;
    }

    /**
     * @param WP_Post $post The post object
     * @return StateMachineInterface
     */
    public static function get_state_machine(WP_Post $post): StateMachineInterface {
        $wrapper = new ACF_Post_Wrapper($post, 'application_status'); // The ACF field name
        return self::get_factory()->get($wrapper, 'application_workflow');
    }
}
```

### 3️⃣ **Integrating with the WordPress Admin**

Instead of a custom metabox for buttons, a cleaner approach with ACF is to use the **Admin Bar** or a custom column in the post list table. Here's how to add buttons to a metabox for demonstration.

```php
// In your main plugin file

add_action('add_meta_boxes_job_application', 'add_application_actions_metabox');
function add_application_actions_metabox() {
    add_meta_box(
        'application_actions',
        'Workflow Actions',
        'render_application_actions_metabox',
        'job_application',
        'side',
        'high'
    );
}

function render_application_actions_metabox($post) {
    $state_machine = Application_State_Machine::get_state_machine($post);
    $possible_transitions = $state_machine->getPossibleTransitions();

    if (!empty($possible_transitions)) {
        echo '<h4>Available Actions:</h4>';
        foreach ($possible_transitions as $transition) {
            $url = add_query_arg([
                'post' => $post->ID,
                'sm_transition' => $transition,
                '_wpnonce' => wp_create_nonce('application_transition_nonce')
            ]);
            echo '<a href="' . esc_url($url) . '" class="button">' . esc_html(ucfirst($transition)) . '</a> ';
        }
    } else {
        echo "<p>No further actions available.</p>";
    }
}

// Handle the transition when a button is clicked
add_action('admin_init', 'handle_application_transition_from_url');
function handle_application_transition_from_url() {
    if (
        !isset($_GET['sm_transition']) ||
        !isset($_GET['_wpnonce']) ||
        !wp_verify_nonce($_GET['_wpnonce'], 'application_transition_nonce') ||
        !isset($_GET['post'])
    ) {
        return;
    }

    $post_id = (int) $_GET['post'];
    if (!current_user_can('edit_post', $post_id)) {
        wp_die('Permission denied.');
    }

    $post = get_post($post_id);
    $state_machine = Application_State_Machine::get_state_machine($post);
    $transition = sanitize_text_field($_GET['sm_transition']);

    if ($state_machine->can($transition)) {
        $state_machine->apply($transition);
        // Redirect to remove the query args from the URL
        wp_safe_redirect(get_edit_post_link($post_id, 'raw'));
        exit;
    }
}
```

This approach provides a much cleaner and more robust way to manage complex states in WordPress by leveraging the power of ACF for the user interface and data storage.
