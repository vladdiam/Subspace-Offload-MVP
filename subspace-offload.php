<?php
/**
 * Plugin Name: Subspace Offload
 * Description: Offload media to a subdomain/CDN based on date.
 * Version: 0.2.0
 * Author: vladd_i_am
 */

if ( ! defined( 'ABSPATH' ) ) exit; //prevent direct entry via url

// ============== MODULE 1-A: fields in admin & related ===
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

add_action( 'admin_enqueue_scripts', function( $hook ) {// setting nonce for this page & ajax
    if ( $hook !== 'settings_page_subspace-offload' ) {
        return;
    }

    wp_localize_script(
        'jquery',          
        'sso_ajax',         
        [
            'nonce' => wp_create_nonce( 'sso_sync_nonce' ),
        ]
    );
} );

function sso_settings_page() {
    ?>
    <div class="wrap"> <?php /*page render  \/*/?>
        <h1>Subspace Offload Options</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'sso_settings_group' ); //secures your submitting (hidden fields), write once
            do_settings_sections( 'subspace-offload' ); //draw section with its fields
            submit_button(); // save btn
            ?>
        </form>

        <!-- //ftp sync btn (part of 3rd module) -->
        <hr>
        <h2>Sync actions</h2>
        <p>This button starts transferring files older than the specified date to the target FTP server</p>
        <p>If you are using Webp-express plugin, make sure all files are in /uploads folder</p>
        <button type="button" id="sso-sync-btn" class="button button-secondary">
            Start transfer
        </button>
        <div id="sso-sync-status" style="margin-top: 20px; padding: 10px; background: #fff; border: 1px solid #ccd0d4; display: none;">
            <strong>Status:</strong> <span id="sso-log-text">Waiting...</span>
        </div>
    </div>

    <!-- ===JS script for confirming, disabling & status=== -->
    <script>
    jQuery(document).ready(function($) {
    $('#sso-sync-btn').on('click', function() {
        var btn = $(this);
        var statusBox = $('#sso-sync-status');
        var log = $('#sso-log-text');

        if(!confirm('Are you sure you want to start FTP sync?')) return;

        btn.attr('disabled', true).text('Syncing...');
        statusBox.show();
        log.text('Connecting to FTP and scanning files...');

        function runBatch( offset ) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sso_start_sync',
                    nonce: sso_ajax.nonce,
                    offset: offset,
                },
                success: function( response ) {
                    if ( response.success ) {
                        if ( response.data.has_more ) {
                            log.html( '<span style="color:green;">✔ ' + response.data.message + ' Loading next batch...</span>' );
                            runBatch( response.data.next_offset );
                        } else {
                            log.html( '<span style="color:green;">✔ All done! ' + response.data.message + '</span>' );
                            btn.attr( 'disabled', false ).text( 'Start transfer' );
                        }
                    } else {
                        log.html( '<span style="color:red;">✘ ' + response.data.message + '</span>' );
                        btn.attr( 'disabled', false ).text( 'Start transfer' );
                    }
                },
                error: function() {
                    log.html( '<span style="color:red;">✘ Server error. Try again.</span>' );
                    btn.attr( 'disabled', false ).text( 'Start transfer' );
                }
            });
        }

        runBatch( 0 );
    });
});
    </script>
    <?php
}

add_action( 'admin_init', 'sso_settings_init' ); //add my vars when admin page is inicial. (1st section)
function sso_settings_init() { //registers options names \/
    register_setting( 'sso_settings_group', 'sso_cdn_url' ); //register this field in DB & put it in sso_settings_group group
    register_setting( 'sso_settings_group', 'sso_cutoff_date' );// 2nd field
    register_setting( 'sso_settings_group', 'sso_ftp_path' );
    register_setting( 'sso_settings_group', 'sso_batch_size' );//size of files batch


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

    // field for files batch dropdown
    add_settings_field( 'sso_batch_size', 'Files per batch', function() {
        $val = get_option( 'sso_batch_size', '20' ); // default 20
        echo '<select name="sso_batch_size">
            <option value="5"'   . selected($val, '5',   false) . '>5 - very safe (slower transfer)</option>
            <option value="10"'  . selected($val, '10',  false) . '>10 - safe</option>
            <option value="20"'  . selected($val, '20',  false) . '>20 - recommended</option>
            <option value="50"'  . selected($val, '50',  false) . '>50 - fast</option>
            <option value="100"' . selected($val, '100', false) . '>100 - fastest (greater load on the server)</option>
        </select>
        <p class="description">How many files to transfer per one request. Lower = safer, higher = faster.</p>';
    }, 'subspace-offload', 'sso_main_section' );
}

