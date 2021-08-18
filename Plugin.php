<?php

/**
 * 评论助手 - 百度内容审核
 *
 * @package Discuss
 * @author 南博工作室
 * @version 1.0.1
 * @link https://github.com/krait-team/Discuss-typecho
 */
class Discuss_Plugin implements Typecho_Plugin_Interface
{
    /**
     * @return string|void
     */
    public static function activate()
    {
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
        $apiKey = new Typecho_Widget_Helper_Form_Element_Text(
            'apiKey', null, null,
            'API Key', '百度内容审核的应用API Key');
        $form->addInput($apiKey->addRule('required', _t('API Key不能为空')));

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text(
            'secretKey', null, null,
            'Secret Key', '百度内容审核的应用Secret Key');
        $form->addInput($secretKey->addRule('required', _t('Secret Key不能为空')));

        $checkAuthor = new Typecho_Widget_Helper_Form_Element_Radio(
            'checkAuthor', array(
            '0' => '关闭',
            '1' => '开启'
        ), '1', _t('检查作者'), '是否检查作者的评论，开启则作者也应验证是否合法评论');
        $form->addInput($checkAuthor);

        $token = new Typecho_Widget_Helper_Form_Element_Text(
            'token', null, '[]',
            'Token', '此处是缓存Token，即使你修改此处，也不会保存，是插件自己更新');
        $form->addInput($token);

        $name_len_min = new Typecho_Widget_Helper_Form_Element_Text(
            'name_len_min', NULL, '2',
            '昵称最短字符数', '昵称允许的最短字符数。');
        $name_len_min->input->setAttribute('class', 'mini');
        $form->addInput($name_len_min);

        $name_len_max = new Typecho_Widget_Helper_Form_Element_Text(
            'name_len_max', NULL, '15',
            '昵称最长字符数', '昵称允许的最长字符数');
        $name_len_max->input->setAttribute('class', 'mini');
        $form->addInput($name_len_max);

        $act_name_len = new Typecho_Widget_Helper_Form_Element_Radio(
            'act_name_len', array(
            '0' => '无动作',
            'waiting' => '标记为待审核',
            'spam' => '标记为垃圾',
            'abandon' => '评论失败'
        ), 'abandon', _t('昵称字符长度操作'),
            '如果昵称长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置');
        $form->addInput($act_name_len);

        $act_name_url = new Typecho_Widget_Helper_Form_Element_Radio(
            'act_name_url', array(
            '0' => '无动作',
            'waiting' => '标记为待审核',
            'spam' => '标记为垃圾',
            'abandon' => '评论失败'
        ), 'abandon', _t('昵称网址操作'),
            '如果用户昵称是网址，则强行按该操作执行');
        $form->addInput($act_name_url);

        $act_text_cn = new Typecho_Widget_Helper_Form_Element_Radio(
            'act_text_cn', array(
            '0' => '无动作',
            'waiting' => '标记为待审核',
            'spam' => '标记为垃圾',
            'abandon' => '评论失败'
        ), 'abandon', _t('无中文评论操作'),
            '如果评论中不包含中文，则强行按该操作执行');
        $form->addInput($act_text_cn);

        $text_len_min = new Typecho_Widget_Helper_Form_Element_Text(
            'text_len_min', NULL, '3',
            '评论最短字符数', '允许评论的最短字符数。');
        $text_len_min->input->setAttribute('class', 'mini');
        $form->addInput($text_len_min);

        $text_len_max = new Typecho_Widget_Helper_Form_Element_Text(
            'text_len_max', NULL, '200',
            '评论最长字符数', '允许评论的最长字符数');
        $text_len_max->input->setAttribute('class', 'mini');
        $form->addInput($text_len_max);

        $act_text_len = new Typecho_Widget_Helper_Form_Element_Radio(
            'act_text_len', array(
            '0' => '无动作',
            'waiting' => '标记为待审核',
            'spam' => '标记为垃圾',
            'abandon' => '评论失败'
        ), 'abandon', _t('评论字符长度操作'),
            '如果评论中长度不符合条件，则强行按该操作执行。如果选择[无动作]，将忽略下面长度的设置');
        $form->addInput($act_text_len);
    }


    /**
     * @param $config
     * @param $isInit
     * @throws Typecho_Exception
     */
    public static function configHandle($config, $isInit)
    {
        if (!$isInit) {
            $before = Helper::options()->plugin('Discuss');
            $config['token'] = $before->token;
        }
        Helper::configPlugin('Discuss', $config);
    }

