<?php
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

/**
 * Action decorator.
 */
#[\Attribute( \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Action extends Base_Hook {
    public const HOOK_TYPE = 'action';
}
