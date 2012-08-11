<?php

class postex extends CI_Controller {
    private $tpl;

    public function __construct() {
        parent::__construct();

        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");

        $this->load->helper('url');

        // 初始化模板类
        $this->load->library('MyQuickSkin');
        $this->tpl = $this->myquickskin;
        //require_once('application/libraries/MyQuickSkin.php');
        //$this->tpl = new MyQuickSkin();
        $this->tpl->debug = TRUE;
        $this->tpl->gValue = array(
            'title' => "武汉市公文交换站",
        );

        // 初始化数据模型
        $this->load->model('db_model', 'user');
        $this->user->init('user', 'account');
        $this->load->model('db_model', 'path');
        $this->path->init('path', 'id');
        $this->load->model('db_model', 'unit');
        $this->unit->init('unit', 'id');
        $this->load->model('db_model', 'postGroup');
        $this->postGroup->init('postGroup', 'fuid');
        $this->load->model('db_model', 'post');
        $this->post->init('post', 'id');
        $this->load->model('db_model', 'bind');
        $this->bind->init('bind', '');
        $this->load->model('db_model', 'lock');
        $this->lock->init('lock', '');

        // 初始化视图模型
        $this->load->model('db_model', 'postInfo');
        $this->postInfo->init('postInfo', 'date');
        $this->load->model('db_model', 'postGroupUnit');
        $this->postGroupUnit->init('postGroupUnit', 'fuid');

        // 初始化Session
        $this->load->library('session');

        // 检查是否登录
        if( $this->session->userdata('user') === FALSE ) {
            $seg = $this->uri->segment_array();
            if( $seg[2] != "noLogin" ) {
                return redirect('/noLogin');
            }
        }
    }

    // 未登录
    public function noLogin($logout = FALSE) {
        if( $logout ) {
            $this->session->unset_userdata('user');
            $this->tpl->output("noLogin.html");
            return;
        }
        if( $this->session->userdata('user') ) {
            redirect("/");
            return;
        }
        if( ($pw = $this->input->post('pass')) ) {
            // 登入处理
            $manager = $this->user->getById('manager');
            $poster = $this->user->getById('poster');
            $login = md5($pw);
            if( $login == $manager['password'] ) {
                $this->session->set_userdata('user', "manager");
            } else if( $login == $poster['password'] ) {
                $this->session->set_userdata('user', "poster");
            }
            redirect("/");
            return;
        }
        $this->tpl->output("noLogin.html");
    }

    // 首页
    public function index() {

        $this->tpl->output("index.html", array());
    }

