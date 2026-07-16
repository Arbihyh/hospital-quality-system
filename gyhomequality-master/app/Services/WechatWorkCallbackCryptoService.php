<?php

namespace App\Services;

use App\Model\WechatWorkPushConfig;

class WechatWorkCallbackCryptoService
{
    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $aesKey;

    /**
     * @var string
     */
    protected $corpId;

    public function __construct(WechatWorkPushConfig $config = null)
    {
        $this->token = $config ? (string) $config->callback_token : '';
        $this->aesKey = $config ? (string) $config->callback_aes_key : '';
        $this->corpId = $config ? (string) $config->corp_id : '';
    }

    /**
     * 验证企业微信回调URL。
     *
     * @param string $signature
     * @param string $timestamp
     * @param string $nonce
     * @param string $echoStr
     * @return array
     */
    public function verifyUrl($signature, $timestamp, $nonce, $echoStr)
    {
        $check = $this->validateSignature($signature, $timestamp, $nonce, $echoStr);
        if (!$check['success']) {
            return $check;
        }

        return $this->decrypt($echoStr);
    }

    /**
     * 解密企业微信回调消息。
     *
     * @param string $signature
     * @param string $timestamp
     * @param string $nonce
     * @param string $body
     * @return array
     */
    public function decryptMessage($signature, $timestamp, $nonce, $body)
    {
        $encrypted = $this->getXmlValue($body, 'Encrypt');
        if ($encrypted === '') {
            return $this->result(false, '缺少企业微信回调加密内容');
        }

        $check = $this->validateSignature($signature, $timestamp, $nonce, $encrypted);
        if (!$check['success']) {
            return $check;
        }

        return $this->decrypt($encrypted);
    }

    /**
     * 将XML转换为数组。
     *
     * @param string $xml
     * @return array
     */
    public function xmlToArray($xml)
    {
        if ($xml === '') {
            return [];
        }

        $loaderChanged = false;
        if (function_exists('libxml_disable_entity_loader')) {
            $loaderChanged = libxml_disable_entity_loader(true);
        }

        $object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

        if (function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($loaderChanged);
        }

        if (!$object) {
            return [];
        }

        return json_decode(json_encode($object), true) ?: [];
    }

    /**
     * 校验企业微信回调签名。
     *
     * @param string $signature
     * @param string $timestamp
     * @param string $nonce
     * @param string $encrypted
     * @return array
     */
    protected function validateSignature($signature, $timestamp, $nonce, $encrypted)
    {
        if ($this->token === '' || $this->aesKey === '') {
            return $this->result(false, '企业微信回调Token或EncodingAESKey未配置');
        }

        $items = [$this->token, $timestamp, $nonce, $encrypted];
        sort($items, SORT_STRING);
        $localSignature = sha1(implode('', $items));

        if (!hash_equals($localSignature, (string) $signature)) {
            return $this->result(false, '企业微信回调签名校验失败');
        }

        return $this->result(true, '签名校验成功');
    }

    /**
     * 解密企业微信加密报文。
     *
     * @param string $encrypted
     * @return array
     */
    protected function decrypt($encrypted)
    {
        $key = base64_decode($this->aesKey . '=', true);
        if ($key === false || strlen($key) !== 32) {
            return $this->result(false, '企业微信EncodingAESKey格式不正确');
        }

        $cipherText = base64_decode($encrypted, true);
        if ($cipherText === false) {
            return $this->result(false, '企业微信回调密文格式不正确');
        }

        $plainText = openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            substr($key, 0, 16)
        );

        if ($plainText === false) {
            return $this->result(false, '企业微信回调解密失败');
        }

        $plainText = $this->removePadding($plainText);
        if (strlen($plainText) < 20) {
            return $this->result(false, '企业微信回调明文长度异常');
        }

        $content = substr($plainText, 16);
        $lengthData = substr($content, 0, 4);
        $unpacked = unpack('Nlength', $lengthData);
        $xmlLength = intval($unpacked['length'] ?? 0);
        $xml = substr($content, 4, $xmlLength);
        $corpId = substr($content, 4 + $xmlLength);

        if ($this->corpId !== '' && $corpId !== '' && $corpId !== $this->corpId) {
            return $this->result(false, '企业微信回调CorpID不匹配');
        }

        return $this->result(true, '解密成功', [
            'xml' => $xml,
            'corp_id' => $corpId,
        ]);
    }

    /**
     * 去除PKCS7补位。
     *
     * @param string $text
     * @return string
     */
    protected function removePadding($text)
    {
        $padding = ord(substr($text, -1));
        if ($padding < 1 || $padding > 32) {
            return $text;
        }

        return substr($text, 0, -$padding);
    }

    /**
     * 获取XML字段。
     *
     * @param string $xml
     * @param string $field
     * @return string
     */
    protected function getXmlValue($xml, $field)
    {
        $data = $this->xmlToArray($xml);

        return isset($data[$field]) ? (string) $data[$field] : '';
    }

    /**
     * 返回统一结构。
     *
     * @param bool $success
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function result($success, $message, array $data = [])
    {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];
    }
}
