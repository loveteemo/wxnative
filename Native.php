<?php
/**
 * Created by PhpStorm.
 * User: 隆航
 * Date: 2017/1/4 0004
 * Time: 9:20
 */
namespace loveteemo\wxnative;

class Native{

    // 定义配置项
    private $config = array();

    // 设置参数
    public function __construct($config){
        if (empty($config)) {
            $this->config = config('auth.weixinpay');
        }else{
            $this->config = $config;
        }
    }

    // 统一下单
    // 传递订单参数
    // 手册地址：https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=9_1
    // body(产品描述)
    // total_fee(订单金额)
    // out_trade_no(订单号)
    // product_id(产品id)
    // trade_type(类型：JSAPI，NATIVE，APP)
    public function unifiedOrder($order){

        $weixinpay_config   = $this->config;
        $config             = array(
            // 公众账号ID
            'appid'             =>  $weixinpay_config['appid'],
            // 商家号
            'mch_id'            =>  $weixinpay_config['mchid'],
            // 随机字符串
            'nonce_str'         =>  uniqid(),
            // 终端IP
            'spbill_create_ip'  =>  $_SERVER['REMOTE_ADDR'],
            // 异步地址
            'notify_url'        =>  $weixinpay_config['notifyurl']

        );
        // 合并配置数据和订单数据
        $data               = array_merge($order,$config);
        // 生成签名
        $sign               = $this->makeSign($data);
        $data['sign']       = $sign;
        $xml                = $this->arrayToXml($data);
        $url                = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result             = $this->httpPost($xml,$url);
        if ($result['return_code'] == 'FAIL') {
            die($result['return_msg']);
        }
        return $result;
    }

    // 查询订单状态
    // 传递商家订单号 也可以传递微信流水订单号
    // 手册地址 https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=9_2
    public function orderQuery($out_trade_no)
    {
        $weixinpay_config   = $this->config;

        $data               = array(
            'appid'         => $weixinpay_config['APPID'],
            'mch_id'        => $weixinpay_config['MCHID'],
            'nonce_str'     => uniqid(),
            'out_trade_no'  => $out_trade_no
        );

        $sign               = $this->makeSign($data);
        $data['sign']       = $sign;
        $xml                = $this->arrayToXml($data);
        $url                = "https://api.mch.weixin.qq.com/pay/orderquery";
        $result             = $this->httpPost($xml,$url);
        return $result;
    }

    // 异步验签
    // 验证异步签名数据 并返回给微信
    // 手册地址 https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=9_1
    public function notify(){
        $xml        = file_get_contents('php://input', 'r');
        $data       = $this->XmltoArray($xml);
        $data_sign  = $data['sign'];
        unset($data['sign']);
        $sign       = $this->makeSign($data);
        if ($sign === $data_sign && $data['return_code']=='SUCCESS' && $data['result_code']=='SUCCESS') {
            $result = $data;
        }else{
            $result = false;
        }
        // 返回状态给微信服务器 不返回 会一直发送异步
        if ($result) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    // 关闭订单
    // 传递商家订单号 也可以传递微信流水订单号
    // 手册地址 https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=9_3
    public function orderClose($out_trade_no)
    {
        $weixinpay_config   = $this->config;

        $data               = array(
            'appid'         => $weixinpay_config['APPID'],
            'mch_id'        => $weixinpay_config['MCHID'],
            'out_trade_no'  => $out_trade_no,
            'nonce_str'     => uniqid()
        );

        $sign               = $this->makeSign($data);
        $data['sign']       = $sign;
        $xml                = $this->arrayToXml($data);
        $url                = "https://api.mch.weixin.qq.com/pay/closeorder";
        $result             = $this->httpPost($xml,$url);
        return $result;
    }

    // CRUL 请求
    function httpPost($xml, $url, $useCert = false, $second = 30)
    {
        $weixinpay_config   = $this->config;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        // todo 兼容本地 正式请改为 true
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        // todo 严格校验 兼容本地 正式请不要注释
        //curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
        //设置header
        $header[] = "Content-type: text/xml";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HEADER, false);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if($useCert == true){
            curl_setopt($ch, CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $weixinpay_config['CERT']);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $weixinpay_config['CERT_KEY']);
        }

        $data = curl_exec($ch);
        if(curl_errno($ch)){
            die(curl_error($ch));
        }
        curl_close($ch);
        $result = $this->XmltoArray($data);
        return $result;
    }

    //数组转xml
    public function arrayToXml($data){
        if(!is_array($data) || count($data) <= 0){
            throw new Exception("数组数据异常！");
        }
        $xml = "<xml>";
        foreach ($data as $key=>$val){
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    // 生成签名
    // 去空->排序->URL转码->拼接KEY->MD5加密->转换大写
    public function makeSign($data){
        $data=array_filter($data);
        ksort($data);
        $string = http_build_query($data);
        $string = urldecode($string);
        $config = $this->config;
        $string_sign = $string."&key=".$config['key'];
        $sign   = md5($string_sign);
        $result = strtoupper($sign);

        return $result;
    }

    // 将xml转为array
    public function xmlToArray($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }
}
