<?php

/**
 * The MIT License
 *
 * Copyright (c) 2021
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace CurrencySDK\Client;

use CurrencySDK\Common\Request;
use Psr\Log\LoggerInterface;
use CurrencySDK\Common\Exceptions\ApiConnectionException;
use CurrencySDK\Common\Exceptions\ApiException;
use CurrencySDK\Common\Exceptions\AuthorizeException;
use CurrencySDK\Common\Enum\Http;
use CurrencySDK\Common\Response;
use CurrencySDK\Helpers\RawHeadersParser;

/**
 * Class CurlClient
 * @package CurrencySDK\Client
 */
class CurlClient implements ApiClientInterface
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var int
     */
    private $timeout = 80;

    /**
     * @var int
     */
    private $connectionTimeout = 30;

    /**
     * @var bool
     */
    private $keepAlive = true;

    /**
     * @var array
     */
    private $defaultHeaders = array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    );

    /**
     * @var resource
     */
    private $curl;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function call(Request $request)
    {
        if ($this->logger !== null) {
            $message = 'Send request: ' . $request->getMethod() . ' ' . $request->getPath();
            if (count($request->getParams())) {
                $message .= ' with query params: ' . json_encode($request->getParams());
            }
            if (count($request->getBody())) {
                $message .= ' with body: ' . $request->getBody();
            }
            if (count($request->getHeaders())) {
                $message .= ' with headers: ' . json_encode($request->getHeaders());
            }
            $this->logger->info($message);
        }

        $url = $this->getUrl($request) . $request->getPath();

        if (count($request->getParams())) {
            $url = $url . '?' . http_build_query($request->getParams());
        }

        $headers = $this->prepareHeaders($request->getHeaders());

        $this->initCurl();

        $this->setCurlOption(CURLOPT_URL, $url);

        $this->setCurlOption(CURLOPT_RETURNTRANSFER, true);

        $this->setCurlOption(CURLOPT_HEADER, true);

        $this->setCurlOption(CURLOPT_BINARYTRANSFER, true);

        if (!$request->isPublic() && (!$this->apiKey || !$this->secretKey)) {
            throw new AuthorizeException('apiKey or secretKey not set');
        } else {
            $this->setCurlOption(CURLOPT_USERPWD, "{$this->apiKey}:{$this->secretKey}");
        }

        $this->setBody($request->getMethod(), $request->getBody());

        $this->setCurlOption(CURLOPT_HTTPHEADER, $headers);

        $this->setCurlOption(CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);

        $this->setCurlOption(CURLOPT_TIMEOUT, $this->timeout);

        list($httpHeaders, $httpBody, $responseInfo) = $this->sendRequest();

        if (!$this->keepAlive) {
            $this->closeCurlConnection();
        }

        if ($this->logger !== null) {
            $message = 'Response with code ' . $responseInfo['http_code'] . ' received with headers: '
                . json_encode($httpHeaders);
            if (!empty($httpBody)) {
                $message .= ' and body: ' . $httpBody;
            }
            $this->logger->info($message);
        }

        return new Response(array(
            'code' => $responseInfo['http_code'],
            'headers' => $httpHeaders,
            'body' => $httpBody
        ));
    }

    /**
     * @param $optionName
     * @param $optionValue
     * @return bool
     */
    public function setCurlOption($optionName, $optionValue)
    {
        return curl_setopt($this->curl, $optionName, $optionValue);
    }


    /**
     * @return resource
     */
    private function initCurl()
    {
        if (!$this->curl || !$this->keepAlive) {
            $this->curl = curl_init();
        }

        return $this->curl;
    }

    /**
     * Close connection
     */
    public function closeCurlConnection()
    {
        if ($this->curl !== null) {
            curl_close($this->curl);
        }
    }

    /**
     * @return array
     */
    public function sendRequest()
    {
        $response = curl_exec($this->curl);
        $httpHeaderSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $httpHeaders = RawHeadersParser::parse(substr($response, 0, $httpHeaderSize));
        $httpBody = substr($response, $httpHeaderSize);
        $responseInfo = curl_getinfo($this->curl);
        $curlError = curl_error($this->curl);
        $curlErrno = curl_errno($this->curl);
        if ($response === false) {
            $this->handleCurlError($curlError, $curlErrno);
        }

        return array($httpHeaders, $httpBody, $responseInfo);
    }

    /**
     * @param $method
     * @param $httpBody
     * @throws ApiException
     */
    public function setBody($method, $httpBody)
    {
        switch ($method) {
            case Http::POST:
                $this->setCurlOption(CURLOPT_POST, true);
                $this->setCurlOption(CURLOPT_POSTFIELDS, $httpBody);
                break;
            case Http::PUT:
                $this->setCurlOption(CURLOPT_CUSTOMREQUEST, Http::PUT);
                $this->setCurlOption(CURLOPT_POSTFIELDS, $httpBody);
                break;
            case Http::DELETE:
                $this->setCurlOption(CURLOPT_CUSTOMREQUEST, Http::DELETE);
                $this->setCurlOption(CURLOPT_POSTFIELDS, $httpBody);
                break;
            case Http::PATCH:
                $this->setCurlOption(CURLOPT_CUSTOMREQUEST, Http::PATCH);
                $this->setCurlOption(CURLOPT_POSTFIELDS, $httpBody);
                break;
            case Http::OPTIONS:
                $this->setCurlOption(CURLOPT_CUSTOMREQUEST, Http::OPTIONS);
                $this->setCurlOption(CURLOPT_POSTFIELDS, $httpBody);
                break;
            case Http::HEAD:
                $this->setCurlOption(CURLOPT_NOBODY, true);
                break;
            case Http::GET:
                break;
            default:
                throw new ApiException('Invalid method verb: ' . $method);
        }
    }

    /**
     * @param mixed $apiKey
     * @return CurlClient
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @param mixed $secretKey
     * @return CurlClient
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return mixed
     */
    public function getConnectionTimeout()
    {
        return $this->connectionTimeout;
    }

    /**
     * @param mixed $connectionTimeout
     */
    public function setConnectionTimeout($connectionTimeout)
    {
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param mixed $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @param string $error
     * @param int $errno
     * @throws ApiConnectionException
     */
    private function handleCurlError($error, $errno)
    {
        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect to Currency API. Please check your internet connection and try again.";
                break;
            case CURLE_SSL_CACERT:
            case CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verify SSL certificate.";
                break;
            default:
                $msg = "Unexpected error communicating.";
        }
        $msg .= "\n\n(Network error [errno $errno]: $error)";
        throw new ApiConnectionException($msg);
    }

    /**
     * @return mixed
     */
    private function getUrl(Request $request)
    {
        $config = $this->config;
        $url = $config['public'];
        if (!$request->isPublic()){
            $url = $request->isDemo() ?  $config['baseDemo']: $config['base'];
        }
        return $url;
    }

    /**
     * @param bool $keepAlive
     * @return CurlClient
     */
    public function setKeepAlive($keepAlive)
    {
        $this->keepAlive = $keepAlive;
        return $this;
    }

    /**
     * @param $headers
     * @return array
     */
    private function prepareHeaders($headers)
    {
        $headers = array_merge($this->defaultHeaders, $headers);
        $headers = array_map(function ($key, $value) {
            return $key . ":" . $value;
        }, array_keys($headers), $headers);

        return $headers;
    }
}