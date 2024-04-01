<?php
/**
 * Executor_Debug class file.
 *
 * @package eXtended WordPress
 * @subpackage Debug
 */

namespace XWP\Hook\Debug;

use Automattic\Jetpack\Constants;
use XWP\Hook\Decorators\Action;
use XWP\Hook\Decorators\Filter;
use XWP\Hook\Decorators\Handler;
use XWP\Hook\Invoker;

/**
 * Initializes the debug panel and status for the Executor service
 */
#[Handler(
	tag: 'plugins_loaded',
	priority: 10,
	context: Handler::CTX_GLOBAL & ~Handler::CTX_CLI & ~Handler::CTX_REST,
)]
class Debug_Handler {
    /**
     * Do we need to activate the debug panel
     *
     * @return bool
     */
    public static function can_invoke() {
        if ( ! \class_exists( '\Debug_Bar' ) || ! Constants::is_true( 'XWP_HOOK_DEBUG' ) ) {
            return false;
        }

        return Constants::is_true( 'JETPACK_DEV_DEBUG' ) ||
            Constants::is_true( 'WP_DEBUG' ) ||
            'production' !== \wp_get_environment_type() ||
            \wp_get_development_mode();
    }

    /**
     * Adds the actual debug panel to the debug bar
     *
     * @param  array<Debug_Bar_Panel> $panels Debug bar panels.
     * @return array<Debug_Bar_Panel>         Debug bar panels.
     */
    #[Filter( tag: 'debug_bar_panels' )]
    public function add_debug_panel( array $panels ): array {
        $panels[] = new Debug_Panel();

        return $panels;
    }

    /**
     * Adds the execution context to the debug bar
     *
     * @param  array $statuses Debug bar statuses.
     * @return array           Debug bar statuses.
     */
    #[Filter( tag: 'debug_bar_statuses', priority: 999 )]
    public function add_ctx_status( array $statuses ): array {
        $statuses[] = array(
            'execution_ctx',
            'Execution Context',
            Invoker::instance()->get_context()->display_name(),
        );

        return $statuses;
    }

    /**
     * Outputs the CSS for the debug panel
     */
    #[Action( tag: 'debug_bar_enqueue_scripts' )]
    public function output_css() {
        //phpcs:ignore
        echo '<' . 'style type="text/css">' . "\n";
        echo <<<'CSS'

        CSS;
        echo '</style>';
    }

    /**
     * Adds the VSCode protocol to the allowed kses protocols
     *
     * @param  array $protocols Allowed protocols.
     * @return array
     */
    #[Filter( 'kses_allowed_protocols' )]
    public function add_vscode_protocol( array $protocols ): array {
        $protocols[] = 'vscode';

        return $protocols;
    }
}
