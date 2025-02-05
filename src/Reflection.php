<?php
/**
 * Reflection class file.
 *
 * @package eXtended WordPress
 */

namespace XWP\Hook;

use ReflectionMethod as Method;
use XWP\Contracts\Hook\Accessible_Hook_Methods;
use XWP\Contracts\Hook\Invokable;

/**
 * Reflection utilities.
 */
final class Reflection extends \XWP\Helper\Classes\Reflection {
    /**
     * Get the hooked methods for a handler.
     *
     * @template T of object
     * @param  \ReflectionClass<T> $r The reflection class to get the methods for.
     * @return array<Method>
     */
    public static function get_hookable_methods( \ReflectionClass $r ): array {
        $traits = self::class_uses_deep( $r->getName() );

        return \array_filter(
            $r->getMethods( self::get_method_types( $traits ) ),
            self::is_method_hookable( ... ),
        );
    }

    /**
     * Get the method types to include.
     *
     * @param  array<class-string> $traits The traits to check.
     * @return int
     */
    public static function get_method_types( array $traits ): int {
        $include = Method::IS_PUBLIC;

        if ( \in_array( Accessible_Hook_Methods::class, $traits, true ) ) {
            $include |= Method::IS_PRIVATE | Method::IS_PROTECTED;
        }

        return $include;
    }

    /**
     * Check if a method is hookable.
     *
     * @param  Method $m The method to check.
     * @return bool
     */
    private static function is_method_hookable( Method $m, ): bool {
        $ignore = array( '__call', '__callStatic', 'check_method_access', 'is_method_valid', 'get_registered_hooks', '__construct' );
        return ! \in_array( $m->getName(), $ignore, true ) &&
            ! $m->isStatic() && self::get_attribute( $m, Invokable::class );
    }
}
