<?php
namespace KissPlugins\FsmDemo;

class Shortcodes {
  public static function init() {
    add_shortcode('fsm_decision_demo', [__CLASS__, 'renderDemo']);
  }

  public static function renderDemo($atts=[]) {
    wp_enqueue_style('fsm-demo-css', FSM_DEMO_URL.'assets/css/demo.css', [], FSM_DEMO_VERSION);
    wp_enqueue_script('fsm-demo-js', FSM_DEMO_URL.'assets/js/demo.js', [], FSM_DEMO_VERSION, true);
    wp_localize_script('fsm-demo-js', 'fsm_demo_data', [
        'api_url' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ]);
    include FSM_DEMO_DIR.'templates/shortcode-demo.php';
    return ob_get_clean();
  }
}
