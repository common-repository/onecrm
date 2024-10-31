<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function oclonerc_fullUpdate()
{
    oclonerc_product("http://api.onecrm.org/Product/Sync");
    oclonerc_user("http://api.onecrm.org/User/Sync");
    oclonerc_orders("http://api.onecrm.org/Order/Sync");
    oclonerc_orderStat("http://api.onecrm.org/Order/StatusSync");
    oclonerc_comment("http://api.onecrm.org/Comment/Sync");
}

function oclonerc_product($url)
{
    if (get_option('oneCrm_Token') != null) {
        //$url = get_option('oneCrm_pc');
        $args = array('body' => array(
            'CMD' => 'Product',
            'CustID' => get_option('oneCrm_CustID'),
            'JsonStringProduct' => oclonerc_product_update(),
            'Token' => get_option('oneCrm_Token')
        ));
        wp_remote_post($url, $args);
    }
}

function oclonerc_user($url)
{
    if (get_option('oneCrm_Token') != null) {
        //$url = get_option('oneCrm_pc');
        $args = array('body' => array(
            'CMD' => 'User',
            'CustID' => get_option('oneCrm_CustID'),
            'JsonStringUser' => oclonerc_user_update(),
            'Token' => get_option('oneCrm_Token')
        ));
        wp_remote_post($url, $args);
    }
}

function oclonerc_orders($url)
{
    if (get_option('oneCrm_Token') != null) {
        //$url = get_option('oneCrm_pc');
        $OrderResult = oclonerc_order_update();
        $args = array('body' => array(
            'CMD' => 'Orders',
            'CustID' => get_option('oneCrm_CustID'),
            'JsonStringOrder' => $OrderResult[0],
            'JsonStringOrderItem' => $OrderResult[1],
            'Token' => get_option('oneCrm_Token')
        ));
        wp_remote_post($url, $args);
    }
}

function oclonerc_orderStat($url)
{
    if (get_option('oneCrm_Token') != null) {
        //$url = get_option('oneCrm_pc');
        $args = array('body' => array(
            'CMD' => 'OrderStatusList',
            'CustID' => get_option('oneCrm_CustID'),
            'JsonStringStatus' => oclonerc_orderStat_update(),
            'Token' => get_option('oneCrm_Token')
        ));
        wp_remote_post($url, $args);
    }
}

function oclonerc_comment($url)
{
    if (get_option('oneCrm_Token') != null) {
        //$url = get_option('oneCrm_pc');
        $args = array('body' => array(
            'CMD' => 'Comment',
            'CustID' => get_option('oneCrm_CustID'),
            'JsonStringComment' => oclonerc_comment_update(),
            'Token' => get_option('oneCrm_Token')
        ));
        wp_remote_post($url, $args);
    }
}

