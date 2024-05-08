<?php
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

/**
 * Filter decorator.
 */
#[\Attribute( \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Action extends Filter {
    public const HOOK_TYPE = 'action';

    /**
     * Indirect action callback.
     *
     * This is overridden compared compared to filters because actions never return a value.
     *
     * @param  mixed ...$args Filter callback arguments.
     * @return void           Actions never return a value.
     */
    protected function indirect_callback( ...$args ) {
		parent::indirect_callback( ...$args );
    }
}
