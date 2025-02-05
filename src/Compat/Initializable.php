<?php
/**
 * Handler interface file.
 *
 * @package eXtended WordPress
 * @subpackage Contracts
 */

namespace XWP\Contracts\Hook;

use ReflectionClass;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Handler interface.
 *
 * @template THndlr of object
 * @extends Can_Handle<THndlr>
 */
interface Initializable extends Can_Handle {
    /**
     * Set the reflector.
     *
     * @param  ReflectionClass<THndlr> $reflector Reflector instance.
     * @return static
     */
    public function set_reflector( ReflectionClass $reflector ): static;
}
