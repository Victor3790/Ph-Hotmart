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

    private function __construct()
    {

        add_action( 'rest_api_init', [ $this, 'set_endpoint' ] );
        add_action( 'init', [ $this, 'register_custom_order_status' ] );
        add_filter( 'wc_order_statuses', [ $this, 'add_custom_order_status' ] );

    }

    public static function get_instance()
    {

        if( !self::$instance )
            self::$instance = new self;

        return self::$instance;

    }

    public function set_endpoint()
    {

        register_rest_route( 
            'ph-hotmart/v1', 
            '/add-sale', 
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'process_sale' ],
                'permission_callback' => [ $this, 'check_origin' ]
            ) 
        );

    }

    public function process_sale()
    {

        $JSON_data = file_get_contents('php://input');
        $data = json_decode( $JSON_data, true );

        if( is_null( $data ) ){

            $this->send_error_mail( 'Hotmart did not send any data.' );
            return;

        }

        $email = $data['data']['buyer']['email'];

        $customer_id = null;

        if( ! is_email( $email ) ) {

            $transaction = $data['data']['purchase']['transaction'];
            $this->send_error_mail( 'Hotmart did not send a valid user email. Transaction: ' . $transaction );
            return;

        }
        
        if( ! email_exists( $email ) ) {

            $customer_id = $this->register_user( $email );

        }else{

            $customer = get_user_by( 'email', $email );
            $customer_id = $customer->id;

        }

        $order_data = array(
            'customer_id' => $customer_id,
            'product_id' => 108,
            'price' => $data['data']['purchase']['original_offer_price']['value'],
            'commissions' => $data['data']['commissions'],
            'transaction' => $data['data']['purchase']['transaction'],
            'date' => $data['data']['purchase']['order_date']
        );

        $this->add_order( $order_data );

        return rest_ensure_response( 'Hello World, this is the WordPress REST API'.PHP_EOL );

    }

    public function register_custom_order_status()
    {

        $args = array(
            'label'                     => 'Hotmart Completed',
            'public'                    => true,
            'show_in_admin_status_list' => true, 
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Hotmart Completed <span class="count">(%s)</span>', 'Hotmart Completed <span class="count">(%s)</span>' )
        );
        register_post_status( 'wc-hotmart-completed', $args );

    }

    public function add_custom_order_status( $order_statuses )
    {

        $new_order_statuses = array();

        foreach ($order_statuses as $key => $value) {
        
            $new_order_statuses[$key] = $value;

            if( $key === 'wc-completed' ) {

                $new_order_statuses['wc-hotmart-completed'] = 'Completado en Hotmart';

            }

        }

        return $new_order_statuses;

    }

    public function check_origin()
    {

        return true;

    }

    private function register_user( $email )
    {

        $password = wp_generate_password( 12, true );
        $customer_id = wp_create_user( $email, $password, $email );

        $user = new \WP_User( $customer_id );
        $user->set_role('customer');

        return $customer_id;

    }

    private function add_order( $data )
    {

        $order_args = array(
            'customer_id' => $data['customer_id'],
        );

        $order = wc_create_order( $order_args );
        $product = wc_get_product( $data['product_id'] );

        $order->add_product( $product, 1, array('total'=>$data['price']) );
        $order->set_total($data['price']);

        //Add meta with total commissions.
        
        $total_commissions = 0;

        if( empty( $data['commissions'] ) )
            $data['commissions'] = array();

        foreach ( $data['commissions'] as $commission ) {
        
            $total_commissions += $commission['value'];

        }

        //Get the date

        $ts = $data['date'] / 1000;
        $date = date( DATE_ATOM, $ts );

        //Set info

        $order->update_meta_data( 'comisiones_hotmart', $total_commissions );
        $order->set_payment_method( 'Hotmart' );
        $order->set_payment_method_title( 'Hotmart' );
        $order->set_date_paid( $date );
        $order->set_transaction_id( $data['transaction'] );
        $order->update_status('wc-completed');
        $order->update_status('wc-hotmart-completed');

        $order->save();

    }

    private function send_error_mail( $message = null )
    {

        wp_mail( 
            'vescareno@phronesisvirtual.com', 
            'There has been an error, PH Hotmart plugin', 
            $message  
        );

    }

}

$ph_hotmart = Ph_Hotmart::get_instance();
