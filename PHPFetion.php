<?php
/**
 * fetion.php
 *
 * @category
 * @author   gouki <gouki.xiao@gmail.com>
 * @version  $Id$
 * @created 13-1-31 下午2:43
 */
define('COOKIE_SAVED_PATH', Yii::app()->getRuntimePath());
/**
 * Class PHPFetion
 */
class PHPFetion
{
    /**
     * @var
     */
    protected $mobile;
    protected $password;
    public $cookieSavedPath = '';
    protected $selfUserInfo = null;
    /**
     * @var string 无需修改
     */
    protected $fetionHostUrl = 'http://f.10086.cn/';
    /**
     * @param $mobile
     * @param $password
     */
    public function __construct($mobile, $password)
    {
        if ($mobile === '' || $password === '') {
            return false;
        }
        $this->mobile = $mobile;
        $this->password = $password;
        if (!$this->cookieSavedPath) {
            $this->cookieSavedPath = COOKIE_SAVED_PATH;
        }
        $this->autoLogin();
    }
    /**
     * 退出？？有必要吗？
     */
    public function __destruct()
    {
        $this->autoLogout();
    }
    /**
     * 取得用户自身信息
     * @return mixed
     */
    public function getUserInfo()
    {
        $uri = '/im5/user/selfInfo.action?t=' . str_replace(".", "", microtime(true));
        $result = json_decode($this->_postWithCookie($uri), true);
        if ($result) {
            $this->selfUserInfo = $result['userinfo'];
            return $result;
        }
        return $this->selfUserInfo = null;
    }
    /**
     * 触发登录
     */
    public function autoLogin()
    {
        if (!$this->getUserInfo()) {
            $this->login();
        }
    }
    /**
     * @return bool
     * @throws Exception
     */
    protected function login()
    {
        $uri = '/im5/login/loginHtml5.action?t=' . str_replace(".", "", microtime(true));
        $data = 'm=' . $this->mobile . '&pass=' . urlencode($this->password) . '&loginstatus=4';
        $result = $this->_postWithCookie($uri, $data);
        $this->selfUserInfo = json_decode($result, true);
        if ($this->selfUserInfo) {
            //主要是只在这里取idUser，所以无所谓
            return true;
        }
        throw new Exception('登录失败');
    }
    /**
     * 向指定的手机号发送飞信
     * @param string $mobile  手机号(接收者)
     * @param string $message 短信内容
     * @return string
     */
    public function send($mobile, $message)
    {
        if ($message === '') {
            return '';
        }
        // 判断是给自己发还是给好友发
        if ($mobile == $this->mobile) {
            return $this->sendToOwner($message);
        } else {
            $uid = $this->getUserFetionId($mobile);
            return $uid === '' ? '' : $this->sendToUserId($uid, $message);
        }
    }
    /**
     * 批量发送(一般情况下没必要)
     * @param $mobile
     * @param $message
     */
    public function multiSend($mobile,$message){
        $mobiles = array_unique(array_filter(explode(",",$mobile)));
        foreach($mobiles as $mobile){
            $this->send( $mobile , $message );
        }
    }
    /**
     * 获取飞信ID
     *
     * @param string $mobile 手机号
     * @return string
     */
    protected function getUserFetionId($mobile)
    {
        $uri = '/im5/index/searchFriendsByQueryKey.action';
        $data = 'queryKey=' . $mobile;
        $result = $this->_postWithCookie($uri, $data);
        $result = json_decode($result, true);
        return $result['contacts'][0]['idContact'];
    }
    /**
     * 向好友发送飞信
     * @param string $uid     飞信ID
     * @param string $message 短信内容
     * @return string
     */
    protected function sendToUserId($uid, $message)
    {
        $uri = '/im5/chat/sendMsg.action?touserid=' . $uid;
        $data = sprintf('touserid=%s&msg=%s', $uid, $message);
        $result = $this->_postWithCookie($uri, $data);
        return $result;
    }
    /**
     * 给自己发飞信
     * @param string $message
     * @return string
     */
    protected function sendToOwner($message)
    {
        $uri = '/im5/chat/sendNewGroupShortMsg.action?t=' . str_replace(".", "", microtime(1));
//        $uri = '/im5/chat/sendMsgToMyselfs.action';
        $data = sprintf('touserid=%s&msg=%s', $this->selfUserInfo['idUser'], $message);
        $result = $this->_postWithCookie($uri, $data);
        return $result;
    }
    /**
     * 如果需要，打开注释
     * @return string {"tip":"退出成功"}
     */
    protected function autoLogout()
    {
    //    $uri = '/im5/index/logoutsubmit.action';
    //    $result = $this->_postWithCookie($uri, '');
    //    return $result;
    }
    /**
     * 携带Cookie向f.10086.cn发送POST请求
     *
     * @param string $uri
     * @param string $data
     * @return mixed
     */
    protected function _postWithCookie($uri, $data = '')
    {
        return $this->postData($this->fetionHostUrl, ($this->fetionHostUrl . ltrim($uri, "/")), $data);
    }
    /**
     * @param $url
     * @param $formAction
     * @param $postVals
     * @return mixed
     */
    function postData($url, $formAction, $postVals)
    {
        $cookiefile = sprintf("%s/cookie_%s.cookie.log", $this->cookieSavedPath, parse_url($url, PHP_URL_HOST));
        $ch = curl_init();
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        $header[] = "Content-Type: application/x-www-form-urlencoded";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
//        $header[] = "Accept-Charset: utf-8,gbk,gb2312,x-gbk;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Accept-Encoding: gzip, deflate";
        $header[] = "Pragma: "; // browsers keep this blank.
        $header[] = "Host: " . parse_url($url, PHP_URL_HOST);
        curl_setopt($ch, CURLOPT_URL, $formAction); //登录地址
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //发送header ，其实这个header可以不发送
        curl_setopt($ch, CURLOPT_POST, 1); //这是POST数据
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($postVals)); //http_build_query( $postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //这个是代表curl_exec后取返回成字符串，而不是象WEB一样跳转
        curl_setopt($ch, CURLOPT_HEADER, 0); //curl返回的时候，默认都是带有header信息的，所以这里设为0，代表返回的时候不要header信息
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); //这是在用sniff抓包的时候发现用了gzip,deflate的encoding，
        curl_setopt($ch, CURLOPT_REFERER, $url); //记录来源的Referer
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:15.0) Gecko/20100101 Firefox/15.0.1');
        $res = curl_exec($ch); //我这里并没有取返回值，主要是把cookie记录下来
        curl_close($ch);
        return $res;
    }
}

?>
