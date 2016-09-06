<?php
/*-----------------------------------------------------------------
！！！！警告！！！！
以下为系统文件，请勿修改
-----------------------------------------------------------------*/

//不能非法包含或直接执行
if (!defined("IN_BAIGO")) {
    exit("Access Denied");
}

include_once(BG_PATH_FUNC . "mail.func.php");
include_once(BG_PATH_CLASS . "api.class.php"); //载入模板类
include_once(BG_PATH_CLASS . "crypt.class.php"); //载入模板类
include_once(BG_PATH_CLASS . "sign.class.php"); //载入模板类
include_once(BG_PATH_MODEL . "app.class.php"); //载入后台用户类
include_once(BG_PATH_MODEL . "belong.class.php");
include_once(BG_PATH_MODEL . "user.class.php"); //载入后台用户类
include_once(BG_PATH_MODEL . "log.class.php"); //载入管理帐号模型
include_once(BG_PATH_MODEL . "verify.class.php"); //载入管理帐号模型

/*-------------用户类-------------*/
class API_USER {

    private $obj_api;
    private $log;
    private $mdl_user;
    private $appAllow;
    private $appRows;
    private $appRequest;

    function __construct() { //构造函数
        $this->obj_api      = new CLASS_API();
        $this->obj_api->chk_install();
        $this->log          = $this->obj_api->log; //初始化 AJAX 基对象
        $this->obj_crypt    = new CLASS_CRYPT();
        $this->obj_sign     = new CLASS_SIGN();
        $this->mdl_user     = new MODEL_USER(); //设置管理组模型
        $this->mdl_app      = new MODEL_APP(); //设置管理组模型
        $this->mdl_belong   = new MODEL_BELONG();
        $this->mdl_log      = new MODEL_LOG(); //设置管理员模型
        $this->mdl_verify   = new MODEL_VERIFY(); //设置管理员模型
    }

