<?php
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

/**
 * Filter decorator.
 */
#[\Attribute( \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Action {
    public const HOOK_TYPE = 'filter';

    /**
     * Indirect filter callback.
     *
     * This is overridden compared compared to action because filters can return a value.
     *
     * @param  mixed ...$args Filter callback arguments.
     * @return mixed Filter callback return value.
     */
    protected function indirect_callback( ...$args ) {
        if ( ! $this->can_invoke() || ! $this->handler->can_initialize() ) {
            return $args[0] ?? null;
        }

        $args[] = $this;

        return \call_user_func_array( $this->target, $args );
    }
}
