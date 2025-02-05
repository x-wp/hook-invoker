<?php
/**
 * Hook Invoker container initialization.
 *
 * @package eXtended WordPress
 * @subpackage Hook Invoker
 */

if ( ! function_exists( 'xwp_hook_ctr_init' ) && function_exists( 'add_action' ) ) :

    /**
     * Initialize the shared container.
     *
     * @return void
     */
    function xwp_hook_ctr_init(): void {
        if ( xwp_has( 'xwp-hook' ) ) {
            return;
        }

        xwp_create_app(
            array(
                'attributes' => false,
                'autowiring' => true,
                'compile'    => false,
                'id'         => 'xwp-hook',
                'module'     => XWP\Hook\Dummy_Module::class,
                'proxies'    => false,
            ),
        );

        do_action( 'xwp_hook_ctr_init' );
    }

    did_action( 'plugins_loaded' )
        ? xwp_hook_ctr_init()
        : add_action( 'plugins_loaded', 'xwp_hook_ctr_init', PHP_INT_MIN, 0 );

endif;
