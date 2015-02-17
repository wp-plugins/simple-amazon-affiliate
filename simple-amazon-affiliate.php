<?php
/*
  Plugin Name: Simple Amazon Affiliate
  Plugin URI: http://duogeek.com
  Description: A Simple and Revenue Generating Amazon Product Affiliate plugin from DuoGeek
  Version: 1.0.3
  Author: DuoGeek
  Author URI: http://duogeek.com
  License: GPL v2 or later
 */

if ( !defined( 'ABSPATH' ) )
    wp_die( __( 'Sorry hackers! This is not your place!', 'saa' ) );

if ( !defined( 'SAA_BRAND' ) )
    define( 'SAA_BRAND', 'Amazon Affiliate Admin Panel' );
if ( !defined( 'SAA_VERSION' ) )
    define( 'SAA_VERSION', '1.0' );
if ( !defined( 'SAA_PLUGIN_DIR' ) )
    define( 'SAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
if ( !defined( 'SAA_FILES_DIR' ) )
    define( 'SAA_FILES_DIR', SAA_PLUGIN_DIR . 'amazon-files' );
if ( !defined( 'SAA_FILES_URI' ) )
    define( 'SAA_FILES_URI', plugin_dir_url( __FILE__ ) . 'amazon-files' );
if ( !defined( 'SAA_CLASSES_DIR' ) )
    define( 'SAA_CLASSES_DIR', SAA_FILES_DIR . '/classes' );
if ( !defined( 'SAA_ADDONS_DIR' ) )
    define( 'SAA_ADDONS_DIR', SAA_FILES_DIR . '/addons' );
if ( !defined( 'SAA_INCLUDES_DIR' ) )
    define( 'SAA_INCLUDES_DIR', SAA_FILES_DIR . '/includes' );

if( ! defined( 'DUO_PLUGIN_URI' ) ) define( 'DUO_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
require 'duogeek/duogeek-panel.php';

add_action( 'init', 'duo_amazon_localization' );

function duo_amazon_localization() {
    load_plugin_textdomain( 'saa', FALSE, SAA_PLUGIN_DIR . '/lang/' );
}

if ( !class_exists( 'DuoAmazonAffiliate' ) ) {

    class DuoAmazonAffiliate {

        public function __construct() {
            add_shortcode( 'dg_saa', array($this, 'show_affiliate_data') );
            add_filter( 'front_scripts_styles', array( $this, 'saa_enqueue' ) );
            add_action( 'admin_notices', array($this, 'saa_admin_notice') );
            add_filter( 'duogeek_submenu_pages', array( $this, 'saa_menu' ) );
            add_filter( 'duo_panel_help', array( $this, 'saa_help_cb' ) );
        }

        public function saa_help_cb( $arr ){
            $arr[] = array(
                'name'          => __( 'Simple Amazon Affiliate' ),
                'shortcodes'    => array(
                    array(
                        'source'			=> __( 'Simple Amazon Affiliate', 'sn' ),
                        'code'              => '[dg_saa]',
                        'example'           => '[dg_saa title="Custom Title"]',
                        'default'           => 'title = Related Products',
                        'desc'              => __( 'This shortcode will show the a list of 5 products based on the associated tag with the post. You can give a custom title, otherwise default title will be shown.' , 'sn' )
                    ),
                )
            );

            return $arr;
        }

        public function saa_enqueue( $enq ) {

            $styles = array(
                array(
                    'name' => 'saa_css',
                    'src' => SAA_FILES_URI .'/css/dgsaa.css',
                    'dep' => '',
                    'version' => DUO_VERSION,
                    'media' => 'all',
                    'condition' => true
                )
            );

            $scripts = array(
                array(
                    'name' => 'saa_js',
                    'src' => SAA_FILES_URI . '/js/dgsaa.js',
                    'dep' => array( 'jquery', ),
                    'version' => DUO_VERSION,
                    'footer' => true,
                    'condition' => true
                )
            );

            if( ! isset( $enq['scripts'] ) || ! is_array( $enq['scripts'] ) ) $enq['scripts'] = array();
            if( ! isset( $enq['styles'] ) || ! is_array( $enq['styles'] ) ) $enq['styles'] = array();
            $enq['scripts'] = array_merge( $enq['scripts'], $scripts );
            $enq['styles'] = array_merge( $enq['styles'], $styles );

            return $enq;
        }

        public function signAmazonUrl( $url, $secret_key ) {
            $original_url = $url;
            $url = urldecode( $url );
            $urlparts = parse_url( $url );

            foreach ( explode( '&', $urlparts['query'] ) as $part ) {
                if ( strpos( $part, '=' ) ) {
                    list($name, $value) = explode( '=', $part, 2 );
                } else {
                    $name = $part;
                    $value = '';
                }
                $params[$name] = $value;
            }

            if ( empty( $params['Timestamp'] ) ) {
                $datetime = new DateTime( 'tomorrow' );
                $params['Timestamp'] = $datetime->format( "Y-m-d\Th:m:s\Z" );
            }

            ksort( $params );

            $canonical = '';
            foreach ( $params as $key => $val ) {
                $canonical .= "$key=" . rawurlencode( utf8_encode( $val ) ) . "&";
            }

            $canonical = preg_replace( "/&$/", '', $canonical );
            $canonical = str_replace( array(' ', '+', ',', ';'), array('%20', '%20', urlencode( ',' ), urlencode( ':' )), $canonical );
            $string_to_sign = "GET\n{$urlparts['host']}\n{$urlparts['path']}\n$canonical";
            $signature = base64_encode( hash_hmac( 'sha256', $string_to_sign, $secret_key, true ) );
            $url = "{$urlparts['scheme']}://{$urlparts['host']}{$urlparts['path']}?$canonical&Signature=" . rawurlencode( $signature );
            return $url;
        }

        public function show_affiliate_data( $atts ) {
            $atts = shortcode_atts(
                    array(
                'title' => 'Related Products',
                    ), $atts, 'dg_saa' );

            global $post;
            $tags = wp_get_post_tags( $post->ID );
            if ( count( $tags ) > 0 ) {
                $count = 1;
                $keyword = '';
                foreach ( $tags as $key => $value ) {
                    $keyword .= $value->name . '%20';
                    if ( $count == 2 ) {
                        break;
                    }
                    $count++;
                }
            } else {
                $keyword = 'make%20money%20online';
            }

            $options = get_option( 'saa_options', true );
            if ( (!isset( $options['options']['accesskey'] ) && !isset( $options['options']['secretkey'] ) && !isset( $options['options']['assoctag'] )) || $options['options']['accesskey'] == "" || $options['options']['secretkey'] == "" || $options['options']['assoctag'] == "" ) {
                $content = '<div class="saa-amazon-wrapper"><h3>You have not cofigured Simple Amazon Affiliate Plugin with necessary keys. <a target="_blank" href="'. admin_url( 'admin.php?page=saa-affiliate' ) .'">Click HERE</a> to configure</h3></div>';
                return $content;
            }

            $DGAccessKey = $options['options']['accesskey'];
            $secret_key = $options['options']['secretkey'];
            $DGAssocTag = $options['options']['assoctag'];

            $dgonewordkeyword = 'Action';
            $datetime = new DateTime( 'tomorrow' );
            $dgtimestamp = $datetime->format( "Y-m-d\Th:m:s\Z" );

            $ourdgurl = 'http://webservices.amazon.com/onca/xml?Service=AWSECommerceService&AWSAccessKeyId=' . $DGAccessKey . '&Operation=ItemSearch&ResponseGroup=Large&Keywords=' . $keyword . '&SearchIndex=All&Timestamp=' . $dgtimestamp . '&AssociateTag=' . $DGAssocTag . '';

            try {
                $xml = @file_get_contents( $this->signAmazonUrl( $ourdgurl, $secret_key ) );
                if ( $xml === false ) {
                    throw new Exception( '<div class="saa-amazon-wrapper"><h3>You have not cofigured Simple Amazon Affiliate Plugin with necessary keys.</h3></div>' );
                } else {
                    $xml = simplexml_load_string( $xml );
                }
            } catch ( Exception $e ) {
                return $e->getMessage();
            }


            $count = 1;
            $content = '<div class="saa-amazon-wrapper"><h3>' . $atts['title'] . '</h3>';
            foreach ( $xml->Items->Item as $key => $value ) {
                if ( strlen( $value->ItemAttributes->Title ) > 29 ) {
                    $pos = strpos( $value->ItemAttributes->Title, ' ', 30 );
                    $title = substr( $value->ItemAttributes->Title, 0, $pos );
                } else {
                    $title = $value->ItemAttributes->Title;
                }
                $content .= '<div class="saa-amazon-item"><div class="saa-amazon-title"><a target="_blank" href="' . $value->DetailPageURL . '">' . $title . '</a></div><div class="saa-amazon-image"><a href="' . $value->DetailPageURL . '"><img src="' . $value->MediumImage->URL . '" alt="' . $title . '"></a></div></div>';
                if ( $count == 5 ) {
                    break;
                }
                $count++;
            }
            $content .= '</div>';
            return $content;
        }

        function saa_admin_notice() {
            $options = get_option( 'saa_options', true );
            //var_dump( $options );
            if ( (!isset( $options['options']['accesskey'] ) && !isset( $options['options']['secretkey'] ) && !isset( $options['options']['assoctag'] )) || $options['options']['accesskey'] == "" || $options['options']['secretkey'] == "" || $options['options']['assoctag'] == "" ) {
                ?>
                <div class="error">
                    <p><?php _e( 'You have not cofigured Simple Amazon Affiliate Plugin with necessary keys. <a href="'. admin_url( 'admin.php?page=saa-affiliate' ) .'">Click HERE</a> to configure', 'saa' ); ?></p>
                </div>
                <?php
            }
        }

        public function saa_menu( $submenus ) {
            $submenus[] = array(
                'title' => 'Amazon Affiliate Options',
                'menu_title' => 'Amazon Affiliate',
                'capability' => 'manage_options',
                'slug' => 'saa-affiliate',
                'object' => $this,
                'function' => 'saa_options'
            );

            return $submenus;
        }

        public function saa_options() {
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
            }

            if ( isset( $_POST['option_save'] ) ) {
                $options = $_POST;

                if ( !isset( $_POST['saa_nonce_val'] ) ) {
                    $msg = "You are not allowed to make this change&res=error";
                } elseif ( !wp_verify_nonce( $_POST['saa_nonce_val'], 'saa_nonce' ) ) {
                    $msg = "You are not allowed to make this change&res=error";
                } else {
                    update_option( 'saa_options', $options );
                    $msg = 'Data Saved';
                }

                wp_redirect( admin_url( 'admin.php?page=saa-affiliate&msg=' . str_replace( ' ', '+', $msg ) ) );
            }

            $options = get_option( 'saa_options', true );
            ?>
            <div class="wrap">
                <h2><?php echo SAA_BRAND ?></h2>
            <?php if ( isset( $_REQUEST['msg'] ) && $_REQUEST['msg'] != '' ) { ?>
                    <div class="<?php echo isset( $_REQUEST['res'] ) ? $_REQUEST['res'] : 'updated' ?>">
                        <p>
                <?php echo str_replace( '+', ' ', $_REQUEST['msg'] ); ?>
                        </p>
                    </div>
            <?php } ?>
                <div id="poststuff">
                    <div class="postbox">
                        <h3 class="hndle">Instruction</h3>
                        <div class="inside">
                            <p>For any issues, problem or query, please feel free to <a href="http://duogeek.com/contact/" target="_blank">contact us</a>.</p>
                        </div>
                    </div>

                    <div class="postbox">
                        <h3 class="hndle">Save your Access Key, Secret Key & Assoc Tag</h3>
                        <div class="inside">
                            <form action="<?php echo admin_url( 'admin.php?page=saa-affiliate&noheader=true' ) ?>" method="post">
            <?php wp_nonce_field( 'saa_nonce', 'saa_nonce_val' ); ?>
                                <table cellpadding="5" cellspacing="5">
                                    <tr>
                                        <th>Access Key</th>
                                        <td>
                                            <input type="text" name="options[accesskey]" value="<?php echo isset( $options['options']['accesskey'] ) && $options['options']['accesskey'] != '' ? $options['options']['accesskey'] : '' ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Secret Key</th>
                                        <td>
                                            <input type="text" name="options[secretkey]" value="<?php echo isset( $options['options']['secretkey'] ) && $options['options']['secretkey'] != '' ? $options['options']['secretkey'] : '' ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Assoc Tag</th>
                                        <td>
                                            <input type="text" name="options[assoctag]" value="<?php echo isset( $options['options']['assoctag'] ) && $options['options']['assoctag'] != '' ? $options['options']['assoctag'] : '' ?>">
                                        </td>
                                    </tr>
                                </table>
                                <p><input type="submit" class="button button-primary" name="option_save" value="Save" style="width: 100px; text-align: center;"></p>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            <?php
        }

    }

    new DuoAmazonAffiliate();

    //require SAA_FILES_DIR . '/classes/class.widget.php';
}