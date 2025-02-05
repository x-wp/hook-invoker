<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 *  Base_Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use ReflectionClass;
use ReflectionMethod;
use XWP\Contracts\Hook\Context;
use XWP\Contracts\Hook\Context_Interface;
use XWP\Contracts\Hook\Hookable;
use XWP\Hook\Context_Host;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template TGt
 * @template THndlr of object
 * @template TRflct of ReflectionClass<THndlr>|ReflectionMethod
 * @implements Hookable<THndlr,TRflct>
 */
abstract class Hook implements Hookable {
    /**
     * The name of the action to which the function is hooked.
     *
     * @var string
     */
    protected string $tag;

    /**
     * Priority when hook was invoked.
     *
     * @var int
     */
    private int $real_priority;

    /**
     * Reflector instance.
     *
     * @var TRflct
     */
    protected ReflectionClass|ReflectionMethod $reflector;

    /**
     * Current context.
     *
     * @var Context_Interface
     */
    protected static Context_Interface $current_ctx;

    /**
     * Handler target.
     *
     * @var TGt
     */
    protected $target;

    /**
     * Constructor.
     *
     * @param  string|null                     $tag         Tag to hook the function to.
     * @param  array|int|string                $priority    Priority to invoke the hook by. Can be one of the following.
     *                                                       * Integer: Constant priority to invoke / initialize by. (Default: 10)
     *                                                       * Array:   Callable function to invoke / initialize by.
     *                                                       * String:  If a function exists with the name, it will be invoked, if not - will be treated as filter.
     * @param  int                             $context     Context bitmask determining where the hook can be invoked.
     * @param  array|string|\Closure|null|null $conditional Conditional to check if the hook should be invoked.
     * @param  array|string|false              $requires    Prerequisite hook or handler that must be registered before this hook.
     * @param  string|array|false              $modifiers   Replacement pairs for the tag name.
     */
    public function __construct(
        ?string $tag,
        protected readonly array|int|string|\Closure|null $priority = null,
        protected readonly int $context = self::CTX_GLOBAL,
        protected readonly array|string|\Closure|null $conditional = null,
        protected readonly array|string|false $requires = false,
        protected readonly string|array|false $modifiers = false,
    ) {
        $this->tag = $this->set_tag( $tag ?? '', $modifiers );

        static::$current_ctx ??= Context_Host::get();
    }

    /**
     * If the tag is dynamic (contains %s), replace the placeholders with the provided arguments.
     *
     * @param  string      $tag       Tag to set.
     * @param  array|false $modifiers Values to replace in the tag name.
     */
    protected function set_tag( string $tag, array|string|false $modifiers ) {
        if ( ! $modifiers ) {
            return $tag;
        }

        $modifiers = \is_array( $modifiers )
            ? $modifiers
            : array( $modifiers );

        return \vsprintf( $tag, $modifiers );
    }

    public function set_reflector( \Reflector $r ): static {
        $this->reflector ??= $r;

        return $this;
    }

    /**
     * Since the priority can be dynamic - we determine it at runtime.
     *
     * @return int
     */
    private function set_real_priority(): int {
        $prio = $this->priority;
        $prio = match ( true ) {
            \defined( $prio )     => \constant( $prio ),
            \is_numeric( $prio )  => (int) $prio,
            \is_array( $prio )    => \call_user_func( $prio ),
            \is_callable( $prio ) => $prio(),
            \is_string( $prio )   => \apply_filters( $prio, 10, $this->tag ),
            default               => 10,
        } ?? 10;

        $this->real_priority = (int) $prio;

        return $this->real_priority;
    }

    protected function can_fire(): bool {
        return $this->check_context() && $this->check_method( $this->conditional );
    }

    protected function check_method( array|string|\Closure|null $method ): bool {
        return ! \is_callable( $method ) || $method( $this );
    }

    public function check_context(): bool {
        return static::$current_ctx->is_valid( $this->context );
    }

    protected function get_prop( string $name ): mixed {
        return $this->$name ?? null;
    }

    protected function get_real_priority(): int {
        if ( ! isset( $this->real_priority ) ) {
            return $this->set_real_priority();
        }

        return $this->real_priority;
    }

    /**
     * Getter for protected properties.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return \method_exists( $this, "get_{$name}" )
            ? $this->{"get_{$name}"}()
            : $this->get_prop( $name );
    }
}
