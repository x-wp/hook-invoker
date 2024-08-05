<?php
/**
 * Context_Host class file.
 *
 * @package eXtended WordPress
 * @subpackage Hooks
 */

namespace XWP\Hook;

use Automattic\Jetpack\Constants;
use XWP\Contracts\Hook\Context;
use XWP\Contracts\Hook\Context_Interface;

/**
 * Enables easy context getting and comparison.
 */
final class Context_Host {
    /**
     * Get the current context.
     *
     * @return Context_Interface
     */
    public static function get_context(): Context_Interface {
        return match ( true ) {
            self::is_admin_request()    => Context::Admin,
            self::is_ajax_request()     => Context::Ajax,
            self::is_cron_request()     => Context::Cron,
            self::is_rest_request()     => Context::REST,
            self::is_frontend_request() => Context::Frontend,
            self::is_cli_request()      => Context::CLI,
            default                     => Context::Frontend,
        };
    }

    /**
     * Check if the context is valid.
     *
     * @param  int $context The context to check.
     * @return bool
     */
    public static function is_valid_context( int $context ): bool {
        return 0 !== ( self::get_context()->value & $context );
    }

    /**
     * Check if the request is a frontend request.
     *
     * @return bool
     */
    public static function is_frontend_request(): bool {
        return ! self::is_admin_request() && ! self::is_cron_request() && ! self::is_rest_request() && ! self::is_cli_request();
    }

    /**
     * Check if the request is an admin request.
     *
     * @return bool
     */
    public static function is_admin_request(): bool {
        return \is_admin() && ! self::is_ajax_request();
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @return bool
     */
    public static function is_ajax_request(): bool {
        return Constants::is_true( 'DOING_AJAX' );
    }

    /**
     * Check if the request is a cron request.
     *
     * @return bool
     */
    public static function is_cron_request(): bool {
        return Constants::is_true( 'DOING_CRON' );
    }

    /**
     * Check if the request is a REST request.
     *
     * @return bool
     */
    public static function is_rest_request(): bool {
        $prefix = \trailingslashit( \rest_get_url_prefix() );

        //phpcs:ignore
        return \strpos( $_SERVER['REQUEST_URI'], $prefix) !== false;
    }

    /**
     * Check if the request is a CLI request.
     *
     * @return bool
     */
    public static function is_cli_request(): bool {
        return Constants::is_true( 'WP_CLI' );
	}
}