    // 发文 - 选择发文线路
    public function postPath() {
        $data = array(
            'title' => "发文",
            'action' => "postUnit",
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/postPath",
                    'text' => "&nbsp;发 文&nbsp;",
                    'class' => "btn4",
                ),
                array(
                    'uri' => "#",
                    'text' => "请选择线路",
                    'class' => "btn3",
                ),
            ),
            'navRight' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
            'regionList' => $this->path->get(array('local'=>'0'), "seq", 20),
        );
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['name'] = $this->autoSize($v['name']);
        }
        foreach($data['regionList'] as $k => $v) {
            $data['regionList'][$k]['name'] = $this->autoSize($v['name']);
        }

        $this->tpl->output("selectPath.html", $data);
    }

    // 发文 - 选择发文单位
    public function postUnit($path) {
        $pInfo = $this->path->getById($path);
        if( 0 == count($pInfo) ) {
            return redirect('/postPath');
        }
        $data = array(
            'title' => "{$pInfo['name']}发文",
            'pathId' => $pInfo['id'],
            'pathLock' => $this->pathState($pInfo['id']),
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/postPath",
                    'text' => "{$pInfo['name']}发文",
                    'class' => "btn4",
                ),
                array(
                    'uri' => "#",
                    'text' => "请选择发文单位",
                    'class' => "btn3",
                ),
            ),
            'navRight' => array(
                array(
                    'id' => "postTitle",
                    'jsfunc' => "togglePostInfo()",
                    'text' => "{$pInfo['name']} 总发文 <span id='postSum' style='color:#EE0000;text-decoration:underline'>". $this->postInfo_sum("from", "path", $pInfo['id']) ."</span> 件",
                    'class' => "btn5",
                ),
                array(
                    'uri' => "/post",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
        );

        $this->tpl->output("postUnit.html", $data);
    }

    // 发文 - 填写文号及选择受文单位
    public function post($unit, $code = FALSE) {
        $uInfo = $this->unit->getById($unit);
        if( 0 == count($uInfo) ) {
            return redirect('/postPath');
        }
        $pInfo = $this->path->getById($uInfo['pid']);

        $data = array(
            'user' => $this->session->userdata('user'),
            'title' => "发文",
            'pathId' => $pInfo['id'],
            'unitId' => $uInfo['id'],
            'unitName' => $uInfo['name'],
            'code' => $code,
            'groupSum' => $this->postGroupUnit->count(array('fuid'=>$unit,'ttohide !='=>1)),
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/postPath",
                    'text' => $pInfo['name'],
                    'class' => "btn4",
                ),
                array(
                    'uri' => "/postUnit/{$pInfo['id']}",
                    'text' => $uInfo['name'],
                    'class' => "btn4",
                ),
                array(
                    'text' => <<<EOF
<div id="postDo">
    <button id="postChg1" type="button" class="btnPD" onclick="postChg(1)">
        新增发文
    </button><button id="postChg0" type="button" class="btnPDH" onclick="postChg(0)">
        删除发文
    </button>
</div>
EOF
                    ,
                ),
            ),
            'navRight' => array(
                array(
                    'manage' => 1,
                    'id' => "gmBtn",
                    'jsfunc' => "groupMod()",
                    'text' => "群发编辑：关",
                    'class' => "btn4",
                ),
                array(
                    'id' => "postTitle",
                    'jsfunc' => "togglePostInfo()",
                    'text' => "{$uInfo['name']} 总发文 <span id='postSum' style='color:#EE0000;text-decoration:underline'>". $this->postInfo_sum("from", "unit", $uInfo['id']) ."</span> 件",
                    'class' => "btn5",
                ),
                array(
                    'uri' => "/postPath/{$pInfo['id']}",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
            //'groupList' => $this->postGroup->get(),
            'pathList' => $this->path->get(array('local'=>'1'), "seq", 10),
        );
        foreach($data['pathList'] as $k => $v) {
            $data['pathList'][$k]['spname'] = mb_substr($v['name'], 0, 1);
        }

        $this->tpl->output("post.html", $data);
    }

    // 核查 - 选择收文线路
    public function checkPath() {
        $data = array(
            'title' => "核查",
            'action' => "check",
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/checkPath",
                    'text' => "&nbsp;核 查&nbsp;",
                    'class' => "btn4",
                ),
                array(
                    'uri' => "#",
                    'text' => "请选择线路",
                    'class' => "btn3",
                ),
            ),
            'navRight' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
            //'regionList' => $this->path->get(array('local'=>'0'), "seq DESC", 20),
        );
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['name'] = $this->autoSize($v['name']);
        }
        //foreach($data['regionList'] as $k => $v) {
        //    $data['regionList'][$k]['name'] = $this->autoSize($v['name']);
        //}

        $this->tpl->output("selectPath.html", $data);
    }

    // 核查 - 确认
    public function check($path) {
        $pInfo = $this->path->getById($path);
        if( 0 == count($pInfo) ) {
            return redirect('/postPath');
        }

        // 今日收文列表
        //$result = $this->postInfo_path("from", $path);
        $result = $this->postInfo_path("to", $path, FALSE, 1);
        $left = array();
        $t = array();
        foreach($result as $v) {
            if( count($t) == 0 || ($t[0]['fuid'] == $v['fuid'] && $t[0]['code'] == $v['code']) ) {
                $t[] = $v;
                continue;
            }
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $left[] = array(
                'funame' => $t[0]['funame'],
                'code' => $t[0]['code'],
                'sum' => $c,
                'tuname' => '',
                'count' => 1
            );
            $left = array_merge($left, $t);
            $t = array($v);
        }
        if( count($result) > 0 ) {
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $left[] = array(
                'funame' => $t[0]['funame'],
                'code' => $t[0]['code'],
                'sum' => $c,
                'tuname' => '',
                'count' => 1
            );
            $left = array_merge($left, $t);
            $t = array($v);
        }

        // 今日受文列表
        $result = $this->postInfo_path("to", $path);
        $right = array();
        $t = array();
        foreach($result as $v) {
            if( count($t) == 0 || $t[0]['tuid'] == $v['tuid']) {
                $t[] = $v;
                continue;
            }
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $right[] = array(
                'tuname' => $t[0]['tuname'],
                'code' => "总受文",
                'sum' => $c,
                'funame' => '',
                'count' => 1
            );
            $right = array_merge($right, $t);
            $t = array($v);
        }
        if( count($result) > 0 ) {
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $right[] = array(
                'tuname' => $t[0]['tuname'],
                'code' => "总受文",
                'sum' => $c,
                'funame' => '',
                'count' => 1
            );
            $right = array_merge($right, $t);
            $t = array($v);
        }

        $pathLock = $this->pathState($pInfo['id']);

        $data = array(
            'title' => "{$pInfo['name']}核查",
            'pathId' => $pInfo['id'],
            'pathLock' => $pathLock,
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/checkPath",
                    'text' => "{$pInfo['name']}核查",
                    'class' => "btn4",
                ),
                array(
                    'uri' => "/checkPath",
                    'text' => "发文及受文核查",
                    'class' => "btn3",
                ),
            ),
            'navRight' => array(
                array(
                    'uri' => "/pathLock/{$pInfo['id']}/". ($pathLock > 0 ? 0 : 1),
                    'text' => $pathLock > 0 ? "{$pInfo['name']} 已锁定，点此解锁" : "{$pInfo['name']} 核查完成，点此锁定",
                    'class' => $pathLock > 0 ? "btn6" : "btn5",
                ),
                array(
                    'uri' => "/",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
            'leftSum' => $this->postInfo_sum("from", "path", $pInfo['id']),
            'rightSum' => $this->postInfo_sum("to", "path", $pInfo['id']),
            'leftPostList' => $left,
            'rightPostList' => $right,
        );
        $this->tpl->output("check.html", $data);
    }

    // 锁定/解锁线路
    public function pathLock($path, $lock) {
        $this->pathState($path, $lock);
        redirect("/check/{$path}");
    }

    // 管理 - 管理首页
    public function manage() {
        $data = array(
            'user' => $this->session->userdata('user'),
            'title' => "管理",
            'navLeft' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;首 页&nbsp;",
                    'class' => "btn1",
                ),
                array(
                    'uri' => "/manage",
                    'text' => "&nbsp;管 理&nbsp;",
                    'class' => "btn4",
                ),
            ),
            'navRight' => array(
                array(
                    'uri' => "/",
                    'text' => "&nbsp;返 回&nbsp;",
                    'class' => "btn2",
                ),
            ),
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
            //'regionList' => $this->path->get(array('local'=>'0'), "seq DESC", 20),
        );

        $data['ready'] = 1;
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['lock'] = $this->pathState($v['id']);
            if( $data['localList'][$k]['lock'] == 0 ) {
                $data['ready'] = 0;
            }
        }

        $this->tpl->output("manage.html", $data);
    }

    // AJAX - 主控
    public function ajax() {
        if( ($argsNum = func_num_args()) == 0 ) {
            return;
        }
        $args = func_get_args();
        if( !method_exists($this, "ajax_{$args[0]}") ) {
            return;
        }
        $action = "return \$this->ajax_{$args[0]}(";
        for($i = 1; $i < $argsNum; $i++) {
            if($i > 1) {
                $action .= ",";
            }
            $action .= "\$args[$i]";
        }
        $action .= ");";

        //header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        //header("Cache-Control: no-cache");
        //header("Pragma: no-cache");
        echo eval($action);
    }

    // AJAX - 发文 - 线路单位列表
    public function ajax_postUnit_unitList($path, $filter = FALSE) {
        $where = "pid = '{$path}' AND `tohide` != 2";
        if( $filter ) {
            $where .= "AND (`name` LIKE '%{$filter}%' OR `namePY` LIKE '%{$filter}%')";
        }

        $data = array('unitList' => $this->unit->get($where, "seq"));
        foreach($data['unitList'] as $k => $v) {
            $data['unitList'][$k]['name'] = $this->autoSize($v['name']);
        }

        return $this->tpl->output("postUnit_unitList.html", $data, TRUE);
    }

    // AJAX - 发文 - 待受文单位列表
    public function ajax_post_unitList($unit, $code = FALSE, $path = 1, $filter = FALSE) {
        $where = "";
        if( $filter ) {
            $where = "AND (`name` LIKE '%{$filter}%' OR `namePY` LIKE '%{$filter}%')";
        }

        $qStr = <<<EOF
SELECT
    *
FROM `unit`
WHERE `pid`='$path' AND `tohide`!=1 $where
ORDER BY `seq`
EOF;
        $data = array('unitList' => $this->db->query($qStr)->result_array());

        if( $code != -1 ) {
            $data['postList'] = $this->postInfo_unit("from", $unit, $code);
            $pathList = $this->path->get(array('local'=>1));
            $pSumList = array();
            foreach($pathList as $k => $v) {
                $pSumList[$v['id']] = 0;
            }
            foreach($data['postList'] as $k => $v) {
                $pSumList[$v['tpid']] += $v['sum'];
            }
            foreach($pathList as $k => $v) {
                $data['postList'][] = array('tuid'=>"p{$v['id']}",'sum'=>$pSumList[$v['id']]);
            }
        } else if( $code == -1 ) {
            // 组编辑
            $data['postList'] = $this->postGroupUnit->get(array('fuid'=>$unit,'ttohide !=' => "1"));
            $pathList = $this->path->get();
            $pSumList = array();
            foreach($pathList as $k => $v) {
                $pSumList[$v['id']] = 0;
            }
            foreach($data['postList'] as $k => $v) {
                $pSumList[$v['tpid']] += $v['sum'];
            }
            foreach($pathList as $k => $v) {
                $data['postList'][] = array('tuid'=>"p{$v['id']}",'sum'=>$pSumList[$v['id']]);
            }
        } /*else {
            $data['postList'] = array();
            $pathList = $this->path->get();
            foreach($pathList as $k => $v) {
                $data['postList'][] = array('tuid'=>"p{$v['id']}",'sum'=>0);
            }
        }
         *
         */

        foreach($data['unitList'] as $k => $v) {
            $data['unitList'][$k]['name'] = $this->autoSize($v['name']);
        }

        return $this->tpl->output("post_unitList.html", $data, TRUE);
    }

    // AJAX - 发文 - 已受文列表
    public function ajax_post_postList($unit, $code) {
        $data = array(
            'code' => $code,
            'codeSum' => 0,
            'postList' => $this->postInfo_unit("from", $unit, $code),
            );
        $pathList = $this->path->get();
        $pSumList = array();
        foreach($pathList as $k => $v) {
            $pSumList[$v['id']] = 0;
        }
        foreach($data['postList'] as $k => $v) {
            $pSumList[$v['tpid']] += $v['sum'];
            $data['codeSum'] += $v['sum'];
        }
        foreach($pathList as $k => $v) {
            $data['postList'][] = array('tuid'=>"p{$v['id']}",'sum'=>$pSumList[$v['id']]);
        }

        return $this->tpl->output("post_postList.html", $data, TRUE);
    }

    // AJAX - 发文 - 发文处理
    public function ajax_post_do($fuid, $tuid, $code, $insert) {
        // 检查线路锁定
        $funit = $this->unit->getById($fuid);
        $tunit = $this->unit->getById($tuid);
        if( $this->pathState($funit['pid']) ) {
            $path = $this->path->getByID($funit['pid']);
            return "{$path['name']}({$path['alias']}) 线路已锁定，无法发文";
        }
        if( $this->pathState($tunit['pid']) ) {
            $path = $this->path->getByID($tunit['pid']);
            return "{$path['name']}({$path['alias']}) 线路已锁定，无法受文";
        }

        $data = array(
                'date' => date("Y-m-d", time()),
                'fuid' => $fuid,
                'tuid' => $tuid,
                'code' => $code
            );
        if( $insert ) {
            //$this->unitBind_update($fuid, $tuid);
            $this->post->set($data);
        } else {
            //$this->unitBind_update($fuid, $tuid, -1);
            $this->post->rm($data);
        }
    }

    // AJAX - 发文 - 组发处理
    public function ajax_post_doGroup($fuid, $tuid, $code, $insert) {
        if( $code == -1 ) {
            // 组发编辑
            if( $insert == 1 ) {
                $this->postGroup->set(array('fuid'=>$fuid,'tuid'=>$tuid));
            } else {
                $this->postGroup->rm(array('fuid'=>$fuid,'tuid'=>$tuid));
            }
            return;
        }

        // 检查线路锁定
        $pList = $this->path->get(array('local'=>1));
        foreach($pList as $v) {
            if( $this->pathState($v['id']) ) {
                return "{$v['name']}({$v['alias']}) 线路已锁定，无法发文";
            }
        }

        $ugList = $this->postGroup->get(array('fuid'=>$fuid));
        foreach($ugList as $v) {
            $this->ajax_post_do($fuid, $v['tuid'], $code, $insert);
        }

    }

    // AJAX - 发文总数
    public function ajax_postInfo_sum($direction, $table, $id, $date = FALSE) {
        return $this->postInfo_sum($direction, $table, $id, $date);
    }

    // AJAX - 发文列表
    public function ajax_postInfo_postList($table, $id, $date = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }

        if( strcasecmp($table, "path") == 0 ) {
            $result = $this->postInfo_path("from", $id);
        } else {
            $result = $this->postInfo_unit("from", $id);
        }

        $p = array();
        $t = array();
        foreach($result as $v) {
            if( count($t) == 0 || $t[0]['fuid'] == $v['fuid']) {
                $t[] = $v;
                continue;
            }
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $p[] = array(
                'funame' => $t[0]['funame'],
                'code' => "总发文",
                'sum' => $c,
                'tuname' => '',
                'count' => 1
            );
            $p = array_merge($p, $t);
            $t = array($v);
        }
        if( count($result) > 0 ) {
            $c = 0;
            foreach($t as $n) {
                $c += $n['sum'];
            }
            $p[] = array(
                'funame' => $t[0]['funame'],
                'code' => "总发文",
                'sum' => $c,
                'tuname' => '',
                'count' => 1
            );
            $p = array_merge($p, $t);
            $t = array($v);
        }

        $data = array(
            //'pathName' => $result[0]['fpname'],
            'today' => $date,
            'postList' => $p,
        );

        return $this->tpl->output("postInfo_postList.html", $data, TRUE);
    }

    // AJAX - 管理 - 发文单线路选择
    public function ajax_manage_printFrom() {
        $data = array(
            'printTitle' => '发文单打印预览',
            'statsDate' => date("Y-m-d",time()),
            'table' => "path",
            'direction' => "from",
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
        );
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['name'] = $this->autoSize($v['name']);
        }

        $this->tpl->output("manage_printPath.html", $data);
    }

    // AJAX - 管理 - 受文单线路选择
    public function ajax_manage_printTo() {
        $data = array(
            'printTitle' => '受文单打印预览',
            'statsDate' => date("Y-m-d",time()),
            'table' => "path",
            'direction' => "to",
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
        );
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['name'] = $this->autoSize($v['name']);
        }

        $this->tpl->output("manage_printPath.html", $data);
    }

    // AJAX - 管理 - 公文交换清单线路选择
    public function ajax_manage_printUnit() {
        switch(date("w",time())) {
            case 0: case 1: case 2: case 3: case 4:
                $postDate = date("Y-m-d",time()+86400);
                break;
            case 5:
                $postDate = date("Y-m-d",time()+86400*3);
                break;
            case 6:
                $postDate = date("Y-m-d",time()+86400*2);
                break;
        }

        $data = array(
            'printTitle' => '公文交换单打印预览',
            'statsDate' => date("Y-m-d",time()),
            'postDate' => $postDate,
            'table' => "unit",
            'direction' => "to",
            'localList' => $this->path->get(array('local'=>'1'), "seq", 10),
        );
        foreach($data['localList'] as $k => $v) {
            $data['localList'][$k]['name'] = $this->autoSize($v['name']);
        }

        $this->tpl->output("manage_printPath.html", $data);
    }

    // AJAX - 管理 - 汇总表
    public function ajax_manage_printStats() {
        $data = array(
            'statsDate' => date("Y-m-d",time()),
            );
        $this->tpl->output("manage_printStats.html", $data);
    }

    // AJAX - 管理 - 打印预览
    public function ajax_manage_printView($table, $id, $direction, $date = FALSE, $pDate = FALSE) {
        if($table == "path") {
            return $this->ajax_manage_printViewPath($id, $direction, $date);
        } else if($table == "unit") {
            return $this->ajax_manage_printViewUnit($id, $date, $pDate);
        } else if($table == "stats") {
            return $this->ajax_manage_printViewStats($date);
        }
    }

    // AJAX - 管理 - 发文单/受文单预览
    public function ajax_manage_printViewPath($path, $direction, $date = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $pInfo = $this->path->getById($path);
        $result = $this->postInfo_path($direction, $path, $date, 1);
        $upList = array();
        $t = array();
        foreach($result as $v) {
            if( !strcasecmp($direction, "from") ) {
                if( !($t['fuid'] == $v['fuid'] && $t['code'] == $v['code']) ) {
                    // 发文单位或文号不同，建新行
                    $t = $v;
                    $upList[] = array(
                        'funame' => $v['funame'],
                        'code' => $v['code'],
                        'sum' => $v['sum'],
                    );
                    continue;
                }
            } else {
                if( !($t['fuid'] == $v['fuid'] && $t['code'] == $v['code']) ) {
                    // 受文单位不同，建新行
                    $t = $v;
                    $upList[] = array(
                        'fpid' => $v['fpid'],
                        'funame' => $v['funame'],
                        'code' => $v['code'],
                        'sum' => $v['sum'],
                    );
                    continue;
                }
            }
            $upList[count($upList)-1]['sum'] += $v['sum'];
        }
        $split = strcasecmp($direction, "from") ? 24 : 26;
        $sPage = intval((count($upList)-1) / $split + 1);
        $t = array_chunk($upList, $split);
        $list = array();
        for($i = 0; $i < $sPage; $i++) {
            $list[] = array(
                'cPage' => $i + 1,
                'upList' => $t[$i],
                );
        }
        if( count($list) == 0 ) {
            $list[0] = array(
                'cPage' => 1,
                'upList' => array(),
            );
        }

        $data = array(
            'printTime' => date("Y-m-d H:i - ", time()). substr(md5(time()),0,4),
            'sPage' => $sPage,
            'name' => $pInfo['name'],
            'alias' => $pInfo['alias'],
            'manager' => $pInfo['mname'],
            'printDate' => $date,
            'postSum' => $this->postInfo_sum($direction, "path", $path, $date),
            'list' => $list,
        );

        if( !strcasecmp($direction, "from") ) {
            $this->tpl->output("manage_printViewPathFrom.html", $data);
        } else {
            $this->tpl->output("manage_printViewPathTo.html", $data);
        }
    }

    // AJAX - 管理 - 发文单/受文单预览
    public function ajax_manage_printViewUnit($path, $date = FALSE, $pDate = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $pInfo = $this->path->getById($path);
        $uList = $this->unit->get(array('pid'=>$path), "seq");
        $printList = array();
        $cp = 1;
        foreach($uList as $v) { // 单位列表
            $postList = $this->post->get(array('date'=>$date,'tuid'=>$v['id']));
            if( count($postList) == 0 ) {
                continue;
            }
            $postList = $this->postInfo_unit("to", $v['id'], NULL, $date);

            // 单位受文超过 20 条
            if( count($postList) > 20 ) {
                $usPage = intval((count($postList)-1) / 20 + 1);
                $usList = array_chunk($postList, 20);
                for($i = 0; $i < $usPage; $i++) {
                    $cpSum = 0;
                    for($j = 0; $j < count($usList[$i]); $j++) {
                        $cpSum += $usList[$i][$j]['sum'];
                    }
                    $printList[] = array(
                        'cPage' => $cp,
                        'cpSum' => $cpSum,
                        'tuname' => $v['name'],
                        'postSum' => $this->postInfo_sum("to", "unit", $v['id'], $date),
                        'postList' => $usList[$i],
                        );
                    $cp ++;
                }
            } else {
                while(count($postList) < 20) {
                    $postList[] = array('funame'=>"&nbsp;",'code'=>"&nbsp;",'sum'=>"&nbsp;");
                }
                $printList[] = array(
                    'cPage' => $cp,
                    'tuname' => $v['name'],
                    'postSum' => $this->postInfo_sum("to", "unit", $v['id'], $date),
                    'postList' => $postList,
                    );
                $cp ++;
            }
        }

        $data = array(
            'printTime' => date("Y-m-d H:i - ", time()). substr(md5(time()),0,4),
            'sPage' => count($printList),
            'manager' => $pInfo['mname'],
            'postDate' => $pDate ? $pDate : $date,
            'printList' => $printList,
        );

        $this->tpl->output("manage_printViewUnit.html", $data);
    }

    // AJAX - 管理 - 汇总表预览
    public function ajax_manage_printViewStats($date = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $fromList = $this->path->get(array('local'=>'1'), "seq");
        $toList = $fromList;

        $fromSum = 0;
        foreach($fromList as $k => $v) {
            $fromList[$k]['sum'] = $this->postInfo_sum("from", "path", $v['id'], $date);
            $fromSum += $fromList[$k]['sum'];
        }

        $toSum = 0;
        $topSum = 0;
        foreach($toList as $k => $v) {
            $toList[$k]['sum'] = $this->postInfo_sum("to", "path", $v['id'], $date);
            $toSum += $toList[$k]['sum'];

            // 计算交换清单数量
            $uList = $this->unit->get(array('pid'=>$v['id']));
            $toList[$k]['pSum'] = 0;
            foreach($uList as $uv) {
                $uvSum = $this->postInfo_sum("to", "unit", $uv['id'], $date);
                $toList[$k]['pSum'] += intval(($uvSum-1) / 20 + 1);
            }
            $topSum += $toList[$k]['pSum'];
        }

        $data = array(
            'printTime' => date("Y-m-d H:i - ", time()). substr(md5(time()),0,4),
            'date' => $date,
            'fromList' => $fromList,
            'fromSum' => $fromSum,
            'toList' => $toList,
            'toSum' => $toSum,
            'topSum' => $topSum,
        );
        $this->tpl->output("manage_printViewStats.html", $data);
    }


    // AJAX - 管理 - 线路列表及顺序
    public function ajax_manage_pathList() {
        $data = array(
            'list' => $this->path->get(array('local'=>'1'), "seq"),
        );
        foreach($data['list'] as $k => $v) {
            $data['list'][$k]['listName'] = "<a href='#' onclick='itemMod({$v['id']})'>{$v['name']} ({$v['alias']})</a>";
            if( isset($data['list'][$k-1]) ) {
                $data['list'][$k]['action'] = "<a href='#' onclick='itemMove({$v['id']},{$data['list'][$k-1]['id']})'>上移</a> ";
            } else {
                $data['list'][$k]['action'] = "上移 ";
            }
            if( isset($data['list'][$k+1]) ) {
                $data['list'][$k]['action'] .= "<a href='#' onclick='itemMove({$v['id']},{$data['list'][$k+1]['id']})'>下移</a>";
            } else {
                $data['list'][$k]['action'] .= "下移";
            }
        }
        return $this->tpl->output("manage_list.html", $data, TRUE);
    }

    // AJAX - 管理 - 线路编辑
    public function ajax_manage_pathList_modify($path = FALSE) {
        if( $path === FALSE ) {
            $data = array();
        } else {
            $data = $this->path->getById($path);
        }
        return $this->tpl->output("manage_pathList_modify.html", $data, TRUE);
    }

    // AJAX - 管理 - 线路调整
    public function ajax_manage_pathList_move($spid, $dpid) {
        $sPath = $this->path->getById($spid);
        $dPath = $this->path->getById($dpid);
        if( $sPath && $dPath) {
            $this->path->set(array('seq'=>$sPath['seq']),array('id'=>$dPath['id']));
            $this->path->set(array('seq'=>$dPath['seq']),array('id'=>$sPath['id']));
        }
    }

    // AJAX - 管理 - 线路管理操作
    public function ajax_manage_pathList_do($do) {
        if( $do == 1 ) {
            // 新增
            $this->path->set(
                    array(
                        'name' => $this->input->post('name'),
                        'alias' => $this->input->post('alias'),
                        'mname' => $this->input->post('mname'),
                        'local' => 1,
                        'seq' => 0,
                    )
                );
        } else if( $do == 2 ) {
            // 编辑
            $this->path->set(
                    array(
                        'name' => $this->input->post('name'),
                        'alias' => $this->input->post('alias'),
                        'mname' => $this->input->post('mname'),
                    ),
                    array('id'=>$this->input->post('id'))
                );
        } else if( $do == 3 ) {
            // 删除
            $this->path->rm(
                    array('id'=>$this->input->post('id'))
                );
        }
    }

    // AJAX - 管理 - 线路单位顺序
    public function ajax_manage_pathSeq() {
        $data = array(
            'list' => $this->path->get(array('local'=>'1'), "seq"),
        );
        foreach($data['list'] as $k => $v) {
            $data['list'][$k]['listName'] = "<a href='#' onclick='manageSeqLoad({$v['id']})'>{$v['name']} ({$v['alias']})</a>";
            $data['list'][$k]['action'] = "";
        }
        return $this->tpl->output("manage_list.html", $data, TRUE);
    }

    // AJAX - 管理 - 线路单位顺序列表
    public function ajax_manage_pathSeq_list($pid) {
        $data = array(
            'list' => $this->unit->get(array('pid'=>$pid), "seq"),
        );
        $path = $this->path->getById($pid);
        foreach($data['list'] as $k => $v) {
            $data['list'][$k]['listName'] = "{$v['name']} ({$path['name']})";
            if( isset($data['list'][$k-1]) ) {
                $data['list'][$k]['action'] = "<a href='#' onclick='seqMove({$v['id']},{$data['list'][$k-1]['id']})'>上移</a>";
            } else {
                $data['list'][$k]['action'] = "";
            }
            if( isset($data['list'][$k+1]) ) {
                $data['list'][$k]['action'] .= " <a href='#' onclick='seqMove({$v['id']},{$data['list'][$k+1]['id']})'>下移</a>";
            } else {
                $data['list'][$k]['action'] .= "";
            }
        }
        return $this->tpl->output("manage_subList.html", $data, TRUE);
    }

    // AJAX - 管理 - 单位调整
    public function ajax_manage_pathSeq_move($suid, $duid) {
        $this->ajax_manage_unitList_move($suid, $duid);
    }

    // AJAX - 管理 - 单位列表及顺序
    public function ajax_manage_unitList() {
        $data = array(
            'list' => $this->unit->get(NULL, "pid, seq"),
        );
        foreach($data['list'] as $k => $v) {
            $path = $this->path->getById($v['pid']);
            $data['list'][$k]['listName'] = "<a href='#' onclick='itemMod({$v['id']})'>{$path['name']} - {$v['name']} ({$v['sname']})</a>";
            $data['list'][$k]['action'] = "";
            /*
            if( isset($data['list'][$k-1]) && $data['list'][$k-1]['lock'] == 1 && $v['lock'] == 1 ) {
                $data['list'][$k]['action'] = "<a href='#' onclick='itemMove({$v['id']},{$data['list'][$k-1]['id']})'>上移</a>";
            } else {
                $data['list'][$k]['action'] = "";
            }
            if( isset($data['list'][$k+1]) && $data['list'][$k+1]['lock'] == 1 && $v['lock'] == 1 ) {
                $data['list'][$k]['action'] .= " <a href='#' onclick='itemMove({$v['id']},{$data['list'][$k+1]['id']})'>下移</a>";
            } else {
                $data['list'][$k]['action'] .= "";
            }
             *
             */
        }
        return $this->tpl->output("manage_list.html", $data, TRUE);
    }

    // AJAX - 管理 - 单位编辑
    public function ajax_manage_unitList_modify($unit = FALSE) {
        if( $unit === FALSE ) {
            $data = array('pid'=>6);
        } else {
            $data = $this->unit->getById($unit);
        }
        $data['pathList'] = $this->path->get(array('local'=>'1'), "seq");

        return $this->tpl->output("manage_unitList_modify.html", $data, TRUE);
    }

    // AJAX - 管理 - 单位调整
    public function ajax_manage_unitList_move($suid, $duid) {
        $sUnit = $this->unit->getById($suid);
        $dUnit = $this->unit->getById($duid);
        if( $sUnit && $dUnit) {
            if( $sUnit['seq'] == 0 ) {
                $sUnit['seq'] = $sUnit['id'];
            }
            if( $dUnit['seq'] == 0 ) {
                $dUnit['seq'] = $dUnit['id'];
            }
            $this->unit->set(array('seq'=>$sUnit['seq']),array('id'=>$dUnit['id']));
            $this->unit->set(array('seq'=>$dUnit['seq']),array('id'=>$sUnit['id']));
        }
    }

    // AJAX - 管理 - 单位管理操作
    public function ajax_manage_unitList_do($do) {
        if( $do == 1 ) {
            // 新增
            $id = $this->unit->set(
                    array(
                        'name' => $this->input->post('name'),
                        'sname' => $this->input->post('sname'),
                        'pid' => $this->input->post('pid'),
                        'tohide' => $this->input->post('tohide'),
                        'namePY' => "",
                        'lock' => 0,
                        'seq' => 0,
                    )
                );
            $this->unit->set(array('seq'=>$id),array('id'=>$id));
        } else if( $do == 2 ) {
            // 编辑
            $this->unit->set(
                    array(
                        'name' => $this->input->post('name'),
                        'sname' => $this->input->post('sname'),
                        'pid' => $this->input->post('pid'),
                        'tohide' => $this->input->post('tohide'),
                    ),
                    array('id'=>$this->input->post('id'))
                );
        } else if( $do == 3 ) {
            // 删除
            $this->unit->rm(
                    array('id'=>$this->input->post('id'))
                );
        }
    }

    // AJAX - 管理 - 受文单位编组
    public function ajax_manage_unitGroup() {
        $data = array(
            'list' => $this->postGroup->get(),
        );
        foreach($data['list'] as $k => $v) {
            $data['list'][$k]['listName'] = "<a href='#' onclick='manageSeqLoad({$v['id']})'>{$v['name']}</a> (". $this->postGroupUnit->count(array('gid'=>$v['id'])) ."个单位)";
            $data['list'][$k]['action'] = "";
        }
        return $this->tpl->output("manage_list.html", $data, TRUE);
    }

    // AJAX - 管理 - 受文单位编组列表
    public function ajax_manage_unitGroup_list($gid) {
        $data = array(
            'checkbox' => 1,
            'list' => $this->unit->get(FALSE, "name,namePY"),
        );
        $ugc = 1;
        foreach($data['list'] as $k => $v) {
            $data['list'][$k]['listName'] = "{$v['name']}";
            $data['list'][$k]['action'] = "";
            $r = $this->postGroupUnit->get(array('gid'=>$gid,'uid'=>$v['id']));
            if( $r[0]['uid'] ) {
                $data['list'][$k]['checked'] = 1;
            } else {
                $ugc = 0;
            }
        }
        if( $ugc ) {
            $data['checkAll'] = 1;
        }

        return $this->tpl->output("manage_subList.html", $data, TRUE);
    }

    // AJAX - 管理 - 受文单位编组 - 单位变更
    public function ajax_manage_unitGroup_chg($gid, $uid, $state) {
        if( $state ) {
            if( $uid == 0 ) {
                $r = $this->unit->get();
                foreach($r as $v) {
                    $t = $this->postGroupUnit->get(array('gid'=>$gid,'uid'=>$v['id']));
                    if( $t ) {
                        continue;
                    }
                    $this->postGroupUnit->set(array('gid'=>$gid,'uid'=>$v['id']));
                }
            } else {
                $this->postGroupUnit->set(array('gid'=>$gid,'uid'=>$uid));
            }
        } else {
            if( $uid == 0 ) {
                $this->postGroupUnit->rm(array('gid'=>$gid), 999999);
            } else {
                $this->postGroupUnit->rm(array('gid'=>$gid,'uid'=>$uid), 999999);
            }
        }
    }

    // AJAX - 管理 - 登录密码修改
    public function ajax_manage_userPass() {
        return $this->tpl->output("manage_userPass.html", FALSE, TRUE);
    }

    // AJAX - 管理 - 登录密码修改
    public function ajax_manage_userPass_do() {
        $manager = $this->user->getById('manager');
        if( $manager['password'] != md5($this->input->post('pass')) ) {
            return "管理员旧密码不符，密码修改失败";
        }
        if( $this->input->post('nmpass') ) {
            $this->user->set(
                    array('password'=>md5($this->input->post('nmpass'))),
                    array('account'=>'manager')
                    );
        }
        if( $this->input->post('nppass') ) {
            $this->user->set(
                    array('password'=>md5($this->input->post('nppass'))),
                    array('account'=>'poster')
                    );
        }
    }


    // 信息查询 - 发文总数
    private function postInfo_sum($direction, $table, $id, $date = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $where = array('date'=>$date);
        if( strcasecmp($direction, "from") == 0 ) {
            // 来源
            if( strcasecmp($table, "path") == 0 ) {
                $where['fpid'] = $id;
            } else {
                $where['fuid'] = $id;
            }
        } else {
            // 目标
            if( strcasecmp($table, "path") == 0 ) {
                $where['tpid'] = $id;
            } else {
                $where['tuid'] = $id;
            }
        }
        if( strcasecmp($table, "unit") == 0 ) {
            // 先在表核查是否有记录
            $result = $this->post->get($where);
            if( count($result) == 0 ) {
                return 0;
            }
        }

        $result = $this->postInfo->select("SUM(`sum`) AS `sum`", $where);
        return $result[0]['sum'] ? $result[0]['sum'] : 0;
    }

    // 信息查询 - 线路公文列表
    private function postInfo_path($direction, $path, $date = FALSE, $od = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $where = array('date'=>$date);
        if( strcasecmp($direction, "from") == 0 ) {
            $where['fpid'] = $path;
            $order = "`fuseq`,`code`,`tunPY`";
        } else {
            $where['tpid'] = $path;
            if( $od ) {
                $order = "`fuseq`,`fpid`,`funPY`,`code`";
            } else {
                $order = "`tuseq`,`fpid`,`funPY`,`code`";
            }
        }

        return $this->postInfo->get($where, $order);
    }

    // 信息查询 - 单位公文列表
    private function postInfo_unit($direction, $unit, $code = FALSE, $date = FALSE) {
        if( $date === FALSE ) {
            $date = date("Y-m-d", time());
        }
        $where = array('date'=>$date);
        $order = "`code`";
        if( strcasecmp($direction, "from") == 0 ) {
            $where['fuid'] = $unit;
            $order .= ",`tunPY`";
        } else {
            $where['tuid'] = $unit;
            $order .= ",`funPY`";
        }
        if( $code ) {
            $where['code'] = $code;
        }

        return $this->postInfo->get($where, $order);
    }

    // 单位关系更新
    private function unitBind_update($fuid, $tuid, $value = 1) {
        $result = $this->bind->get(array(
            'fuid' => $fuid,
            'tuid' => $tuid
        ));
        if( $result ) {
            // 存在关系记录
            if( $result[0]['lock'] == 1 ) {
                // 锁定
                return;
            }
            $seq = $result[0]['seq'] + $value;
            if( $seq > 0 ) {
                // 顺序大于0
                return $this->bind->set(
                    array('seq'=>$seq),
                    array('fuid'=>$fuid,'tuid'=>$tuid)
                );
            } else {
                return $this->bind->rm(array('fuid'=>$fuid,'tuid'=>$tuid));
            }
        } else {
            // 不存在关系记录
            return $this->bind->set(
                array(
                    'fuid' => $fuid,
                    'tuid' => $tuid,
                    'seq' => 1
                ));
        }

        return 0;
    }

    // 线路锁定处理
    private function pathState($path, $lock = FALSE) {
        if( $lock === FALSE) {
            return $this->lock->count(
                    array(
                        'date' => date("Y-m-d", time()),
                        'path' => $path,
                    ));
        } else {
            if( $lock ) {
                $this->lock->set(
                        array(
                            'date' => date("Y-m-d", time()),
                            'path' => $path,
                        ));
            } else {
                $this->lock->rm(
                        array(
                            'date' => date("Y-m-d", time()),
                            'path' => $path,
                        ));
            }
        }
    }


    /*
     * 自动调整文字大小，用于按钮
     */
    private function autoSize($text) {
        $em = "1";
        if(mb_strlen($text) > 5) {
            $em = "0.8";
        }
        /*
        switch(mb_strlen($text)) {
            case 1: case 2: case 3:
                $em = "1.6"; break;
            case 4:
                $em = "1.4"; break;
            case 5:
                $em = "1.2"; break;
        }
         *
         */
        return "<span style='font-size:{$em}em'>{$text}</span>";
    }
}



?>
