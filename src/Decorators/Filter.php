<?php //phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use ReflectionMethod;
use XWP\DI\Decorators\Filter as DI_Filter;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Filter decorator.
 *
 * @template TObj of object
 * @template THnd of Can_Handle<TObj>
 * @extends DI_Filter<TObj,THnd>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Filter extends DI_Filter {
    public function set_reflector( ReflectionMethod $reflector ): static {
        return $this->with_reflector( $reflector );
    }
}
