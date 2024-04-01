<?php
/**
 * Invoker class file.
 *
 * @package eXtended WordPress-DI
 * @subpackage Services
 */

namespace XWP\Hook;

use Automattic\Jetpack\Constants;
use XWP\Contracts\Hook\Context;
use XWP\Contracts\Hook\Context_Interface;
use XWP\Contracts\Hook\Hook_Interface;
use XWP\Contracts\Hook\Invoker_Interface;

/**
 * Executes the registered handlers.
 */
class Invoker implements Invoker_Interface {
    /**
     * Hook manager instance.
     *
     * @var Invoker|null
     */
    private static ?Invoker $instance = null;

    /**
     * Current execution context.
     *
     * @var Context
     */
    private Context $context;

    /**
     * Is the manager initialized.
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Killswitch request parameter.
     *
     * @var string|false
     */
    private readonly string|false $killswitch;

    /**
     * Handler instances
     *
     * @var array<string, object>
     */
    private array $objects = array();

    /**
     * Array of handler data
     *
     * @var array<string, Hook_Interface>
     */
    private array $handlers = array();

    /**
     * Array of registered hooks.
     *
     * @var array<string, array<string, Hook_Interface>>
     */
    public array $hooks = array();

    /**
     * Get the instance of the Hook Manager.
     *
     * @return Invoker
     */
    public static function instance(): static {
        return static::$instance ??= new static();
    }

    /**
     * Prevent cloning
     *
     * @throws \BadMethodCallException If cloning is attempted.
     */
    public function __clone() {
        throw new \BadMethodCallException( 'Cloning is not allowed' );
    }

    /**
     * Prevent unserializing
     *
     * @throws \BadMethodCallException If unserializing is attempted.
     */
    public function __wakeup() {
        throw new \BadMethodCallException( 'Unserializing is not allowed' );
    }

    /**
     * Protected constructor to prevent creating a new instance
     */
    protected function __construct() {
        $this->context    = Context_Host::get_context();
        $this->killswitch = $this->get_killswitch();

        $this->initialize();
    }

    /**
     * Initialize the invoker.
     */
    private function initialize() {
        $this->register_handler( Debug\Debug_Handler::class );

        $this->initialized = true;
    }

    /**
     * Get the killswitch.
     *
     * @return string|false
     */
    private function get_killswitch(): string|false {
        return Constants::is_true( 'XWP_HOOK_DEBUG' )
            ? Constants::get_constant( 'XWP_HOOK_KILLSWITCH' ) ?? false
            : false;
    }

    /**
     * Checks if the killswitch is set.
     *
     * @param Hook_Interface $hook The hook to check.
     * @return bool
     */
    private function has_killswitch( Hook_Interface $hook ): bool {
        return ! $this->killswitch
            ? false
            : $this->check_killswitch( $hook );
    }

    /**
     * Actually check the killswitch.
     *
     * @param  Hook_Interface $hook The hook to check.
     * @return bool
     */
    private function check_killswitch( Hook_Interface $hook ): bool {
        $target = \is_array( $hook->target ) ? $hook->target[0] : $hook->target;

        if ( \str_contains( 'XWP\Debug', $target ) ) {
            return false;
        }

        // phpcs:disable WordPress.Security.NonceVerification
        return $this->initialized &&
        isset( $_REQUEST[ $this->killswitch ] ) &&
        '1' === $_REQUEST[ $this->killswitch ];
        // phpcs:enable
    }

    /**
     * Get the registered handlers.
     *
     * @return array<class-string, Hook_Interface>
     */
    public function get_handlers(): array {
        return $this->handlers;
    }

    /**
     * Get the registered hooks.
     *
     * @param  string|null $handler The key to get the hooks for.
     * @return array
     */
    public function get_hooks( ?string $handler = null ): array {
        if ( ! $handler ) {
            return $this->hooks;
        }

        return $this->hooks[ $handler ] ?? array();
    }

    /**
     * Get the current context.
     *
     * @return Context_Interface
     */
    public function get_context(): Context_Interface {
        return $this->context;
    }

    /**
     * Check if the context is valid.
     *
     * @param  Hook_Interface $hook The context to check.
     * @return bool
     */
    private function is_valid_context( Hook_Interface $hook ): bool {
        return $this->context->is_valid( $hook->context );
    }

    /**
     * Register the handlers.
     *
     * @param string|object ...$handlers The handlers to register.
     * @return static
     */
    public function register_handlers( ...$handlers ): static {
        foreach ( $handlers as $handler ) {
            $this->register_handler( $handler );
        }

        return $this;
    }

    /**
     * Register a handler.
     *
     * @param  string|object $handler The handler to register.
     * @return static
     */
    public function register_handler( string $handler ): static {
        $refl = $this->get_reflector( $handler );
        $hook = $this->get_decorator_instance( $refl );
        $prio = $hook->get_priority();

        $hook->target = $handler;

        $this->handlers[ $handler ] = $hook;

        if ( ! $this->can_register( $hook ) ) {
            return $this;
        }

        \add_action(
            $hook->tag,
            fn() => $this->invoke_handler( $handler, $refl, $hook ),
            $prio,
            0,
        );

        return $this;
    }

