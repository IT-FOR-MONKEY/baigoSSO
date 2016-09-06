<?php
/*-----------------------------------------------------------------
！！！！警告！！！！
以下为系统文件，请勿修改
-----------------------------------------------------------------*/

//不能非法包含或直接执行
if (!defined("IN_BAIGO")) {
    exit("Access Denied");
}

if (!defined("BG_PATH_LIB")) {
    define("BG_PATH_LIB", BG_PATH_CORE . "/lib/");
}
include_once(BG_PATH_LIB . "smarty/smarty.class.php"); //载入 Smarty 类

/*-------------模板类-------------*/
class CLASS_TPL {

    public $common; //通用
    public $obj_base; //基类
    public $obj_smarty; //Smarty
    public $config; //配置
    public $lang; //语言 通用
    public $status; //语言 状态
    public $type; //语言 类型
    public $alert; //语言 返回代码
    public $adminMod; //语言 后台
    public $opt; //语言 后台

    function __construct($str_pathTpl, $arr_cfg = false) { //构造函数
        $this->arr_cfg  = $arr_cfg;
        $this->obj_base = $GLOBALS["obj_base"];
        $this->config   = $this->obj_base->config;

        $this->obj_smarty               = new Smarty(); //初始化 Smarty 对象
        $this->obj_smarty->template_dir = $str_pathTpl;
        $this->obj_smarty->compile_dir  = BG_PATH_CACHE . "tpl";
        $this->obj_smarty->debugging    = BG_SWITCH_SMARTY_DEBUG; //调试模式

        $this->lang     = include_once(BG_PATH_LANG . $this->config["lang"] . "/common.php"); //载入语言文件
        $this->type     = include_once(BG_PATH_LANG . $this->config["lang"] . "/type.php"); //载入类型文件
        $this->allow    = include_once(BG_PATH_LANG . $this->config["lang"] . "/allow.php"); //载入权限文件
        $this->alert    = include_once(BG_PATH_LANG . $this->config["lang"] . "/alert.php"); //载入返回代码

        if (isset($arr_cfg["admin"])) {
            $this->install  = include_once(BG_PATH_LANG . $this->config["lang"] . "/install.php"); //载入安装信息
            $this->adminMod = include_once(BG_PATH_LANG . $this->config["lang"] . "/adminMod.php"); //载入后台模块配置
        }

        if (isset($arr_cfg["admin"]) || isset($arr_cfg["user"])) {
            $this->status   = include_once(BG_PATH_LANG . $this->config["lang"] . "/status.php"); //载入状态文件
            $this->opt      = include_once(BG_PATH_LANG . $this->config["lang"] . "/opt.php"); //载入配置信息
            //$this->userMod  = include_once(BG_PATH_LANG . $this->config["lang"] . "/userMod.php"); //载入用户模块配置
        }
    }


    /** 显示界面
     * tplDisplay function.
     *
     * @access public
     * @param mixed $str_tpl
     * @param string $arr_tplData (default: "")
     * @return void
     */
    function tplDisplay($str_tpl, $arr_tplData = "") {
        $this->common["tokenRow"]   = fn_token();
        $this->common["ssid"]       = session_id();

        $this->obj_smarty->assign("common", $this->common);
        $this->obj_smarty->assign("config", $this->config);
        $this->obj_smarty->assign("lang", $this->lang);
        $this->obj_smarty->assign("type", $this->type);
        $this->obj_smarty->assign("allow", $this->allow);
        $this->obj_smarty->assign("alert", $this->alert);

        if (isset($this->arr_cfg["admin"])) {
            $this->obj_smarty->assign("install", $this->install);
            $this->obj_smarty->assign("adminMod", $this->adminMod);
        }

        if (isset($this->arr_cfg["admin"]) || isset($this->arr_cfg["user"])) {
            $this->obj_smarty->assign("status", $this->status);
            $this->obj_smarty->assign("opt", $this->opt);
            //$this->obj_smarty->assign("userMod", $this->userMod);
        }

        $this->obj_smarty->assign("tplData", $arr_tplData);

        $this->obj_smarty->display($str_tpl);
    }
}
