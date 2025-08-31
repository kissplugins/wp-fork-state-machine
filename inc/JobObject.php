<?php
namespace KissPlugins\FsmDemo;

/**
 * Job Object - Represents a job in the FSM demo
 * This class wraps database job data for use with the state machine
 */
class JobObject {
    public $id;
    public $state;
    public $version;
    public $updated_at;
    public $log;

    public function __construct($jobData) {
        if (is_object($jobData)) {
            $this->id = $jobData->id ?? null;
            $this->state = $jobData->state ?? 'idle';
            $this->version = $jobData->version ?? 0;
            $this->updated_at = $jobData->updated_at ?? null;
            $this->log = $jobData->log ?? '[]';
        } elseif (is_array($jobData)) {
            $this->id = $jobData['id'] ?? null;
            $this->state = $jobData['state'] ?? 'idle';
            $this->version = $jobData['version'] ?? 0;
            $this->updated_at = $jobData['updated_at'] ?? null;
            $this->log = $jobData['log'] ?? '[]';
        } else {
            // Default values for new job
            $this->id = null;
            $this->state = 'idle';
            $this->version = 0;
            $this->updated_at = null;
            $this->log = '[]';
        }
    }

    /**
     * Get the current state (required by state machine)
     */
    public function getState() {
        return $this->state;
    }

    /**
     * Set the state (used by state machine)
     */
    public function setState($state) {
        $this->state = $state;
    }

    /**
     * Add a log entry
     */
    public function addLogEntry($type, $message, $extra = null) {
        $logData = json_decode($this->log, true) ?: [];
        $logData[] = [
            'timestamp' => current_time('mysql', 1),
            'type' => $type,
            'message' => $message,
            'extra' => $extra
        ];
        
        // Keep only last 100 entries
        if (count($logData) > 100) {
            $logData = array_slice($logData, -100);
        }
        
        $this->log = json_encode($logData);
    }

    /**
     * Get log entries
     */
    public function getLogEntries() {
        return json_decode($this->log, true) ?: [];
    }

    /**
     * Convert to array for database storage
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'version' => $this->version,
            'updated_at' => $this->updated_at,
            'log' => $this->log
        ];
    }
}