    /**
     * Invokes the handler.
     *
     * @param  string           $handler The handler to invoke.
     * @param  \ReflectionClass $refl    The reflector for the handler.
     * @param  Hook_Interface   $hook    The hook to invoke.
     */
    private function invoke_handler( string $handler, \ReflectionClass $refl, Hook_Interface $hook ) {
        if ( ! $this->can_invoke( $hook ) ) {
            return;
        }

        $this->objects[ $handler ] ??= new $handler();

        $this->handlers[ $handler ]->invoked = true;

        $this->register_handler_methods( $refl );
    }

    /**
     * Checks if a handler/hook can be registered.
     *
     * Handlers can be registered when:
     *  * The context is valid
     *  * Handler has a can_register static method that returns true
     *
     * Hooks can be registered when:
     *  * The context is valid
     *
     * @param  Hook_Interface $hook The hook to check.
     * @return bool
     */
    private function can_register( Hook_Interface $hook ): bool {
        return $this->is_valid_context( $hook ) &&
            $this->can_activate( $hook, 'can_register' );
    }

    /**
     * Checks if a handler/hook can be invoked.
     *
     * Handlers can be invoked when:
     *  * Handler requirements are met
     *  * The handler has a can_invoke method that returns true
     *  * Conditional check returns true
     *
     * Hooks can be invoked when:
     *  * Hook requirements are met
     *  * Conditional check returns true
     *
     * @param  Hook_Interface $hook The hook to check.
     * @return bool
     */
    public function can_invoke( Hook_Interface $hook ): bool {
        return ! $this->has_killswitch( $hook ) &&
            $this->has_dependency( $hook ) &&
            $this->can_activate( $hook, 'can_invoke' ) &&
            $this->conditional_check( $hook );
    }

    /**
     * Check if a hook has a listed dependency.
     *
     * @param  Hook_Interface $hook The hook to check.
     * @return bool
     */
    private function has_dependency( Hook_Interface $hook ): bool {
        if ( ! $hook->requires ) {
            return true;
        }

        if ( \is_string( $hook->requires ) ) {
            return $this->has_handler( $hook->requires );
        }

        return $this->has_hook( $hook->requires[0], $hook->requires[1] );
    }

    /**
     * Check if the handler has a `can_register` or `can_invoke` method
     *
     * @param  Hook_Interface $hook   The hook to check.
     * @param  string         $method The method to check.
     * @return bool
     */
    private function can_activate( Hook_Interface $hook, string $method = 'can_register' ): bool {
        $target = $hook->target;
        if ( \is_array( $target ) ) {
            $method .= '_' . $target[1];
            $target  = $target[0];
        }

        $has_method = \method_exists( $target, $method );

        return ! $has_method || ( $has_method && $target::$method() );
    }

    /**
     * Check if the handler is invoked.
     *
     * @param  string $handler The handler to check.
     * @return bool
     */
    private function has_handler( string|false $handler ) {
        if ( ! $handler ) {
            return true;
        }

        return isset( $this->handlers[ $handler ] ) && $this->handlers[ $handler ]->invoked;
    }

    /**
     * Check if the hook is invoked.
     *
     * @param  class-string $handler The handler to check.
     * @param  string       $method The method to check.
     * @return bool
     */
    private function has_hook( string $handler, string $method ): bool {
        if ( ! $this->has_handler( $handler ) ) {
            return false;
        }

        return isset( $this->hooks[ $handler ][ $method ] ) && $this->hooks[ $handler ][ $method ]->invoked;
    }

    /**
     * Run a conditional check on the hook.
     *
     * @param  Hook_Interface $hook The hook to check.
     * @return bool
     */
    private function conditional_check( Hook_Interface $hook ): bool {
        return $hook->conditional ? ( $hook->conditional )() : true;
    }

    /**
     * Register the handler methods.
     *
     * @param  object|\ReflectionClass $handler The reflector for the handler.
     * @return static
     */
    public function register_handler_methods( object $handler ): static {
        if ( \is_object( $handler ) && ! isset( $this->objects[ $handler::class ] ) ) {
            $this->objects[ $handler::class ] = $handler;
        }

        $refl   = $this->get_reflector( $handler );
        $h_name = $refl->getName();

        foreach ( $this->get_hooked_methods( $refl ) as $m ) {
            $this->invoke_hooks(
                $h_name,
                $m->getName(),
                $m->getNumberOfParameters(),
                $this->get_decorators( $m ),
            );
        }

        return $this;
    }

    /**
     * Invoke the hooks for the handler.
     *
     * @param  string $handler    The handler to invoke the hooks for.
     * @param  string $method     The method to invoke the hooks for.
     * @param  int    $args       The number of arguments the method accepts.
     * @param  array  $decorators The decorators to invoke.
     */
    private function invoke_hooks( string $handler, string $method, int $args, array $decorators ) {
        foreach ( $decorators as $decorator ) {
            $hook = $decorator->newInstance();

            $this->hooks[ $handler ][ $method ][] = $this->invoke_hook( $handler, $method, $hook, $args );
        }
    }

