<?php
namespace Endurance\WP\Module\Data\Helpers;

use Bluehost\AccessToken;
use Bluehost\SiteMeta;

/**
 * Helper class for gathering and formatting customer data
 */
class Customer {

    /**
	 * Prepare customer data
	 *
     * @return array of customer data
	 */
    public static function collect() {
        $guapi = self::get_account_info();
        $mole  = array( 'meta' => self::get_onboarding_info() );
        $customer =  array_merge( $guapi, $mole );
		return $customer;
	}

    /**
     * Connect to API with token via AccessToken Class in Bluehost Plugin
     * 
     * @param string $path of desired API endpoint
     * @return object of response data in json format
     */
    public static function connect( $path ) {
        if ( ! $path ) {
            return;
        }
        // AccessToken::refresh_token();
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


    /**
     * Connect to the hosting info (guapi) endpoint and format response into hiive friendly data
     * 
     * @return array of relevant data
     */
    public static function get_account_info(){
        $info     = array();
        $response = self::connect( '/hosting-account-info' );
        
        // transfer relevant data to $info array
        $info['customer_id']  = AccessToken::get_user();
        $info['affiliate']    = $response->affiliate->id .":". $response->affiliate->tracking_code;
        $info['provider']     = $response->customer->provider;
        $info['plan_term']    = $response->plan->term;
        $info['plan_type']    = $response->plan->type;
        $info['plan_subtype'] = $response->plan->subtype;
        $info['signup_date']  = $response->customer->signup_date;
        // $info['ltv'] // life time spend
        // $info['cancel_date']

        return $info;
    }

    
    /**     
     * Connect to the onboarding info (mole) endpoint and format response into hiive friendly data
     * 
     * @return array of relevant data
     */
    public static function get_onboarding_info(){
        $info     = array();
        $response = self::connect( '/onboarding-info' );

        // transfer existing relevant data to $info array
        $comfort = self::normalize_comfort($response->description->comfort_creating_sites); // normalize to 0-100 value
        if ( $comfort > 0 ) 
            $info['comfort'] = $comfort;

        $help = self::normalize_help($response->description->help_needed); // normalize to 0-100
        if ( $help > 0 )
            $info['help'] = $help;
           
        $blog = self::normalize_blog($response->site_intentions->want_blog);
        if ( $blog > 0 ) 
            $info['blog'] = $blog;
        
        $store = self::normalize_store($response->site_intentions->want_store);
        if ( $store > 0 )
            $info['store'] = $store;
        
        if ( isset( $response->site_intentions->type ) )
            $info['type'] = $response->site_intentions->type;

        if ( isset( $response->site_intentions->topic ) )
            $info['topic'] = $response->site_intentions->topic;

        if ( isset( $response->site_intentions->owner ) )
            $info['owner'] = $response->site_intentions->owner;

        return $info;
    }

    /**
     * Normalize blog
     * 
     * For now this is just 0 or 20 values, but in the future we can update based on other factors and treat as a blog score
     */
    public static function normalize_blog($blog){
        switch ($blog){
            case '1':
                return 20;
                break;
            default: // 0 or blank
                return 0;
                break;
        }
    }

    /**
     * Normalize store
     * 
     * For now this is just 0 or 20 values, but in the future we can update based on other factors and treat as a store score
     */
    public static function normalize_store($store){
        switch ($store){
            case '1':
                return 20;
                break;
            default: // 0 or blank
                return 0;
                break;
        }
    }

    /**
     * Normalize values returned for comfort_creating_sites:
     * -1 When "Skip this step" is clicked
     *  0 When selected comfort level is closest to "A little" and "Continue" is clicked
     *  1 When selected comfort level is second closest to "A little" and "Continue" is clicked
     *  2 When selected comfort level is second closest to "Very" and "Continue" is clicked
     *  3 When selected comfort level is closest to "Very" and "Continue" is clicked
     * 
     * @param string $comfort value returned from api for comfort_creating_sites
     * @return integer representing normalized comfort level 
     */
    public static function normalize_comfort($comfort){
        switch ($comfort){
            case "0":
                return 1;
                break;
            case "1":
                return 33;
                break;
            case "2":
                return 66;
                break;
            case "3":
                return 100;
                break;
            default: // -1 or blank
                return 0; // null ?
                break;
        }
    }

    /**
     * Normalize values returned for help_needed:
     * no_help When "No help needed" is clicked
     * diy_with_help When "A little Help" is clicked
     * do_it_for_me When "Built for you" is clicked
     * skip When "Skip this step" is clicked
     * 
     * @param string $help value returned from api for help_needed
     * @return integer representing normalized help level
     */
    public static function normalize_help($help){
        switch ($help){
            case "no_help":
                return 1;
                break;
            case "diy_with_help":
                return 50;
                break;
            case "do_it_for_me":
                return 100;
                break;
             default: // skip
                return 0; // null ?
                break;
        }
    }

}