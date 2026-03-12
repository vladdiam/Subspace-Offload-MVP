<?php
/**
 * Plugin Name: Subspace Offload
 * Description: Offload media to a subdomain/CDN based on date.
 * Version: 0.1.0
 * Author: vladd_i_am
 */

if ( ! defined( 'ABSPATH' ) ) exit; //prevent direct entry via url

add_action( 'admin_menu', 'sso_add_admin_menu' );
function sso_add_admin_menu() {
    add_options_page(
        'Subspace Offload', //tab title
        'Subspace Offload', //name in opt.list
        'manage_options', //who has permission
        'subspace-offload', //slug
        'sso_settings_page' //function name
    );
}

function sso_settings_page() {
    ?>
    <div class="wrap"> <?php /*page render  \/*/?>
        <h1>Subspace Offload Options</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'sso_settings_group' ); //secures your submitting (hidden fields)
            do_settings_sections( 'subspace-offload' ); //drawing all visual content
            submit_button(); // save btn
            ?>
        </form>
    </div>
    <?php
}

add_action( 'admin_init', 'sso_settings_init' ); //add my vars when admin page is inicial.
function sso_settings_init() { //registers options names \/
    register_setting( 'sso_settings_group', 'sso_cdn_url' ); //when request on form submit is recieved, refresh this in DB
    register_setting( 'sso_settings_group', 'sso_cutoff_date' );// 2nd field

    add_settings_section( 'sso_main_section'/*field name */, 'General settings', null, 'subspace-offload' ); //makes a section for opt.fields

    //makes an input field in our options \/
    add_settings_field( 'sso_cdn_url', 'CDN URL' /*visible label in admin*/, function() /*recieves value from db & insert in drawed html input*/{ 
        $val = get_option('sso_cdn_url'); //existing value
        echo '<input type="text" name="sso_cdn_url" value="' . esc_attr($val) . '" class="regular-text" placeholder="https://cdn.site.com">';
    }, 'subspace-offload', 'sso_main_section' /* place of inserting this field & value */);

    // 2nd field
    add_settings_field( 'sso_cutoff_date', 'Max. cutoff date', function() {
        $val = get_option('sso_cutoff_date');
        echo '<input type="month" name="sso_cutoff_date" value="' . esc_attr($val) . '">';
    }, 'subspace-offload', 'sso_main_section' );
}