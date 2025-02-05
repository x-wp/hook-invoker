<?php
/**
 * Accessible_Hook_Methods trait file.
 *
 * @package eXtended WordPress
 * @subpackage Compat
 */

namespace XWP\Contracts\Hook;

use XWP\DI\Traits\Accessible_Hook_Methods as DI_Hook_Methods;

/**
 * Hook methods accessible from the outside.
 */
trait Accessible_Hook_Methods {
    use DI_Hook_Methods {
        get_registered_hooks as private;
    }

    /**
     * Get the valid hooks for a class and method.
     *
     * @param  string $classname Class name.
     * @param  string $method    Method name.
     * @return array
     */
    protected static function get_registered_hooks( string $classname, string $method ): array {
        static::$hooks[ $classname ][ $method ] ??= \array_unique(
            \wp_list_pluck( \xwp_hook_invoker()->get_hooks( $classname )[ $method ] ?? array(), 'tag' ),
        );

        return static::$hooks[ $classname ][ $method ];
    }
}
