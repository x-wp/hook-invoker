<?php
/**
 * Dummy_Module class file.
 *
 * @package eXtended WordPress
 * @subpackage Hook
 */

namespace XWP\Hook;

use XWP\DI\Decorators\Module;

/**
 * Dummy module for container initialization.
 */
#[Module( container: 'xwp-hook', hook: 'xwp_hook_ctr_init', priority: 0 )]
class Dummy_Module {
}
