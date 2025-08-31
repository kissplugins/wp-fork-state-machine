<?php
namespace KissPlugins\FsmDemo;

use SM\Factory\Factory;

/**
 * FSM Engine - Handles state machine operations for demo jobs
 */
class Engine {
    private static $factory = null;

    /**
     * Get or create the FSM factory instance
     */
    public static function getFactory() {
        if (self::$factory === null) {
            $configs = [
                [
                    'class' => 'KissPlugins\FsmDemo\JobObject',
                    'graph' => 'media_upload',
                    'property_path' => 'state',
                    'states' => ['idle', 'uploading', 'processing', 'failed_retryable', 'failed_permanent', 'done'],
                    'transitions' => [
                        'start' => [
                            'from' => ['idle'],
                            'to' => 'uploading'
                        ],
                        'progress' => [
                            'from' => ['uploading'],
                            'to' => 'uploading'
                        ],
                        'success_upload' => [
                            'from' => ['uploading'],
                            'to' => 'processing'
                        ],
                        'success_process' => [
                            'from' => ['processing'],
                            'to' => 'done'
                        ],
                        'fail_temp' => [
                            'from' => ['uploading', 'processing'],
                            'to' => 'failed_retryable'
                        ],
                        'fail_perm' => [
                            'from' => ['uploading', 'processing'],
                            'to' => 'failed_permanent'
                        ],
                        'retry' => [
                            'from' => ['failed_retryable'],
                            'to' => 'uploading'
                        ],
                        'abort' => [
                            'from' => ['idle', 'uploading', 'processing'],
                            'to' => 'failed_permanent'
                        ],
                        'reset' => [
                            'from' => ['idle', 'uploading', 'processing', 'failed_retryable', 'failed_permanent', 'done'],
                            'to' => 'idle'
                        ]
                    ],
                    'callbacks' => [
                        'after' => [
                            'log_transition' => [
                                'on' => ['start', 'success_upload', 'success_process', 'fail_temp', 'fail_perm', 'retry', 'abort', 'reset'],
                                'do' => [self::class, 'logTransition']
                            ]
                        ]
                    ]
                ]
            ];

            self::$factory = new Factory($configs);
        }

        return self::$factory;
    }

    /**
     * Get state machine for a job object
     */
    public static function getStateMachine($jobObject) {
        return self::getFactory()->get($jobObject, 'media_upload');
    }

    /**
     * Create a job object from database row
     */
    public static function createJobObject($jobData) {
        return new JobObject($jobData);
    }

    /**
     * Callback to log transitions
     */
    public static function logTransition($event) {
        // This will be called after each transition
        error_log("FSM Transition: {$event->getTransition()} from {$event->getFromState()} to {$event->getToState()}");
    }
}
