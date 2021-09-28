<?php
namespace Endurance\WP\Module\Data\Helpers;

// DEBUG require statements
require_once($_SERVER["DOCUMENT_ROOT"]."/wp-load.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/wp-content/plugins/bluehost-wordpress-plugin/inc/AccessToken.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/wp-content/plugins/bluehost-wordpress-plugin/inc/SiteMeta.php");

use Endurance\WP\Module\Data\Helpers\Transient;
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
        $customer = json_encode( 
            array_merge(
                self::get_account_info(),
                self::get_onboarding_info()
            )
        );
        Transient::set( 'nfd_customer', $customer );
        
        // DEBUG
        echo '<h3>Customer Data:</h3><code>'. $customer . '</code>';

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

        // DEBUG
        echo '<h3>' . $url . '</h3><code>'. wp_remote_retrieve_body( $response ) . '</code>';

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
        $info['cust_id']      = AccessToken::get_user();
        $info['affiliate']    = $response->affiliate->id .":". $response->affiliate->tracking_code;
        $info['provider']     = $response->customer->provider;
        $info['term']         = $response->plan->term;
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
           
        if ( isset( $response->site_intentions->want_blog ) ) 
            $info['want_blog'] = $response->site_intentions->want_blog;
        
        if ( isset( $response->site_intentions->want_store ) )
            $info['want_store'] = $response->site_intentions->want_store;
        
        if ( isset( $response->site_intentions->type ) )
            $info['vertical'] = $response->site_intentions->type .':'. $response->site_intentions->topic; 

        return $info;
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

// DEBUG
// Fire it off - this is just for testing- it will be called fron Cron job later on.
Customer::collect();