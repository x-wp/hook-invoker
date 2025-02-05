<?php //phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use ReflectionMethod;
use XWP\DI\Decorators\Action as DI_Action;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Action decorator.
 *
 * @template TObj of object
 * @template THnd of Can_Handle<TObj>
 * @extends DI_Action<TObj,THnd>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Action extends DI_Action {
    public function set_reflector( ReflectionMethod $reflector ): static {
        return $this->with_reflector( $reflector );
    }
}
