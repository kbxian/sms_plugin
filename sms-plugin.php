<?php

/*
Plugin Name: Woocommerce SMS Notification
Plugin URI: http://kohbeixian.com
Description: Woocommerce SMS Notification (BulkSMS and WooCommerce Marketplace)
Version: 1.0
Author: Xian
Author URI: http://kohbeixian.com
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;


add_action('woocommerce_order_status_completed', 'custom_process_order', 10, 1);
function custom_process_order($order_id) {
    $order = new WC_Order( $order_id );
    $order_meta = get_post_meta($order_id);
    $vendors = array();

    $items = $order->get_items();

    foreach ($items as $item) {

        $prodid = $item['product_id'];
        $vends = get_the_terms($prodid, 'dc_vendor_shop');

        foreach ($vends as $vend) {
            if (!in_array($vend, $vendors)) {
                array_push($vendors, $vend);
            }
        }
    }

    $vids = array();

    foreach ($vendors as $vend) {
        $terms = get_terms( 'dc_vendor_shop', array(
            'term_id' => $vend,
        ) );
        foreach ($terms as $term) {
            $vdetails = get_user_by('slug', $term->slug);
            $vid = $vdetails->ID;
            if (!in_array($vid, $vids)) {
                array_push($vids, $vid);
            }
        }
    }

    // Send order details to seller
    $buyer_firstname = $order->billing_first_name;
    $buyer_lastname = $order->billing_last_name;
    $buyer_email = $order->billing_email;
    $buyer_phone = $order->billing_phone;


    $ordertotal = $order->order_total;

    $sellermessage = "From: Bei Xian Koh\r\nNew order notification: Order #".$order_id."\r\nBuyer First Name: ".$buyer_firstname."\r\nBuyer Last Name: "
        .$buyer_lastname."\r\nBuyer Email: ".$buyer_email."\r\nBuyer Phone: ".$buyer_phone."\r\nTotal Price: ".$ordertotal;

    $sellermessage = rawurlencode($sellermessage);

    $vphone = '';

    foreach ($vids as $v) {
        // send sms to vendors
        $vphone = get_user_meta($v, 'billing_phone', true);
    }

    //get wcmp vendor details

    $buyermessage = "From: Bei Xian Koh\r\nSummary of your order: Order #".$order_id."\r\nTotal Price: ".$ordertotal;

    for ($v = 0; $v < sizeof($vids); $v++) {
        $u = $v + 1;
        // send sms to vendors
        $sfn = get_user_meta($vids[$v], 'first_name', true);
        $sln = get_user_meta($vids[$v], 'last_name', true);
        $semail = get_user_meta($vids[$v], 'email', true);
        $sphone = get_user_meta($vids[$v], 'billing_phone', true);
        $buyermessage .="\r\nSeller ".$u." First Name: ".$sfn."\r\nSeller ".$u." Last Name: "
            .$sln."\r\nSeller ".$u." Email: ".$semail."\r\nSeller ".$u." Phone: ".$sphone;

        // SMS TO SELLERS

        wp_mail( 'kbxian@hotmail.com', 'Seller New Order Notification SMS', $sellermessage );

        $url = 'http://login.bulksms.my/websmsapi/ISendSMS.aspx?username=newusername&password=newpassword&message='.$sellermessage.'&mobile='.$sphone.'&sender=&type=1';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        //process $response
        if ($response === FALSE) {
            wp_mail( 'kbxian@hotmail.com', 'FAIL: WooCommerce Seller SMS'.$v, $sellermessage );
        } else {
            wp_mail( 'kbxian@hotmail.com', 'SUCCESS: WooCommerce Seller SMS'.$v, $sellermessage );
        }

    }

    // send sms to buyer

    $buyermessage = rawurlencode($buyermessage);
    wp_mail( 'kbxian@hotmail.com', 'Buyer New Order Notification SMS', $buyermessage );

    $url = 'http://login.bulksms.my/websmsapi/ISendSMS.aspx?username=newusername&password=newpassword&message='.$buyermessage.'&mobile='.$buyer_phone.'&sender=&type=1';
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);
    //process $response
    if ($response === FALSE) {
        wp_mail( 'kbxian@hotmail.com', 'FAIL: WooCommerce Buyer SMS' , $buyermessage );
    } else {
        wp_mail( 'kbxian@hotmail.com', 'SUCCESS: WooCommerce Buyer SMS' , $buyermessage );
    }


    return $order_id;
}