add_action( 'admin_init', 'sso_ftp_set_init' );//(3rd module) ftp section & fields (2nd section)
function sso_ftp_set_init() {
    register_setting( 'sso_settings_group', 'sso_ftp_host' );//register ftp-host field in DB
    register_setting( 'sso_settings_group', 'sso_ftp_user' );//reg. ftp-user
    register_setting( 'sso_settings_group', 'sso_ftp_pass', [//reg. ftp pass w encryption
        'sanitize_callback' => function( $value ) {
            if ( empty( $value ) ) return get_option( 'sso_ftp_pass' ); // if field is empty - skip 
            return sso_encrypt( $value );
        }
    ]);

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
        echo '<input type="password" name="sso_ftp_pass" value="" class="regular-text" placeholder="Leave blank to keep current" autocomplete="off">';
    }, 'subspace-offload', 'sso_ftp_section');

    // Server Path field
    add_settings_field( 'sso_ftp_path', 'Server path', function(){ 
        $val = get_option('sso_ftp_path');
        echo '<input type="text" name="sso_ftp_path" value="' . esc_attr($val) . '" class="regular-text" placeholder="/oursite/uploads/2026/ (optional)" autocomplete="off">';
    }, 'subspace-offload', 'sso_ftp_section');
}

//=============== MODULE 1-B : Setting of Transients (protects db from query overload) ===
function sso_get_settings() {
    $cached = get_transient( 'sso_settings_cache' );//get fields from cache
    
    if ( $cached !== false ) {
        return $cached; // if cache exist -> return from cache
    }

    // if cache doesnt exist -> get opt. fields from db
    $settings = [
        'cdn_url'     => get_option( 'sso_cdn_url', '' ),
        'cutoff_date' => get_option( 'sso_cutoff_date', '' ),
    ];

    set_transient( 'sso_settings_cache', $settings, 12 * HOUR_IN_SECONDS );//set transients from db fields for 12 hours

    return $settings;
}

add_action( 'update_option_sso_cdn_url', 'sso_clear_settings_cache' );//refresh cache when user updates this field
add_action( 'update_option_sso_cutoff_date', 'sso_clear_settings_cache' );

function sso_clear_settings_cache() {//decribing the function for cache deleting
    delete_transient( 'sso_settings_cache' );
}

//=============== MODULE 1-C : encryption & decryption of ftp passwd ===
function sso_encrypt( $value ) {
    $key = wp_salt( 'auth' ); // use secret key from wp config
    $iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
    $encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
    return base64_encode( $iv . '::' . $encrypted );
}

function sso_decrypt( $value ) {
    $key   = wp_salt( 'auth' );
    $parts = explode( '::', base64_decode( $value ), 2 );
    if ( count( $parts ) !== 2 ) return ''; // if format isn't proper - return null
    [ $iv, $encrypted ] = $parts;
    return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
}

//=============== MODULE 1-D : Media Library warnings ===

// banner on top of Media Library page
add_action( 'admin_notices', 'sso_admin_notice' );

function sso_admin_notice(): void {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'upload' ) {
        return; // only on Media Library page
    }

    $settings    = sso_get_settings();
    $cdn_url     = $settings['cdn_url'];
    $cutoff_date = $settings['cutoff_date'];

    if ( empty( $cdn_url ) || empty( $cutoff_date ) ) {
        return; // plugin not configured — nothing to warn about
    }
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>Subspace Offload:</strong>
            Files uploaded before <strong><?php echo esc_html( $cutoff_date ); ?></strong>
            are being served from
            <a href="<?php echo esc_url( $cdn_url ); ?>" target="_blank"><?php echo esc_html( $cdn_url ); ?></a>.
            (View the file description)
        </p>
    </div>
    <?php
}

// warning badge under each offloaded attachment in grid view
add_filter( 'wp_prepare_attachment_for_js', 'sso_attachment_js_notice', 10, 3 );

