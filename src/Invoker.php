<?php // phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Hook Invoker
 */

namespace XWP\Hook;

use XWP\DI\Invoker as DI_Invoker;
use XWP\Helper\Traits\Singleton;

/**
 * Executes the registered handlers.
 *
 * @mixin DI_Invoker
 */
class Invoker {
    use Singleton;

    /**
     * Call the DI_Invoker method.
     *
     * @param  string $name Method name.
     * @param  array  $args Method arguments.
     * @return mixed
     */
    public function __call( string $name, array $args ): mixed {
        if ( \method_exists( DI_Invoker::class, $name ) ) {
            return DI_Invoker::instance()->$name( ...$args );
        }

        return null;
    }

    public function load_handler( object $instance, string $container = 'xwp-hook' ): static {
        DI_Invoker::instance()->load_handler( $instance, $container );

        return $this;
    }
}
