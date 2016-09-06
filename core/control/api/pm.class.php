<?php
/*-----------------------------------------------------------------
！！！！警告！！！！
以下为系统文件，请勿修改
-----------------------------------------------------------------*/

//不能非法包含或直接执行
if (!defined("IN_BAIGO")) {
    exit("Access Denied");
}

include_once(BG_PATH_CLASS . "api.class.php"); //载入模板类
include_once(BG_PATH_CLASS . "crypt.class.php"); //载入模板类
include_once(BG_PATH_CLASS . "sign.class.php"); //载入模板类
include_once(BG_PATH_MODEL . "app.class.php"); //载入后台用户类
include_once(BG_PATH_MODEL . "pm.class.php"); //载入后台用户类
include_once(BG_PATH_MODEL . "log.class.php"); //载入管理帐号模型
include_once(BG_PATH_MODEL . "user.class.php"); //载入后台用户类

/*-------------用户类-------------*/
class API_PM {

    private $obj_api;
    private $log;
    private $mdl_pm;
    private $appAllow;
    private $appRequest;

    function __construct() { //构造函数
        $this->obj_api      = new CLASS_API();
        $this->obj_api->chk_install();
        $this->log          = $this->obj_api->log;
        $this->obj_crypt    = new CLASS_CRYPT();
        $this->obj_sign     = new CLASS_SIGN();
        $this->mdl_pm       = new MODEL_PM();
        $this->mdl_app      = new MODEL_APP();
        $this->mdl_log      = new MODEL_LOG();
        $this->mdl_user     = new MODEL_USER();
    }

