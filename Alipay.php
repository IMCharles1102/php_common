<?php
use Alipay\aop\AopClient;
use Alipay\aop\request\AlipayFundTransToaccountTransferRequest;
use Alipay\aop\request\AlipayFundTransOrderQueryRequest;


/**
 * https://docs.open.alipay.com/api_28/alipay.fund.trans.toaccount.transfer
 *
 */


class Alipay
{
    private $config;

    const CALLBACK_NOTICE = 'http://www.baidu.com/';


    public function __construct()
    {
        $this->config = [
        'appId' => '##',
        'merchantPrivateKey' => '##',
            'notifyUrl' => self::CALLBACK_NOTICE,
            'returnUrl' => '',
            'charset' => 'UTF-8',
            'signType' => 'RSA2',
            'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
        'alipayPublicKey' => '##',
        'aesKey' => '##'
        ];
    }

    /**
     * @param $amt : 金额(分)
     * @return boolean | string
     */
    public function payMoney2Acct($platNo, $acct, $amt, $name)
    {
        $aop = new AopClient();
        $aop->gatewayUrl = $this->config['gatewayUrl'];
        $aop->apiVersion = '1.0';
        $aop->appId = $this->config['appId'];
        $aop->format = 'json';
        $aop->postCharset = 'UTF-8';
        $aop->signType = 'RSA2';
        $aop->rsaPrivateKey = $this->config['merchantPrivateKey'];
        $aop->alipayrsaPublicKey = $this->config['alipayPublicKey'];


        $bizContent = [
            'out_biz_no' => $platNo,
            'payee_type' => 'ALIPAY_LOGONID',
            'payee_account' => $acct,
            'amount' => number_format($amt/100, 2, '.', ''),
            'payee_real_name' => $name,
        ];
        $bizContent = json_encode($bizContent);


        $tranReq = new AlipayFundTransToaccountTransferRequest();
        $tranReq->setBizContent($bizContent);

REQ_PAY:
        $respObj = $aop->execute($tranReq);
        $respNode = str_replace('.', '_', $tranReq->getApiMethodName()) . '_response';
        $respCode = $respObj->$respNode->code;
        $respSubCode = $respObj->$respNode->sub_code;

        // 请求交易结果
        if (empty($respCode) || $respCode == '10000' || $respCode == '20000' || ($respCode == '40004' && $respSubCode == 'SYSTEM_ERROR')) {
            $aliOrderId = $respObj->$respNode->order_id;
            $orderRet = $this->queryPayRet($platNo, $aliOrderId);

            if ($orderRet == 1) {
                file_put_contents(ALI_LOG_FILE, "{$platNo} - ali pay fail\n", FILE_APPEND);
                return false;
            } elseif ($orderRet == -1) {
                goto REQ_PAY;
            }

            return $aliOrderId;
        } else {
            $respMsg = $respObj->$respNode->msg;
            $respSubMsg = $respObj->$respNode->sub_msg;
            file_put_contents(ALI_LOG_FILE, "{$platNo} - {$respMsg} : {$respSubMsg}\n", FILE_APPEND);
            return false;
        }

    }


    /**
     * @param $platNo : 本平台订单号
     * @param $aliOrder : 阿里订单号
     * @return int : -1 结果未知; 0 - 成功; 1 - 失败
     */
    public function queryPayRet($platNo, $aliOrder)
    {
        $aop = new AopClient();
        $aop->gatewayUrl = $this->config['gatewayUrl'];
        $aop->apiVersion = '1.0';
        $aop->appId = $this->config['appId'];
        $aop->format = 'json';
        $aop->postCharset = 'UTF-8';
        $aop->signType = 'RSA2';
        $aop->rsaPrivateKey = $this->config['merchantPrivateKey'];
        $aop->alipayrsaPublicKey = $this->config['alipayPublicKey'];

        $bizContent = [
            'out_biz_no' => $platNo,
            'order_id' => $aliOrder
        ];
        $bizContent = json_encode($bizContent);

        $queryRet = new AlipayFundTransOrderQueryRequest();
        $queryRet->setBizContent($bizContent);

TRY_REQ_QUERY:
        $respObj = $aop->execute($queryRet);
        $respNode = str_replace('.', '_', $queryRet->getApiMethodName()) . '_response';
        $respCode = $respObj->$respNode->code;
        $respSubCode = $respObj->$respNode->sub_code;


        if ($respCode == '10000') {
            $respStatus = $respObj->$respNode->status;

            if ($respStatus == 'SUCCESS') {
                return 0;
            } elseif ($respStatus == 'FAIL' || $respStatus == 'REFUND') {
                $errCode = $respObj->$respNode->error_code;
                $failReason = $respObj->$respNode->fail_reason;
                file_put_contents(ALI_LOG_FILE, "{$platNo} - {$aliOrder} - {$errCode} : {$failReason}\n", FILE_APPEND);
                return 1;
            }
        }

        // 需要重复发起请求
        if (empty($respCode) || $respCode == '20000' || ($respCode == '40004' && $respSubCode == 'SYSTEM_ERROR')) {
            goto TRY_REQ_QUERY;
        }

        return -1;
    }
}
