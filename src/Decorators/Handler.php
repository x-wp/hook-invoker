<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Initialize;
use XWP\Contracts\Hook\On_Initialize;

/**
 * Handler decorator.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Initializable {
    public const HOOK_TYPE = 'handler';

    /**
     * Handler classname.
     *
     * @var class-string
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

    public function set_classname( string $classname ): static {
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

        $this->target ??= \method_exists( $this->classname, 'instance' )
            ? $this->classname::instance()
            : new ( $this->classname )();

        return $this->on_initialize();
    }

    protected function on_initialize(): static {
        $this->initialized = true;

        if ( \in_array( On_Initialize::class, \class_implements( $this->target ), true ) ) {
            $this->target->on_initialize();
        }

        return $this;
    }
}
