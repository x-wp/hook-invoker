<?php // phpcs:disable SlevomatCodingStandard.Operators.SpreadOperatorSpacing.IncorrectSpacesAfterOperator
/**
 * Invoker class file.
 *
 * @package eXtended WordPress-DI
 * @subpackage Services
 */

namespace XWP\Hook;

use Automattic\Jetpack\Constants;
use ReflectionClass;
use ReflectionMethod;
use XWP\Contracts\Hook\Accessible_Hook_Methods;
use XWP\Contracts\Hook\Context;
use XWP\Contracts\Hook\Context_Interface;
use XWP\Contracts\Hook\Hookable;
use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Initialize;
use XWP\Contracts\Hook\Invokable;
use XWP\Contracts\Hook\Invoker_Interface;
use XWP\Hook\Decorators\Handler;

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
    public readonly Context $context;

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
     * Array of handler data
     *
     * @var array<string, Initializable>
     */
    private array $handlers = array();

    /**
     * Array of registered hooks.
     *
     * @var array<string, array<string, Invokable>>
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
     * @param Hookable $hook The hook to check.
     * @return bool
     */
    private function has_killswitch( Hookable $hook ): bool {
        return ! $this->killswitch
            ? false
            : $this->check_killswitch( $hook );
    }

    /**
     * Actually check the killswitch.
     *
     * @param  Hookable $hook The hook to check.
     * @return bool
     */
    private function check_killswitch( Hookable $hook ): bool {
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
     * @return array<class-string, Initializable>
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
     * @param  Hookable $hook The context to check.
     * @return bool
     */
    private function is_valid_context( Hookable $hook ): bool {
        return $this->context->is_valid( $hook->context );
    }

    /**
     * Set a handler.
     *
     * @param  Initializable $handler The handler to set.
     * @return static
     */
    private function set_handler( Initializable $handler ): static {
        $this->handlers[ $handler->classname ] = $handler;

        return $this;
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
     * Loads an object as a handler.
     *
     * Used for loading handlers that are not decorated.
     *
     * @param object $instance Object to load as a handler.
     *
     * @throws \InvalidArgumentException If the object is decorated.
     */
    public function load_handler( object $instance ) {
        $reflector = Reflection::get_reflector( $instance );

        if ( Reflection::get_decorator( $reflector, Initializable::class ) ) {
            throw new \InvalidArgumentException( 'Only non-decorated classes can be loaded' );
        }

        $handler = ( new Handler( strategy: Initialize::Dynamically ) )
            ->set_classname( $instance::class )
            ->set_target( $instance )
            ->set_reflector( $reflector );

        $this->handlers[ $handler->classname ] = $handler;
        $this->hooks[ $handler->classname ]    = array();

        return $this
            ->set_handler( $handler )
            ->register_methods( $handler )
            ->invoke_methods( $handler );
    }

    /**
     * Register a handler.
     *
     * @param  string|object $classname The handler to register.
     * @return static
     */
    public function register_handler( string $classname ): static {
        $refl = Reflection::get_reflector( $classname );

        $handler = Reflection::get_decorator( $refl, Initializable::class )
            ->set_classname( $classname )
            ->set_reflector( $refl );

        $this->handlers[ $classname ] = $handler;
        $this->hooks[ $classname ]    = array();

        switch ( $handler->strategy ) {
            case Initialize::Immediately:
                return $this
                    ->initialize_handler( $handler )
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );

            case Initialize::OnDemand:
            case Initialize::JustInTime:
                return $this
                    ->set_handler( $handler )
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );

            case Initialize::Early:
                $this->initialize_handler( $handler );

                $callback = fn() => $this
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );
                break;

            default:
                $callback = fn() => $this
                    ->initialize_handler( $handler )
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );
                break;
        }

        \add_action( $handler->tag, $callback, $handler->real_priority, 0 );

        return $this;
    }

    /**
     * Initializes the handler.
     *
     * @param Initializable $handler Handler to initialize.
     */
    private function initialize_handler( Initializable $handler ) {
        if ( ! $this->can_activate( $handler ) || ! $handler->can_initialize() ) {
            return $this;
        }

        $handler->initialize();

        return $this;
    }

    /**
     * Invokes the handler.
     *
     * @param  Initializable $handler Handler to register methods for.
     */
    private function register_methods( Initializable $handler ) {
        foreach ( $this->get_hookable_methods( $handler->reflector ) as $m ) {
            $hooks = $this->register_method( $handler, $m );

            if ( ! $hooks ) {
                continue;
            }

            $this->hooks[ $handler->classname ][ $m->getName() ] = $hooks;
        }

        return $this;
    }

    /**
     * Register a method.
     *
     * @param  Initializable    $handler The handler to register the method for.
     * @param  ReflectionMethod $m       The method to register.
     * @return array<Invokable>
     */
    private function register_method( Initializable $handler, ReflectionMethod $m ) {
        $hooks  = array();
        $target = array( $handler->target, $m->getName() );

        foreach ( Reflection::get_decorators( $m, Invokable::class ) as $hook ) {
            $hooks[] = $hook
                ->set_handler( $handler )
                ->set_target( $target )
                ->set_reflector( $m );
        }

        return $hooks;
    }

    /**
     * Invoke the methods for a handler.
     *
     * @param  Initializable $handler The handler to invoke methods for.
     */
    public function invoke_methods( Initializable $handler ) {
		foreach ( $this->hooks[ $handler->classname ] as $hooks ) {
            foreach ( $hooks as $hook ) {
                $this->invoke_hook( $hook );
            }
        }

        return $this;
    }

    /**
     * Invoke a hook.
     *
     * @param  Invokable $hook The hook to invoke.
     */
    private function invoke_hook( Invokable $hook ) {
        if ( ! $this->is_valid_context( $hook ) || ! $hook->can_invoke() ) {
            return;
        }

        $hook->invoke();
    }

    /**
     * Get the hooked methods for a handler.
     *
     * @param  ReflectionClass $r The reflection class to get the methods for.
     * @return array<ReflectionMethod>
     */
    private function get_hookable_methods( ReflectionClass $r ): array {
        $traits = $this->class_uses_deep( $r->getName() );

        return \array_filter(
            $r->getMethods( $this->get_method_types( $traits ) ),
            $this->is_method_hookable( ... ),
        );
    }

    /**
     * Get the method types to include.
     *
     * @param  array<string> $traits The traits to check.
     * @return int
     */
    private function get_method_types( array $traits ): int {
        $include = ReflectionMethod::IS_PUBLIC;

        if ( \in_array( Accessible_Hook_Methods::class, $traits, true ) ) {
            $include |= ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PROTECTED;
        }

        return $include;
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
     * Check if a method is hookable.
     *
     * @param  ReflectionMethod $m The method to check.
     * @return bool
     */
    private function is_method_hookable( ReflectionMethod $m, ): bool {
        $ignore = array( '__call', '__callStatic', 'check_method_access', 'is_method_valid', 'get_registered_hooks', '__construct' );
        return ! \in_array( $m->getName(), $ignore, true ) &&
            ! $m->isStatic()
            && $m->getAttributes( Invokable::class, \ReflectionAttribute::IS_INSTANCEOF );
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
     * @param  Hookable $hook The hook to check.
     * @return bool
     */
    public function can_activate( Hookable $hook ): bool {
        return ! $this->has_killswitch( $hook ) &&
            $this->has_dependency( $hook );
    }

    /**
     * Check if a hook has a listed dependency.
     *
     * @param  Hookable $hook The hook to check.
     * @return bool
     */
    private function has_dependency( Hookable $hook ): bool {
        if ( ! $hook->requires ) {
            return true;
        }

        if ( \is_string( $hook->requires ) ) {
            return $this->has_handler( $hook->requires );
        }

        return $this->has_hook( $hook->requires[0], $hook->requires[1] );
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

        return isset( $this->handlers[ $handler ] ) && $this->handlers[ $handler ]->initialized;
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
}
