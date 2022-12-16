<?php 

namespace ph_hotmart;

class Api_Connection
{

    private $auth_method;

    public function __construct( Auth_Method $auth_method )
    {

        $this->auth_method = $auth_method;

    }

    public function get_sale_tracking_data( string $transaction_id ) : array
    {

        $tracking_data = array();

        $access_token = $this->auth_method->get_access_token();

        $Wp_Http_Curl = new \WP_Http_Curl();

        $response = $Wp_Http_Curl->request( 
            'https://developers.hotmart.com/payments/api/v1/sales/history' . 
            '?transaction='. $transaction_id, 
            array(
                'method' => 'GET',
                'headers' => array( 
                    'Content-Type' => 'application/json', 
                    'Authorization' => 'Bearer ' . $access_token
                )
            ) 
        );

        $body = $response['body'];

        $sale_data = json_decode( $body, true );

        if( empty( $sale_data ) )
            throw new \Exception("AuthMethod Error, invalid response.", 1);

        if( empty( $sale_data['items'][0]['purchase']['tracking'] ) )
            return $tracking_data;

        $tracking_data = $sale_data['items'][0]['purchase']['tracking'];

        return $tracking_data;

    }

}