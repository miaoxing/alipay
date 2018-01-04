<?php

namespace Miaoxing\Alipay\Service;

/**
 * @SuppressWarnings(PHPMD)
 * @codingStandardsIgnoreFile
 */
class AlipayNotify extends \Miaoxing\Plugin\BaseService
{
    /**
     * HTTPS形式消息验证地址
     */
    public $httpsVerifyUrl = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
    /**
     * HTTP形式消息验证地址
     */
    public $httpVerifyUrl = 'http://notify.alipay.com/trade/notify_query.do?';
    public $alipayConfig = [];

    public function setAlipayConfig($alipayConfig)
    {
        $this->alipayConfig = $alipayConfig;

        return $this;
    }

    /**
     * 针对notify_url验证消息是否是支付宝发出的合法消息
     * @return 验证结果
     */
    public function verifyNotify($data)
    {
        if (empty($data)) {
            //判断POST来的数组是否为空
            return false;
        } else {
            $isSign = $this->getSignVeryfy($data, $data['sign']);
            $responseTxt = 'true';
            if (!empty($data['notify_id'])) {
                $responseTxt = $this->getResponse($data['notify_id']);
            }

            if (preg_match('/true$/i', $responseTxt) && $isSign) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 针对return_url验证消息是否是支付宝发出的合法消息
     * @return 验证结果
     */
    public function verifyReturn()
    {
        if (empty($_GET)) {
            //判断POST来的数组是否为空
            return false;
        } else {
            //生成签名结果
            $isSign = $this->getSignVeryfy($_GET, $_GET['sign']);
            //获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
            $responseTxt = 'true';
            if (!empty($_GET['notify_id'])) {
                $responseTxt = $this->getResponse($_GET['notify_id']);
            }

            // 写日志记录
            if ($isSign) {
                $isSignStr = 'true';
            } else {
                $isSignStr = 'false';
            }
            $log_text = 'responseTxt=' . $responseTxt . "\n return_url_log:isSign=" . $isSignStr . ',';
            $log_text = $log_text . createLinkString($_GET);
            wei()->logger->info($log_text);

            //验证
            //$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
            //isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
            if (preg_match('/true$/i', $responseTxt) && $isSign) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 获取返回时的签名验证结果
     * @param $para_temp 通知返回来的参数数组
     * @param $sign 返回的签名结果
     * @return 签名验证结果
     */
    public function getSignVeryfy($para_temp, $sign)
    {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = wei()->alipayCore->paraFilter($para_temp);

        //对待签名参数数组排序
        $para_sort = wei()->alipayCore->argSort($para_filter);

        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = wei()->alipayCore->createLinkstring($para_sort);

        $isSgin = false;
        switch (strtoupper(trim($this->alipayConfig['sign_type']))) {
            case 'MD5':
                $isSgin = wei()->alipayMD5->md5Verify($prestr, $sign, $this->alipayConfig['key']);
                break;
            default:
                $isSgin = false;
        }

        return $isSgin;
    }

    /**
     * 获取远程服务器ATN结果,验证返回URL
     * @param $notify_id 通知校验ID
     * @return 服务器ATN结果
     * 验证结果集：
     * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
     * true 返回正确信息
     * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
     */
    public function getResponse($notify_id)
    {
        $transport = strtolower(trim($this->alipayConfig['transport']));
        $partner = trim($this->alipayConfig['partner']);
        $veryfy_url = '';
        if ($transport == 'https') {
            $veryfy_url = $this->httpsVerifyUrl;
        } else {
            $veryfy_url = $this->httpVerifyUrl;
        }
        $veryfy_url = $veryfy_url . 'partner=' . $partner . '&notify_id=' . $notify_id;
        $responseTxt = wei()->alipayCore->getHttpResponseGET($veryfy_url, $this->alipayConfig['cacert']);

        return $responseTxt;
    }
}
