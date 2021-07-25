<?php

/**
 * Aip Base 基类
 */
class AipBase
{
    /**
     * 单实例
     * @var AipBase
     */
    private static $instance;

    /**
     * 获取access token url
     * @var string
     */
    protected $accessTokenUrl = 'https://aip.baidubce.com/oauth/2.0/token';

    /**
     * 反馈接口
     * @var string
     */
    protected $reportUrl = 'https://aip.baidubce.com/rpc/2.0/feedback/v1/report';

    /**
     * appId
     * @var string
     */
    protected $appId = '';

    /**
     * apiKey
     * @var string
     */
    protected $apiKey = '';

    /**
     * secretKey
     * @var string
     */
    protected $secretKey = '';

    /**
     * 权限
     * @var array
     */
    protected $scope = 'brain_all_scope';

    // 添加
    protected $isCloudUser = null;
    protected $version = '2_2_20';
    protected $textCensorUserDefinedUrl = 'https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined';

    /**
     * @param string $appId
     * @param string $apiKey
     * @param string $secretKey
     */
    public function __construct($appId, $apiKey, $secretKey)
    {
        $this->appId = trim($appId);
        $this->apiKey = trim($apiKey);
        $this->secretKey = trim($secretKey);
        // 去掉三行
    }

    /**
     * 获取单实例
     * @return AipBase
     * @throws Typecho_Exception
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            $option = Typecho_Widget::widget('Widget_Options')->plugin("Discuss");
            self::$instance = new AipBase(
                $option->appId,
                $option->apiKey,
                $option->secretKey
            );
        }
        return self::$instance;
    }

    /**
     * 检查内容是否规范
     * @param $text
     * @return mixed
     * @throws Typecho_Exception
     */
    public function startCensor($text)
    {
        $data = array();
        $data['text'] = $text;
        return $this->request($this->textCensorUserDefinedUrl, $data);
    }

//    移除
//    public function getVersion()
//    public function setConnectionTimeoutInMillis($ms)
//    public function setSocketTimeoutInMillis($ms)
//    public function setProxies($proxies)
//    public function setProxies($proxies)
//    protected function multi_request($url, $data)
//    public function report($feedback)
//    public function post($url, $data, $headers = array())
//    private function getAuthHeaders($method, $url, $params = array(), $headers = array())
//    private function getAuthFilePath()
//    protected function validate($url, &$data)
//    protected function proccessRequest($url, &$params, &$data, $headers)

    /**
     * Api 请求
     * @param string $url
     * @param mixed $data
     * @param array $headers
     * @return mixed
     * @throws Typecho_Exception
     * @throws Exception
     */
    protected function request($url, $data)
    {
//        在插件里 try
//        try {
//        $result = $this->validate($url, $data);
//        if ($result !== true) {
//            return $result;
//        }

        $params = array();
        $authObj = $this->auth();

        if ($this->isCloudUser === false) {
            $params['access_token'] = $authObj['access_token'];
        }

        // 特殊处理
//        $this->proccessRequest($url, $params, $data, $headers);
        $params['aipSdk'] = 'php';
        $params['aipSdkVersion'] = $this->version;

//            干掉
//            $headers = $this->getAuthHeaders('POST', $url, $params, $headers);
//            $response = $this->client->post($url, $data, $params, $headers);
        $response = $this->curlRequest($url . "?" . http_build_query($params), $data, true);

        $obj = $this->proccessResult($response['content']);

        if (!$this->isCloudUser && isset($obj['error_code']) && $obj['error_code'] == 110) {
            $authObj = $this->auth(true);
            $params['access_token'] = $authObj['access_token'];
//                干掉
//                $response = $this->client->post($url, $data, $params, $headers);
            $response = $this->curlRequest($url . "?" . http_build_query($params), $data, true);
            $obj = $this->proccessResult($response['content']);
        }

        if (empty($obj) || !isset($obj['error_code'])) {
            $this->writeAuthObj($authObj);
        }
//        } catch (Exception $e) {
//            return array(
//                'error_code' => 'SDK108',
//                'error_msg' => 'connection or read data timeout',
//            );
//        }

        return $obj;
    }

    /**
     * 格式化结果
     * @param $content string
     * @return mixed
     */
    protected function proccessResult($content)
    {
        return json_decode($content, true);
    }

    /**
     * 写入本地文件
     * @param array $obj
     * @return void
     * @throws Typecho_Exception
     */
    private function writeAuthObj($obj)
    {
        if ($obj === null || (isset($obj['is_read']) && $obj['is_read'] === true)) {
            return;
        }

        $obj['time'] = time();
        $obj['is_cloud_user'] = $this->isCloudUser;

//        重写
//        @file_put_contents($this->getAuthFilePath(), json_encode($obj));

        $arr = unserialize(Typecho_Widget::widget('Widget_Options')->__get('plugin:Discuss'));
        $arr['authObj'] = json_encode($obj);
        Helper::configPlugin("Discuss", $arr);
    }

    /**
     * 读取本地缓存
     * @return array
     * @throws Typecho_Exception
     */
    private function readAuthObj()
    {
//        $content = @file_get_contents($this->getAuthFilePath());
//        if ($content !== false) {
        $content = Typecho_Widget::widget('Widget_Options')->plugin("Discuss")->authObj;

        $obj = json_decode($content, true);
        $this->isCloudUser = $obj['is_cloud_user'];
        $obj['is_read'] = true;
        if ($this->isCloudUser || $obj['time'] + $obj['expires_in'] - 30 > time()) {
            return $obj;
        }
//        }

        return null;
    }

    /**
     * 认证
     * @param bool $refresh 是否刷新
     * @return array
     * @throws Exception
     */
    private function auth($refresh = false)
    {

        //非过期刷新
        if (!$refresh) {
            $obj = $this->readAuthObj();
            if (!empty($obj)) {
                return $obj;
            }
        }

//        干掉
//        $response = $this->client->get($this->accessTokenUrl, array(
//            'grant_type' => 'client_credentials',
//            'client_id' => $this->apiKey,
//            'client_secret' => $this->secretKey,
//        ));

        $response = $this->curlRequest(
            $this->accessTokenUrl,
            array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->apiKey,
                'client_secret' => $this->secretKey,
            )
        );

        $obj = json_decode($response['content'], true);

        $this->isCloudUser = !$this->isPermission($obj);
        return $obj;
    }

    /**
     * 判断认证是否有权限
     * @param array $authObj
     * @return boolean
     */
    protected function isPermission($authObj)
    {
        if (empty($authObj) || !isset($authObj['scope'])) {
            return false;
        }

        $scopes = explode(' ', $authObj['scope']);
        return in_array($this->scope, $scopes);
    }

    /**
     * @param $url
     * @param string $params
     * @param bool $isPost
     * @return array
     * @throws Exception
     */
    private function curlRequest($url, $params = "", $isPost = false)
    {
        $params = is_array($params) ? http_build_query($params) : $params;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 0) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return array(
            'code' => $code,
            'content' => $response,
        );
    }

}
