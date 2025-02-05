<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Decorators
 */

namespace XWP\Hook\Decorators;

use Closure;
use ReflectionClass;
use XWP\DI\Decorators\Handler as DI_Handler;

/**
 * Handler decorator.
 *
 * @template T of object
 * @extends DI_Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends DI_Handler {
    /**
     * Constructor.
     *
     * @param string                                         $tag         Hook tag.
     * @param Closure|string|int|array{class-string,string}  $priority    Hook priority.
     * @param string                                         $container   Container ID.
     * @param int                                            $context     Hook context.
     * @param null|Closure|string|array{class-string,string} $conditional Conditional callback.
     * @param array<int,string>|string|false                 $modifiers   Values to replace in the tag name.
     * @param string                                         $strategy    Initialization strategy.
     * @param bool                                           $hookable    Is the handler hookable.
     */
    public function __construct(
        ?string $tag = null,
        Closure|string|int|array $priority = 10,
        ?string $container = 'xwp-hook',
        int $context = self::CTX_GLOBAL,
        array|string|Closure|null $conditional = null,
        string|array|false $modifiers = false,
        string $strategy = self::INIT_DEFFERED,
        ?bool $hookable = null,
    ) {
        parent::__construct(
            tag: $tag,
            priority: $priority,
            container: $container,
            context: $context,
            conditional: $conditional,
            modifiers: $modifiers,
            strategy: $strategy,
            hookable: $hookable,
        );
    }

    public function set_reflector( ReflectionClass $reflector ): static {
        return $this->with_reflector( $reflector );
    }
}
