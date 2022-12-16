<?php 

namespace ph_hotmart;

class Auth_Method 
{

    public function get_access_token() : string 
    {

        $db_token_data = get_option( 'hotmart-token-data', null );

        if( ! empty( $db_token_data['access_token'] ) && ! empty( $db_token_data['expires_in'] ) )
            if( $this->is_token_valid( $db_token_data['expires_in'] ) )
                return $db_token_data['access_token']; 

        $client_id = get_option( 'hotmart-client-id', null );
        $client_secret = get_option( 'hotmart-client-secret', null );
        $basic_auth = get_option( 'hotmart-basic-auth', null );

        if(
            empty( $client_id ) || 
            empty( $client_secret ) || 
            empty( $basic_auth )
        )
            throw new \Exception("Hotmart Membership Error, no token auth data set.", 2);

        $Wp_Http_Curl = new \WP_Http_Curl();

        $response = $Wp_Http_Curl->request( 
            'https://api-sec-vlc.hotmart.com/security/oauth/token' . 
            '?grant_type=client_credentials'. 
            '&client_id='.$client_id. 
            '&client_secret='.$client_secret, 
            array(
                'method' => 'POST',
                'headers' => array( 
                    'Content-Type' => 'application/json', 
                    'Authorization' => 'Basic '.$basic_auth 
                )
            ) 
        );

        $body = $response['body'];

        $token_data = json_decode( $body, true );

        if( empty( $token_data ) )
            throw new \Exception("AuthMethod Error, invalid response.", 1);

        $token_data['expires_in'] = date("Y-m-d h:m:s", strtotime( '+ '.$token_data['expires_in'].' seconds' ) );
        
        update_option( 'hotmart-token-data', $token_data, 'no' );

        return $token_data['access_token'];

    }

    private function is_token_valid( string $token_exp_date ) : bool 
    {

        $today = new \DateTime();
        $exp_date = new \DateTime( $token_exp_date );

        if( $today < $exp_date )
            return true;

        return false;

    }

}
