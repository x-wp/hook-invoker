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
    public static function get(): Context_Interface {
        return match ( true ) {
            self::admin()    => Context::Admin,
            self::ajax()     => Context::Ajax,
            self::cron()     => Context::Cron,
            self::rest()     => Context::REST,
            self::cli()      => Context::CLI,
            self::frontend() => Context::Frontend,
            default          => Context::Frontend,
        };
    }

    /**
     * Check if the context is valid.
     *
     * @param  int $context The context to check.
     * @return bool
     */
    public static function is_valid_context( int $context ): bool {
        return 0 !== ( self::get()->value & $context );
    }

    /**
     * Check if the request is a frontend request.
     *
     * @return bool
     */
    public static function frontend(): bool {
        return ! self::admin() && ! self::cron() && ! self::rest() && ! self::cli();
    }

    /**
     * Check if the request is an admin request.
     *
     * @return bool
     */
    public static function admin(): bool {
        return \is_admin() && ! self::ajax();
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @return bool
     */
    public static function ajax(): bool {
        return Constants::is_true( 'DOING_AJAX' );
    }

    /**
     * Check if the request is a cron request.
     *
     * @return bool
     */
    public static function cron(): bool {
        return Constants::is_true( 'DOING_CRON' );
    }

    /**
     * Check if the request is a REST request.
     *
     * @return bool
     */
    public static function rest(): bool {
        $prefix = \trailingslashit( \rest_get_url_prefix() );

        return false !== \strpos( \xwp_fetch_server_var( 'REQUEST_URI', '' ), $prefix );
    }

    /**
     * Check if the request is a CLI request.
     *
     * @return bool
     */
    public static function cli(): bool {
        return Constants::is_true( 'WP_CLI' );
	}
}