    /**
     * @param Typecho_Widget_Helper_Form $form
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
        // option
        $option = Helper::options()->plugin('Discuss');

        // check author
        if (empty($option->checkAuthor)) {
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->hasLogin() && ($user->__get('uid') == $post->author->uid
                    || $user->pass('administrator', true))) {
                return $comment;
            }
        }

        // filter
        $comment = self::filter(
            $option, $comment
        );

        try {
            // censor
            $conclusion = self::censor(
                $option, $comment
            );
        } catch (Exception $e) {
            return $comment;
        }

        // result
        $result = json_decode($conclusion, true);
        if (empty($result)) {
            return $comment;
        }

        // 1.合规，2.不合规，3.疑似，4.审核失败
        switch ($result['conclusionType']) {
            case 1:
                break;
            case 2:
                throw new Typecho_Exception('不合规！你的评论' . $result['data'][0]['msg'] . ', 已被阻断');
            default:
                $comment['status'] = 'waiting';
        }

        return $comment;
    }

    /**
     * @param $option
     * @param $comment
     * @return string|null
     * @throws Typecho_Http_Client_Exception
     * @throws Typecho_Plugin_Exception
     */
    public static function censor($option, $comment)
    {
        // token
        $token = self::getToken($option);

        if (empty($token) || empty($comment['text'])) {
            return null;
        }

        $client = Typecho_Http_Client::get();
        if ($client === false) {
            throw new Typecho_Plugin_Exception('无法启用 Http_Client');
        }
        $client->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT'])
            ->setTimeout(5)
            ->setData([
                'text' => $comment['text']
            ])->send("https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined?access_token={$token}");

        // code
        $code = $client->getResponseStatus();
        if ($code > 299 || $code < 200) {
            throw new Typecho_Plugin_Exception('内容审核请求失败');
        }

        return $client->getResponseBody();
    }

    /**
     * @param $option
     * @param $comment
     * @return mixed
     * @throws Typecho_Exception
     */
    private static function filter($option, $comment)
    {
        // Fork SmartSpam
        $opt = null;
        $msg = null;

        // name length
        if ($option->act_name_len) {
            $name_len = mb_strlen($comment['author'], 'utf-8');
            if ($name_len < $option->name_len_min) {
                $msg = '昵称请不得少于' . $option->name_len_min . '个字符';
                $opt = $option->act_name_len;
            } else if ($name_len > $option->name_len_max) {
                $msg = '昵称请不得多于' . $option->name_len_max . '个字符';
                $opt = $option->act_name_len;
            }
            unset($name_len);
        }

        // nickname check url
        if (empty($opt) && $option->act_name_url) {
            if (preg_match("/((https?|ftp|news):\/\/)?((?:[A-za-z0-9-]+\.)+[A-za-z]{2,})/", $comment['author'])) {
                $msg = '用户昵称不允许含有网址';
                $opt = $option->act_name_url;
            }
        }

        // check chinese
        if (empty($opt) && $option->act_text_cn) {
            if (empty(preg_match("/[\x{4e00}-\x{9fa5}]/u", $comment['text']))) {
                $msg = '评论内容请不少于一个中文汉字';
                $opt = $option->act_text_cn;
            }
        }

        // text length
        if (empty($opt) && $option->act_text_len) {
            $text_len = mb_strlen($comment['text'], 'utf-8');
            if ($text_len < $option->text_len_min) {
                $msg = '评论内容请不得少于' . $option->text_len_min . '个字符';
                $opt = $option->act_text_len;
            } else if ($text_len > $option->text_len_max) {
                $msg = '评论内容请不得多于' . $option->text_len_max . '个字符';
                $opt = $option->act_text_len;
            }
            unset($text_len);
        }

        // abandon
        if ($opt == 'abandon') {
            throw new Typecho_Exception($msg);
        }

        // operate
        if (in_array($opt, ['spam', 'waiting'])) {
            $comment['status'] = $opt;
        }

        return $comment;
    }

    /**
     * @param $option
     * @return mixed
     * @throws Typecho_Http_Client_Exception
     * @throws Typecho_Plugin_Exception
     */
    public static function getToken($option)
    {
        // access
        $access = json_encode($option->token, true);

        // update
        if (empty($access) || $access['expires'] < time()) {
            $client = Typecho_Http_Client::get();
            if ($client === false) {
                throw new Typecho_Plugin_Exception('无法启用 Http_Client');
            }
            $client->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT'])
                ->setTimeout(5)
                ->setData([
                    'grant_type' => 'client_credentials',
                    'client_id' => $option->apiKey,
                    'client_secret' => $option->secretKey
                ])->send('https://aip.baidubce.com/oauth/2.0/token');

            // code
            $code = $client->getResponseStatus();
            if ($code > 299 || $code < 200) {
                throw new Typecho_Plugin_Exception('内容审核请求失败');
            }

            // response
            $response = json_decode($client->getResponseBody(), true);
            $access = array(
                'token' => $response['access_token'],
                'expires' => $response['expires_in'] + time() - 3600
            );

            // update
            $config = unserialize(Helper::options()->__get('plugin:Discuss'));
            $config['token'] = json_encode($access);
            Helper::configPlugin('Discuss', $config);
        }

        return $access['token'];
    }
}
