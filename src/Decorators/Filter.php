<?php //phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use ReflectionMethod;
use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Initialize;
use XWP\Contracts\Hook\Invokable;
use XWP\Contracts\Hook\Invoke;
use XWP\Hook\Reflection;

/**
 * Action decorator.
 *
 * @template THndlr of object
 * @extends Hook<string,THndlr, ReflectionMethod>
 * @implements Invokable<THndlr>
 */
#[\Attribute( \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Hook implements Invokable {
    /**
     * Hook type.
     *
     * @var string
     */
    public const HOOK_TYPE = 'filter';

    /**
     * Number of arguments the hook accepts.
     *
     * @var int
     */
    protected int $args = 0;

    /**
     * Real number of arguments the hook accepts.
     *
     * @var int
     */
    protected int $real_args;

    /**
     * Flag indicating if the hook has been invoked.
     *
     * @var bool
     */
    protected bool $invoked = false;

    /**
     * Handler instance.
     *
     * @var Initializable<THndlr>
     */
    protected Initializable $handler;

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
        protected ?Invoke $invoke = Invoke::Directly,
        protected readonly string $id = '',
    ) {
        parent::__construct( $tag, $priority, $context, $conditional, $requires, $modifiers );
    }

    public function with_handler( Initializable $handler ): static {
        $this->handler = $handler;

        if ( ! $handler->strategy->hooksIndirectly() ) {
            return $this;
        }

        if ( ! $this->invoke->isIndirect() ) {
            $this->invoke = Invoke::Indirectly;
        }

        return $this;
    }

    public function with_target( string $method ): static {
        $this->target = $method;

        return $this;
    }

    /**
     * Sets the number of arguments the hook accepts, and the reflector instance.
     *
     * @param  ReflectionMethod $r Reflector instance.
     * @return static
     */
    public function set_reflector( \Reflector $r ): static {
        $this->args      = $r->getNumberOfParameters();
        $this->real_args = $this->args;
        $this->reflector = $r;

        return $this;
    }

    public function can_invoke(): bool {
        if ( $this->invoke->isIndirect() && ! \doing_action( $this->tag ) ) {
            return true;
        }

        return $this->can_fire() && $this->handler->initialized;
    }

    public function check_handler() {
        return $this->handler->initialized;
    }

    public function invoke() {
        if ( ! $this->init_handler( Initialize::OnDemand ) ) {
            return;
        }

        $fn = 'add_' . static::HOOK_TYPE;

        $fn( ...$this->get_invocation_args() );

        $this->invoked = true;
    }

    protected function get_invocation_args(): array {
        $hook_name     = $this->tag;
        $callback      = $this->get_target();
        $accepted_args = $this->get_real_args();
        $priority      = $this->get_real_priority();

        return \compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
    }

    protected function get_target() {
        if ( $this->invoke->isIndirect() ) {
            return $this->indirect_callback( ... );
        }

        return $this->handler->get_target()->{$this->target}( ... );
    }

    /**
     * Used to adjust the number of parameters passed to the hook callback.
     *
     * Indirect invoking can optionally send this instance as the last parameter.
     * But, in order to do that, and preserve the original parameters, we need to reduce the number by 1
     *
     * @return int
     */
    protected function get_real_args(): int {
        if ( ! $this->invoke->isIndirect() ) {
            return $this->args;
        }

        $params = $this->reflector->getParameters();
        $params = \array_pop( $params );
        $type   = $params?->getType() ?? null;

        if (
            ! ( $type instanceof \ReflectionNamedType ) ||
            ! Reflection::class_implements( $type->getName(), Invokable::class )
        ) {
            return $this->args;
        }

        $this->real_args = $this->args - 1;

        return $this->real_args;
    }

    protected function init_handler( Initialize $strategy ): bool {
        if ( $this->handler->strategy !== $strategy ) {
            return true;
        }

        if ( $this->handler->initialized ) {
            return true;
        }

        return $this->handler->can_initialize();
    }

    protected function indirect_callback( ...$args ) {
        if (
            ! $this->init_handler( Initialize::JustInTime ) ||
            ! $this->can_invoke() ||
            ! $this->check_hook_method( $args )
        ) {
			return $args[0] ?? null;
        }

        if ( $this->args !== $this->real_args ) {
			$args[] = $this;
        }

        return $this->handler->get_target()->{$this->target}( ...$args );
    }

    protected function check_hook_method( $args ): bool {
        $method = "can_invoke_{$this->target[1]}";

        return \method_exists( $this->target[0], $method )
            ? $this->handler->get_target()->$method( $this, ...$args )
            : true;
    }
}
