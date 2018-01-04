<?php

namespace Miaoxing\Alipay\Controller;

use Wei\Request;

class Alipay extends \Miaoxing\Plugin\BaseController
{
    protected $guestPages = [
        'alipay',
    ];

    public function __construct($options)
    {
        parent::__construct($options);

        $this->logger->info('Received payment data', [
            'content' => $this->request->getContent(),
        ]);
    }

    /**
     * 支付平台通过后台通知支付结果
     */
    public function refundNotifyAction(Request $req)
    {
        // 验证当前请求的签名,数据是否正确
        $alipayConfig = wei()->alipaySubmit->getDefaultConfig();
        $notify = wei()->alipayNotify->setAlipayConfig($alipayConfig);
        $result = $notify->verifyNotify($req->getParameterReference('post'));

        if ($result) {
            $batchNo = $req['batch_no'];
            $refundId = substr($batchNo, 11);

            $refund = wei()->refund()->curApp()->find(['id' => $refundId]);
            $order = wei()->order()->find(['id' => $refund['orderId']]);
            $refund->saveSucAndSendTplMsg($order, '支付宝退款');

            return 'success';
        } else {
            return 'fail';
        }
    }
}
