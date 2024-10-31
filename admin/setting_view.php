<div class="wrap">
    <h2 style='font-family: tahoma;'><b>تنظیمات وان ‌سی-‌آر-‌ام </b></h2>
    <hr>
    <h3 style='font-family: tahoma;'><b>ثبت کد اتصال</b></h3>
    <form method="post">
        <p>برای اتصال وردپرس به وان سی آر ام لطفا کد اتصال را از بخش تنظیمات در پنل مدیریت دریافت کنید و در کادر زیر وارد کنید.</p>
        <p style="font-weight: 500; font-size: 14px; color:blue;">
            <strong>توجه:</strong>
            پس از اتصال به سرور تمامی اطلاعات شما از قبیل: کاربران، سفارشات، محصولات، دیدگاه ها و بازدید کاربران شما به صورت هر ساعت یکبار به سرور ارسال خواهد شد همچنین این عملیات در چند ثانیه انجام خواهد شد و هیچ فشاری به سایت و هاستینگ شما وارد نخواهد شد.
        </p>
        <p style="font-weight: 500; font-size: 14px; color:green;">
            این اطلاعات به صورت رمز شده در سرور ذخیره می شود و فقط توسط حساب کاربری شما قابل بازگشایی و خواندن خواهد بود و تضمین می شود که توسط هیچ فردی غیر از شما قابل مشاهده نباشد.
        </p>
        <?php wp_nonce_field('oc-secure-token' , 'secure_token'); ?>
        <input type="text" name='tokenTxt' style="width: 400px;" />
        <input type="submit" value="ارسال کد" class='button-primary' /><br />
    </form>
    <?php
    if (!defined('ABSPATH')) exit; // Exit if accessed directly


    include_once plugin_dir_path(__FILE__) . '../sync.php';

    if (isset($_GET['updt']) && current_user_can('administrator')) {
        oclonerc_fullUpdate();
    }

    if (get_option('oneCrm_Token') != null && get_option('oneCrm_CustID') != null) {
        print '<p style="font-weight: 600; font-size: 14px; color:limegreen;">تبریک! اتصال افزونه شما به سرور، توسط کد اتصال زیر برقرار است.</p>';
        print '<b>' . get_option('oneCrm_Token') . '</b>';
    }

    if (array_key_exists('tokenTxt', $_POST) && current_user_can('administrator')) {

        $nonce = $_POST['secure_token'];

        if (!isset($nonce) || !wp_verify_nonce($nonce, 'oc-secure-token')) {
            die('اشکال امنیتی');
        }

        $tokenVal = sanitize_text_field($_POST['tokenTxt']);
        print $tokenVal;
        $url = "http://api.onecrm.org/Token/Connection";
        $args = array(
            'body' => array(
                'CMD' => 'TokenConnect',
                'Url' => $_SERVER['HTTP_HOST'],
                'Token' => $tokenVal
            )
        );

        $res = wp_remote_post($url, $args);
        print "<br/>";
        $bodyRes = wp_remote_retrieve_body($res);
        $rStr = explode("|", $bodyRes);
        if ($rStr[0] == "OK") {
            update_option('oneCrm_CustID', $rStr[1]);
            update_option('oneCrm_Token', $tokenVal);
            print '<p style="font-weight: 500; font-size: 14px; color:limegreen;">اتصال با موفقیت بر قرار شد.</p>';
        } else {
            print '<p style="font-weight: 500; font-size: 14px; color:red;">کد اتصال  یا آدرس وب سایت شما در پایگاه داده یافت نشد.</p>';
        }
    }
    ?>
    <hr>
    <br /><a href='admin.php?page=oc_OneCrmSetting&updt=true' class='button-primary'>همگام سازی</a>
    <br />

    <hr>
    <p>این افزونه توسط OneCrm طراحی و توسعه داده شده است.</p>
    <a href="http://onecrm.org">OneCrm</a>
    <br />
    <a href="http://app.onecrm.org/Main/WPSetting">OneCrm Wordpress Plugin Document</a>
</div>