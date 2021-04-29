<?php
/**
 * Created by PhpStorm.
 * User: 小灰灰
 * Date: 2021-04-29
 * Time: 下午8:47:11
 * Info:
 */

namespace weixin_pay;

use think\Cache;

class ShareH5
{

    public $appId;

    public $appSecret;

    public function __construct($appId, $appSecret)
    {
        $this->appId     = $appId;
        $this->appSecret = $appSecret;
    }

    public function getSignPackage()
    {
        //获取ticket
        $jsapiTicket = $this->getJsApiTicket();

        $url       = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $timestamp = time();
        $nonceStr  = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string      = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature   = sha1($string);
        $signPackage = array(
            "appId"     => $this->appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );

        return $signPackage;
    }

    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }

        return $str;
    }

    private function getJsApiTicket()
    {
        // jsapi_ticket 应该全局存储与更新
        $data = json_decode($this->getCacheTicket());
        //判断是否过期
        if ( ! isset($data->expire_time) || $data->expire_time < time()) {
            $accessToken = $this->getAccessToken();
            $url         = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            //$res = json_decode($this->https_request($url));
            $res    = json_decode(file_get_contents($url), 1);
            $ticket = isset($res['ticket']) ? $res['ticket'] : '';
            if ($ticket) {
                $data->expire_time  = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $this->setCacheTicket(json_encode($data));
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }

        return $ticket;
    }

    private function getAccessToken()
    {
        // access_token 应该全局存储与更新
        $data = json_decode($this->getCacheToken());
        if ( ! isset($data->expire_time) || $data->expire_time < time()) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
            //$res = json_decode($this->https_request($url));
            $res          = json_decode(file_get_contents($url), 1);
            $access_token = isset($res['access_token']) ? $res['access_token'] : '';
            if ($access_token) {
                $data->expire_time  = time() + 7000;
                $data->access_token = $access_token;
                $this->setCacheToken(json_encode($data));
            }
        } else {
            $access_token = $data->access_token;
        }

        return $access_token;
    }

    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    //获取ticket缓存
    private function getCacheTicket()
    {
        return Cache::has("weixin_share_ticket") ? Cache::get("weixin_share_ticket") : "{}";  //缓存获取，也可自己放入数据库存储
    }

    //设置ticket缓存
    private function setCacheTicket($ticket)
    {
        return Cache::set("weixin_share_ticket", $ticket, 7000);  //设置缓存
    }

    //获取token缓存
    private function getCacheToken()
    {
        return Cache::has("weixin_share_token") ? Cache::get("weixin_share_token") : "{}";  //缓存获取，也可自己放入数据库存储
    }

    //设置token缓存
    private function setCacheToken($token)
    {
        return Cache::set("weixin_share_token", $token, 7000);  //设置缓存
    }

}
