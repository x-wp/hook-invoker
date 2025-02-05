<?php
/**
 * Helper functions for Hook Invoker
 *
 * @package eXtended WordPress
 * @subpackage Functions
 */

use XWP\DI\Handler_Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Invoker;

/**
 * Register a handler with the Invoker.
 *
 * @param  string ...$handlers The handlers to register.
 * @return Invoker
 */
function xwp_register_handler( string ...$handlers ): Invoker {
    return xwp_hook_invoker()->register_handlers( ...$handlers );
}

/**
 * Create a handler for a given instance.
 *
 * @template TObj of object
 * @param  TObj $instance The instance to create a handler for.
 * @return Can_Handle<TObj>
 */
function xwp_create_handler( object $instance ): Can_Handle {
    return Handler_Factory::from_instance( $instance, 'xwp-hook' );
}

/**
 * Load a handler for a given instance.
 *
 * @template TObj of object
 * @param  TObj $instance The instance to load a handler for.
 * @return Invoker
 */
function xwp_load_handler( object $instance ): Invoker {
    return xwp_hook_invoker()->load_handler( $instance, 'xwp-hook' );
}

/**
 * Load hooks for a given handler.
 *
 * @template TObj of object
 * @template THnd of Can_Handle<TObj>
 * @param  THnd                                           $handler Handler instance.
 * @param  array<string,array<int,Can_Invoke<TObj,THnd>>> $hooks   The hooks to load.
 * @return Invoker
 */
function xwp_load_hooks( Can_Handle $handler, array $hooks ): Invoker {
    return xwp_hook_invoker()->load_hooks( $handler, $hooks );
}
