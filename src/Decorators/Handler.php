<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Initialize;
use XWP\Contracts\Hook\On_Initialize;

/**
 * Handler decorator.
 *
 * @template T of object
 * @extends Hook<T|null,T,ReflectionClass<T>>
 * @implements Initializable<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Initializable {
    public const HOOK_TYPE = 'handler';

    /**
     * Handler classname.
     *
     * @var class-string<T>
     */
    public string $classname;

    /**
     * Is the handler initialized?
     *
     * @var bool
     */
    protected bool $initialized = false;

    public function __construct(
        ?string $tag = null,
        array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        array|string|\Closure|null $conditional = null,
        array|string|false $requires = false,
        string|array|false $modifiers = false,
        protected readonly Initialize $strategy = Initialize::Deferred,
        protected string|\Closure|null $container = null,
    ) {
        if ( ! $strategy->isTagValid( $tag ) ) {
            throw new \InvalidArgumentException(
                \esc_html( "Specified tag is not valid for {$strategy->value} strategy" ),
            );
        }

        if ( Initialize::Dynamically === $strategy ) {
            $this->initialized = true;
        }

        parent::__construct( $tag, $priority, $context, $conditional, $requires, $modifiers );
    }

    public function with_target( object $instance ): static {
        $this->target    ??= $instance;
        $this->classname ??= $instance::class;
        $this->initialized = true;

        return $this;
    }

    public function with_classname( string $classname ): static {
        $this->classname ??= $classname;

        return $this;
    }

    public function can_initialize(): bool {
        if ( Initialize::Unconditionally === $this->strategy ) {
            return true;
        }
        return $this->can_fire() &&
            $this->check_method( array( $this->classname, 'can_initialize' ) );
    }

    public function initialize(): static {
        if ( $this->initialized ) {
            return $this;
        }

        $this->target ??= $this->init_target();

        return $this->on_initialize();
    }

    protected function init_target(): object {
        return match ( true ) {
            isset( $this->container )                      => $this->get_container()->get( $this->classname ),
            \method_exists( $this->classname, 'instance' ) => $this->classname::instance(),
            default                                        => new ( $this->classname )(),
        };
    }

    protected function on_initialize(): static {
        $this->initialized = true;

        if ( \method_exists( $this->classname, 'on_initialize' ) ) {
            $this->get_target()->on_initialize();
        }

        return $this;
    }

    /**
     * Get the handler target.
     *
     * @return ?T
     */
    public function get_target(): ?object {
        return $this->target;
    }

    protected function get_container(): ?ContainerInterface {
        if ( null === $this->container ) {
            return null;
        }

        if ( \is_callable( $this->container ) ) {
            return ( $this->container )();
        }

        return $this->container::instance();
    }
}
