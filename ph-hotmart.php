<?php
/*
Plugin Name: Ph Hotmart connection
Plugin URI: elartedesabervivir.com
Description: Custom plugin that connects Ph with Hotmart.
Version: 1.0.0
Author: Victor Crespo
Author URI: https://victorcrespo.net
*/

namespace ph_hotmart;

if ( ! defined( 'ABSPATH' ) ) die();

if( !defined( __NAMESPACE__ . '\PATH' ) )
	define( __NAMESPACE__ . '\PATH', plugin_dir_path(__FILE__) );

require_once namespace\PATH.'ph-hotmart-public.php';
require_once namespace\PATH.'ph-hotmart-admin.php';


class Ph_Hotmart
{

    static $instance = false;

    private function __construct()
    {

        $public = new namespace\Plugin_Public();
        $admin = new namespace\Plugin_Admin();

        // Public hooks

        add_action( 'rest_api_init', [ $public, 'set_endpoint' ] );

        // Admin hooks 

        add_action( 'admin_menu', [$admin, 'register_page'] );
        add_action( 'admin_init', [$admin, 'register_settings'] );
        add_action( 'init', [ $admin, 'register_custom_order_status' ] );
        add_action( 'woocommerce_product_options_general_product_data', [ $admin, 'add_custom_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $admin, 'save_custom_field' ] );

        add_action( 'add_option_hotmart-webhook-token', [ $admin, 'change_autoload_to_no' ], 10, 2 );
        add_action( 'add_option_hotmart-client-id', [ $admin, 'change_autoload_to_no' ], 10, 2 );
        add_action( 'add_option_hotmart-client-secret', [ $admin, 'change_autoload_to_no' ], 10, 2 );
        add_action( 'add_option_hotmart-basic-auth', [ $admin, 'change_autoload_to_no' ], 10, 2 );
        add_action( 'add_option_ph-hotmart-admin-mail', [ $admin, 'change_autoload_to_no' ], 10, 2 );

        add_filter( 'wc_order_statuses', [ $admin, 'add_custom_order_status' ] );
        add_filter( 'woocommerce_product_data_store_cpt_get_products_query', [ $admin, 'handle_custom_query_var' ], 10, 2 );
        

    }

    public static function get_instance()
    {

        if( !self::$instance )
            self::$instance = new self;

        return self::$instance;

    }

}

$ph_hotmart = Ph_Hotmart::get_instance();
