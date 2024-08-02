<?php //phpcs:disable Universal.Operators.DisallowShortTernary
/**
 * Executor_Debug_Panel class file.
 *
 * @package eXtended WordPress
 * @subpackage Debug
 */

namespace XWP\Hook\Debug;

use XWP\Contracts\Hook\Hookable;
use XWP\Contracts\Hook\Initializable;
use XWP\Contracts\Hook\Invokable;
use XWP\Hook\Invoker;

/**
 * Outputs the Executor debug panel
 */
class Debug_Panel extends \Debug_Bar_Panel {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( 'xWP Hook Invoker' );
    }

    /**
     * Set the panel to be visible
     */
    public function prerender() {
        $this->set_visible( true );
    }

    /**
     * Renders the header
     */
    private function render_header() {
        echo '<div id="debug-bar-executor">' . "\n";
    }

    /**
     * Renders the panel
     */
    public function render() {
        $this->render_header();

        $executor = Invoker::instance();

        foreach ( $executor->get_handlers() as $handler => $data ) {
            $this->render_handler( $handler, $data );

            $this->render_hooks( $executor->get_hooks( $handler ) );

        }

        echo '</div>' . "\n";
    }

    /**
     * Render the handler
     *
     * @param  string        $handler Handler name.
     * @param  Initializable $data    Handler data.
     */
    private function render_handler( string $handler, Initializable $data ) {
        $reflector = new \ReflectionClass( $handler );
        echo '<h3>' . \wp_kses_post( $this->render_handler_header( $reflector, $data ) ) . '</h3>' . "\n";
    }

    /**
     * Render the hooks
     *
     * @param  array<class-string, array<Invokable>> $hook_data  Hooks data.
     */
    private function render_hooks( $hook_data ) {
        if ( ! $hook_data ) {
            echo '<strong>NO HOOKS</strong>' . "\n";
            return;
        }

        foreach ( $hook_data as $method => $hooks ) {

            echo '<h4>&rarr;' . \esc_html( $method ) . '()</h4>' . "\n";

            echo '<ul>' . "\n";
            foreach ( $hooks as $hook ) {
                $this->render_hook( $hook );
            }

            echo '</ul>';
        }
    }

    /**
     * Render the hook
     *
     * @param  Invokable $hook Hook data.
     */
    private function render_hook( Invokable $hook ) {
        \printf(
            '<li style="font-size: 15px" >%s %s on <strong>%s</strong> with priority %s (%s)</li>' . "\n",
            \esc_html( $hook::HOOK_TYPE ),
            \esc_html( $hook->invoked ? 'invoked' : 'registered' ),
            \esc_html( $hook->tag ),
            \esc_html( $hook->priority ),
            \esc_html( $hook->real_priority ),
        );
    }

    /**
     * Render the handler header
     *
     * @param  \ReflectionClass $r    Reflection class.
     * @param  Initializable    $hook Hook data.
     * @return string
     */
    private function render_handler_header( \ReflectionClass $r, Initializable $hook ): string {
        return \sprintf(
            '<a href="%s"><strong>%s</strong></a> &ndash; %s',
            $this->get_file_path( $r->getFileName() ),
            $r->getName(),
            $hook->initialized ? 'Initialized' : 'registered',
        );
    }

    /**
     * Transform the file path to a vscode protocol
     *
     * @param  string $path File path.
     */
    private function get_file_path( string $path ) {
        $project_name = \getenv( 'DDEV_PROJECT' ) ?: false;
        $ddev_wp_path = \getenv( 'DDEV_WP_PATH', ) ?: '';

        $path_map = \apply_filters( 'xwp_executor_path_maps', array(), $ddev_wp_path, $project_name );

        return \sprintf(
            'vscode://vscode-remote/wsl+WLinux%s:1',
            \strtr( $path, $path_map ),
        );
    }
}
