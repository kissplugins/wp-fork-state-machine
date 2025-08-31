<?php
namespace KissPlugins\FsmDemo;

use SM\Factory\Factory;

class Rest {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('kiss-fsm/v1', '/jobs', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_job'],
            'permission_callback' => function () {
                return current_user_can('read');
            }
        ]);

        register_rest_route('kiss-fsm/v1', '/jobs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_job'],
            'permission_callback' => function () {
                return current_user_can('read');
            }
        ]);

        register_rest_route('kiss-fsm/v1', '/jobs/(?P<id>\d+)/transition', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'transition'],
            'permission_callback' => function () {
                return current_user_can('read');
            }
        ]);
    }

    public static function create_job($req) {
        global $wpdb;
        $job_id = Store::createJob($wpdb);
        return new \WP_REST_Response(['id' => $job_id], 200);
    }

    public static function get_job($req) {
        global $wpdb;
        $job_id = $req->get_param('id');
        $job = Store::getJob($wpdb, $job_id);
        return new \WP_REST_Response($job, 200);
    }

    public static function transition($req) {
        global $wpdb;
        $job_id = $req->get_param('id');
        $event = $req->get_param('event');

        $job = Store::getJob($wpdb, $job_id);

        $graph = Graphs::media_upload();

        $factory = new Factory($graph);
        $statemachine = $factory->get($job, 'state');

        if ($statemachine->can($event)) {
            $statemachine->apply($event);
            $wpdb->update(
                $wpdb->prefix . 'fsm_demo_jobs',
                [
                    'state' => $job->state,
                    'updated_at' => current_time('mysql', 1),
                ],
                ['id' => $job_id]
            );

            return new \WP_REST_Response([
                'from' => $statemachine->getTransition()->getFromState(),
                'to' => $statemachine->getTransition()->getToState(),
                'state' => $job->state,
                'allowed' => array_keys($statemachine->getPossibleTransitions()),
            ], 200);
        } else {
            return new \WP_Error('transition_not_allowed', 'Transition not allowed', ['status' => 400]);
        }
    }
}

Rest::init();