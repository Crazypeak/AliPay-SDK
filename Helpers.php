<?php

namespace AliPay;

use AliPay\Request\AlipayFundTransToaccountTransferRequest;
use AliPay\Request\AlipayTradeAppPayRequest;
use AliPay\Request\AlipayTradePrecreateRequest;
use AliPay\Request\AlipayTradeRefundRequest;
use AliPay\AopClient;

class Helpers
{
    //2012 mapi网关 //
    //2018 openapi网关1.0

    private $aliPay;
    private $site_url;      //当前域名
    private $pay_subject;   //当前项目主体

    /**
     * Helpers constructor.
     * @param \stdClass $aliConfig
     * @param string $aliConfig ->appID appid
     * @param string $aliConfig ->privateKey 应用私钥
     * @param string $aliConfig ->payRsaPublicKey 支付宝公钥
     * @param string $aliConfig ->AES aes_KEY
     */
    function __construct(\stdClass $aliConfig)
    {
        $this->aliPay = new AopClient();
        $this->aliPay->appId = $aliConfig->appID;             //appid
        $this->aliPay->rsaPrivateKey = $aliConfig->privateKey;        //应用私钥 <=> 应用公钥支付宝线上保存
        $this->aliPay->alipayrsaPublicKey = $aliConfig->payRsaPublicKey;   //支付宝公钥
        $this->aliPay->encryptKey = $aliConfig->AES;               //aes KEY

        $this->site_url = config('app.url');
        $this->pay_subject = config('app.name');
    }

    /**
     * App支付
     * out_trade_no、out_request_no、out_biz_no 业务订单号均可由平台后端控制
     * @param string $order_sn 平台生成支付订单号
     * @param float $order_amount 支付金额
     * @return array                支付信息串
     */
    function AppPay(string $order_sn, float $order_amount)
    {
        //支付订单参数
        $data = [
            "out_trade_no" => $order_sn,
            //商户订单号
            "total_amount" => $order_amount,
            //订单价格，单位：元￥
            "subject"      => $this->subject,
            //订单名称 可以中文
        ];

        //App支付
        $request = new AlipayTradeAppPayRequest();

        $request->setNotifyUrl($this->site_url . 'Notify');//设置对应回调接口
        $request->setBizContent(json_encode($data));

        $pay_data['pay_sign'] = $this->aliPay->sdkExecute($request);
        return $pay_data;
    }

    /**
     * PC扫码支付
     * @param string $order_sn 平台生成支付订单号
     * @param float $order_amount 支付金额
     * @return array                支付信息：二维码链接——qr_code
     * @throws \Exception
     */
    function NativePay(string $order_sn, float $order_amount)
    {
        //支付订单参数
        $data = [
            "out_trade_no" => $order_sn,
            //商户订单号
            "total_amount" => $order_amount,
            //订单价格，单位：元￥
            "subject"      => $this->subject,
            //订单名称 可以中文
        ];

        //App支付
        $request = new AlipayTradePrecreateRequest();

        $request->setNotifyUrl($this->site_url . 'Notify');//设置对应回调接口
        $request->setBizContent(json_encode($data));

        $result = @$this->aliPay->execute($request);
        if (!$result)
            return FALSE;

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        $pay_data = [
            'out_trade_no' => $result->$responseNode->out_trade_no,
            'qr_code'      => $result->$responseNode->qr_code,
        ];
        return $pay_data;
    }

    /**
     * 退款
     * @param string $order_sn 平台生成退款订单号
     * @param string $trade_no 已支付的支付流水号，支付成功后返回
     * @param float $amount 本次退款金额
     * @return integer
     */
    function Refund(string $order_sn, string $trade_no, float $amount)
    {
        $data = [
            "trade_no"   => $trade_no,         //平台后台保存的支付订单号
            //商户订单号
            'out_request_no' => $order_sn,  //本次退款业务的退款订单号
            //退款订单号
            "refund_amount"  => $amount,
            //退款金额
        ];
        $request = new AlipayTradeRefundRequest();
        $request->setBizContent(json_encode($data));
        $result = @$this->aliPay->execute($request);
        if (!$result)
            return FALSE;

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;

        //$resultCode 异步请求Ali服务时，回调的业务code，10000为成功
        return $resultCode;
    }

    /**
     * 转账
     * @param string $order_sn 平台生成转账订单号
     * @param string $user_sn 用户支付宝账号，ALIPAY_LOGONID：手机号格式
     * @param float $amount 转账金额
     * @return integer
     */
    function FundTransToaccountTransfer(string $order_sn, string $user_sn, float $amount)
    {
        $data = [
            "out_biz_no"    => $order_sn,       //本次转账业务的转账订单号
            //商户转账唯一订单号
            "payee_type"    => 'ALIPAY_LOGONID',
            //收款方账户类型
            "payee_account" => $user_sn,
            //收款方账户
            "amount"        => $amount,
            //付款金额
        ];
        $request = new AlipayFundTransToaccountTransferRequest();
        $request->setBizContent(json_encode($data));
        $result = @$this->aliPay->execute($request);
        if (!$result)
            return FALSE;

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;

        //$resultCode 异步请求Ali服务时，回调的业务code，10000为成功
        return $resultCode;
    }

    /**
     * 支付回调
     * @param mixed $callback 回调函数，接收post数据，对象方法用数组传递，具体参照call_user_func()
     * @param array $request 支付宝方请求参数，参照文档
     * @return string
     */
    function Notify($callback)
    {
        $aliPay = new AopClient();
        $aliPay->alipayrsaPublicKey = $this->ali_payRsaPublicKey;   //支付宝公钥

        $request = $_POST;
        if ($aliPay->rsaCheckV1($request, '', $request['sign_type']))
            //成功逻辑处理
            if (call_user_func($callback, $request))
                return "success"; // 告诉支付宝处理成功
        return "fail"; //验证失败
    }

    /**
     * 回调参数例子
     * {
     * "gmt_create": "1970-01-01 00:00:00",
     * "charset": "UTF-8",
     * "seller_email": "",
     * "subject": "支付订单",
     * "sign": "",
     * "invoice_amount": "0.01",
     * "notify_id": "",
     * "fund_bill_list": "[{\"amount\":\"0.01\",\"fundChannel\":\"ALIPAYACCOUNT\"}]",
     * "notify_type": "trade_status_sync",
     * "trade_status": "TRADE_SUCCESS",
     * "buyer_pay_amount": "0.01",
     * "app_id": "",
     * "sign_type": "RSA2",
     * "seller_id": "",
     * "notify_time": "1970-01-01 00:00:00",
     * "version": "1.0",
     * "total_amount": "0.01",
     * "trade_no": "",
     * "auth_app_id": "",
     * "buyer_logon_id": "",
     * "point_amount": "0.00",
     *
     * //支付时间
     * "gmt_payment": "1970-01-01 00:00:00",
     * //支付金额
     * "buyer_pay_amount": "0.01",
     * //实收金额
     * "receipt_amount": "0.01",
     * //平台提交的订单号
     * "out_trade_no": "",
     * //支付宝生成支付流水号，建议保存
     * "trade_no": "",
     * //支付人平台id
     * "buyer_id": "",
     * }
     */
}