    /**
     * Invoke a hook.
     *
     * @param  string         $handler The handler to invoke the hook for.
     * @param  string         $method  The method to invoke the hook for.
     * @param  Hook_Interface $hook    The hook to invoke.
     * @param  int            $args    The number of arguments the method accepts.
     */
    private function invoke_hook( string $handler, string $method, Hook_Interface $hook, int $args ): Hook_Interface {
        $callback = 'add_' . $hook::HOOK_TYPE;
        $priority = $hook->get_priority();

        $hook->target = array( $handler, $method );

        if ( $this->can_invoke( $hook ) ) {
            $callback( $hook->tag, array( $this->objects[ $handler ], $method ), $priority, $args );
            $hook->invoked = true;
        }

        return $hook;
    }

    /**
     * Get the hooked methods for a handler.
     *
     * @param  \ReflectionClass $reflector The reflector for the handler.
     * @return array
     */
    private function get_hooked_methods( \ReflectionClass $reflector ): array {
        $methods = \array_filter(
            $reflector->getMethods( $this->get_method_types( $reflector->getName() ) ),
            array( $this, 'is_method_hookable' ),
        );

        return $methods;
    }

    /**
     * Get the method types to include.
     *
     * @param  object|string $t The object or class name to get the method types for.
     * @return int
     */
    private function get_method_types( object|string $t ): int {
        $include = \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC;

        if ( $this->has_accessible_trait( $t ) ) {
            $include |= \ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_PROTECTED;
        }

        return $include;
    }

    /**
     * Check if a method is hookable.
     *
     * @param  \ReflectionMethod $m The method to check.
     * @return bool
     */
    private function is_method_hookable( \ReflectionMethod $m, ): bool {
        $ignore = array( '__call', '__callStatic', 'check_method_access', 'is_method_valid', 'get_registered_hooks', '__construct' );
        return ! \in_array( $m->getName(), $ignore, true ) && ( ! $m->isStatic() );
    }

    /**
     * Check if the object has the accessible trait.
     *
     * @param  object|string $obj The object to check.
     * @return bool
     */
    private function has_accessible_trait( object|string $obj ): bool {
        return \in_array( 'XWP\Contracts\Hook\Accessible_Hook_Methods', $this->class_uses_deep( $obj ), true );
    }

    /**
     * Get all the traits used by a class.
     *
     * @param  string|object $target Class or object to get the traits for.
     * @param  bool          $autoload        Whether to allow this function to load the class automatically through the __autoload() magic method.
     * @return array                          Array of traits.
     */
	private function class_uses_deep( string|object $target, bool $autoload = true ) {
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
     * Get the handler meta.
     *
     * @param  string|object $handler The handler to get the meta from.
     * @return Hook_Interface|null
     */
    private function get_decorator_instance( string|object $handler ) {
        return $this->
            get_decorator(
                $this->get_reflector( $handler ),
            )?->newInstance();
    }

    /**
     * Get the decorators from a reflector.
     *
     * @param  \ReflectionClass|\ReflectionMethod|\ReflectionFunction $r The reflector to get the decorator from.
     * @return array
     */
    private function get_decorators( \Reflector $r ): array {
        return $r->getAttributes( Hook_Interface::class, \ReflectionAttribute::IS_INSTANCEOF );
    }

    /**
     * Get the decorator from a reflector.
     *
     * @param  \Reflector $r The reflector to get the decorator from.
     * @return \ReflectionAttribute|null
     */
    private function get_decorator( \Reflector $r ): ?\ReflectionAttribute {
        return \current( $this->get_decorators( $r ) ) ?: null; //phpcs:ignore
    }

    /**
     * Get the reflector for a target.
     *
     * @param  mixed $target The target to get the reflector for.
     * @return \ReflectionClass|\ReflectionMethod|\ReflectionFunction
     *
     * @throws \InvalidArgumentException If the target is invalid.
     */
    private function get_reflector( mixed $target ): \Reflector {
        return match ( true ) {
            $target instanceof \Reflector     => $target,
            $this->is_valid_class( $target )    => new \ReflectionClass( $target ),
            $this->is_valid_method( $target )   => new \ReflectionMethod( $target ),
            $this->is_valid_function( $target ) => new \ReflectionFunction( $target ),
            default => throw new \InvalidArgumentException( 'Invalid target' ),
        };
    }

    /**
     * Is the target a valid class.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    private function is_valid_class( mixed $target ): bool {
        return \is_object( $target ) || \class_exists( $target );
    }

    /**
     * Is the target a valid method.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    private function is_valid_method( mixed $target ): bool {
        return \is_array( $target ) && $this->is_valid_class( $target[0] ) && \is_string( $target[1] );
    }

    /**
     * Is the target a valid function.
     *
     * @param  mixed $target The target to check.
     * @return bool
     */
    private function is_valid_function( mixed $target ): bool {
        return \is_string( $target ) && \function_exists( $target );
    }
}
