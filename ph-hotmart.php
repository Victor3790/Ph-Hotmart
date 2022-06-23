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
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_custom_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_custom_field' ] );

        add_filter( 'wc_order_statuses', [ $this, 'add_custom_order_status' ] );
        add_filter( 'woocommerce_product_data_store_cpt_get_products_query', [ $this, 'handle_custom_query_var' ], 10, 2 );

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

        if( $data['event'] != 'PURCHASE_COMPLETE' )
            return;

        if( $this->order_exists( $data['data']['purchase']['transaction'] ) )
            return;

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

        if( empty( $data['data']['product']['id'] ) )
            return;

        $query = new \WC_Product_Query();
        $query->set( '_hotmart_product_id', $data['data']['product']['id'] );
        $query->set( 'return', 'ids' );
        $product_id = $query->get_products();

        if( empty( $product_id ) ) {

            $transaction = $data['data']['purchase']['transaction'];
            $this->send_error_mail( 'Product id not set in Woocommerce. ID: ' . $data['data']['product']['id'] );
            return;

        }

        $order_data = array(
            'customer_id' => $customer_id,
            'product_id' => $product_id[0],
            'price' => $data['data']['purchase']['original_offer_price']['value'],
            'commissions' => $data['data']['commissions'],
            'transaction' => $data['data']['purchase']['transaction'],
            'date' => $data['data']['purchase']['order_date']
        );

        $this->add_order( $order_data );

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

        if( empty( $_GET['un'] ) || empty( $_GET['pass'] ) )
            return false;

        $user = sanitize_text_field( $_GET['un'] );
        $password = sanitize_text_field( $_GET['pass'] );

        if( $user !== 'phronesis' || $password !== 'ph22062022' )
            return false;

        return true;

    }

    public function add_custom_field()
    {

        global $woocommerce, $post;

        echo '<div class="product_custom_field">';

        woocommerce_wp_text_input(
            array(
                'id' => '_hotmart_product_id',
                'placeholder' => 'Id',
                'label' => 'Hotmart id'
            )
        );

        echo '</div>';

    }

    public function save_custom_field( $post_id )
    {

        if( empty( $_POST['_hotmart_product_id'] ) )
            return;

        $hotmart_id = esc_attr( $_POST['_hotmart_product_id'] );

        update_post_meta( $post_id, '_hotmart_product_id', $hotmart_id );

    }

    public function handle_custom_query_var( $query, $query_vars )
    {

        if( empty( $query_vars['_hotmart_product_id'] ) )
            return $query;

        $query['meta_query'][] = array(
            'key' => '_hotmart_product_id',
            'value' => esc_attr( $query_vars['_hotmart_product_id'] )
        );

        return $query;

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
        
            if( $commission['source'] == 'PRODUCER' )
                continue;
                
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

    //Checks if the order has been processed already.

    private function order_exists( $transaction_id )
    {

        $args = array(
            'transaction_id' => $transaction_id
        );

        $order = wc_get_orders( $args );

        if( empty( $order ) )
            return false;

        return true;

    }

    //Sends an email to admin in case of error in Hotmart info.

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
