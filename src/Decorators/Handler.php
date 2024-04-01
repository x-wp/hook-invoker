<?php
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

/**
 * Handler decorator.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Base_Hook {
    public const HOOK_TYPE = 'handler';
}
