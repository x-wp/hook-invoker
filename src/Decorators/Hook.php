<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 *  Base_Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use XWP\Contracts\Hook\Hookable;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template T
 */
abstract class Hook implements Hookable {
    /**
     * The name of the action to which the function is hooked.
     *
     * @var string
     */
    public string $tag;

    /**
     * Priority when hook was invoked.
     *
     * @var int
     */
    private int $real_priority;

    /**
     * Reflector instance.
     *
     * @var \ReflectionClass<T>|\ReflectionMethod<T>
     */
    protected \ReflectionClass|\ReflectionMethod $reflector;

    /**
     * Hook target.
     *
     * @var T|array{0: T, 1:method-string}
     */
    protected array|object $target;

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
     * @param  bool                            $requires    Prerequisite hook or handler that must be registered before this hook.
     * @param  bool                            $modifiers   Replacement pairs for the tag name.
     */
    public function __construct(
        ?string $tag,
        public readonly array|int|string $priority = 10,
        public readonly int $context = self::CTX_GLOBAL,
        public readonly array|string|\Closure|null $conditional = null,
        public readonly array|string|false $requires = false,
        public readonly string|array|false $modifiers = false,
    ) {
        $this->tag = $this->set_tag( $tag ?? '', $modifiers );
    }

    /**
     * If the tag is dynamic (contains %s), replace the placeholders with the provided arguments.
     *
     * @param  string      $tag       Tag to set.
     * @param  array|false $modifiers Values to replace in the tag name.
     */
    private function set_tag( string $tag, array|string|false $modifiers ) {
        if ( ! $modifiers ) {
            return $tag;
        }

        $modifiers = \is_array( $modifiers )
            ? $modifiers
            : array( $modifiers );

        return \vsprintf( $tag, $modifiers );
    }

    /**
     * Since the priority can be dynamic - we determine it at runtime.
     *
     * @return int
     */
    private function set_priority(): int {
        if ( isset( $this->real_priority ) ) {
            return $this->real_priority;
        }

        $prio = $this->priority;
        $prio = match ( true ) {
            \defined( $prio )         => \constant( $prio ),
            \is_numeric( $prio )      => (int) $prio,
            \is_array( $prio )        => \call_user_func( $prio ),
            \is_callable( $prio )     => $prio(),
            \is_string( $prio )       => \apply_filters( $prio, 10, $this->tag ),
            default                  => 10,
        } ?? 10;

        $this->real_priority = (int) $prio;

        return $this->real_priority;
    }

    /**
     * Check the if the conditional method exists and can be invoked.
     *
     * @param  string $method Method to check.
     * @return bool
     */
    abstract protected function check_method( string $method ): bool;

    protected function check_conditional(): bool {
        return ! $this->conditional || ( $this->conditional )( $this );
    }

    public function set_target( array|object $target ): static {
        $this->target ??= $target;

        return $this;
    }

    public function set_reflector( \Reflector $r ): static {
        $this->reflector ??= $r;

        return $this;
    }

    /**
     * Getter for protected properties.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        if ( 'real_priority' === $name ) {
            return $this->set_priority();
        }

        return $this->$name ?? null;
    }
}
