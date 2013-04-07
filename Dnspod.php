#!/usr/bin/env php
<?php
/**
 * @category ${NAME}
 * @author   gouki <gouki.xiao@gmail.com>
 * @created 13-4-7 下午9:17
 * @since
 */
error_reporting(7);
/**
 * 最早这里用的是https://dnsapi.cn/Record.Ddns接口，但有时候内网取得的IP不准。
 * 所以只能用Record.Modify
 */
define('DNSPOD_APIURL', 'https://dnsapi.cn/Record.Modify');
define('LAST_IP_TMP_FILE', '/tmp/dnspod_ip');
class Dnspod
{
    protected $username = 'xxx@xxx.com'; //登录的邮箱
    protected $password = '密码';
    protected $domainId = '对应的domainId';
    protected $updated = array(
        'subDomain对应的ID' => '域名',
    );
    public function getLastIp()
    {
        if (!file_exists(LAST_IP_TMP_FILE)) {
            touch(LAST_IP_TMP_FILE);
        }
        return file_get_contents(LAST_IP_TMP_FILE);
    }
    public function getCurrentIp()
    {
        $ip138 = 'http://iframe.ip138.com/ic.asp';
        $ip138Content = @file_get_contents($ip138);
        preg_match('/\[(.*?)\]/', $ip138Content, $output);
        if (isset($output[1])) {
            return trim($output[1]);
        }
        return '';
    }
    public function saveCurrentIp($ip)
    {
        file_put_contents(LAST_IP_TMP_FILE, trim($ip));
    }
    public function run()
    {
        $currentIp = $this->getCurrentIp();
        $lastIp = $this->getLastIp();
        if ($currentIp == $lastIp || !$currentIp) {
            echo '两次IP一样，或者本次没有取到IP';
            return;
        }
        foreach ($this->updated as $recordId => $subDomain) {
            $result = $this->saveRecord($recordId, $subDomain, $currentIp);
            echo $result;
        }
        $this->saveCurrentIp($currentIp);
    }
    protected function saveRecord($recordId, $subDomain, $ip = '')
    {
        //"format=json&login_email=$email&login_password=$password&domain_id=$domain_id&record_id=$record_id&sub_domain=$sub_domain&record_line=默认"
        $recordData = array(
            'format'         => 'json',
            'login_email'    => $this->username,
            'login_password' => $this->password,
            'domain_id'      => $this->domainId,
            'record_id'      => $recordId,
            'sub_domain'     => $subDomain,
            'record_line'    => '默认',
            /**
             * 如果使用Record.Ddns接口的话，下面这四个是不需要的
             */
            'record_type'    => 'A',
            'value'          => $ip,
            'mx'             => 1,
            'ttl'            => 10,
        );
        return $this->postData(DNSPOD_APIURL, $recordData);
    }
    protected function postData($url, $post = null)
    {
        $context = array();
        if (is_array($post)) {
            ksort($post);
            $context['http'] = array(
                'method'  => 'POST',
                'content' => http_build_query($post, '', '&'),
            );
        }
        return file_get_contents($url, false, stream_context_create($context));
    }
}

$ddns = new Dnspod();
$ddns->run();
