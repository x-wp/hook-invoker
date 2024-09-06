<?php // phpcs:disable SlevomatCodingStandard.Operators.SpreadOperatorSpacing.IncorrectSpacesAfterOperator
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Hook Invoker
 */

namespace XWP\Hook;

use Automattic\Jetpack\Constants;
use ReflectionMethod;
use XWP\Contracts\Hook\Context_Interface;
use XWP\Contracts\Hook\Hookable;
use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Initialize;
use XWP\Contracts\Hook\Invokable;
use XWP\Contracts\Hook\Invoker_Interface;
use XWP\Helper\Traits\Singleton;
use XWP\Hook\Decorators\Handler;

/**
 * Executes the registered handlers.
 */
class Invoker implements Invoker_Interface {
    use Singleton;

    /**
     * Current execution context.
     *
     * @var Context_Interface
     */
    public readonly Context_Interface $context;

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
     * @var array<class-string, array<string, array<int,Invokable>>>
     */
    public array $hooks = array();

    /**
     * Protected constructor to prevent creating a new instance
     */
    protected function __construct() {
        $this->context    = Context_Host::get();
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
     * @param  Hookable $hook The hook to check.
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
        $target = match ( true ) {
            $hook instanceof Invokable => $hook->handler->classname,
            $hook instanceof Initializable => $hook->classname,
            default => '',
        };

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
     * Create a handler for a given instance.
     *
     * @template THndlr of object
     * @param  THndlr $instance The instance to create a handler for.
     * @return Initializable<THndlr>
     */
	public function create_handler( object $instance ): Initializable {
		if ( Reflection::class_implements( $instance, Initializable::class ) ) {
			return $instance;
		}

		if ( isset( $this->handlers[ $instance::class ] ) ) {
			return $this->handlers[ $instance::class ];
		}

		$reflector = Reflection::get_reflector( $instance );
		$handler   = Reflection::get_decorator( $reflector, Initializable::class )
                    ??
                    new Handler( strategy: Initialize::Dynamically );

		return $handler
            ->set_reflector( $reflector )
            ->with_classname( $reflector->getName() )
            ->with_target( $instance )
            ->initialize();
	}

    /**
     * Loads an object as a handler.
     *
     * Used for loading handlers that are not decorated.
     *
     * @template THndlr of object
     * @param  THndlr $instance The instance to load as a handler.
     * @return static
     *
     * @throws \InvalidArgumentException If the object is decorated.
     */
	public function load_handler( object $instance ): static {
		$handler = $this->create_handler( $instance );

		if ( isset( $this->handlers[ $handler->classname ] ) ) {
			return $this;
		}

		$this->handlers[ $handler->classname ] = $handler;
		$this->hooks[ $handler->classname ]    = array();

		return $this
            ->register_methods( $handler )
            ->invoke_methods( $handler );
	}

    /**
     * Register a handler.
     *
     * @template THhndlr of object
     * @param  class-string<THhndlr> $classname The handler to register.
     * @return static
     */
	public function register_handler( string $classname ): static {
		$refl = Reflection::get_reflector( $classname );

		$handler = Reflection::get_decorator( $refl, Initializable::class )
		->with_classname( $classname )
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
	public function register_methods( Initializable $handler ) {
		if ( \count( $this->hooks[ $handler->classname ] ) > 0 ) {
			return $this;
		}

		$this->hooks[ $handler->classname ] ??= array();

		foreach ( Reflection::get_hookable_methods( $handler->reflector ) as $m ) {
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
     * @param  Initializable     $handler The handler to register the method for.
     * @param  \ReflectionMethod $m       The method to register.
     * @return array<int,Invokable>
     */
	private function register_method( Initializable $handler, \ReflectionMethod $m ) {
		$hooks = array();

		foreach ( Reflection::get_decorators( $m, Invokable::class ) as $hook ) {
			$hooks[] = $hook
			->with_handler( $handler )
			->with_target( $m->getName() )
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
     * Load hooks for a handler.
     *
     * @param Initializable $handler The handler to load hooks for.
     * @param array         $hooks   The hooks to load.
     */
	public function load_hooks( Initializable $handler, array $hooks ): static {
		$this->handlers[ $handler->classname ] ??= $handler;

		if ( \count( $this->hooks[ $handler->classname ] ?? array() ) ) {
			return $this;
		}

		$this->hooks[ $handler->classname ] = $hooks;

		return $this;
	}

    /**
     * Invoke a hook.
     *
     * @param  Invokable $hook The hook to invoke.
     */
	private function invoke_hook( Invokable $hook ) {
		if (
		! $this->is_valid_context( $hook ) ||
		! $this->is_valid_context( $hook->handler ) ||
		! $hook->can_invoke()
		) {
			return;
		}

		$hook->invoke();
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
		$this->has_dependency( $hook ) &&
		$this->is_valid_context( $hook );
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
     * Check if the hook is invoked.
     *
     * @param  class-string $handler The handler to check.
     * @param  string       $method The method to check.
     * @return bool
     */
	private function has_hook( string $handler, string $method ): bool {
		if ( ! $this->has_handler( $handler ) || ! isset( $this->hooks[ $handler ][ $method ] ) ) {
			return false;
		}

		return \array_reduce(
            $this->hooks[ $handler ][ $method ],
            static fn( $carry, $hook ) => $carry && $hook->invoked,
            true,
		);
	}
}