//------------------------------------------------- Updates
function oclonerc_user_update()
{
    global $wpdb;
    $lastUid = get_option('oneCrm_LstUID');
    $userTbl = $wpdb->users;
    $userMetaTbl = $wpdb->usermeta;
    $postsTbl = $wpdb->posts;
    $postMetaTbl = $wpdb->postmeta;
    $orderItemTbl = $wpdb->prefix . "woocommerce_order_items";
    $orderItemMetaTbl = $wpdb->prefix . "woocommerce_order_itemmeta";
    $userResults = $wpdb->get_results("SELECT u.id,u.user_login,u.user_email,u.user_registered,u.display_name,
    (select meta_value from {$userMetaTbl} where user_id = u.id and meta_key = 'first_name' limit 1) as first_name,
    (select meta_value from {$userMetaTbl} where user_id = u.id and meta_key = 'last_name' limit 1) as last_name,
    (select meta_value from {$userMetaTbl} where user_id = u.id and meta_key = 'mobile' limit 1) as mobile
    FROM {$userTbl} u");

    //"SELECT ID,user_login,user_email,user_registered,display_name FROM {$userTbl} WHERE ID > " . $lastUid);

    return json_encode($userResults);
}

function oclonerc_comment_update()
{
    global $wpdb;
    $commentTbl = $wpdb->comments;
    $postsTbl = $wpdb->posts;

    $commentResults = $wpdb->get_results("SELECT comment_ID, comment_date, comment_content, user_id, comment_post_ID "
        . "FROM {$commentTbl} WHERE comment_agent != 'WooCommerce' And user_id > 0");

    $jsonCommentText .= "[";
    foreach ($commentResults as $CommentRow) {
        $comment_ID = $CommentRow->comment_ID;
        $comment_date = $CommentRow->comment_date;
        $comment_content = $CommentRow->comment_content;
        $user_id = $CommentRow->user_id;
        $comment_post_ID = $CommentRow->comment_post_ID;
        $PostLink = oclonerc_GetPostLink($comment_post_ID);
        $PostTitle = onloner_GetPostTitle($comment_post_ID);
        $jsonCommentText .= "{\"WPCommentID\":\"" . $comment_ID . "\","
            . "\"CommentDate\":\"" . $comment_date . "\","
            //. "\"CommentContent\":\"" . substr($comment_content, 0, 100) . "...\","
            . "\"WPUserID\":\"" . $user_id . "\","
            . "\"PostTitle\":\"" . $PostTitle . "\","
            . "\"PostLink\":\"" . $PostLink . "#comment-" . $comment_post_ID . "\"},";
    }
    $jsonCommentText = substr_replace($jsonCommentText, '', -1);
    $jsonCommentText .= "]";

    return $jsonCommentText;
}

function oclonerc_order_update()
{
    global $wpdb;
    $lastUid = get_option('oneCrm_LstUID');
    $userTbl = $wpdb->users;
    $postMetaTbl = $wpdb->postmeta;
    $postsTbl = $wpdb->posts;
    $orderItemTbl = $wpdb->prefix . "woocommerce_order_items";
    $orderItemMetaTbl = $wpdb->prefix . "woocommerce_order_itemmeta";
    $userResults = $wpdb->get_results("SELECT 
		  (select meta_value from {$postMetaTbl} where meta_key = '_customer_user' and post_id = p.ID limit 1) as meta_value, p.ID, p.post_status
			FROM {$postsTbl} p
		   WHERE post_type = 'shop_order'");


    $jsonOrderText = "";
    $jsonOItemText = "";
    $jsonOrderText .= "[";
    $jsonOItemText .= "[";
    $orderID = 0;
    foreach ($userResults as $userRow) {
        $userID = $userRow->meta_value;
        $orderID = $userRow->ID;
        $orderStatus = $userRow->post_status;
        if ($userID > 0) {
            $OrderDate = oclonerc_getPostDate($orderID);
            $TotalPrice = oclonerc_orderDetail("_order_total", $orderID);
            $UserMobile = oclonerc_orderDetail("_billing_phone", $orderID);
            $UserAddress = oclonerc_orderDetail("_shipping_address_1", $orderID);
            $jsonOrderText .= "{\"WPUserID\":\"" . $userID . "\","
                . "\"WPOrderID\":\"" . $orderID . "\","
                . "\"WPOrderDate\":\"" . $OrderDate . "\","
                . "\"OrderStat\":\"" . $orderStatus . "\","
                . "\"TotalPrice\":\"" . $TotalPrice . "\","
                . "\"UserAddress\":\"" . $UserAddress . "\","
                . "\"UserMobile\":\"" . $UserMobile . "\"},";
            $orderItem = $wpdb->get_results("SELECT order_item_id FROM {$orderItemTbl} "
                . "WHERE order_id = '" . $orderID . "'"
                . "And order_item_type = 'line_item'");
            foreach ($orderItem as $orderItemRow) {
                $orderItemID = $orderItemRow->order_item_id;
                $productID = oclonerc_orderItemDetail("_product_id", $orderItemID);
                $prodCount = oclonerc_orderItemDetail("_qty", $orderItemID);
                $jsonOItemText .= "{\"WPUserID\":\"" . $userID . "\","
                    . "\"WPOrderID\":\"" . $orderID . "\","
                    . "\"WPProductID\":\"" . $productID . "\","
                    . "\"Count\":\"" . $prodCount . "\"},";
            }
        }
    }
    $jsonOItemText = substr_replace($jsonOItemText, '', -1);
    $jsonOItemText .= "]";
    $jsonOrderText = substr_replace($jsonOrderText, '', -1);
    $jsonOrderText .= "]";

    $OrderFuncResult = array($jsonOrderText, $jsonOItemText);
    return $OrderFuncResult;
}

function oclonerc_orderStat_update()
{
    global $wpdb;
    $postsTbl = $wpdb->posts;
    $StatList = $wpdb->get_results("SELECT ID, post_title FROM {$postsTbl} "
        . "WHERE post_type = 'yith-wccos-ostatus'");
    $jsonStatText = "";
    $jsonStatText .= "[";
    foreach ($StatList as $StatRow) {
        $postID = $StatRow->ID;
        $StatTitle = $StatRow->post_title;
        $StatSlug = oclonerc_GetStatSlug($postID);
        $jsonStatText .= "{\"StatusTitle\":\"" . $StatTitle . "\","
            . "\"StatusSlug\":\"" . $StatSlug . "\"},";
    }
    $jsonStatText = substr_replace($jsonStatText, '', -1);
    $jsonStatText .= "]";

    return $jsonStatText;
}

function oclonerc_product_update()
{
    global $wpdb;
    $CustID = get_option('oneCrm_CustID');
    $postsTbl = $wpdb->posts;
    $ProductList = $wpdb->get_results("SELECT * FROM {$postsTbl} "
        . "WHERE post_type = 'product'");
    $cnt = 0;
    $jsonProductText .= "[";
    foreach ($ProductList as $ProductRow) {
        $productId = $ProductRow->ID;
        $ProdName = $ProductRow->post_title;
        $ProdRegDate = $ProductRow->post_date;
        $ProdStock = oclonerc_ProductDetail("_stock_status", $productId);
        $ProdTotSale = oclonerc_ProductDetail("total_sales", $productId);
        $ProdPrice = oclonerc_ProductDetail("_price", $productId);
        $jsonProductText .= "{\"WPProductID\":\"" . $productId . "\","
            . "\"ProductName\":\"" . $ProdName . "\","
            . "\"ProductRegDate\":\"" . $ProdRegDate . "\","
            . "\"ProductStock\":\"" . $ProdStock . "\","
            . "\"ProductTotalSale\":\"" . $ProdTotSale . "\","
            . "\"ProductPrice\":\"" . $ProdPrice . "\"},";
    }
    $jsonProductText = substr_replace($jsonProductText, '', -1);
    $jsonProductText .= "]";

    return $jsonProductText;
}

//--------------------------------------------------- Utitlities

function oclonerc_UserDetail($Detail, $UsrID)
{
    global $wpdb;
    $userMetaTbl = $wpdb->usermeta;
    $userD = $wpdb->get_results("SELECT meta_value FROM {$userMetaTbl} "
        . "WHERE meta_key = '" . $Detail . "' "
        . "AND user_id = '" . $UsrID . "'");
    if (count($userD) > 0) {
        return $userD[0]->meta_value;
    } else
        return "0";
}

function oclonerc_ProductDetail($detail, $productID)
{
    global $wpdb;
    $postMetaTbl = $wpdb->postmeta;
    $productD = $wpdb->get_results("SELECT meta_value FROM {$postMetaTbl} "
        . "WHERE meta_key = '" . $detail . "' "
        . "AND post_id = '" . $productID . "'");
    return $productD[0]->meta_value;
}

function oclonerc_GetStatSlug($postID)
{
    global $wpdb;
    $postMetaTbl = $wpdb->postmeta;
    $statName = $wpdb->get_results("SELECT meta_value FROM {$postMetaTbl} "
        . "WHERE meta_key = 'slug' "
        . "AND post_id = '" . $postID . "'");
    return $statName[0]->meta_value;
}

function oclonerc_GetPostLink($PostID)
{
    global $wpdb;
    $postTbl = $wpdb->posts;
    $hostAdrs = $_SERVER['HTTP_HOST'];
    $postRes = $wpdb->get_results("SELECT post_type FROM {$postTbl} WHERE ID = $PostID");
    $PostType = $postRes[0]->post_type;

    if ($PostType == "post") {
        return "http://$hostAdrs/?p=$PostID";
    }
    if ($PostType == "product") {
        return "http://$hostAdrs/?post_type=product&p=$PostID";
    }
}

function onloner_GetPostTitle($PostID)
{
    global $wpdb;
    $postTbl = $wpdb->posts;
    $postRes = $wpdb->get_results("SELECT post_title FROM {$postTbl} WHERE ID = $PostID");
    $PostTitle = $postRes[0]->post_title;

    return $PostTitle;
}

function oclonerc_orderItemDetail($detail, $oItemID)
{
    global $wpdb;
    $orderItemMetaTbl = $wpdb->prefix . "woocommerce_order_itemmeta";
    $orderD = $wpdb->get_results("SELECT meta_value FROM {$orderItemMetaTbl} "
        . "WHERE meta_key = '" . $detail . "' "
        . "AND order_item_id = '" . $oItemID . "'");
    return $orderD[0]->meta_value;
}

function oclonerc_orderDetail($detail, $oID)
{
    global $wpdb;
    $postMetaTbl = $wpdb->postmeta;
    $orderD = $wpdb->get_results("SELECT meta_value FROM {$postMetaTbl} "
        . "WHERE meta_key = '" . $detail . "' "
        . "AND post_id = '" . $oID . "'");
    return $orderD[0]->meta_value;
}

function oclonerc_getPostDate($PostID)
{
    global $wpdb;
    $postTbl = $wpdb->posts;
    $orderD = $wpdb->get_results("SELECT post_date FROM {$postTbl} "
        . "WHERE ID = '" . $PostID . "'");
    return substr($orderD[0]->post_date, 0, 10);
}

function oclonerc_GetUserMobile($userId)
{
    global $wpdb;
    $postMetaTbl = $wpdb->postmeta;
    $query1 = $wpdb->get_results("SELECT post_id FROM {$postMetaTbl} "
        . "WHERE meta_key = '_customer_user' "
        . "AND meta_value = '" . $userId . "' "
        . "ORDER BY post_id DESC");

    $query2 = $wpdb->get_results("SELECT meta_value FROM {$postMetaTbl} "
        . "WHERE meta_key = '_billing_phone' "
        . "AND mpost_id = '" . $query1[0]->post_id . "'");
    return $query2[0]->meta_value;
}

function oclonerc_cUrlSend($postString)
{
    $url = get_option('oneCrm_pc') . $postString;
    $result = wp_remote_request($url);
    $res = wp_remote_retrieve_body($result);
    return $res;
}

function SendToOneCrmApi($args)
{
    $url = get_option('oneCrm_pc');
    $result = wp_remote_post($url, $args);
    $res = wp_remote_retrieve_body($result);
    return $res;
}

function ocloner_S2H($string)
{
    $hex = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $hex .= dechex(ord($string[$i]));
    }
    return $hex;
}
