<?php

/* Plugin name: User Settings mailChimp Sync
 * Description: MailChimp Addon for onevoice user settings plugin.
 * Version: 1.1
 * Author: subair TC
 * Author URI: 
  */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'onevoice_mailchimp_sync_add_action_links' );

function onevoice_mailchimp_sync_add_action_links ( $links ) {
	$mylinks = array(
	'<a href="' . admin_url( 'admin.php?page=mailchimp-settings' ) . '">Settings</a>',
	);
	return array_merge( $links, $mylinks );
}


add_action('onvus_after_notification_settings_updated','onvus_sync_mailchimp_subscription_status',10, 2);

function onvus_sync_mailchimp_subscription_status( $setings_status,$user ){
	
    $maiChimpOptions = get_onvs_mailchimp_options();
    $list_id = $maiChimpOptions['mail_chimp_list_id'];
    $apiKey = $maiChimpOptions['mail_chimp_api_key'];
    $datacenter = explode('-',$apiKey );
    $datacenter = $datacenter[1];

    $url = 'http://'.$datacenter.'.api.mailchimp.com/3.0/lists/'.$list_id.'/members/'.md5( strtolower( $user->user_email ) );
    $headers[] = 'Content-Type: application/json';

    if( $setings_status ) {
        $user_data['status'] = 'subscribed';
    } else {
        $user_data['status'] = 'unsubscribed';
    }
    

    $data_json  = json_encode( $user_data );
  
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt( $ch, CURLOPT_USERPWD, "onevoice:".$apiKey);
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS,$data_json );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $response  = curl_exec( $ch );
    curl_close($ch);
    $responce_arr = json_decode( $response ,true);
  
   if( $responce_arr['status'] != $user_data['status'] ) {  // if any error in updates
        
        $to = 'dhanya.cp@netspective.support';
        $subject = 'Failed to Sync user Subscription data';
        $message = '<tr>
        <td style="border:none;height:auto; width:90%; padding:30px 5%;float:left;">
            <ul style="font-size:16px; color:#000;font-family:Arial, Helvetica, sans-serif !important; text-decoration:none;">
                <li> email:'.$user->user_email.' </li>
                <li> List:0cbee48913</li>
                <li> Action:'.$user_data['status'].' </li>
                <li> Response:'.$response.' </li>
                <li> data:'.$data_json.' </li>
            </ul>
            </td></tr>
        ';
        $headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/html; charset=UTF-8"; 
        $headers[] = 'from:no-reply@onescdvoice.com';
        $headers[] = 'Bcc: subair.tc@citrusinformatics.com';
        $headers[] = "X-Mailer: PHP/".phpversion();


        if ( function_exists( 'ot_get_option')){
            $header_part    = ot_get_option( 'header_part') ; 
            $footer_part    = ot_get_option( 'footer_part') ;

            $header_part    = str_replace('{siteurl}', site_url(), $header_part);
            $footer_part    = str_replace('{siteurl}', site_url(), $footer_part); 
        }
        
         $message = $header_part.$message.$footer_part;
        wp_mail( $to, $subject, $message,implode( "\r\n", $headers) );
    }
}

//  subscribe/unsubscribe a user on spam/unspam


add_action( 'make_ham_user', 'onvus_sync_mailchimp_make_ham_user', 10, 2 );
function onvus_sync_mailchimp_make_ham_user( $user_id ){
    $user = get_user_by( 'id', $user_id );
    $setings_status = 1;
    onvus_sync_mailchimp_subscription_status( $setings_status,$user );
}
add_action( 'make_spam_user', 'onvus_sync_mailchimp_make_spam_user', 10, 2 );
add_action( 'delete_user',  'onvus_sync_mailchimp_make_spam_user', 10, 2 );
function onvus_sync_mailchimp_make_spam_user( $user_id ){
    $user = get_user_by( 'id', $user_id );
    $setings_status = 0;
    onvus_sync_mailchimp_subscription_status( $setings_status,$user );
}


