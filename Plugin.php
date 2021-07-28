<?php

/**
 * 评论助手 - 反垃圾
 *
 * @package Discuss
 * @author 南博工作室
 * @version 1.0.0
 * @link https://github.com/krait-team/Discuss-typecho
 */
class Discuss_Plugin implements Typecho_Plugin_Interface
{
    /**
     * @return string|void
     */
    public static function activate()
    {
         /**
        * 判断是否可用HTTP库 CURL库
        * 使用Typecho_Http_Client
        */ 
        if ( false == Typecho_Http_Client::get() ) {
         //throw new Typecho_Plugin_Exception( _t( '你的服务器并不支持curl!' ) );
        }
        Typecho_Plugin::factory('Widget_Feedback')->comment = ['Discuss_Plugin', 'feedback'];
        Typecho_Plugin::factory('Widget_Feedback')->trackback = ['Discuss_Plugin', 'feedback'];
        Typecho_Plugin::factory('Widget_XmlRpc')->pingback = ['Discuss_Plugin', 'feedback'];

        return _t('插件已经激活!');
    }

    /**
     * @return string|void
     */
    public static function deactivate()
    {
        return _t('插件已禁用成功!');
    }

    /**
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $t = new Typecho_Widget_Helper_Form_Element_Text('appId', null, null, _t('AppID'));
        $form->addInput($t->addRule('required', _t('AppID不能为空！')));

        $t = new Typecho_Widget_Helper_Form_Element_Text('apiKey', null, null, _t('API Key'));
        $form->addInput($t->addRule('required', _t('API Key不能为空！')));

        $t = new Typecho_Widget_Helper_Form_Element_Text('secretKey', null, null, _t('Secret Key'), _t("Secret Key"));
        $form->addInput($t->addRule('required', _t('Secret Key不能为空！')));

        $t = new Typecho_Widget_Helper_Form_Element_Radio(
            'checkAuthor', array(
            false => '关闭',
            true => '开启'
        ), false, _t('检查作者'), '是否检查作者的评论，开启则作者也应验证是否合法评论');        
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Radio(
            'ExpectionHandler', array(
            true => '返回评论',
            false => '忽略评论'
        ), true, _t('紧急异常'), '当评论过滤发生错误时,你可以选择的操作');       
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Text('authObj', null, null, _t('AuthObj'), _t("此处是缓存Token"));
        $t->input->setAttribute('readonly', 'readonly');
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Text('au_length_min', NULL, '2', '昵称最短字符数', '昵称允许的最短字符数。');        
        $t->input->setAttribute('class', 'mini');
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Text('au_length_max', NULL, '15', '昵称最长字符数', '昵称允许的最长字符数');  
        $t->input->setAttribute('class', 'mini');      
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Radio('opt_au_length', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('昵称字符长度操作'), "如果昵称长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置");
        $form->addInput($t);

        $t = new Typecho_Widget_Helper_Form_Element_Radio('opt_nourl_au', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('昵称网址操作'), "如果用户昵称是网址，则强行按该操作执行");
        $form->addInput($t);

        $t = new Typecho_Widget_Helper_Form_Element_Radio('opt_nocn', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('无中文评论操作'), "如果评论中不包含中文，则强行按该操作执行");
        $form->addInput($t);

        $t = new Typecho_Widget_Helper_Form_Element_Text('length_min', NULL, '3', '评论最短字符数', '允许评论的最短字符数。');
        $t->input->setAttribute('class', 'mini');
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Text('length_max', NULL, '200', '评论最长字符数', '允许评论的最长字符数');
        $t->input->setAttribute('class', 'mini');
        $form->addInput($t);
        
        $t = new Typecho_Widget_Helper_Form_Element_Radio('opt_length', array("none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"), "abandon",
            _t('评论字符长度操作'), "如果评论中长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置");
        $form->addInput($t);

    }


    /**
    configHandle 仅仅为了实现配置不变 不值得浪费 已删除    
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 过滤评论
     * @param $comment
     * @param $post
     * @return mixed
     * @throws Typecho_Exception
     * @throws Typecho_Plugin_Exception
     * @noinspection DuplicatedCode
     */
    public static function feedback($comment, $post)
    {      
        $option = Helper::options()->plugin('Discuss');  
        if (!$option->checkAuthor) {
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->hasLogin()) {
                if ($user->__get('uid') == $post->author->uid) {
                    return $comment;
                } else if ($user->pass('administrator', true)) {
                    return $comment;
                }
            }
        }