function sso_attachment_js_notice( array $response, WP_Post $attachment, $meta ): array {

    $settings    = sso_get_settings();
    $cdn_url     = $settings['cdn_url'];
    $cutoff_date = $settings['cutoff_date'];

    if ( empty( $cdn_url ) || empty( $cutoff_date ) ) {
        return $response;
    }

    //check if file exists localy
    $relative_path = get_post_meta( $attachment->ID, '_wp_attached_file', true );
    $local_path    = wp_upload_dir()['basedir'] . '/' . $relative_path;
    
    if ( ! file_exists( $local_path ) ) {
        $response['description'] = '⚠️ Served from FTP: ' . esc_html( $cdn_url );
    }

    return $response;
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
add_filter( 'the_content', 'sso_apply_cdn_to_content', 999, 1 );//999 priority makes it wait for all content, plugins shortcodes to be loaded


function sso_apply_cdn_replacement( $url ) {//main fnctn of this module. url-replacing.
    $settings    = sso_get_settings();//retrieve fields from cache or db via module 1B fnctn
    $cdn_url     = $settings['cdn_url'];
    $cutoff_date = $settings['cutoff_date'];

    if ( empty($cdn_url)) {//if empty, return original url
        return $url;
    }
    
    static $date_cache = [];

    if ( ! isset( $date_cache[ $url ] ) ) {
        $upload_dir = wp_upload_dir();
        $base_url   = untrailingslashit( $upload_dir['baseurl'] );
        $base_dir   = untrailingslashit( $upload_dir['basedir'] );
    
        // url to local path
        $local_path = str_replace( $base_url, $base_dir, $url );
        
        $date_cache[ $url ] = file_exists( $local_path );
    }
    
    if ( $date_cache[ $url ] === true ) {
        return $url; // return local if local exist
    }

    $base_url = untrailingslashit( wp_upload_dir()['baseurl'] );//get base url of local /uploads
    $target_url = untrailingslashit( $cdn_url );//get base url of our cdn. all without slashes

    return str_replace( $base_url, $target_url, $url );//replace url with cdn address
}

function sso_apply_cdn_to_content( $content ) {//get all html content of the page
    $settings    = sso_get_settings();//get fields
    $cdn_url     = $settings['cdn_url'];

    if ( empty( $cdn_url )) {//if plugin isnt setted up - return primary html content
        return $content;
    }

    $base_url = untrailingslashit( wp_upload_dir()['baseurl'] );//get uploads url

    return preg_replace_callback( //find ~, screening spec. symb., find all exc. 'xxx'
        '~' . preg_quote( $base_url, '~' ) . '/[^\s"\'<>]+~',
        function( $matches ) {//if content is url - apply fnctn 
            return sso_apply_cdn_replacement( $matches[0] );
        },
        $content
    );
}

//=============== MODULE 3-A : FTP conn setting ===
add_action( 'wp_ajax_sso_start_sync', 'sso_handle_sync_ajax' );//if you recieve ajax request with sso_start_sync action, trigger next fnctn

function sso_handle_sync_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {//if user is not admin, then \/
        wp_send_json_error( array( 'message' => 'Security check failed!' ) );//send error msg & stop this fnctn via wp_die()
    }

    if ( ! check_ajax_referer( 'sso_sync_nonce', 'nonce', false ) ) {// check nonce token
        wp_send_json_error( array( 'message' => 'Security check failed!' ) );
    }

    $ftp_host = get_option('sso_ftp_host');// get our ftp data + server path
    $ftp_user = get_option('sso_ftp_user');
    $ftp_pass = sso_decrypt( get_option('sso_ftp_pass') );
    $ftp_root = get_option('sso_ftp_path', '/');
    $ftp_path = untrailingslashit( trim($ftp_root) );// additional trim & slash deleting from path value

    if ( empty($ftp_host) || empty($ftp_user) || empty($ftp_pass)) {
        wp_send_json_error( array( 'message' => 'FTP Credentials missing!' ) );
    }

    $conn_id = @ftp_connect($ftp_host); // connect to host via ftp 

    if ( ! $conn_id ) {// if conn is empty, send error & wp_die()
        wp_send_json_error( array( 'message' => "Could not connect to $ftp_host" ) );
    }

    $login_result = @ftp_login($conn_id, $ftp_user, $ftp_pass);// send our login data to ftp servers

    if ( ! $login_result ) {// if smth wrong, close connection
        ftp_close($conn_id);
        wp_send_json_error( array( 'message' => 'FTP Login failed. Check user/pass.' ) );
    }

    ftp_pasv($conn_id, true);//set passive connection (for modern security requirements)

    // ============ MODULE 3-B : replace files to certain place on the server =======
    $upload_dir  = wp_upload_dir();
    $base_dir    = $upload_dir['basedir']; // uploads path
    $cutoff_date = get_option( 'sso_cutoff_date' ); // get cutoff date

    if ( empty( $cutoff_date ) ) {// if isnt set - close conn
        ftp_close( $conn_id );
        wp_send_json_error( array( 'message' => 'Cutoff date is not set!' ) );
    }

    // get all files which are older then date
    $batch_size = (int) get_option( 'sso_batch_size', 20 );//get size of a batch
    $offset     = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0; // which file is starting
    
    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => $batch_size, // batch size (limit for 1 transfer)
        'offset'         => $offset,     // start/progress point
        'date_query'     => array(
            array(
                'column' => 'post_date',
                'before' => $cutoff_date . '-01',
            ),
        ),
    ) );
    
    $has_more = count( $attachments ) === $batch_size;

    $transferred = 0; // Counter - successful
    $failed      = 0; // Counter - errors
    $log         = []; // log array

    foreach ( $attachments as $attachment ) {

        // get all meta - file by itself & its miniatures
        $meta          = wp_get_attachment_metadata( $attachment->ID );
        $relative_file = get_post_meta( $attachment->ID, '_wp_attached_file', true ); // get path in uploads
        $local_file    = $base_dir . '/' . $relative_file; // get full file path

        // collect all files for transfering
        $files_to_transfer = [];

        if ( file_exists( $local_file ) ) {
            $files_to_transfer[] = array(
                'local'  => $local_file,
                'remote' => $relative_file,
            );
            // check for webp companion file
            if ( file_exists( $local_file . '.webp' ) ) {
                $files_to_transfer[] = array(
                    'local'  => $local_file . '.webp',
                    'remote' => $relative_file . '.webp',
                );
            }
        }

        // process all file sizes (variants)
        if ( ! empty( $meta['sizes'] ) ) {
            $subdir = dirname( $relative_file ); // get subdir (/2024/03/)

            foreach ( $meta['sizes'] as $size ) {
                $local_thumb  = $base_dir . '/' . $subdir . '/' . $size['file'];
                $remote_thumb = $subdir . '/' . $size['file'];
    
                if ( file_exists( $local_thumb ) ) {
                    $files_to_transfer[] = array(
                        'local'  => $local_thumb,
                        'remote' => $remote_thumb,
                    );
                    // check for webp companion file
                    if ( file_exists( $local_thumb . '.webp' ) ) {
                        $files_to_transfer[] = array(
                            'local'  => $local_thumb . '.webp',
                            'remote' => $remote_thumb . '.webp',
                        );
                    }
                }
            }
        }

        // transfer every file via ftp
        $attachment_ok = true;

        foreach ( $files_to_transfer as $file ) {
            $remote_path = untrailingslashit( $ftp_path ) . '/' . $file['remote']; // exact full path on ftp serv.
            $remote_dir  = dirname( $remote_path ); // subdir

            // create subdir if does not exist
            sso_ftp_mkdir_recursive( $conn_id, $remote_dir );

            // put file to ftp server
            $uploaded = ftp_put( $conn_id, $remote_path, $file['local'], FTP_BINARY );

            if ( $uploaded ) {
                @unlink( $file['local'] ); // if success -> delete local file
            } else {
                $attachment_ok = false;
                $failed++;
                $log[] = '✘ Failed: ' . $file['remote'];
            }
        }

        if ( $attachment_ok && ! empty( $files_to_transfer ) ) {// +1 to Success
            $transferred++;
            $log[] = '✔ Transferred: ' . $relative_file;
        }
    }

    ftp_close( $conn_id );//close ftp

    $message = "Done. Transferred: $transferred, Failed: $failed.";//Summary of this translation
    if ( ! empty( $log ) ) {
        $message .= '<br><small>' . implode( '<br>', array_slice( $log, 0, 20 ) ) . '</small>'; // show first 20 status records
    }

    wp_send_json_success( array(
        'message'     => $message,
        'has_more'    => $has_more,
        'next_offset' => $offset + $batch_size,
    ) );
    wp_die();
}

function sso_ftp_mkdir_recursive( $conn_id, $path ) {//fnctn for recursive creating of folders in ftp server
    $parts   = explode( '/', ltrim( $path, '/' ) ); // divide path into parts
    $current = '';

    foreach ( $parts as $part ) {
        if ( empty( $part ) ) continue;
        $current .= '/' . $part;

        // try "cd" command in ftp cerver for this folder
        if ( @ftp_chdir( $conn_id, $current ) ) {
            continue; // if success -> exit
        }

        @ftp_mkdir( $conn_id, $current ); // if unsucceed -> make directory
    }

    // after all - go to root 
    @ftp_chdir( $conn_id, '/' );
}