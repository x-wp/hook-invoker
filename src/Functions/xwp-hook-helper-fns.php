<?php
/**
 * Helper functions for Hook Invoker
 *
 * @package eXtended WordPress
 * @subpackage Functions
 */

use XWP\Hook\Invoker;

/**
 * Register a handler with the Invoker.
 *
 * @param  string|object ...$handlers The handlers to register.
 * @return Invoker
 */
function xwp_register_handler( string|object ...$handlers ): Invoker {
    return Invoker::instance()->register_handlers( ...$handlers );
}
