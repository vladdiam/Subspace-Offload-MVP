<?php
/**
 * Plugin Name: Subspace Offload
 * Description: Offload media to a subdomain/CDN based on date.
 * Version: 0.1.0
 * Author: vladd_i_am
 */

if ( ! defined( 'ABSPATH' ) ) exit; //prevent direct entry via url

add_action( 'admin_menu', 'sso_add_admin_menu' ); // ============== MODULE 1 : fields in admin & related ===
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

//=============== MODULE 2 : URL filtering & logic ===
add_filter( 'wp_get_attachment_url', 'sso_maybe_offload_url', 10, 2 );//target attachment url hook, set priority to 10(def) and indicate 2 args

function sso_maybe_offload_url( $url, $post_id ) {
    $cdn_url = get_option('sso_cdn_url');//get value from cdn-url field in admin & assign to this var
    $cutoff_date = get_option('sso_cutoff_date');//cutoff date field

    if ( empty($cdn_url) || empty($cutoff_date) ) {//if smth is empty, return original url
        return $url;
    }

    $post = get_post($post_id);//retrieve the object of this post (attachment)
    if ( ! $post ) return $url;//returng og. url if is not found
    
    $file_date = date( 'Y-m', strtotime( $post->post_date ) );//get post date & cut it to 2026-03 format 

    if ( $file_date <= $cutoff_date ) {//if file date is older or equal to date in admin field, then \/
        $upload_dir = wp_upload_dir();//get /uploads folder url
        $base_url = $upload_dir['baseurl'];//get url of upload dir from upload_dir array

        $url = str_replace( $base_url, untrailingslashit($cdn_url), $url );//replace url & erase the slash
    }

    return $url;//returns new value
}

// MODULE IS WORKING. URL ARE REPLACING. BUT IMG'S ARE DISPLAYING ON SITE (HARDCODE, DIFF. SIZES)