<?php

namespace WpStateMachine;

/**
 * A simple domain object for the FSM proof of concept.
 * Its state is persisted in the WordPress options table.
 */
class DomainObject
{
    /**
     * The option key used to store the state in the wp_options table.
     */
    public const OPTION_KEY = 'wp_fsm_poc_state';

    /**
     * The current state of the object.
     *
     * @var string
     */
    private $state;

    public function __construct()
    {
        // When the object is created, load its state from the database.
        // If it's not set, default to 'checkout'.
        $this->state = get_option(self::OPTION_KEY, 'checkout');
    }

    /**
     * Gets the current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Sets the new state and saves it to the database.
     */
    public function setState(string $state): void
    {
        $this->state = $state;
        update_option(self::OPTION_KEY, $state);
    }
}