    /**
     * api_reg function.
     *
     * @access public
     * @return void
     */
    function api_send() {
        $this->app_check("post");

        if (!isset($this->appAllow["pm"]["send"])) { //无权限并记录日志
            $_arr_return = array(
                "alert" => "x050320",
            );
            $_arr_logType = array("pm", "send");
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow   = $this->user_check("post");
        $_arr_pmSend    = $this->mdl_pm->input_send();
        if ($_arr_pmSend["alert"] != "ok") {
            $this->obj_ajax->halt_alert($_arr_pmSend["alert"]);
        }

        $_arr_sign = array(
            "act_post"                      => $GLOBALS["act_post"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
        );

        if (fn_isEmpty(fn_get("pm_title"))) {
            unset($_arr_pmSend["pm_title"]); //如果标题为自动生成, 则忽略
        }

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_pmSend, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if (stristr($_arr_pmSend["pm_to"], "|")) {
            $_arr_pmTo = explode("|", $_arr_pmSend["pm_to"]);
        } else {
            $_arr_pmTo = array($_arr_pmSend["pm_to"]);
        }

        $_arr_pmTo = array_unique($_arr_pmTo);

        $_arr_pmRows = array();

        foreach ($_arr_pmTo as $_key=>$_value) {
            $_arr_toUser = $this->mdl_user->mdl_read($_value, "user_name");
            if ($_arr_toUser["alert"] == "y010102") {
                $_arr_pmRows[$_key] = $this->mdl_pm->mdl_submit($_arr_toUser["user_id"], $_arr_userRow["user_id"]);
                $_arr_pmRows[$_key]["pm_to"] = $_arr_toUser["user_id"];
            }
        }

        $_str_src   = fn_jsonEncode($_arr_pmRows, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
        );

        $_arr_return["alert"]   = $_arr_pmRows[$_key]["alert"];

        $this->obj_api->halt_re($_arr_return);
    }


    function api_revoke() {
        $this->app_check("post");

        if (!isset($this->appAllow["pm"]["revoke"])) {
            $_arr_return = array(
                "alert" => "x050322",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "revoke");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->user_check("post");

        $_arr_pmIds   = $this->mdl_pm->input_ids_api();

        $_arr_sign = array(
            "act_post"                      => $GLOBALS["act_post"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
            "pm_ids"                        => $_arr_pmIds["str_pmIds"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmDel = $this->mdl_pm->mdl_del($_arr_userRow["user_id"], true);

        $this->obj_api->halt_re($_arr_pmDel);
    }


    function api_status() {
        $this->app_check("post");

        if (!isset($this->appAllow["pm"]["status"])) {
            $_arr_return = array(
                "alert" => "x050321",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "status");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->user_check("post");

        $_arr_pmIds   = $this->mdl_pm->input_ids_api();

        $_str_status = fn_getSafe(fn_post("pm_status"), "txt", "");
        if (!$_str_status) {
            $_arr_return = array(
                "alert" => "x110219",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_sign = array(
            "act_post"                      => $GLOBALS["act_post"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
            "pm_status"                     => $_str_status,
            "pm_ids"                        => $_arr_pmIds["str_pmIds"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmStatus = $this->mdl_pm->mdl_status($_str_status, $_arr_userRow["user_id"]);

        $this->obj_api->halt_re($_arr_pmStatus);
    }

    /**
     * api_del function.
     *
     * @access public
     * @return void
     */
    function api_del() {
        $this->app_check("post");

        if (!isset($this->appAllow["pm"]["del"])) {
            $_arr_return = array(
                "alert" => "x050309",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "del");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->user_check("post");

        $_arr_pmIds   = $this->mdl_pm->input_ids_api();

        $_arr_sign = array(
            "act_post"                      => $GLOBALS["act_post"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
            "pm_ids"                        => $_arr_pmIds["str_pmIds"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmDel = $this->mdl_pm->mdl_del($_arr_userRow["user_id"]);

        $this->obj_api->halt_re($_arr_pmDel);
    }


    /**
     * api_read function.
     *
     * @access public
     * @return void
     */
    function api_read() {
        $this->app_check("get");

        if (!isset($this->appAllow["pm"]["read"])) {
            $_arr_return = array(
                "alert" => "x050319",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "read");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->user_check("get");

        $_num_pmId = fn_getSafe(fn_get("pm_id"), "int", 0);
        if ($_num_pmId < 1) {
            $_arr_return = array(
                "alert" => "x110211",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_sign = array(
            "act_get"                       => $GLOBALS["act_get"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
            "pm_id"                         => $_num_pmId,
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmRow = $this->mdl_pm->mdl_read($_num_pmId);
        if ($_arr_pmRow["alert"] != "y110102") {
            $this->obj_api->halt_re($_arr_pmRow);
        }

        if ($_arr_pmRow["pm_from"] != $_arr_userRow["user_id"] && $_arr_pmRow["pm_to"] != $_arr_userRow["user_id"]) {
            $_arr_return = array(
                "alert" => "x110403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmRow["fromUser"] = $this->mdl_user->mdl_read_api($_arr_pmRow["pm_from"]);
        $_arr_pmRow["toUser"]   = $this->mdl_user->mdl_read_api($_arr_pmRow["pm_to"]);

        if ($_arr_pmRow["pm_type"] == "out") {
            $_arr_sendRow = $this->mdl_pm->mdl_read($_arr_pmRow["pm_send_id"]);
            if ($_arr_sendRow["alert"] != "y110102") {
                $_arr_pmRow["pm_send_status"] = "revoke";
            } else {
                $_arr_pmRow["pm_send_status"] = $_arr_sendRow["pm_status"];
            }
        }

        //unset($_arr_pmRow["alert"]);
        $_str_src   = fn_jsonEncode($_arr_pmRow, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
            "alert"  => $_arr_pmRow["alert"],
        );

        $this->obj_api->halt_re($_arr_return);
    }


    function api_check() {
        $this->app_check("get");

        if (!isset($this->appAllow["pm"]["check"])) {
            $_arr_return = array(
                "alert" => "x050319",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "check");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow = $this->user_check("get");

        $_arr_sign = array(
            "act_get"                       => $GLOBALS["act_get"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
        );

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_search = array(
            "type"      => "in",
            "pm_to"     => $_arr_userRow["user_id"],
            "status"    => fn_getSafe(fn_get("status"), "txt", "wait"),
        );

        $_num_pmCount   = $this->mdl_pm->mdl_count($_arr_search);

        $_arr_return = array(
            "pm_count"  => $_num_pmCount,
            "alert"     => "y110402",
        );

        $this->obj_api->halt_re($_arr_return);
    }


    /**
     * api_chkname function.
     *
     * @access public
     * @return void
     */
    function api_list() {
        $this->app_check("get");

        if (!isset($this->appAllow["pm"]["list"])) {
            $_arr_return = array(
                "alert" => "x050319",
            );
            $_arr_logTarget[] = array(
                "app_id" => $this->appRequest["app_id"],
            );
            $_arr_logType = array("pm", "list");
            $this->log_do($_arr_logTarget, "app", $_arr_return, $_arr_logType);
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_userRow   = $this->user_check("get");

        $_num_perPage   = fn_getSafe(fn_get("per_page"), "int", BG_SITE_PERPAGE);
        $_str_pmIds     = fn_getSafe(fn_get("pm_ids"), "txt", "");
        $_str_type      = fn_getSafe(fn_get("pm_type"), "txt", "");
        $_str_status    = fn_getSafe(fn_get("pm_status"), "txt", "");
        $_str_key       = fn_getSafe(fn_get("key"), "txt", "");

        $_arr_sign = array(
            "act_get"                       => $GLOBALS["act_get"],
            $this->userRequest["user_by"]   => $this->userRequest["user_str"],
            "user_access_token"             => $this->userRequest["user_access_token"],
            "pm_ids"                        => $_str_pmIds,
            "pm_type"                       => $_str_type,
            "pm_status"                     => $_str_status,
            "key"                           => $_str_key,
        );

        if (!fn_isEmpty(fn_get("per_page"))) {
            $_arr_sign["per_page"] = $_num_perPage;
        }

    	//file_put_contents(BG_PATH_ROOT . "debug.txt", json_encode($_arr_sign), FILE_APPEND);

        if (!$this->obj_sign->sign_check(array_merge($this->appRequest, $_arr_sign), $this->appRequest["signature"])) {
            $_arr_return = array(
                "alert" => "x050403",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_pmIds = array();

        if ($_str_pmIds) {
            if (stristr($_str_pmIds, "|")) {
                $_arr_pmIds = explode("|", $_str_pmIds);
            } else {
                $_arr_pmIds = array($_str_pmIds);
            }
        }

        if (!$_str_type) {
            $_arr_return = array(
                "alert" => "x110218",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        $_arr_search = array(
            "type"      => $_str_type,
            "status"    => $_str_status,
            "key"       => $_str_key,
            "pm_ids"    => $_arr_pmIds,
        );

        switch ($_str_type) {
            case "in":
                $_arr_search["pm_to"]   = $_arr_userRow["user_id"];
            break;

            case "out":
                $_arr_search["pm_from"] = $_arr_userRow["user_id"];
            break;
        }

        $_num_pmCount   = $this->mdl_pm->mdl_count($_arr_search);
        $_arr_page      = fn_page($_num_pmCount);
        $_arr_pmRows    = $this->mdl_pm->mdl_list($_num_perPage, $_arr_page["except"], $_arr_search);

        foreach ($_arr_pmRows as $_key=>$_value) {
            $_arr_pmRows[$_key]["fromUser"] = $this->mdl_user->mdl_read_api($_value["pm_from"]);
            $_arr_pmRows[$_key]["toUser"]   = $this->mdl_user->mdl_read_api($_value["pm_to"]);

            if ($_str_type == "out") {
                $_arr_sendRow = $this->mdl_pm->mdl_read($_value["pm_send_id"]);
                if ($_arr_sendRow["alert"] != "y110102") {
                    $_arr_pmRows[$_key]["pm_send_status"] = "revoke";
                } else {
                    $_arr_pmRows[$_key]["pm_send_status"] = $_arr_sendRow["pm_status"];
                }
            }
        }

        //print_r($_arr_pmRows);

        $_arr_return = array(
            "pmRows"    => $_arr_pmRows,
            "pageRow"   => $_arr_page,
        );

        $_str_src   = fn_jsonEncode($_arr_return, "encode");
        $_str_code  = $this->obj_crypt->encrypt($_str_src, $this->appRow["app_key"]);

        $_arr_return = array(
            "code"   => $_str_code,
            "alert"  => "y110402",
        );

        $this->obj_api->halt_re($_arr_return);
    }


    private function user_check($str_method = "get") {
        $this->userRequest = $this->mdl_user->input_token_api($str_method);

        if ($this->userRequest["alert"] != "ok") {
            $this->obj_api->halt_re($this->userRequest);
        }

        $_arr_userRow = $this->mdl_user->mdl_read($this->userRequest["user_str"], $this->userRequest["user_by"]);
        if ($_arr_userRow["alert"] != "y010102") {
            $this->obj_api->halt_re($_arr_userRow);
        }

        if ($_arr_userRow["user_status"] == "disable") {
            $_arr_return = array(
                "alert" => "x010401",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if ($_arr_userRow["user_access_expire"] < time()) {
            $_arr_return = array(
                "alert" => "x010231",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        if ($this->userRequest["user_access_token"] != $_arr_userRow["user_access_token"]) {
            $_arr_return = array(
                "alert" => "x010230",
            );
            $this->obj_api->halt_re($_arr_return);
        }

        return $_arr_userRow;
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