    /**
     * api_reg function.
     *
     * @access public
     * @return void
     */
    function api_reg() {
        $this->app_check("post");

        if (defined("BG_REG_ACC") && BG_REG_ACC != "enable") {
            $_arr_return = array(
                "alert" => "x050316",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (!isset($this->appAllow["user"]["reg"])) { //无权限并记录日志
            $_arr_return = array(
                "alert" => "x050305",
            );
            $_arr_logType = array("user", "reg");
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userSubmit = $this->mdl_user->input_reg_api(); //获取数据
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post"      => $GLOBALS["act_post"],
            "user_name"     => $_arr_userSubmit["user_name"],
            "user_mail"     => $_arr_userSubmit["user_mail"],
            "user_pass"     => $_arr_userSubmit["user_pass"],
            "user_nick"     => $_arr_userSubmit["user_nick"],
            "user_contact"  => $_arr_userSubmit["user_contactStr"],
            "user_extend"   => $_arr_userSubmit["user_extendStr"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_str_rand        = fn_rand(6);
        $_str_userPass    = fn_baigoEncrypt($_arr_userSubmit["user_pass"], $_str_rand, true); //生成密码

        if (BG_REG_CONFIRM == "on") { //开启验证则为等待
            $_str_status = "wait";
        } else {
            $_str_status = "enable";
        }
        $_arr_userRow = $this->mdl_user->mdl_submit($_str_userPass, $_str_rand, $_str_status);

        if (BG_REG_CONFIRM == "on") { //开启验证发送邮件
            $_arr_returnRow    = $this->mdl_verify->mdl_submit($_arr_userRow["user_id"], $_arr_userSubmit["user_mail"]);
            if ($_arr_returnRow["alert"] != "y120101" && $_arr_returnRow["alert"] != "y120103") { //生成验证失败
                $_arr_return = array(
                    "alert" => "x010410",
                );
                $this->obj_api->halt_re($_arr_return);
            }

            $_str_verifyUrl = BG_SITE_URL . BG_URL_ROOT . "user/ctl.php?mod=reg&act_get=confirm&verify_id=" . $_arr_returnRow["verify_id"] . "&verify_token=" . $_arr_returnRow["verify_token"];
            $_str_url       = "<a href=\"" . $_str_verifyUrl . "\">" . $_str_verifyUrl . "</a>";
            $_str_html      = str_ireplace("{verify_url}", $_str_url, $this->obj_api->mail["reg"]["content"]);
            $_str_html      = str_ireplace("{user_name}", $_arr_userSubmit["user_name"], $_str_html);
            $_str_html      = str_ireplace("{user_mail}", $_arr_userSubmit["user_mail"], $_str_html);

            if (fn_mailSend($_arr_userSubmit["user_mail"], $this->obj_api->mail["reg"]["subject"], $_str_html)) { //发送邮件
                $_str_alert = "y010410";
            } else {
                $_str_alert = "x010410";
            }

            $_arr_userRow["alert"]          = $_str_alert;
            $_arr_userRow["verify_id"]      = $_arr_returnRow["verify_id"];
            $_arr_userRow["verify_token"]   = $_arr_returnRow["verify_token"];
        }

        //unset($_arr_userRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_userRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $this->mdl_belong->mdl_submit($_arr_userRow["user_id"], $this->appRequest["app_id"]); //用户授权

        $_arr_return = array(
            "code" => $_str_code,
        );

        $_tm_time = time();

        //通知
        foreach ($this->appRows as $_key=>$_value) {
            $_arr_data = array(
                "act_post"  => "reg",
                "code"      => $this->obj_crypt->encrypt($_str_src, $_value["app_key"]),
                "time"      => $_tm_time,
                "app_id"    => $_value["app_id"],
                "app_key"   => $_value["app_key"],
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            fn_http($_value["app_url_notify"] . $_str_conn . "mod=notify", $_arr_data, "post");
        }

        $_arr_return["alert"] = $_arr_userRow["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_login function.
     *
     * @access public
     * @return void
     */
    function api_login() {
        $this->app_check("post");

        $_arr_userSubmit = $this->mdl_user->input_login_api();
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post"  => $GLOBALS["act_post"],
            "user_pass" => $_arr_userSubmit["user_pass"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (fn_baigoEncrypt($_arr_userSubmit["user_pass"], $_arr_userRow["user_rand"], true) != $_arr_userRow["user_pass"]) {
            $_arr_return = array(
                "alert" => "x010213",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        //print_r($_arr_userRow);

        $_arr_userRowLogin = $this->mdl_user->mdl_login($_arr_userRow["user_id"]);

        unset($_arr_userRow["user_rand"], $_arr_userRow["user_pass"], $_arr_userRow["user_note"]);

        $_arr_userRow["user_access_token"]      = $_arr_userRowLogin["user_access_token"];
        $_arr_userRow["user_access_expire"]     = $_arr_userRowLogin["user_access_expire"];
        $_arr_userRow["user_refresh_token"]     = $_arr_userRowLogin["user_refresh_token"];
        $_arr_userRow["user_refresh_expire"]    = $_arr_userRowLogin["user_refresh_expire"];

        //unset($_arr_userRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_userRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_arr_return["alert"]  = "y010401";

        $this->obj_api->halt_re($_arr_return);
    }


    function api_refresh_token() {
        $this->app_check("post");

        $_arr_userSubmit = $this->mdl_user->input_refresh_api();
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post"  => $GLOBALS["act_post"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
            "user_refresh_token" => $_arr_userSubmit["user_refresh_token"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if ($_arr_userRow["user_refresh_expire"] < time()) {
            $_arr_return = array(
                "alert" => "x010235",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if ($_arr_userSubmit["user_refresh_token"] != $_arr_userRow["user_refresh_token"]) {
            $_arr_return = array(
                "alert" => "x010234",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        //print_r($_arr_userRow);

        $_arr_userRowRefresh = $this->mdl_user->mdl_refresh($_arr_userRow["user_id"]);

        unset($_arr_userRow["user_rand"], $_arr_userRow["user_pass"], $_arr_userRow["user_note"]);

        $_arr_userRow["user_access_token"]  = $_arr_userRowRefresh["user_access_token"];
        $_arr_userRow["user_access_expire"] = $_arr_userRowRefresh["user_access_expire"];

        //unset($_arr_userRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_userRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_arr_return["alert"]  = "y010411";

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_read function.
     *
     * @access public
     * @return void
     */
    function api_read() {
        $this->app_check("get");

        $_arr_userSubmit = $this->mdl_user->input_get_by("get");
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_get" => $GLOBALS["act_get"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read_api($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        //print_r($_arr_userRow);
        unset($_arr_userRow["user_rand"], $_arr_userRow["user_pass"], $_arr_userRow["user_note"]);

        //unset($_arr_userRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_userRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
            "alert"  => $_arr_userRow["alert"],
        );

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_edit function.
     *
     * @access public
     * @return void
     */
    function api_edit() {
        $this->app_check("post");

        if (!isset($this->appAllow["user"]["edit"])) { //无权限并记录日志
            $_arr_return = array(
                "alert" => "x050308",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("user", "edit");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userSubmit = $this->mdl_user->input_edit_api();
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post" => $GLOBALS["act_post"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
        );

        if (isset($_arr_userSubmit["user_check_pass"]) && $_arr_userSubmit["user_check_pass"] == true) {
            $_arr_sign["user_check_pass"]    = true;
            $_arr_sign["user_pass"]          = $_arr_userSubmit["user_pass"];
        } else {
            $_arr_sign["user_check_pass"] = false;
        }

        if (isset($_arr_userSubmit["user_pass_new"]) && $_arr_userSubmit["user_pass_new"]) {
            $_arr_sign["user_pass_new"] = $_arr_userSubmit["user_pass_new"];
        }

        if (isset($_arr_userSubmit["user_mail_new"]) && $_arr_userSubmit["user_mail_new"]) {
            $_arr_sign["user_mail_new"] = $_arr_userSubmit["user_mail_new"];
        }

        if (isset($_arr_userSubmit["user_nick"]) && $_arr_userSubmit["user_nick"]) {
            $_arr_sign["user_nick"] = $_arr_userSubmit["user_nick"];
        }

        if (isset($_arr_userSubmit["user_contactStr"]) && $_arr_userSubmit["user_contactStr"]) {
            $_arr_sign["user_contact"] = $_arr_userSubmit["user_contactStr"];
        }

        if (isset($_arr_userSubmit["user_extendStr"]) && $_arr_userSubmit["user_extendStr"]) {
            $_arr_sign["user_extend"] = $_arr_userSubmit["user_extendStr"];
        }

        //print_r($_arr_userSubmit);
        //print_r(array_merge($this->appRequest, $_arr_sign));

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_is_pass = false;

        if ($_arr_userSubmit["user_check_pass"] == true) { //是否验证密码
            if (fn_baigoEncrypt($_arr_userSubmit["user_pass"], $_arr_userRow["user_rand"], true) != $_arr_userRow["user_pass"]) {
                $_arr_return = array(
                    "alert" => "x010213",
                );
                $this->obj_api->halt_re($_arr_return);
            } else {
                $_is_pass = true;
            }
        }

        if (!isset($this->appAllow["user"]["global"]) && !$_is_pass) {  //是否授权
            $_arr_belongRow = $this->mdl_belong->mdl_read($_arr_userRow["user_id"], $this->appRequest["app_id"]);
            if ($_arr_belongRow["alert"] != "y070102") {
                $_arr_return = array(
                    "alert" => "x050308",
                );
                $this->obj_api->halt_re($_arr_return);
            }
        }

        if ((BG_REG_ONEMAIL == "false" || BG_LOGIN_MAIL == "on") && isset($_arr_userSubmit["user_mail_new"]) && $_arr_userSubmit["user_mail_new"]) {
            $_arr_userCheck = $this->mdl_user->mdl_read($_arr_userSubmit["user_mail_new"], "user_mail", $_arr_userRow["user_id"]); //检查邮箱
            if ($_arr_userCheck["alert"] == "y010102") {
                return array(
                    "alert" => "x010211",
                );
            }
        }

        //file_put_contents(BG_PATH_ROOT . "test.txt", $_str_userPass . "||" . $_str_rand);

        $_arr_userEdit              = $this->mdl_user->mdl_edit($_arr_userRow["user_id"]);
        $_arr_userEdit["user_name"] = $_arr_userRow["user_name"];

        //unset($_arr_userEdit["alert"]);
        $_str_src   = fn_jsonEncode($_arr_userEdit, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_tm_time    = time();

        //通知
        foreach ($this->appRows as $_key=>$_value) {
            $_arr_data = array(
                "act_post"  => "edit",
                "code"      => $this->obj_crypt->encrypt($_str_src, $_value["app_key"]),
                "time"      => $_tm_time,
                "app_id"    => $_value["app_id"],
                "app_key"   => $_value["app_key"],
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            fn_http($_value["app_url_notify"] . $_str_conn . "mod=notify", $_arr_data, "post");
        }

        $_arr_return["alert"]       = $_arr_userEdit["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_mailbox function.
     *
     * @access public
     * @return void
     */
    function api_mailbox() {
        $this->app_check("post");

        if (!isset($this->appAllow["user"]["mailbox"])) { //无权限并记录日志
            $_arr_return = array(
                "alert" => "x050308",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("user", "mailbox");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userSubmit = $this->mdl_user->input_mail_api();
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post" => $GLOBALS["act_post"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
            "user_mail_new" => $_arr_userSubmit["user_mail_new"],
        );

        if (isset($_arr_userSubmit["user_check_pass"]) && $_arr_userSubmit["user_check_pass"] == true) {
            $_arr_sign["user_check_pass"]    = true;
            $_arr_sign["user_pass"]          = $_arr_userSubmit["user_pass"];
        } else {
            $_arr_sign["user_check_pass"] = false;
        }

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if ($_arr_userSubmit["user_mail_new"] == $_arr_userRow["user_mail"]) {
            $_arr_return = array(
                "alert" => "x010223",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_is_pass = false;

        if ($_arr_userSubmit["user_check_pass"] == true) {
            if (fn_baigoEncrypt($_arr_userSubmit["user_pass"], $_arr_userRow["user_rand"], true) != $_arr_userRow["user_pass"]) {
                $_arr_return = array(
                    "alert" => "x010213",
                );
                $this->obj_api->halt_re($_arr_return);
            } else {
                $_is_pass = true;
            }
        }

        if (!isset($this->appAllow["user"]["global"]) && !$_is_pass) {
            $_arr_belongRow = $this->mdl_belong->mdl_read($_arr_userRow["user_id"], $this->appRequest["app_id"]);
            if ($_arr_belongRow["alert"] != "y070102") {
                $_arr_return = array(
                    "alert" => "x050308",
                );
                $this->obj_api->halt_re($_arr_return);
            }
        }

        if ((BG_REG_ONEMAIL == "false" || BG_LOGIN_MAIL == "on") && isset($_arr_userSubmit["user_mail_new"]) && $_arr_userSubmit["user_mail_new"]) {
            $_arr_userRowChk = $this->mdl_user->mdl_read($_arr_userSubmit["user_mail_new"], "user_mail", $_arr_userRow["user_id"]); //检查邮箱
            if ($_arr_userRowChk["alert"] == "y010102") {
                $_arr_return = array(
                    "alert" => "x010211",
                );
                $this->obj_api->halt_re($_arr_return);
            }
        }

        //file_put_contents(BG_PATH_ROOT . "test.txt", $_str_userPass . "||" . $_str_rand);

        if (BG_REG_CONFIRM == "on") {
            $_arr_returnRow    = $this->mdl_verify->mdl_submit($_arr_userRow["user_id"], $_arr_userSubmit["user_mail_new"]);
            if ($_arr_returnRow["alert"] != "y120101" && $_arr_returnRow["alert"] != "y120103") {
                $_arr_return = array(
                    "alert" => "x010405",
                );
                $this->obj_api->halt_re($_arr_return);
            }

            $_str_verifyUrl = BG_SITE_URL . BG_URL_ROOT . "user/ctl.php?mod=reg&act_get=mailbox&verify_id=" . $_arr_returnRow["verify_id"] . "&verify_token=" . $_arr_returnRow["verify_token"];
            $_str_url       = "<a href=\"" . $_str_verifyUrl . "\">" . $_str_verifyUrl . "</a>";
            $_str_html      = str_ireplace("{verify_url}", $_str_url, $this->obj_api->mail["mailbox"]["content"]);
            $_str_html      = str_ireplace("{user_name}", $_arr_userRow["user_name"], $_str_html);
            $_str_html      = str_ireplace("{user_mail}", $_arr_userRow["user_mail"], $_str_html);
            $_str_html      = str_ireplace("{user_mail_new}", $_arr_userSubmit["user_mail_new"], $_str_html);

            if (fn_mailSend($_arr_userSubmit["user_mail_new"], $this->obj_api->mail["mailbox"]["subject"], $_str_html)) {
                $_arr_returnRow["alert"] = "y010406";
            } else {
                $_arr_returnRow["alert"] = "x010406";
            }
        } else {
            $_arr_returnRow = $this->mdl_user->mdl_mail($_arr_userRow["user_id"], $_arr_userSubmit["user_mail_new"]);
        }

        $_arr_returnRow["user_id"]      = $_arr_userRow["user_id"];
        $_arr_returnRow["user_name"]    = $_arr_userRow["user_name"];

        //unset($_arr_returnRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_returnRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_tm_time    = time();

        //通知
        foreach ($this->appRows as $_key=>$_value) {
            $_arr_data = array(
                "act_post"  => "mailbox",
                "code"      => $this->obj_crypt->encrypt($_str_src, $_value["app_key"]),
                "time"      => $_tm_time,
                "app_id"    => $_value["app_id"],
                "app_key"   => $_value["app_key"],
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            fn_http($_value["app_url_notify"] . $_str_conn . "mod=notify", $_arr_data, "post");
        }

        $_arr_return["alert"]       = $_arr_returnRow["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    function api_forgot() {
        $this->app_check("post");

        if (!isset($this->appAllow["user"]["forgot"])) {
            $_arr_return = array(
                "alert" => "x050308",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("user", "forgot");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userSubmit = $this->mdl_user->input_get_by("post");
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post" => $GLOBALS["act_post"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (!isset($this->appAllow["user"]["global"])) {
            $_arr_belongRow = $this->mdl_belong->mdl_read($_arr_userRow["user_id"], $this->appRequest["app_id"]);
            if ($_arr_belongRow["alert"] != "y070102") {
                $_arr_return = array(
                    "alert" => "x050308",
                );
                $this->obj_api->halt_re($_arr_return);
            }
        }

        //file_put_contents(BG_PATH_ROOT . "test.txt", $_str_userPass . "||" . $_str_rand);

        $_arr_returnRow    = $this->mdl_verify->mdl_submit($_arr_userRow["user_id"], $_arr_userRow["user_mail"]);
        if ($_arr_returnRow["alert"] != "y120101" && $_arr_returnRow["alert"] != "y120103") {
            $_arr_return = array(
                "alert" => "x010407",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_str_verifyUrl = BG_SITE_URL . BG_URL_ROOT . "user/ctl.php?mod=reg&act_get=forgot&verify_id=" . $_arr_returnRow["verify_id"] . "&verify_token=" . $_arr_returnRow["verify_token"];
        $_str_url       = "<a href=\"" . $_str_verifyUrl . "\">" . $_str_verifyUrl . "</a>";
        $_str_html      = str_ireplace("{verify_url}", $_str_url, $this->obj_api->mail["forgot"]["content"]);
        $_str_html      = str_ireplace("{user_name}", $_arr_userRow["user_name"], $_str_html);

        if (fn_mailSend($_arr_userRow["user_mail"], $this->obj_api->mail["forgot"]["subject"], $_str_html)) {
            $_arr_returnRow["alert"] = "y010408";
        } else {
            $_arr_returnRow["alert"] = "x010408";
        }

        $_arr_returnRow["user_id"]      = $_arr_userRow["user_id"];
        $_arr_returnRow["user_name"]    = $_arr_userRow["user_name"];

        //unset($_arr_returnRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_returnRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_arr_return["alert"]       = $_arr_returnRow["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    function api_nomail() {
        $this->app_check("post");

        if (!isset($this->appAllow["user"]["reg"])) {
            $_arr_return = array(
                "alert" => "x050308",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("user", "reg");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userSubmit = $this->mdl_user->input_get_by("post");
        if ($_arr_userSubmit["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userSubmit);
        }

        $_arr_sign = array(
            "act_post" => $GLOBALS["act_post"],
            $_arr_userSubmit["user_by"] => $_arr_userSubmit["user_str"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($_arr_userSubmit["user_str"], $_arr_userSubmit["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "enable") {
            $_arr_return = array(
                "alert" => "x010226",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (!isset($this->appAllow["user"]["global"])) {
            $_arr_belongRow = $this->mdl_belong->mdl_read($_arr_userRow["user_id"], $this->appRequest["app_id"]);
            if ($_arr_belongRow["alert"] != "y070102") {
                $_arr_return = array(
                    "alert" => "x050308",
                );
                $this->obj_api->halt_re($_arr_return);
            }
        }

        //file_put_contents(BG_PATH_ROOT . "test.txt", $_str_userPass . "||" . $_str_rand);

        $_arr_returnRow    = $this->mdl_verify->mdl_submit($_arr_userRow["user_id"], $_arr_userRow["user_mail"]);
        if ($_arr_returnRow["alert"] != "y120101" && $_arr_returnRow["alert"] != "y120103") {
            $_arr_return = array(
                "alert" => "x010407",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_str_verifyUrl = BG_SITE_URL . BG_URL_ROOT . "user/ctl.php?mod=reg&act_get=confirm&verify_id=" . $_arr_returnRow["verify_id"] . "&verify_token=" . $_arr_returnRow["verify_token"];
        $_str_url       = "<a href=\"" . $_str_verifyUrl . "\">" . $_str_verifyUrl . "</a>";
        $_str_html      = str_ireplace("{verify_url}", $_str_url, $this->obj_api->mail["reg"]["content"]);
        $_str_html      = str_ireplace("{user_name}", $_arr_userRow["user_name"], $_str_html);
        $_str_html      = str_ireplace("{user_mail}", $_arr_userRow["user_mail"], $_str_html);

        if (fn_mailSend($_arr_userRow["user_mail"], $this->obj_api->mail["reg"]["subject"], $_str_html)) {
            $_arr_returnRow["alert"] = "y010408";
        } else {
            $_arr_returnRow["alert"] = "x010408";
        }

        $_arr_returnRow["user_id"]      = $_arr_userRow["user_id"];

        //unset($_arr_returnRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_returnRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_arr_return["alert"]       = $_arr_returnRow["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_del function.
     *
     * @access public
     * @return void
     */
    function api_del() {
        $this->app_check("post");

        if (!isset($this->appAllow["user"]["del"])) {
            $_arr_return = array(
                "alert" => "x050309",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("user", "del");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userIds   = $this->mdl_user->input_ids_api();

        $_arr_sign = array(
            "act_post" => $GLOBALS["act_post"],
            "user_ids" => $_arr_userIds["str_userIds"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (!isset($this->appAllow["user"]["global"])) {
            $_arr_search = array(
                "app_id"    => $this->appRequest["app_id"],
                "user_ids"  => $_arr_userIds["user_ids"],
            );
            $_arr_users = $this->mdl_belong->mdl_list(1000, 0, $_arr_search);
        } else {
            $_arr_users = $_arr_userIds;
        }

        $_arr_userDel = $this->mdl_user->mdl_del($_arr_users);

        if ($_arr_userDel["alert"] == "y010104") {
            foreach ($_arr_userIds["user_ids"] as $_key=>$_value) {
                $_arr_targets[] = array(
                    "user_id" => $_value,
                );
                $_str_targets = json_encode($_arr_targets);
            }

            $_arr_logData = array(
                "log_targets"        => $_str_targets,
                "log_target_type"    => "user",
                "log_title"          => $this->log["user"]["del"],
                "log_result"         => $_str_result,
                "log_type"           => "app",
            );

            $this->mdl_log->mdl_submit($_arr_logData, $this->appRequest["app_id"]);
        }

        $_tm_time   = time();
        $_str_src   = fn_jsonEncode($_arr_userIds, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        foreach ($this->appRows as $_key=>$_value) {
            $_arr_data = array(
                "act_post"  => "del",
                "code"      => $this->obj_crypt->encrypt($_str_src, $_value["app_key"]),
                "time"      => $_tm_time,
                "app_id"    => $_value["app_id"],
                "app_key"   => $_value["app_key"],
            );

            $_arr_data["signature"] = $this->obj_sign->sign_make($_arr_data);

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            if (stristr($_value["app_url_notify"], "?")) {
                $_str_conn = "&";
            } else {
                $_str_conn = "?";
            }

            fn_http($_value["app_url_notify"] . $_str_conn . "mod=notify", $_arr_data, "post");
        }

        $this->obj_api->halt_re($_arr_userDel);
    }


    /**
     * api_chkname function.
     *
     * @access public
     * @return void
     */
    function api_chkname() {
        $this->app_check("get");

        $_arr_userName = $this->mdl_user->input_chk_name();
        if ($_arr_userName["alert"] != "ok") {
            $this->obj_api->halt_re($_arr_userName);
        }

        $_arr_userName["act_get"] = $GLOBALS["act_get"];

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_userName), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->mdl_user->mdl_read_api($_arr_userName["user_name"], "user_name");
        if ($_arr_userRow["alert"] == "y010102") {
            $_str_alert = "x010205";
        } else {
            $_str_alert = "y010205";
        }
        $_arr_return = array(
            "alert" => $_str_alert,
        );
        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_chkmail function.
     *
     * @access public
     * @return void
     */
    function api_chkmail() {
        $this->app_check("get");

        $_arr_userMail = $this->mdl_user->input_chk_mail();

        if (BG_REG_ONEMAIL == "false" || BG_LOGIN_MAIL == "on") { //不允许重复
            if ($_arr_userMail["alert"] != "ok") {
                $this->obj_api->halt_re($_arr_userMail);
            }

            if ($_arr_userMail["user_mail"]) {
                $_arr_userRow = $this->mdl_user->mdl_read_api($_arr_userMail["user_mail"], "user_mail", $_arr_userMail["not_id"]);
                if ($_arr_userRow["alert"] == "y010102") {
                    $_str_alert = "x010211";
                } else {
                    $_str_alert = "y010211";
                }
            } else {
                $_str_alert = "y010211";
            }
        } else {
            $_str_alert = "y010211";
        }

        $_arr_sign = array(
            "act_get"   => $GLOBALS["act_get"],
            "user_mail" => $_arr_userMail["user_mail"],
            "not_id"    => $_arr_userMail["not_id"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_str_alert = "x050403";
        }

        $_arr_return = array(
            "alert" => $_str_alert,
        );
        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * app_check function.
     *
     * @access private
     * @param mixed $num_appId
     * @param string $str_method (default: "get")
     * @return void
     */
    private function app_check($str_method = "get") {
        $this->appRequest = $this->obj_api->app_request($str_method, true);

        if ($this->appRequest["alert"] != "ok") {
            $this->obj_api->halt_re($this->appRequest);
        }

        $_arr_logTarget[] = array(
            "app_id" => $this->appRequest["app_id"]
        );

        $this->appRow = $this->mdl_app->mdl_read($this->appRequest["app_id"]);
        if ($this->appRow["alert"] != "y050102") {
            $_arr_logType = array("app", "read");
            $this->log_do($_arr_logTarget, "app", $this->appRow, $_arr_logType);
            $this->obj_api->halt_re($this->appRow);
        }
        $this->appAllow = $this->appRow["app_allow"];

        $_arr_appChk = $this->obj_api->app_chk($this->appRequest, $this->appRow);
        if ($_arr_appChk["alert"] != "ok") {
            $_arr_logType = array("app", "check");
            $this->log_do($_arr_logTarget, "app", $_arr_appChk, $_arr_logType);
            $this->obj_api->halt_re($_arr_appChk);
        }

        $_arr_search = array(
            "status"        => "enable",
            "sync"          => "on",
            "has_notify"    => true,
        );
        $this->appRows = $this->mdl_app->mdl_list(100, 0, $_arr_search);
    }


    /**
     * log_do function.
     *
     * @access private
     * @param mixed $arr_logResult
     * @param mixed $str_logType
     * @return void
     */
    private function log_do($arr_logTarget, $str_targetType, $arr_logResult, $arr_logType) {
        $_str_targets = json_encode($arr_logTarget);
        $_str_result  = json_encode($arr_logResult);

        $_arr_logData = array(
            "log_targets"        => $_str_targets,
            "log_target_type"    => $str_targetType,
            "log_title"          => $this->log[$arr_logType[0]][$arr_logType[1]],
            "log_result"         => $_str_result,
            "log_type"           => "app",
        );

        $this->mdl_log->mdl_submit($_arr_logData, $this->appRequest["app_id"]);
    }
}
