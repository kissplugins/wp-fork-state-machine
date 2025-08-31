<?php
namespace KissPlugins\FsmDemo;

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

        if (!$job_id || !$event) {
            return new \WP_Error('missing_params', 'Job ID and event are required', ['status' => 400]);
        }

        // Get job from database
        $jobData = Store::getJob($wpdb, $job_id);
        if (!$jobData) {
            return new \WP_Error('job_not_found', 'Job not found', ['status' => 404]);
        }

        // Create job object for state machine
        $jobObject = Engine::createJobObject($jobData);

        try {
            // Get state machine
            $stateMachine = Engine::getStateMachine($jobObject);

            // Map frontend events to backend transitions
            $transitionMap = [
                'START' => 'start',
                'PROGRESS' => 'progress',
                'SUCCESS' => 'success_upload',
                'FAIL_TEMP' => 'fail_temp',
                'FAIL_PERM' => 'fail_perm',
                'RETRY' => 'retry',
                'ABORT' => 'abort',
                'RESET' => 'reset'
            ];

            $transition = $transitionMap[$event] ?? $event;

            if ($stateMachine->can($transition)) {
                $fromState = $stateMachine->getState();
                $stateMachine->apply($transition);
                $toState = $stateMachine->getState();

                // Add log entry
                $jobObject->addLogEntry('info', "Transition: {$event}", [
                    'from' => $fromState,
                    'to' => $toState,
                    'transition' => $transition
                ]);

                // Update database
                Store::updateJob($wpdb, $job_id, $jobObject);

                return new \WP_REST_Response([
                    'success' => true,
                    'from' => $fromState,
                    'to' => $toState,
                    'state' => $toState,
                    'allowed' => array_keys($stateMachine->getPossibleTransitions()),
                    'event' => $event,
                    'transition' => $transition
                ], 200);
            } else {
                return new \WP_Error('transition_not_allowed', "Transition '{$transition}' not allowed from state '{$stateMachine->getState()}'", ['status' => 400]);
            }
        } catch (\Exception $e) {
            error_log("FSM Transition Error: " . $e->getMessage());
            return new \WP_Error('fsm_error', 'State machine error: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}

Rest::init();