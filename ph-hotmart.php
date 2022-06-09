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

if( !defined( __NAMESPACE__ . '\URL' ) )
	define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

class Ph_Hotmart
{

    static $instance = false;

    /*private function __construct()
    {

        //Add hooks

    }*/

    public static function get_instance()
    {

        if( !self::$instance )
            self::$instance = new self;

        return self::$instance;

    }

}

$ph_hotmart = Ph_Hotmart::get_instance();
