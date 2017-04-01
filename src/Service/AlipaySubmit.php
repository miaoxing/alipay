<?php

namespace Miaoxing\Alipay\Service;

use DOMDocument;

/**
 * @SuppressWarnings(PHPMD)
 * @codingStandardsIgnoreFile
 */
class AlipaySubmit extends \miaoxing\plugin\BaseService
{
    public $alipayConfig;
    /**
     *支付宝网关地址（新）
     */
    public $alipayGatewayNew = 'https://mapi.alipay.com/gateway.do?';

    public function setAlipayConfig($alipayConfig)
    {
        $this->alipayConfig = $alipayConfig;

        return $this;
    }

    public function getDefaultConfig()
    {
        $dir = wei()->plugin->getOneById('alipay')->getBasePath() . '/configs/';
        $publicKey = $dir . 'alipay_public_key.pem';
        $privateKey = $dir . 'rsa_private_key.pem';
        $cacert = $dir . 'cacert.pem';

        $alipay = wei()->payment()->createAlipayService();

        $alipayConfig = [];
        //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
        //合作身份者id，以2088开头的16位纯数字
        $alipayConfig['partner'] = $alipay->getPartner();

        //安全检验码，以数字和字母组成的32位字符
        //如果签名方式设置为“MD5”时，请设置该参数
        $alipayConfig['key'] = $alipay->getKey();

        //商户的私钥（后缀是.pen）文件相对路径
        //如果签名方式设置为“0001”时，请设置该参数
        $alipayConfig['private_key_path'] = $privateKey;

        //支付宝公钥（后缀是.pen）文件相对路径
        //如果签名方式设置为“0001”时，请设置该参数
        $alipayConfig['ali_public_key_path'] = $publicKey;

        //签名方式 不需修改
        $alipayConfig['sign_type'] = 'MD5';

        //字符编码格式 目前支持 gbk 或 utf-8
        $alipayConfig['input_charset'] = 'utf-8';

        //ca证书路径地址，用于curl中ssl校验
        //请保证cacert.pem文件在当前文件夹目录中
        $alipayConfig['cacert'] = $cacert;

        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        $alipayConfig['transport'] = 'http';

        return $alipayConfig;
    }

    /**
     * 生成签名结果
     * @param $paraSort 已排序要签名的数组
     * return 签名结果字符串
     */
    public function buildRequestMysign($paraSort)
    {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = wei()->alipayCore->createLinkstring($paraSort);

        $mysign = '';
        switch (strtoupper(trim($this->alipayConfig['sign_type']))) {
            case 'MD5':
                $mysign = wei()->alipayMD5->md5Sign($prestr, $this->alipayConfig['key']);
                break;
            default:
                $mysign = '';
        }

        return $mysign;
    }

    /**
     * 生成要请求给支付宝的参数数组
     * @param $paraTemp 请求前的参数数组
     * @return 要请求的参数数组
     */
    public function buildRequestPara($paraTemp)
    {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = wei()->alipayCore->paraFilter($paraTemp);

        //对待签名参数数组排序
        $para_sort = wei()->alipayCore->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildRequestMysign($para_sort);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = strtoupper(trim($this->alipayConfig['sign_type']));

        return $para_sort;
    }

    /**
     * 生成要请求给支付宝的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组字符串
     */
    public function buildRequestParaToString($para_temp)
    {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);

        //把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
        $request_data = wei()->alipayCore->createLinkstringUrlencode($para);

        return $request_data;
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @param $method 提交方式。两个值可选：post、get
     * @param $button_name 确认按钮显示文字
     * @return 提交表单HTML文本
     */
    public function buildRequestForm($para_temp, $method, $button_name)
    {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->alipayGatewayNew.'_input_charset='.trim(strtolower($this->alipayConfig['input_charset']))."' method='".$method."'>";
        while (list($key, $val) = each($para)) {
            $sHtml .= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='".$button_name."'></form>";

        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }

    /**
     * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果
     * @param $para_temp 请求参数数组
     * @return 支付宝处理结果
     */
    public function buildRequestHttp($para_temp)
    {
        $sResult = '';

        //待请求参数数组字符串
        $request_data = $this->buildRequestPara($para_temp);

        //远程获取数据
        $sResult = wei()->alipayCore->getHttpResponsePOST($this->alipayGatewayNew, $this->alipayConfig['cacert'], $request_data, trim(strtolower($this->alipayConfig['input_charset'])));

        return $sResult;
    }

    /**
     * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果，带文件上传功能
     * @param $para_temp 请求参数数组
     * @param $file_para_name 文件类型的参数名
     * @param $file_name 文件完整绝对路径
     * @return 支付宝返回处理结果
     */
    public function buildRequestHttpInFile($para_temp, $file_para_name, $file_name)
    {

        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);
        $para[$file_para_name] = '@'.$file_name;

        //远程获取数据
        $sResult = wei()->alipayCore->getHttpResponsePOST($this->alipayGatewayNew, $this->alipayConfig['cacert'], $para, trim(strtolower($this->alipayConfig['input_charset'])));

        return $sResult;
    }

    /**
     * 用于防钓鱼，调用接口query_timestamp来获取时间戳的处理函数
     * 注意：该功能PHP5环境及以上支持，因此必须服务器、本地电脑中装有支持DOMDocument、SSL的PHP配置环境。建议本地调试时使用PHP开发软件
     * return 时间戳字符串
     */
    public function query_timestamp()
    {
        $url = $this->alipayGatewayNew.'service=query_timestamp&partner='.trim(strtolower($this->alipayConfig['partner'])).'&_input_charset='.trim(strtolower($this->alipayConfig['input_charset']));
        $encrypt_key = '';

        $doc = new DOMDocument();
        $doc->load($url);
        $itemEncrypt_key = $doc->getElementsByTagName('encrypt_key');
        $encrypt_key = $itemEncrypt_key->item(0)->nodeValue;

        return $encrypt_key;
    }
}
