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

//=============== MODULE 2 : URL filtering & logic (extended version) ===

//hook 1: direct url of ANY attachment (level 1 filter)
add_filter( 'wp_get_attachment_url', 'sso_apply_cdn_replacement', 10, 1);//trigger our main fnctn of this module, when WP requests direct url of the file (img, video, doc). set priority to 10(def), 1 arg.

//hook 2: if attachment is img (level 2 filter)
add_filter( 'wp_get_attachment_image_src', function( $image ) {/* trigger anon fnctn if attachment is img & insert img data in this fnctn via $image variable*/
    if ( $image ) {
        $image[0] = sso_apply_cdn_replacement( $image[0] );// $image[0] is img's url
    }
    return $image;//return image array with new url
}, 10, 1);

//hook 3: image different sizes (srcsets) (level 3 filter)
add_filter( 'wp_calculate_image_srcset', function( $sources ) {/* trigger anon fnctn when calculating srcsets for this image */
    if ( is_array( $sources ) ) {//$sources is like a "box" with $images
        foreach ( $sources as &$source ) {//iterate every image variant for this image
            $source['url'] = sso_apply_cdn_replacement( $source['url'] );//replace url for this variant with new url
        }
    }
    return $sources;//return all variants
}, 10, 1);

//hook 4: the final controller. Inspects all content after generating all shortcodes & etc.(level 4, the last)
add_filter( 'the_content', 'sso_apply_cdn_replacement', 999, 1 );//999 priority makes it wait for all content, plugins shortcodes to be loaded


function sso_apply_cdn_replacement( $url ) {//main fnctn of this module. url-replacing.
    $cdn_url = get_option('sso_cdn_url');//get value from cdn-url field in admin & assign to this var
    $cutoff_date = get_option('sso_cutoff_date');//cutoff date field

    if ( empty($cdn_url) || empty($cutoff_date) ) {//if smth is empty, return original url
        return $url;
    }

    $attachment_id = attachment_url_to_postid( $url );//find postid from url
    
    if ( $attachment_id ) {//check if exists
        $post = get_post( $attachment_id );//get "post" object of this attachment
        $file_date = date( 'Y-m', strtotime( $post->post_date ) );//get post date & cut it to 2026-03 format 
        if ( $file_date > $cutoff_date ) {//check if date is older
            return $url;//return og. url if date isnt older
        }
    }

    $base_url = untrailingslashit( wp_upload_dir()['baseurl'] );//get base url of local /uploads
    $target_url = untrailingslashit( $cdn_url );//get base url of our cdn. all without slashes

    return str_replace( $base_url, $target_url, $url );//replace url with cdn address
}