function onvus_sync_mailchimp_subscription(){
       if (isset($_POST['updateMailChimpSettings'])) {
			check_admin_referer('onevoice_sync_mailchimp_update-options');
           
            if( isset( $_POST['apikey'] ) ) {
                  $onevoiceMailchimpOptions['mail_chimp_api_key'] = $_POST['apikey'];
            }

            if( isset( $_POST['listid'] ) ) {
                  $onevoiceMailchimpOptions['mail_chimp_list_id'] = $_POST['listid'];
            }

            update_option("onevoiceMailchimpOptions", $onevoiceMailchimpOptions);
			?>
			<div class="updated"><p><strong><?php echo "Settings Updated."; ?></strong></p></div>
            <?php
       }

       $maiChimpOptions = get_onvs_mailchimp_options();
      // var_dump($maiChimpOptions );
       ?>  <div class="row">
       <div class="col-md-6">
            <form method="post" action="<?php echo esc_attr($_SERVER["REQUEST_URI"]); ?>">
                <?php
               
                    if ( function_exists('wp_nonce_field') ) {
                        wp_nonce_field('onevoice_sync_mailchimp_update-options');
                      

                    }
                ?>
                 <div class="form-group">
                    <label for="APIkey">MailChimp API Key</label>
                    <input type="text" class="form-control" id="apikey" name="apikey"  placeholder="MailChimp API Key" value="<?php echo $maiChimpOptions['mail_chimp_api_key']; ?>">
                </div>

                <div class="form-group">
                    <label for="ListID">MailChimp ListID</label>
                    <input type="text" class="form-control" name="listid" id="listid" placeholder="MailChimp List ID" value="<?php echo $maiChimpOptions['mail_chimp_list_id']; ?>">
                </div>

                <div class="form-group">
                    <input type="submit" class="form-control" name="updateMailChimpSettings" id="updateMailChimpSettings" value="update settings">
                </div>
            </form>

            </div>
            </div>

       <?php
}

add_action('admin_menu', 'onvus_mailchimp_settings');

function onvus_mailchimp_settings() {
	if ( function_exists('add_options_page') ) {
		add_submenu_page('user-settings','mailChimp user Sync', 'mailChimp user Sync', 'manage_options','mailchimp-settings', 'onvus_sync_mailchimp_subscription');
	}
}



function get_onvs_mailchimp_options() {
	$onevoiceMailchimpOptions = array(
		'mail_chimp_api_key' => '',
		'mail_chimp_list_id' => ''
	);
	$MailchimpOptions = get_option("onevoiceMailchimpOptions");
	
	if ( !empty($MailchimpOptions) ) {
		foreach ( $MailchimpOptions as $key => $option ) {
			$onevoiceMailchimpOptions[$key] = $option;
		}
	}
	update_option("onevoiceMailchimpOptions", $onevoiceMailchimpOptions);
	return $onevoiceMailchimpOptions;
}

// Creating end point for webhook (2 way sync).


add_action( 'rest_api_init', 'handle_header_onevoice_mailchimp_api' );
function handle_header_onevoice_mailchimp_api() {
    header("Access-Control-Allow-Origin: " . get_http_origin());
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    if ( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] ) {
        status_header(200);
        exit();
    }
}
add_action( 'rest_api_init', 'register_onevoice_mailchimp_api_hooks' );


function register_onevoice_mailchimp_api_hooks() {
 register_rest_route( 'api/wp/v2', '/mailchimp-data/', array(
        'methods' => 'POST,GET',
        'callback' => 'onevoice_mailchimp_api_getdata',
    ) );
   
}
function onevoice_mailchimp_api_getdata() {
     $content = $_POST;
    if( isset( $content  )) {
        $type = $content['type'];
        $email = $content['data']['email'];
        $user = get_user_by( 'email',$email );
        if( !$user ) {
            return;
        }
        if( $type == 'subscribe' ) {

            if( function_exists ('us_update_user_settings') ) {
                $return = us_update_user_settings( 'courage_email_digest',1 ,true,$user->ID );
            }

        } elseif ( $type == 'unsubscribe' ) {
             if( function_exists ('us_update_user_settings') ) {
                 $return =  us_update_user_settings( 'courage_email_digest',0 ,false,$user->ID );
             }
        }
    }

   
   $postarr =  array(
        'post_author'   =>  1,
        'post_title'    =>  'mailchimp data - '.$user->ID,
        'post_type'     =>  'post',
        'post_content'  =>  'Post: <br/>'.json_encode($_POST).'<br/><br/>return : <br/>'.$return
    );
    $post_id = wp_insert_post( $postarr);
}

