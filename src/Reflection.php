<?php
/**
 * Reflection class file.
 *
 * @package eXtended WordPress
 */

namespace XWP\Hook;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Reflector;
use XWP\Contracts\Hook\Accessible_Hook_Methods;
use XWP\Contracts\Hook\Invokable;

/**
 * Reflection utilities.
 */
final class Reflection {
    /**
     * Get a reflector for the target.
     *
     * @param  mixed $target The target to get a reflector for.
     * @return ReflectionClass|ReflectionMethod|ReflectionFunction
     *
     * @throws \InvalidArgumentException If the target is invalid.
     */
    public static function get_reflector( mixed $target ): Reflector {
        return match ( true ) {
            $target instanceof Reflector        => $target,
            self::is_valid_class( $target )    => new ReflectionClass( $target ),
            self::is_valid_method( $target )   => new ReflectionMethod( ...$target ),
            self::is_valid_function( $target ) => new ReflectionFunction( $target ),
            default => throw new \InvalidArgumentException( 'Invalid target' ),
        };
    }

    /**
     * Is the target callable.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_callable( mixed $target ): bool {
        return self::is_valid_method( $target ) || self::is_valid_function( $target );
    }

    /**
     * Is the target a valid class.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_class( mixed $target ): bool {
        return \is_object( $target ) || \class_exists( $target );
    }

    /**
     * Is the target a valid method.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_method( mixed $target ): bool {
        return \is_array( $target ) && \is_callable( $target );
    }

    /**
     * Is the target a valid function.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    public static function is_valid_function( mixed $target ): bool {
        return \is_string( $target ) && ( \function_exists( $target ) || \is_callable( $target ) );
    }

    /**
     * Get decorators for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_attributes(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
	): array {
        return self::get_reflector( $target )
            ->getAttributes( $decorator, $flags );
    }

    /**
     * Get decorators for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_decorators(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        return \array_map(
            static fn( $att ) => $att->newInstance(),
            self::get_attributes( $target, $decorator, $flags ),
        );
    }

    /**
     * Get decorators for a target class, and its parent classes.
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @return array<T>
     */
    public static function get_decorators_deep(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
    ): array {
        $decorators = array();

        while ( $target ) {
            $decorators = \array_merge(
                $decorators,
                self::get_decorators( $target, $decorator, $flags ),
            );

            $target = $target instanceof ReflectionClass
                ? $target->getParentClass()
                : \get_parent_class( $target );
        }

        return $decorators;
    }

    /**
     * Get a **SINGLE** attribute for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  int             $index     The index of the decorator to get.
     * @return T|null
     */
    public static function get_attribute(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?ReflectionAttribute {
        return self::get_attributes( $target, $decorator, $flags )[ $index ] ?? null;
    }

    /**
     * Get a **SINGLE** decorator for a target
     *
     * @template T
     * @param  Reflector|mixed $target    The target to get decorators for.
     * @param  class-string<T> $decorator The decorator to get.
     * @param  int|null        $flags     Flags to pass to getAttributes.
     * @param  int             $index     The index of the decorator to get.
     * @return T|null
     */
    public static function get_decorator(
        mixed $target,
        string $decorator,
        ?int $flags = ReflectionAttribute::IS_INSTANCEOF,
        int $index = 0,
    ): ?object {
        return self::get_attribute( $target, $decorator, $flags, $index )
            ?->newInstance()
            ?? null;
    }

    /**
     * Get the hooked methods for a handler.
     *
     * @param  ReflectionClass $r The reflection class to get the methods for.
     * @return array<ReflectionMethod>
     */
    public static function get_hookable_methods( ReflectionClass $r ): array {
        $traits = self::class_uses_deep( $r->getName() );

        return \array_filter(
            $r->getMethods( self::get_method_types( $traits ) ),
            self::is_method_hookable( ... ),
        );
    }

    /**
     * Get all the traits used by a class.
     *
     * @param  string|object $target Class or object to get the traits for.
     * @param  bool          $autoload        Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return array                          Array of traits.
     */
	public static function class_uses_deep( string|object $target, bool $autoload = true ) {
		$traits = array();

		do {
			$traits = \array_merge( \class_uses( $target, $autoload ), $traits );
            $target = \get_parent_class( $target );
		} while ( $target );

		foreach ( $traits as $trait ) {
			$traits = \array_merge( \class_uses( $trait, $autoload ), $traits );
		}

		return \array_values( \array_unique( $traits ) );
	}

    /**
     * Get the method types to include.
     *
     * @param  array<string> $traits The traits to check.
     * @return int
     */
    public static function get_method_types( array $traits ): int {
        $include = ReflectionMethod::IS_PUBLIC;

        if ( \in_array( Accessible_Hook_Methods::class, $traits, true ) ) {
            $include |= ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PROTECTED;
        }

        return $include;
    }

    /**
     * Check if a method is hookable.
     *
     * @param  ReflectionMethod $m The method to check.
     * @return bool
     */
    private static function is_method_hookable( ReflectionMethod $m, ): bool {
        $ignore = array( '__call', '__callStatic', 'check_method_access', 'is_method_valid', 'get_registered_hooks', '__construct' );
        return ! \in_array( $m->getName(), $ignore, true ) &&
            ! $m->isStatic() && self::get_attribute( $m, Invokable::class );
    }
}
