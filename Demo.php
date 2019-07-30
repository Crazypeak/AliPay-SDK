<?php

namespace app\index\controller;

use AliPay\request\AlipayFundTransToaccountTransferRequest;
use AliPay\request\AlipayTradeAppPayRequest;
use AliPay\request\AlipayTradePrecreateRequest;
use AliPay\request\AlipayTradeRefundRequest;
use AliPay\AopClient;

class Demo {

    //2012 mapi网关 //
    //2018 openapi网关1.0

    function __construct()
    {

        define('SITE_URL','http://127.0.0.1');
    }

    function index($order_sn = 'O00000000000000R0000', $order_amount = 0.01) {
        $order_sn = 'O' . date('YmdHis') . 'R' . rand(1000, 9999);
        //out_trade_no、out_request_no、out_biz_no 业务订单号均可由平台后端控制

        $aliPay                     = new AopClient();
        $aliPay->appId              = $this->ali_appID;             //appid
        $aliPay->rsaPrivateKey      = $this->ali_privateKey;        //应用私钥 <=> 应用公钥支付宝线上保存
        $aliPay->alipayrsaPublicKey = $this->ali_payRsaPublicKey;   //支付宝公钥
        $aliPay->encryptKey         = $this->ali_AES;               //aes KEY
        $aliPay->signType           = 'RSA2';

        //支付订单参数
        $data = [
            "out_trade_no" => $order_sn,
            //商户订单号
            "total_amount" => 0.10,
            //订单价格，单位：元￥
            "subject"      => '支付测试订单',
            //订单名称 可以中文
        ];

        //二维码支付
        $request = new AlipayTradePrecreateRequest();

        $request->setNotifyUrl(SITE_URL.'Notify');//设置对应回调接口
        $request->setBizContent(json_encode($data));

        $result       = $aliPay->exec($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        $result       = [
            'out_trade_no' => $result->$responseNode->out_trade_no,
            'qr_code'      => $result->$responseNode->qr_code,
        ];

//        //支付参数生成，用于app spk
//        $request = new AlipayTradeAppPayRequest();
//        $request->setBizContent(json_encode($data));
//        $request->setNotifyUrl(SITE_URL.'Notify');//设置对应回调接口
//        $result = $aliPay->sdkExecute($request);

//        //订单退款
//        $data    = [
//            "out_trade_no"   => '',         //平台后台保存的支付订单号
//            //商户订单号
//            'out_request_no' => $order_sn,  //本次退款业务的退款订单号
//            //退款订单号
//            "refund_amount"  => 0.08,
//            //退款金额
//        ];
//        $request = new AlipayTradeRefundRequest();
//        $request->setBizContent(json_encode($data));
//        $result       = $aliPay->execute($request);
//        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
//        $resultCode   = $result->$responseNode->code;

//        //转账
//        $data    = [
//            "out_biz_no"  => $order_sn,       //本次转账业务的转账订单号
//            //商户转账唯一订单号
//            "payee_type"    => 'ALIPAY_LOGONID',
//            //收款方账户类型
//            "payee_account" => '',
//            //收款方账户
//            "amount"        => 0.12,
//            //付款金额
//        ];
//        $request = new AlipayFundTransToaccountTransferRequest();
//        $request->setBizContent(json_encode($data));
//        $result       = $aliPay->execute($request);
//        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
//        $resultCode   = $result->$responseNode->code;

        //$resultCode 异步请求Ali服务时，回调的业务code，10000为成功
        $this->result($result, $resultCode == 10000 ? 1 : 0, 1, 'json');
    }

    function Notify(){
        $aliPay                     = new AopClient();
        $aliPay->alipayrsaPublicKey = $this->ali_payRsaPublicKey;   //支付宝公钥

        $request = $_POST;
        if ($aliPay->rsaCheckV1($request, '', $request['sign_type'])) {
            //成功逻辑处理

            echo "success"; // 告诉支付宝处理成功
        } else {
            echo "fail"; //验证失败
        };
    }

    //密钥配置
    private $ali_appID           = '';   //appid
    private $ali_AES             = '';   //aes密钥   支付宝设置获取
    private $ali_privateKey      = '';   //应用私钥   <=> 应用公钥支付宝线上保存
    private $ali_payRsaPublicKey = '';   //支付宝公钥
}