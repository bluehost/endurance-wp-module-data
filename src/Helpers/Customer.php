<?php

namespace Endurance\WP\Module\Data\Helpers;


/**
 * Helper class for gathering and formatting customer data
 */
class Customer {

    /**
	 * Prepare customer data
	 *
	 * @return array of data for customer
	 */
	public static function collect() {
        $customer = array(
            'account'    => self::get_account_info(),
            'onboarding' => self::get_onboarding_info(),
        );
        Transient::set( 'nfd_customer', json_encode( $customer ) );
		return $customer;
	}


    // Wrapper to connect to API via AccessToken Class in Bluehost Plugin
    public static function connect( $path ) {
        if ( ! $path ) {
            return;
        }
        AccessToken::refresh_token();
        AccessToken::maybe_refresh_token();
        $token         = AccessToken::get_token();
        $user_id       = AccessToken::get_user();
        $domain        = SiteMeta::get_domain();
        $api_endpoint  = 'https://my.bluehost.com/api/users/'.$user_id.'/usersite/'.$domain;
        $args          = array( 'headers' => array( 'X-SiteAPI-Token' => $token ) );
        $url           = $api_endpoint . $path;
        $response      = wp_remote_get( $url, $args );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 ) {
            return;
        }
        
        return json_decode( wp_remote_retrieve_body( $response ) );
    }


    // Use wrapper to connect to account info endpoint (guapi)
    public static function get_account_info(){
        $info     = array();
        $response = self::connect( '/hosting-account-info' );
        
        // transfer relevant data to $info array
        $info['cust_id']     = AccessToken::get_user();
        $info['affiliate']   = $response->affiliate;
        $info['provider']    = $response->customer->provider;
        $info['term']        = $response->plan->term;
        $info['plan']        = $response->plan->type . '-' . $response->plan->subtype;
        $info['signup_date'] = $response->customer->signup_date;
        // $info['ltv'] // life time spend
        // $info['cancel_date']

        return $info;
    }

    // User wrapper to connect to onboarding info (mole)
    public static function get_onboarding_info(){
        $info     = array();
        $response = self::connect( '/onboarding-info' );

        // transfer relevant data to $info array
        $info['comfort_level'] = $response->description->comfort_creating_sites; // normalize to 1-100 value
        // $info['wants_blog'] 
        // $info['wants_store'] 

        return $info;
    }

}