<?php
/**
 * Invokable interface file.
 *
 * @package eXtended WordPress
 * @subpackage Contracts
 */

namespace XWP\Contracts\Hook;

use ReflectionMethod;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Invokable interface. Used by action and filter decorators.
 *
 * @template TInst of object
 * @template THndl of Can_Handle<TInst>
 * @extends Can_Invoke<TInst,THndl>
 */
interface Invokable extends Can_Invoke {
    /**
     * Set the reflector.
     *
     * @param  ReflectionMethod $reflector Reflector instance.
     * @return static
     */
    public function set_reflector( ReflectionMethod $reflector ): static;
}