        // filter
        $comment = self::filter($option, $comment);

        // AipBase
        include_once 'AipBase.php';
        // try，以免百度AI那边突变后博客无法评论
        try {
            $client = AipBase::getInstance();
            $conclusion = $client->startCensor($comment['text']);
        } catch (Exception $e) {
            //此处欠考虑 暂时这样
            if ($option->checkAuthor) {
            return $comment;
            }
        }

        // analyze
        list($state, $comment, $error) = self::analyze($conclusion, $comment);

        if ($state) {
            return $comment;
        } else {
            throw new Typecho_Exception($error);
        }
    }

    /**
     * @param $filter_set
     * @param $comment
     * @return mixed
     * @throws Typecho_Exception
     */
    private static function filter($filter_set, $comment)
    {
        /** fork SmartSpam */
        $opt = 'none';
        $error = '';

        /** 昵称长度 */
        if ($opt == "none" && $filter_set->opt_au_length != "none") {
            if (mb_strlen($comment['author'], 'utf-8') < $filter_set->au_length_min) {
                $error = '昵称请不得少于' . $filter_set->au_length_min . '个字符';
                $opt = $able = $filter_set->opt_au_length;
            } else if (mb_strlen($comment['author'], 'utf-8') > $filter_set->au_length_max) {
                $error = '昵称请不得多于' . $filter_set->au_length_max . '个字符';
                $opt = $filter_set->opt_au_length;
            }
        }

        /** 用户昵称网址判断 */
        if ($opt == "none" && $filter_set->opt_nourl_au != "none") {
            if (preg_match(" /^((https?|ftp|news):\/\/)?([a-z]([a-z0-9\-]*[\.。])+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?[a-z0-9+_\-\.%=&]*)?)?(#[a-z][a-z0-9_]*)?$/ ", $comment['author']) > 0) {
                $error = "用户昵称不允许为网址";
                $opt = $filter_set->opt_nourl_au;
            }
        }

        /** 有无中文评论 */
        if ($opt == "none" && $filter_set->opt_nocn != "none") {
            if (preg_match("/[\x{4e00}-\x{9fa5}]/u", $comment['text']) == 0) {
                $error = "评论内容请不少于一个中文汉字";
                $opt = $filter_set->opt_nocn;
            }
        }

        /** 字符长度 */
        if ($opt == "none" && $filter_set->opt_length != "none") {
            if (mb_strlen($comment['text'], 'utf-8') < $filter_set->length_min) {
                $error = "评论内容请不得少于" . $filter_set->length_min . "个字符";
                $opt = $filter_set->opt_length;
            } else if (mb_strlen($comment['text'], 'utf-8') > $filter_set->length_max) {
                $error = "评论内容请不得多于" . $filter_set->length_max . "个字符";
                $opt = $filter_set->opt_length;
            }
        }

        /** 执行操作 */
        if ($opt == 'abandon') {
            throw new Typecho_Exception($error);
        } else if ($opt == 'spam') {
            $comment['status'] = 'spam';
        } else if ($opt == 'waiting') {
            $comment['status'] = 'waiting';
        }
        return $comment;
    }

    /**
     * @param $result
     * @param $comment
     * @return array
     */
    private static function analyze($result, $comment)
    {
        $state = true;
        $error = '';

        // 0 失败响应，1 成功，2 非法
        switch ($result['conclusionType']) {
            case 2:
                $state = false;
                $error = '不合规！你的评论' . $result['data'][0]['msg'] . ', 已被阻断"';
                break;
            case 3:
            case 4:
                $comment['status'] = 'waiting';
        }

        return array(
            $state, $comment, $error
        );
    }
}
