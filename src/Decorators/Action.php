<?php //phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Invokable;
use XWP\Contracts\Hook\Invoke;

/**
 * Action decorator.
 */
#[\Attribute( \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Action extends Hook implements Invokable {
    /**
     * Hook type.
     *
     * @var string
     */
    public const HOOK_TYPE = 'action';

    /**
     * Number of arguments the hook accepts.
     *
     * @var int
     */
    protected int $args = 0;

    /**
     * Flag indicating if the hook has been invoked.
     *
     * @var bool
     */
    protected bool $invoked = false;

    /**
     * Handler instance.
     *
     * @var Handler
     */
    protected Handler $handler;

    /**
     * Constructor
     *
     * @param  Invoke $invoke Invocation strategy.
     */
    public function __construct(
        string $tag,
        array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        array|string|\Closure|null $conditional = null,
        array|string|false $requires = false,
        string|array|false $modifiers = false,
        public readonly Invoke $invoke = Invoke::Directly,
    ) {
        parent::__construct( $tag, $priority, $context, $conditional, $requires, $modifiers );
    }

    public function set_handler( Initializable $handler ): static {
        $this->handler = $handler;

        return $this;
    }

    public function can_invoke(): bool {
        return $this->check_conditional();
    }

    protected function check_method( string $method ): bool {
        return ! \method_exists( $this->target[0], $method ) || ( $this->target[0] )->$method( $this );
    }

    public function invoke() {
        $this->args = $this->reflector->getNumberOfParameters();

        if ( $this->handler->strategy->is_ondemand() ) {
            $this->maybe_initialize_handler();
        }

        $callback = $this->target;
        $args     = $this->args;

		if ( $this->can_invoke_indirectly() ) {
            $args     = $this->maybe_adjust_params( $args );
			$callback = $this->indirect_callback( ... );
		}

        $fn = 'add_' . static::HOOK_TYPE;

        $fn( $this->tag, $callback, $this->real_priority, $args );

        $this->invoked = true;
    }

    /**
     * Used to adjust the number of parameters passed to the hook callback.
     *
     * Indirect invoking can optionally send this instance as the last parameter.
     * But, in order to do that, and preserve the original parameters, we need to reduce the number by 1
     *
     * @param  int $num_args Number of parameters.
     * @return int
     */
    protected function maybe_adjust_params( int $num_args ): int {
        $params = $this->reflector->getParameters();
        $type   = \end( $params )?->getType() ?? null;

        if ( ! ( $type instanceof \ReflectionNamedType ) ) {
            return $num_args;
        }
        $type = $type->getName();

        return \class_exists( $type ) && \in_array( Invokable::class, \class_implements( $type ), true )
            ? --$num_args
            : $num_args;
    }

    protected function can_invoke_indirectly(): bool {
        return Invoke::Indirectly === $this->invoke || $this->handler->strategy->is_just_in_time();
    }

    protected function maybe_initialize_handler() {
        if ( ! $this->handler->initialized ) {
            $this->handler->initialize();
		}

        if ( \is_object( $this->target[0] ) ) {
            return;
        }

        $this->target[0] = $this->handler->target;
    }

    protected function indirect_callback( ...$args ) {
        if ( ! $this->can_invoke() ) {
			return;
        }

        if ( $this->handler->strategy->is_just_in_time() ) {
			$this->maybe_initialize_handler();
        }

        if ( ! $this->check_method( "can_invoke_{$this->target[1]}" ) ) {
			return;
        }
        if ( \count( $args ) + 1 === $this->args ) {
			$args[] = $this;
        }

        \call_user_func_array( $this->target, $args );
    }
}
