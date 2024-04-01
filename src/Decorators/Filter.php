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
class Filter extends Base_Hook {
    public const HOOK_TYPE = 'filter';
}
