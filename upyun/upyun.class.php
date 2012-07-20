<?php
/**
 * UpYun.php
 * 原文件是UPYUN官方提供的API接口，有部分注释不正确，配合项目进行了一些微调
 *
 * @link https://www.upyun.com/
 * @resource http://static.b0.upaiyun.com/upyun_api_doc.pdf
 * @resource http://static.b0.upaiyun.com/upyunapi/upyun-php-api.zip
 * @category Upyun
 * @package  Upyun
 * @author   upyun.com
 * @modifier gouki <gouki.xiao@gmail.com>
 * @version  $Id$
 * @created  12-7-6 PM11:07
 */
class UpYun {

    public $timeout = 300;
    public $debug = FALSE;

    private $bucketname;
    private $username;
    private $password;

    private $api_domain = 'v0.api.upyun.com';
    private $tmp_infos;

    private $content_md5 = NULL;
    private $file_secret = NULL;
    private $auto_mkdir = FALSE;

    /**
     * 初始化 UpYun 存储接口
     *
     * @param $bucketname 空间名称
     * @param $username   操作员名称
     * @param $password   密码
     *
     */
    public function __construct($bucketname, $username, $password)
    {
        $this->bucketname = $bucketname;
        $this->username = $username;
        $this->password = md5($password);
    }
    /**
     * 获取Upyun的API版本
     * @return string
     */
    public function version()
    {
        return '1.0.1';
    }
    /**
     * 切换 API 接口的域名
     *
     * @param $domain {默认 v0.api.upyun.com 自动识别, v1.api.upyun.com 电信, v2.api.upyun.com 联通, v3.api.upyun.com 移动}
     *
     * @return null;
     */
    public function setApiDomain($domain)
    {
        $this->api_domain = $domain;
    }
    /**
     * 设置连接超时时间
     * @param $time 秒
     *
     * @return null;
     */
    public function setTimeout($time)
    {
        $this->timeout = (int) $time;
    }
    /**
     * 设置待上传文件的 Content-MD5 值（如又拍云服务端收到的文件MD5值与用户设置的不一致，将回报 406 Not Acceptable 错误）
     *
     * @param $str （文件 MD5 校验码）
     *
     * @return null;
     */
    public function setContentMD5($str)
    {
        $this->content_md5 = $str;
    }
    /**
     *
     * 根据url/时间以及长度生成连接签名字符串
     *
     * @param $method 请求方式 {GET, POST, PUT, DELETE}
     * @param $uri
     * @param $date
     * @param $length
     *
     * @return string 签名字符串
     */
    private function sign($method, $uri, $date, $length)
    {
        $sign = "{$method}&{$uri}&{$date}&{$length}&{$this->password}";
        return 'UpYun ' . $this->username . ':' . md5($sign);
    }
    /**
     * 连接处理逻辑
     * @param $method      请求方式 {GET, POST, PUT, DELETE}
     * @param $uri         请求地址
     * @param $datas       如果是 POST 上传文件，传递文件内容 或 文件IO数据流
     * @param $output_file 如果是 GET 下载文件，可传递文件IO数据流
     *
     * @return string or NULL 请求返回字符串，失败返回 null （打开 debug 状态下遇到错误将中止程序执行）
     * @throws Exception
     */
    private function HttpAction($method, $uri, $datas, $output_file = NULL)
    {
        unset($this->tmp_infos);
        $uri = "/{$this->bucketname}{$uri}";
        $process = curl_init("http://{$this->api_domain}{$uri}");
        $headers = array('Expect:');
        if ($datas == 'folder:true') {
            $headers[] = $datas;
            $datas = NULL;
        }
        $length = @strlen($datas);
        if ($method == 'PUT' || $method == 'POST') {
            if ($this->auto_mkdir == TRUE) {
                $headers[] = 'mkdir: true';
            }
            $method = 'POST';
            curl_setopt($process, CURLOPT_POST, 1);
            if ($datas) {
                $headers[] = 'Content-Type: ';
                if ($this->content_md5 != NULL) {
                    $headers[] = 'Content-MD5: ' . $this->content_md5;
                }
                $this->content_md5 = NULL;
                if ($this->file_secret != NULL) {
                    $headers[] = 'Content-Secret: ' . $this->file_secret;
                }
                $this->file_secret = NULL;
                if (is_resource($datas)) {
                    fseek($datas, 0, SEEK_END);
                    $length = ftell($datas);
                    fseek($datas, 0);
                    $headers[] = 'Content-Length: ' . $length;
                    curl_setopt($process, CURLOPT_INFILE, $datas);
                    curl_setopt($process, CURLOPT_INFILESIZE, $length);
                }
                else {
                    curl_setopt($process, CURLOPT_POSTFIELDS, $datas);
                }
            }
            else {
                curl_setopt($process, CURLOPT_POSTFIELDS, "");
            }
        }
        curl_setopt($process, CURLOPT_CUSTOMREQUEST, $method);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $headers[] = "Date: {$date}";
        $headers[] = 'Authorization: ' . $this->sign($method, $uri, $date, $length);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, 1); /// 获取 header
        curl_setopt($process, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, (ini_get('open_basedir')!='' ? 0 : 1));
        if ($method == 'HEAD') {
            curl_setopt($process, CURLOPT_NOBODY, TRUE);
        }
        if (is_resource($output_file)) {
            curl_setopt($process, CURLOPT_HEADER, 0);
            curl_setopt($process, CURLOPT_FILE, $output_file);
        }
        $r = curl_exec($process);
        $rc = curl_getinfo($process, CURLINFO_HTTP_CODE);
        $r_offset = curl_getinfo($process, CURLINFO_HEADER_SIZE);
        if ($rc != 200 && $method != 'HEAD') {
            if ($this->debug) {
                throw new Exception($r, $rc);
            }
            return NULL;
        }
        curl_close($process);
        $r_headers = explode("\n", substr($r, 0, $r_offset) . "]");
        foreach ($r_headers as $hl) {
            $hl = trim($hl);
            if (substr($hl, 0, 7) == 'x-upyun') {
                if (!isset($this->tmp_infos)) {
                    $this->tmp_infos = array();
                }
                list($k, $v) = explode(':', $hl);
                if (in_array(substr($k, 8, 5), array(
                    'width', 'heigh', 'frame'
                ))
                ) {
                    $this->tmp_infos[trim($k)] = intval($v);
                }
                else {
                    $this->tmp_infos[trim($k)] = trim($v);
                }
            }
        }
        if ($rc != 200 && $method == 'HEAD') {
            return NULL;
        }
        return substr($r, $r_offset, strlen($r));
    }
    /**
     * 获取总体空间的占用信息
     * @return int 空间占用量，失败返回 null
     */
    public function getBucketUsage()
    {
        return $this->getFolderUsage('/');
    }
    /**
     * 获取某个子目录的占用信息
     * @param $path 目标路径
     *
     * @return int 空间占用量，失败返回 null
     */
    public function getFolderUsage($path)
    {
        $r = $this->HttpAction('GET', "{$path}?usage", NULL);
        if ($r == '') {
            return NULL;
        }
        return floatval($r);
    }
    /**
     * 设置待上传文件的 访问密钥（注意：仅支持图片空！，设置密钥后，无法根据原文件URL直接访问，需带 URL 后面加上 （缩略图间隔标志符+密钥） 进行访问）
     * 如缩略图间隔标志符为 ! ，密钥为 bac，上传文件路径为 /folder/test.jpg ，那么该图片的对外访问地址为： http://空间域名/folder/test.jpg!bac
     *
     * @param $str （文件 MD5 校验码）
     *
     * @return null;
     */
    public function setFileSecret($str)
    {
        $this->file_secret = $str;
    }
    /**
     * 上传文件
     * @param $file      文件路径（包含文件名）
     * @param $datas     文件内容 或 文件IO数据流
     * @param $auto_mkdir=false 是否自动创建父级目录
     *
     * @return true or false
     */
    public function writeFile($file, $datas, $auto_mkdir = FALSE)
    {
        $this->auto_mkdir = $auto_mkdir;
        $r = $this->HttpAction('PUT', $file, $datas);
        return !is_null($r);
    }
    /**
     * 获取上传文件后的信息（仅图片空间有返回数据）
     * @param $key 信息字段名（x-upyun-width、x-upyun-height、x-upyun-frames、x-upyun-file-type）
     *
     * @return value or NULL
     */
    public function getWritedFileInfo($key)
    {
        if (!isset($this->tmp_infos)) {
            return NULL;
        }
        return $this->tmp_infos[$key];
    }
    /**
     * 读取文件
     * @param $file        文件路径（包含文件名）
     * @param $output_file 可传递文件IO数据流（默认为 null，结果返回文件内容，如设置文件数据流，将返回 true or false）
     *
     * @return string 或 null
     */
    public function readFile($file, $output_file = NULL)
    {
        return $this->HttpAction('GET', $file, NULL, $output_file);
    }
    /**
     * 获取文件信息
     * @param $file 文件路径（包含文件名）
     *
     * @return array('type'=> file | folder, 'size'=> file size, 'date'=> unix time) 或 null
     */
    public function getFileInfo($file)
    {
        $r = $this->HttpAction('HEAD', $file, NULL);
        if (is_null($r)) {
            return NULL;
        }
        return array(
            'type' => $this->tmp_infos['x-upyun-file-type'],
            'size' => @intval($this->tmp_infos['x-upyun-file-size']),
            'date' => @intval($this->tmp_infos['x-upyun-file-date'])
        );
    }
    /**
     * 读取目录列表
     * @param $path 目录路径
     *
     * @return array 数组 或 null
     */
    public function readDir($path)
    {
        $r = $this->HttpAction('GET', $path, NULL);
        if (is_null($r)) {
            return NULL;
        }
        $rs = explode("\n", $r);
        $returns = array();
        foreach ($rs as $r) {
            $r = trim($r);
            $l = new stdclass;
            @list($l->name, $l->type, $l->size, $l->time) = explode("\t", $r);
            if (!empty($l->time)) {
                $l->type = ($l->type == 'N'
                        ? 'file'
                        : 'folder');
                $l->size = intval($l->size);
                $l->time = intval($l->time);
                $returns[] = $l;
            }
        }
        return $returns;
    }
    /**
     * 删除文件
     * @param $file 文件路径（包含文件名）
     *
     * @return boolean
     */
    public function deleteFile($file)
    {
        $r = $this->HttpAction('DELETE', $file, NULL);
        return !is_null($r);
    }
    /**
     * 创建目录
     * @param $path      目录路径
     * @param $auto_mkdir=false 是否自动创建父级目录
     *
     * @return boolean
     */
    public function mkDir($path, $auto_mkdir = FALSE)
    {
        $this->auto_mkdir = $auto_mkdir;
        $r = $this->HttpAction('PUT', $path, 'folder:true');
        return !is_null($r);
    }
    /**
     * 删除目录
     * @param $dir 目录路径
     *
     * @return boolean
     */
    public function rmDir($dir)
    {
        $r = $this->HttpAction('DELETE', $dir, NULL);
        return !is_null($r);
    }
}
