<?php
/**
 * Helper functions for Hook Invoker
 *
 * @package eXtended WordPress
 * @subpackage Functions
 */

use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Invokable;
use XWP\Hook\Invoker;

/**
 * Register a handler with the Invoker.
 *
 * @param  string ...$handlers The handlers to register.
 * @return Invoker
 */
function xwp_register_handler( string ...$handlers ): Invoker {
    return Invoker::instance()->register_handlers( ...$handlers );
}

/**
 * Create a handler for a given instance.
 *
 * @template THndlr of object
 * @param  THndlr $instance The instance to create a handler for.
 * @return Initializable<THndlr>
 */
function xwp_create_handler( object $instance ): Initializable {
    return Invoker::instance()->create_handler( $instance );
}

/**
 * Load a handler for a given instance.
 *
 * @template THndlr of object
 * @param  THndlr $instance The instance to load a handler for.
 * @return Invoker
 */
function xwp_load_handler( object $instance ): Invoker {
    return Invoker::instance()->load_handler( $instance );
}

/**
 * Load hooks for a given handler.
 *
 * @template THndlr of object
 * @param  Initializable<THndlr>    $handler Handler instance.
 * @param  array<Invokable<THndlr>> $hooks   The hooks to load.
 * @return Invoker
 */
function xwp_load_hooks( Initializable $handler, array $hooks ): Invoker {
    return Invoker::instance()->load_hooks( $handler, $hooks );
}
