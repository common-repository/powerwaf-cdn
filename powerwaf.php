<?php
/**
 * Plugin Name:  PowerWAF CDN
 * Plugin URI:   https://www.powerwaf.com/en/
 * Description:  Protect and speed up your website by enabling dynamic & static page caching on PowerWAF CDN. Automatically sync your WordPress site with PowerWAF CDN
 * Version:      1.0.3
 * Author:       PowerWAF
 * Author URI:   https://www.powerwaf.com/
 * License:      GPLv2 or later
 * Text Domain:  powerwaf
 * Requires at least: 4.9
 * Requires PHP: 7.0
 */

register_deactivation_hook( __FILE__, 'powerwaf_deactivate' );
register_activation_hook( __FILE__, 'powerwaf_activate' );

if (!defined('POWERWAF_VERSION')) define( 'POWERWAF_VERSION', '1.0.0' );

if ( is_admin() ) {
    if (! class_exists('pwf_layout')) require 'adminlayout.php';   // Menu items
    if (! class_exists('pwf_dashboard'))  require 'dashboard.php';   // Dashboard
    if (! class_exists('pwf_settings'))  require 'settings.php';   // Settings page    // Admin only
}



if ( !class_exists( 'pwf_powerwaf' ) ) {
    class pwf_powerwaf {

        public static function init()
        {

            // Register actions
            // See: https://codex.wordpress.org/Plugin_API/Action_Reference
            add_action('post_updated', 'pwf_post_updated', 10, 3);


        }

        /**
         * Send a request to PowerWAF api.
         *
         * @param string $api Api name
         * @param string $user Api username
         * @param string $pass Api password
         * @param array $postVars  Array containing properties. Example: array( ["val" => 23], ["id" => 12] )
         * @return bool|object Lo que responde el servidor
         */
        public static function Request(string $api, string $user,string $pass,array $postVars)  {

            // Prepend a / if not present
            if (substr( $api, 0, 1 ) != '/') { $api='/'.$api; }

            // Select production or development environment
            $url="https://cloud.powerwaf.com";
            $postVars['user']=$user;
            $postVars['key']=$pass;
            $postVarsString=$params=http_build_query($postVars);
            $response = wp_remote_post( "{$url}{$api}" ,array('timeout' => 30, 'method' => 'POST', 'body' => $postVarsString) );
            if (is_wp_error( $response ) ) {
                error_log('PowerWAF API Call Error');
                return "{}";
            }

            $server_output = wp_remote_retrieve_body( $response );
            return json_decode($server_output);
        }

        // Clear cache. Url can be absolute or relative. Must start with '/'
        public static function clearCache($url) {
            $options=get_option( 'powerwaf_settings' );

            if (self::isDuplicatedCall($url)) {return;}

            // Prepare variables
            $theURL = preg_replace('#^https*://.+?/#m', '/', $url);

            error_log("Clear cache: site {$options['domain']} url $theURL");
            $response=self::Request('/api/cache/clear',$options['api_user'], $options['api_password'], array(
                'site' => $options['domain'],
                'url' => $theURL,
            ));
            if ($response->status!='OK') {
                error_log("PowerWAF API Error: {$response->errormsg}");
            }

        }

        // TODO: make this a real update method
        public static function updateCache($url) {
            $options=get_option( 'powerwaf_settings' );

            if (self::isDuplicatedCall($url)) {return;}

            $theURL = preg_replace('#https*://.+?/#m', '/', $url);
            error_log("Update cache: site {$options['domain']} url $theURL");
            $response=self::Request('/api/cache/clear',$options['api_user'], $options['api_password'], array(
                'site' => $options['domain'],
                'url' => $theURL,
            ));
            if ($response->status!='OK') {
                error_log("PowerWAF API Error: {$response->errormsg}");
            }


        }

        private static function isDuplicatedCall($url) {
            // Avoid duplicated calls in a 2 seconds range
            $lastUpdateTime=get_option('powerwafcdn_last_update_time');
            $lastUpdateUrl=get_option('powerwafcdn_last_update_url');

            if ($url==$lastUpdateUrl &&  $lastUpdateTime!=0 && time()-$lastUpdateTime<=2) {
                return true;
            }
            update_option('powerwafcdn_last_update_time',time());
            update_option('powerwafcdn_last_update_url',$url);
            return false;
        }

    }

    pwf_powerwaf::init();


}



