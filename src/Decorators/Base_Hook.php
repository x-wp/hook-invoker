<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 *  Base_Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use XWP\Contracts\Hook\Hook_Interface;
use XWP\Contracts\Hook\Invoker_Interface;
use XWP\Hook\Invoker;

/**
 * Base hook from which the action and filter decorators inherit.
 */
abstract class Base_Hook implements Hook_Interface {
    public const CTX_FRONTEND = 1;  // 0000001
    public const CTX_ADMIN    = 2;  // 0000010
    public const CTX_AJAX     = 4;  // 0000100
    public const CTX_CRON     = 8;  // 0001000
    public const CTX_REST     = 16; // 0010000
    public const CTX_CLI      = 32; // 0100000
    public const CTX_GLOBAL   = 63; // 0111111

    public const HOOK_TYPE = self::HOOK_TYPE;

    /**
     * The name of the action to which the function is hooked.
     *
     * @var string
     */
    public readonly string $tag;


    /**
     * Priority when hook was invoked.
     *
     * @var int
     */
    public int $invoke_priority;

    /**
     * Is this hook invoked?
     *
     * @var bool
     */
    public bool $invoked = false;

    /**
     * Hook target.
     *
     * @var string|array
     */
    public string|array $target;

    public function __construct(
        string $tag,
        public readonly array|int|string $priority = 10,
        public readonly int $context = self::CTX_GLOBAL,
        public readonly array|string|\Closure|null $conditional = null,
        public readonly array|string|false $requires = false,
        public readonly string|array|false $modifiers = false,
    ) {
        $this->tag = $this->set_tag( $tag, $modifiers );
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

    public function get_priority(): int {
        $prio = $this->priority;
        $prio = match ( true ) {
            \defined( $prio )          => \constant( $prio ),
            \is_numeric( $prio )      => (int) $prio,
            \is_array( $prio )        => \call_user_func( $prio ),
            \is_callable( $prio )     => $prio(),
            \is_string( $prio )       => \apply_filters( $prio, 10, $this->tag ),
            default                  => 10,
        } ?? 10;

        $this->invoke_priority = (int) $prio;

        return $this->invoke_priority;
    }

    public function apply( string|object $target ): Invoker_Interface {
        if ( 'handler' === static::HOOK_TYPE ) {
            return $this->get_manager()->register_handler( $target );
        }

        throw new \InvalidArgumentException( 'Not implemented. For now...' );
    }

    public function get_manager(): Invoker_Interface {
        return Invoker::instance();
    }
}
