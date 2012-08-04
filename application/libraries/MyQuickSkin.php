<?php

require_once('QuickSkin/class.quickskin.php');

class MyQuickSkin {
    public $tpl;
    public $gValue = array();
    public $debug = FALSE;
    public $template_dir = "tpl/";
    public $cache_dir = "cache/";
    public $css_dir = "css/";
    public $img_dir = "images/";
    public $js_dir = "js/";

    public function __construct() {
        $this->tpl = new QuickSkin();

        if( $this->debug ) {
            $this->tpl->reuse_code = true;
            $this->tpl->cache_lifetime = 0;
        } else {
            $this->tpl->reuse_code = false;
            $this->tpl->cache_lifetime = 600;
        }

        $baseDir = $_SERVER['DOCUMENT_ROOT'] .'/';
        $this->tpl->template_dir = $baseDir . $this->template_dir;
        $this->tpl->temp_dir = $baseDir . $this->cache_dir;
        $this->tpl->cache_dir = $baseDir . $this->cache_dir;
        $this->tpl->extensions_dir = "qx/";
        $this->tpl->left_delimiter = "{{";
        $this->tpl->right_delimiter = "}}";
    }

    public function output($tplfile, $data = array(), $rtn = FALSE) {
        foreach($this->gValue as $k => $v) {
            $data["global_{$k}"] = $v;
        }

        $this->tpl->set_templatefile($tplfile);
        $this->tpl->assign(array_merge($data,
                array(
                    'tpl_css' => $this->css_dir,
                    'tpl_img' => $this->img_dir,
                    'tpl_js' => $this->js_dir,
                    'url_css' => $this->template_dir . $this->css_dir,
                    'url_img' => $this->template_dir . $this->img_dir,
                    'url_js' => $this->template_dir . $this->js_dir
                )
        ));
        if( $rtn ) {
            return $this->tpl->result();
        } else {
            return $this->tpl->output();
        }
    }
}

?>
