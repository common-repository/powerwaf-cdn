<?php

class pwf_layout {

    public static function init() {

        // Add Settings link in plugin list
        add_filter('plugin_action_links_powerwaf/powerwaf.php', 'pwf_layout::addSettingLink');
    }

    // addSettingsLink adds a link to settings page in plugin list
    public static function addSettingLink($links) {
        // Build and escape the URL.
        $url = esc_url( add_query_arg(
            'page',
            'pwaf_settings',
            get_admin_url() . 'admin.php'
        ) );

        // Create the link.
        $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
        // Adds the link to the end of the array.
        array_push(
            $links,
            $settings_link
        );
        return $links;
    }


}

if (is_admin()) {
    pwf_layout::init();
}
