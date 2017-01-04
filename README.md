# ThinkPHP 5 & 微信扫码支付模式2

类库是基于微信扫码支付模式2结合ThinkPHP 5 修改部分内容，仅适配 ThinkPhP 5

个人博客主页： http://www.loveteemo.com

## 安装方法

composer安装:

``` bash
composer require loveteemo/wxnative
```

添加公共配置:
``` php
    // 微信扫码支付
    'weixinpay' =>  [
        'appid'     =>  'wx426b3015555a46be',
        'mchid'     =>  '1900009851',
        'key'       =>  '8934e7d15453e97507ef794cf7b0519d',
        'notifyurl' =>  'http://www.xx.com/index/weixinpay/notify'
    ],
```

## 示例

### 微信支付控制器:

```
<?php
/**
 * Created by PhpStorm.
 * User: 隆航
 * Date: 2016/12/11 0011
 * Time: 17:21
 */
namespace app\index\controller;
use think\Controller;
use loveteemo\wxnative\Native;


class Weixinpay extends Controller
{
    // 此控制器的回调地址需要没有权限验证

    // 远程请求支付接口的时候ajax返回微信支付的URL地址
    public function dowithpay()
    {

        // 过滤请求

        // todo 根据传递的订单号，信息查询 状态为 未支付 微信支付
        $info = "我是订单信息";

        // todo 判断订单是否存在 且支付是否超时

        // 测试支付假数据 实际项目自己替换
        $order = array(
            // 订单主体
            'body'          =>  "测试支付",
            // 订单金额 单位是分
            'total_fee'     =>  1,
            // 商品订单号
            'out_trade_no'  =>  time(),
            // 商品ID 扫码支付必须的参数!
            'product_id'    =>  1,
            // 支付方式 模式2 扫码支付
            'trade_type'    =>  'NATIVE',
            // 订单支付结束时间
            'time_expire'   =>  date("YmdHis",time()+1800)
        );

        $config_weixin = config("auth.weixinpay");

        $Native1 = new Native($config_weixin);
        $result = $Native1->unifiedOrder($order);

        // todo 把微信返回的第三方订单号存入数据库

        // 返回前端扫码支付的URL地址和订单号
        $arr['url'] = urldecode($result['code_url']);
        $arr['out_trade_no'] = $order['out_trade_no'];

        return json(['err' => 0 ,"result" => $arr ]);
    }
 }

```

以上的todo逻辑需要自己完善，建议数据库操作添加事务


异步操作
``` php
    //微信异步地址
    public function notify()
    {
        $config_weixin = config("weixinpay");
        $Native = new Native($config_weixin);
        $result=$Native->notify();
        // 异步验签通过
        if ($result) {

            // 返回 result_code 值为业务结果 return_code 为通信结果
            if($result['result_code'] == 'SUCCESS'){

                $info = "我是订单信息"; //订单号 微信支付 未支付

                if(!empty($info)){

                    // todo　订单存在 修改状态
                    return json(["err" =>0 ,"msg" => "修改订单完成"]);
                }else{

                    // todo 订单不存在 或者订单已修改
                    return json(["err" => 1,"msg" => "订单不存在 或者订单已修改"]);
                }

            }else{

                // 异步收到订单".$result['out_trade_no']."支付失败通知,错误代码：".$result['err_code'].",错误描述：".$result['err_code_des']
            }
        }else{
            // 异步验证不通过
        }
    }
```

订单查询
``` php
    // 订单状态查询
    public function orderquery()
    {

        $out_trade_no  =  $_POST['out_trade_no'];

        //检测必填参数
        if(empty($out_trade_no)) {
            return json(["err" => 1,"msg" => "查询订单号不能为空"]);
        }

        $config_weixin  = config("weixinpay");
        $Native         = new Native($config_weixin);
        $result         = $Native->orderquery($out_trade_no);

        // return_code 为通信结果
        if($result['return_code']== 'SUCCESS'){

            // result_code 值为业务结果
            if($result['result_code'] == 'SUCCESS'){

                if($result["trade_state"] == "SUCCESS" ){

                    // 支付完成
                    $info = "我是订单信息"; // 微信支付 未支付 订单号

                    if(!empty($info)){

                        // todo 支付完成 修改数据库状态
                        return json(["err" => 0,"msg" => "支付完成 修改数据库状态"]);
                    }else{

                        // todo 支付完成 数据库状态已经改了
                        return json(["err" => 0,"msg" => "支付完成 修改数据库状态"]);
                    }

                }elseif ($result['trade_state'] == "REFUND"){

                    return json(["err" => 0,"msg" => "查询完成 订单转入退款"]);
                }elseif ($result['trade_state'] == "NOTPAY"){

                    return json(["err" => 0,"msg" => "查询完成 订单未支付"]);
                }elseif ($result['trade_state'] == "CLOSED"){

                    return json(["err" => 0,"msg" => "查询完成 订单已关闭"]);
                }elseif ($result['trade_state'] == "USERPAYING"){

                    return json(["err" => 0,"msg" => "查询完成 订单正在支付中"]);
                }elseif ($result['trade_state'] == "PAYERROR"){

                    return json(["err" => 0,"msg" => "查询完成 订单支付失败"]);
                }
            }else{

                return json(["err" => 0,"msg" => "查询失败，错误代码".$result['err_code'].",错误描述：".$result['err_code_des']]);
            }
        }else {

            return json(["err" => 0,"msg" => "查询时，通信失败"]);
        }
    }
```

获取到的微信返回数据
``` html
array(10) {
  ["return_code"] => string(7) "SUCCESS"
  ["return_msg"] => string(2) "OK"
  ["appid"] => string(18) "wx426b3015555a46be"
  ["mch_id"] => string(10) "1900009851"
  ["nonce_str"] => string(16) "Z8xR7Nkayk4KtZkM"
  ["sign"] => string(32) "0A94E6CFE436AD8EE18763E129AECA2C"
  ["result_code"] => string(7) "SUCCESS"
  ["prepay_id"] => string(36) "wx2017010415123488a12f9e590574695206"
  ["trade_type"] => string(6) "NATIVE"
  ["code_url"] => string(35) "weixin://wxpay/bizpayurl?pr=ijomHdL"
}
```

返回给前段的微信支付二维码需要用JS或者PHP生成出来，手机扫码支付。同时轮询请求查询订单接口。
查询到支付完成后，数据处理，且返回支付完成提示。


