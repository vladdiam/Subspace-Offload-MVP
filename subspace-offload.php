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
            submit_button(); // save btn
            settings_fields( 'sso_settings_group' ); //secures your submitting (hidden fields), write once
            do_settings_sections( 'subspace-offload' ); //draw section with its fields
            ?>
        </form>

        <!-- //ftp sync btn (part of 3rd module) -->
        <hr>
        <h2>Sync actions</h2>
        <p>This button starts transferring files older than the specified date to the target FTP server</p>
        <button type="button" id="sso-sync-btn" class="button button-secondary">
            Start transfer
        </button>
        <div id="sso-sync-status" style="margin-top: 20px; padding: 10px; background: #fff; border: 1px solid #ccd0d4; display: none;">
            <strong>Status:</strong> <span id="sso-log-text">Waiting...</span>
        </div>
    </div>

    <!-- ===JS script for confirming, disabling & status=== -->
    <script>
    jQuery(document).ready(function($) {//do on full loaded DOM
        $('#sso-sync-btn').on('click', function() {//event listener (click btn)
            var btn = $(this);//this btn
            var statusBox = $('#sso-sync-status');//div with status texts
            var log = $('#sso-log-text');//status text

            if(!confirm('Are you sure you want to start FTP sync?')) return;//if user click "cancel" -> stop fnctn

            btn.attr('disabled', true).text('Syncing...');//set btn state to Disabled & display syncing text
            statusBox.show();//show div with status
            log.text('Connecting to FTP and scanning files...');//set status text

            // SIMULATION of success (imitation of successfull transfered files with 2sec timeout)
            setTimeout(function() {
                log.html('<span style="color: green;">Success! (Simulation)</span>. Found 15 files to move.');//success text
                btn.attr('disabled', false).text('Start transfer');//return btn state to initial
            }, 2000);
        });
    });
    </script>
    <?php
}

add_action( 'admin_init', 'sso_settings_init' ); //add my vars when admin page is inicial. (1st section)
function sso_settings_init() { //registers options names \/
    register_setting( 'sso_settings_group', 'sso_cdn_url' ); //register this field in DB & put it in sso_settings_group group
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

add_action( 'admin_init', 'sso_ftp_set_init' );//(3rd module) ftp section & fields (2nd section)
function sso_ftp_set_init() {
    register_setting( 'sso_settings_group', 'sso_ftp_host' );//register ftp-host field in DB
    register_setting( 'sso_settings_group', 'sso_ftp_user' );//reg. ftp-user
    register_setting( 'sso_settings_group', 'sso_ftp_pass' );//reg. ftp pword

    add_settings_section( 'sso_ftp_section'/*field name */, 'FTP Data', null, 'subspace-offload' );//add ftp data fields section

    // FTP host field (3rd module)
    add_settings_field( 'sso_ftp_host', 'Host', function(){ 
        $val = get_option('sso_ftp_host');
        echo '<input type="text" name="sso_ftp_host" value="' . esc_attr($val) . '" class="regular-text" placeholder="123.45.67.89">';
    }, 'subspace-offload', 'sso_ftp_section');

    // FTP user field
    add_settings_field( 'sso_ftp_user', 'User', function(){ 
        $val = get_option('sso_ftp_user');
        echo '<input type="text" name="sso_ftp_user" value="' . esc_attr($val) . '" class="regular-text" placeholder="User">';
    }, 'subspace-offload', 'sso_ftp_section');

    // FTP pword field
    add_settings_field( 'sso_ftp_pass', 'Password', function(){ 
        $val = get_option('sso_ftp_pass');
        echo '<input type="password" name="sso_ftp_pass" value="' . esc_attr($val) . '" class="regular-text" placeholder="****" autocomplete="off">';
    }, 'subspace-offload', 'sso_ftp_section');
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

//=============== MODULE 3 : FTP transfer (simulation) ===
add_action( 'wp_ajax_sso_start_sync', 'sso_handle_sync_ajax' );//if you recieve ajax request with sso_start_sync action, trigger next fnctn

function sso_handle_sync_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {//if user is not admin, then \/
        wp_send_json_error( array( 'message' => 'Security check failed!' ) );//send error msg & stop this fnctn via wp_die()
    }

    $ftp_host = get_option('sso_ftp_host');//get ftp_host field from admin
    $cutoff = get_option('sso_cutoff_date');//get cutoff date

    if ( empty($ftp_host) || empty($cutoff) ) {//if any field is empty, then \/
        wp_send_json_error( array( 'message' => 'Fill FTP settings and Date first!' ) );//send error msg & stop this fnctn via wp_die()
    }

    wp_send_json_success( array(//send success msg
        'message' => 'Connected to PHP! Ready to sync files older than ' . $cutoff,
        'count'   => 42 // random number of files for Simulation of success
    ));

    wp_die(); //required in ajax-requests
}