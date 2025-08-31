<?php
namespace KissPlugins\FsmDemo;

class Store {
  public static function createJob($wpdb) {
    $wpdb->insert("{$wpdb->prefix}fsm_demo_jobs", [
      'state' => 'idle',
      'version' => 0,
      'updated_at' => current_time('mysql', 1),
      'log' => json_encode([])
    ]);
    return $wpdb->insert_id;
  }

  public static function getJob($wpdb, $id) {
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fsm_demo_jobs WHERE id=%d", $id));
  }
}
