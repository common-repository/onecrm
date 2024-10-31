<?php
/*
Plugin Name:  سیستم مدیریت بازاریابی اینترنتی - OneCrm
Plugin URI: https://wordpress.org/plugins/onecrm/
Description: این افزونه برای ارتباط OneCrm با وب سایت شماست.
Author: Milad Helmi
Version: 1.4
Author URI: https://miladhelmi.ir/
 */
if (!defined('ABSPATH')) exit; // Exit if accessed directly

add_action('admin_menu', 'oclonerc_addMenuPage');
function oclonerc_addMenuPage()
{
    add_menu_page(
        'تنظیمات',
        'وان سی آر ام',
        'administrator',
        'oc_OneCrmSetting',
        'oc_echo_OneCrm_Setting',
        plugins_url('images/oclogo.png', __FILE__),
        30
    );
}

function oc_echo_OneCrm_Setting()
{
    if (is_admin()) {
        include plugin_dir_path(__FILE__) . 'admin/setting_view.php';
    }
}

include_once plugin_dir_path(__FILE__) . 'sync.php';

register_activation_hook(__FILE__, function () {
    add_option('oneCrm_Token', '');
    add_option('oneCrm_CustID', 0);
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('oclonerc_ron_sync_hook');
});

if (!wp_next_scheduled('oclonerc_ron_sync_hook')) {
    wp_schedule_event(time(), 'hourly', 'oclonerc_ron_sync_hook');
}

add_action('oclonerc_ron_sync_hook', 'oclonerc_syncCron');
function oclonerc_syncCron()
{
    oclonerc_fullUpdate();
}

add_action('init', 'set_oclonerc_userid_cookie');
function set_oclonerc_userid_cookie()
{
    $userid = get_current_user_id();
    $custId = get_option('oneCrm_CustID');
    if ($userid != '0') {
        $oc_cookie_name = "_ocWpUser_" . $custId;
        setcookie($oc_cookie_name, $userid, time() + 3600 * 24 * 365, COOKIEPATH, COOKIE_DOMAIN, false);
    }
}

add_action('wp_footer', 'oclonerc_obsUser', 1);
function oclonerc_obsUser()
{
    $custId = get_option('oneCrm_CustID');
    $oc_cookie_name = "_ocWpUser_" . $custId;
    $userid = $_COOKIE[$oc_cookie_name];
    if ($userid != 0) {
        $uriLinkStr = $_SERVER[REQUEST_URI];
        if (
            strpos($uriLinkStr, 'wp-content') === false &&
            strpos($uriLinkStr, 'preview=true') === false &&
            strpos($uriLinkStr, '/checkout/') === false &&
            strpos($uriLinkStr, '/cart/') === false &&
            strpos($uriLinkStr, '/?customize_changeset_uuid') === false
        ) {
            $page_link = "http://$_SERVER[HTTP_HOST]" . "$_SERVER[REQUEST_URI]";
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $url = "http://api.onecrm.org/Visit/Submit";

            $args = array(
                'body' => array(
                    'WPUserID' => $userid,
                    'PageLink' => $page_link,
                    'UserIP' => $user_ip,
                    'CustID' => $custId
                )
            );
            wp_remote_post($url, $args);
        }
    }
}

function ocloner_St2He($string)
{
    $hex = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $hex .= dechex(ord($string[$i]));
    }
    return $hex;
}