// Called when a post is saved. https://developer.wordpress.org/reference/hooks/post_updated/
// post_updated(id, post after, post before)
function pwf_post_updated($post_id, WP_Post $wp_post2,WP_Post $wp_post1 ) {


    $url1=get_permalink($wp_post1,false);
    $url2=get_permalink($wp_post2,false);

    $deletePost=(int) ('publish' == $wp_post1->post_status && $wp_post2->post_status!='publish');
    $renamePost=(int) ('publish' == $wp_post1->post_status && $wp_post2->post_status=='publish' && $url1 !== $url2);
    $publishPost=(int) ( 'publish' == $wp_post2->post_status );


    //error_log("Post 1: $url1 Type: {$wp_post1->post_type} Status: {$wp_post1->post_status}");
    //error_log("Post 2: $url2 Type: {$wp_post2->post_type} Status: {$wp_post2->post_status}");
    //error_log("Delete: $deletePost Rename: $renamePost Publish: $publishPost");


    //if it is just a revision don't worry about it
    if (wp_is_post_revision($post_id)) {
        error_log('Post revision. Exiting');
        return;
    }

    if (wp_is_post_autosave($post_id)) {
        error_log('Post autosave. Ignoring');
        return;
    }

    // Gutemberg editor call this function with REST_REQUEST=true and then a second call to this hook is made.
    // No sirve de nada chequear esto. Hay que asumir que algunas veces se llama 2 veces la funcion.
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        //error_log("REST REQUEST");
    }

    if ($renamePost) {
        //error_log("[PowerWAF Plugin]: Rename $url1 to $url2");
        pwf_powerwaf::clearCache($url1);
        //pwf_powerwaf::updateCache($url2); <- as it's a new url it can't be updated.
        return;
    }

    if ($deletePost) {
        //error_log("[PowerWAF Plugin]: Remove $url1");
        pwf_powerwaf::clearCache($url1);
        return;
    }

    // Post changed
    if ($publishPost || $deletePost) {
        //error_log("[PowerWAF Plugin]: Update " . $url2);
        pwf_powerwaf::updateCache($url2);
        return;
    };

}




function powerwaf_deactivate() {
    error_log('Deactivate powerwaf');
    $options=get_option( 'powerwaf_settings' );
    if (!empty($options['api_user']) and  !empty($options['api_password']) and !empty($options['domain'])) {
        $response=pwf_powerwaf::Request('/api/dcache/setconf',$options['api_user'],$options['api_password'], [
            'site' => $options['domain'],
            'active' => "0",
            'preset' => "0",
        ]);
        if ($response->status=='error') {
            error_log('Error disabling dynamic cache in PowerWAF: ' . $response->errormsg);
        }
    }
}

function powerwaf_activate() {
    return;
}

function powerwaf_set_dcache_config(bool $enabled ,int $preset, bool $CacheByDeviceType, bool $DisableWPFrontendCacheAdmin) {
    error_log('Configuring remote PowerWAF');
    $options=get_option( 'powerwaf_settings' );
    if (!empty($options['api_user']) and  !empty($options['api_password']) and !empty($options['domain'])) {
        $response=pwf_powerwaf::Request('/api/dcache/setconf',$options['api_user'],$options['api_password'], [
            'site' => $options['domain'],
            'active' => $enabled?'1':'0',
            'preset' => (string)$preset,
            'cacheByDevice' => $CacheByDeviceType?'1':'0',
            'disableWPFCA' => $DisableWPFrontendCacheAdmin?'1':'0'
        ]);
        if ($response->status == 'error') {
            error_log('Error enabling dynamic cache in PowerWAF: ' . $response->errormsg);
        }
    }
}