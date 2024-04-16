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
     * Flag indicating if the handler methods have been registered.
     *
     * @var bool
     */
    public bool $processed = false;

    /**
     * Handler classname.
     *
     * @var class-string
     */
    protected string $classname;

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
        public readonly Initialize $strategy = Initialize::Deferred,
    ) {
        if ( ! $strategy->is_tag_valid( $tag ) ) {
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

    protected function check_method( string $method ): bool {
        return ! \method_exists( $this->classname, $method ) || $this->classname::$method( $this );
    }

    public function can_initialize(): bool {
        if ( Initialize::Unconditionally === $this->strategy ) {
            return true;
        }

        $method = $this->check_method( 'can_initialize' );
        return $method && $this->check_conditional();
    }

    public function initialize(): static {
        $classname = $this->classname;

        $this->target      = \method_exists( $classname, 'instance' )
        ? $classname::instance()
        : new $classname();
        $this->initialized = true;

        if ( \in_array( On_Initialize::class, \class_implements( $this->target ), true ) ) {
            $this->target->on_initialize();
        }

        return $this;
    }
}
