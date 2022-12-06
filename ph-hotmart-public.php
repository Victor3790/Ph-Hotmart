<?php 

namespace ph_hotmart;

class Plugin_Public 
{
    
    public function set_endpoint() : void 
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

    public function process_sale() : void 
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

    public function check_origin() : bool 
    {

        if( empty( $_SERVER['HTTP_X_HOTMART_HOTTOK'] ) )
            return false;

        $token_sent = $_SERVER['HTTP_X_HOTMART_HOTTOK'];
        $token_in_db = get_option( 'hotmart-key', false );

        if( empty( $token_in_db ) )
            return false;

        $permission = strcmp( $token_in_db, $token_sent ) === 0 ? true : false;

        return $permission;

    }

    private function register_user( string $email ) : string 
    {

        $password = wp_generate_password( 12, true );
        $customer_id = wp_create_user( $email, $password, $email );

        $user = new \WP_User( $customer_id );
        $user->set_role('customer');

        return $customer_id;

    }

    private function add_order( array $data ) : void 
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

    private function order_exists( string $transaction_id ) : bool 
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

    private function send_error_mail( string $message = '' ) : void 
    {

        wp_mail( 
            'vescareno@phronesisvirtual.com', 
            'There has been an error, PH Hotmart plugin', 
            $message  
        );

    }

}

