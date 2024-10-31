<?php

/**
 * Generated by the WordPress Option Page generator
 * at http://jeremyhixon.com/wp-tools/option-page/
 */

class pwf_dashboard {
    private static $powerwaf_settings;

    public static function init() {
        self::$powerwaf_settings= get_option('powerwaf_settings');
        add_action( 'admin_menu',  'pwf_dashboard::add_dashboard_page_link' ); // add page link to left menu
        //add_action( 'admin_init', array( $this, 'powerwaf_settings_page_init' ) );
        add_action('admin_enqueue_scripts', 'pwf_dashboard::enqueue_scripts');

    }

    public static function enqueue_scripts() {
            wp_register_style( 'pwfbulma',plugins_url('public/css/bulma-custom.css',__FILE__));
            wp_enqueue_style( 'pwfbulma' );
            //wp_enqueue_script( 'namespaceformyscript', 'http://locationofscript.com/myscript.js', array( 'jquery' ) );
        }

    public static function add_dashboard_page_link() {
         add_menu_page(
            'PowerWAF CDN',
            'PowerWAF CDN',
            'administrator',
            'pwf_powerwaf',
            'pwf_dashboard::render_dashboard_page',
            plugins_url( 'public/images/mnuicon.png', __FILE__ )
        );
}

    public static function render_dashboard_page() {

        $action=sanitize_key($_GET['action']);
        $options = self::$powerwaf_settings;

        // Redirect to settings if something is missing there.
        if (empty($options['api_user']) or empty($options['api_password']) or empty($options['domain'])) {
            wp_redirect(esc_url(add_query_arg('page','pwaf_settings',get_admin_url() . 'admin.php')));
            die();
        }

        switch ($action) {
            case "":
                self::renderDashboard();
                break;

        }

    }


    public static function renderError($response) {

        switch ($response->errorcode) {
            case 101:  // Bad credentials
                $settingsPage = add_query_arg('page','pwaf_settings', get_admin_url() . 'admin.php' );
                echo '<div class="columns">';
                echo '<div class="column is-half">';
                echo '<article class="message is-danger is-light">';
                echo '<div class="message-body">';
                echo sprintf("Error connecting to the server: <strong>invalid credentials</strong>. (Code %s)<br>", esc_html($response->errorcode));
                echo 'Please check the credentials on the settings page.<br>';
                echo '</div>';
                echo '</article>';
                echo sprintf("<a class='button is-small' href='%s'>Check Settings</a>", esc_url($settingsPage));
                echo '</div>';
                echo '</div>';
                break;

            default:
                echo sprintf('<div class="columns">
    <div class="column is-half">
        <article class="message is-danger is-light">
            <div class="message-body">
                Server error: <strong>%s</strong>. (Code %s)<br>
            </div>
        </article>
    </div>
</div>',esc_html(esc_html($response->errormsg)), esc_html($response->errorcode));
                break;

        }
    }

    public static function startPage($title="PowerWAF CDN Dashboard") {
        $imglogo=plugins_url( 'public/images/logotipo-vertical-180x180.png',__FILE__ );
        echo '<div class="wrap powerwaf">';
        echo sprintf('<div class="columns is-vcentered">
            <div class="column is-1"><img src="%s" style="width: 100px" alt="PowerWAF CDN"/></div>
            <div class="column"><h1 class="title">%s</h1></div>
        </div>', $imglogo, esc_html($title));
    }

    public static function endPage() {
        echo '</div>'; // powerwaf class container
    }

    public static function renderDashboard() {
        $options = self::$powerwaf_settings;


        self::startPage();

        // Get the domain DNS configuration if today was not checked
        $today=date('d-m-Y');
        if ($options['lastDNSCheck']<$today) {
            $response=pwf_powerwaf::Request('/api/sites/get',$options['api_user'],$options['api_password'], [ 'site' => $options['domain'] ]);
            if ('error' == $response->status) {
                self::renderError($response);
            } else {
                if ($response->data->assignedCname!=$response->data->registeredCname) {
                    echo <<<HTML
                    <div class="columns">
                        <div class="column xis-half">
                                <div style="background-color: white; padding: 15px; border: 1px solid black; border-left: 4px solid darkgoldenrod">
                                    <strong>Only one step to go!</strong> For your domain to be linked to PowerWAF CDN, you need to modify one of your DNS records. 
                                    Check the instructions here: <a href="https://www.powerwaf.com/en/doc/dns/how-to-link-your-website-with-powerwaf-cdn/" target="_blank">Create CNAME record for PowerWAF</a>
                                </div>
                        </div>
                    </div>
HTML;
                }
            }
        }


        // Request domain stats
        $response=pwf_powerwaf::Request('/api/sites/getstats',$options['api_user'],$options['api_password'], [ 'site' => $options['domain'] ]);


        // Render error, if any.
        if ('error' == $response->status)  {
            pwf_dashboard::renderError($response);
            self::endPage();
            return;
        }

        echo <<<HTML
        <p>PowerWAF CDN is working ok.</p>
HTML;

        self::endPage();
    }



}
if ( is_admin() )
    pwf_dashboard::init();

