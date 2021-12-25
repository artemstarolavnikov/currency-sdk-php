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

namespace CurrencySDK;

use CurrencySDK\Client\ApiClientInterface;
use CurrencySDK\Client\CurlClient;
use CurrencySDK\Collections\AssetsCollection;
use CurrencySDK\Common\Enum\ApiPath;
use CurrencySDK\Common\Enum\Http;
use CurrencySDK\Common\Enum\HttpCode;
use CurrencySDK\Common\Exceptions\ApiException;
use CurrencySDK\Common\Exceptions\BadApiRequestException;
use CurrencySDK\Common\Exceptions\JsonException;
use CurrencySDK\Common\LoggerWrapper;
use CurrencySDK\Common\Request;
use CurrencySDK\Common\Response;
use CurrencySDK\Helpers\Config\ConfigurationLoader;
use CurrencySDK\Helpers\Config\ConfigurationLoaderInterface;
use Psr\Log\LoggerInterface;

class Client
{
    /**
     * Текущая версия библиотеки
     */
    const SDK_VERSION = '1.0.0';

    /**
     * @var null|Client\ApiClientInterface
     */
    protected $apiClient;

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
     * @var bool
     */
    private $demo = false;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param ApiClientInterface|null $apiClient
     * @param ConfigurationLoaderInterface|null $configLoader
     */
    public function __construct(ApiClientInterface $apiClient = null, ConfigurationLoaderInterface $configLoader = null)
    {
        if ($apiClient === null) {
            $apiClient = new CurlClient();
        }

        if ($configLoader === null) {
            $configLoader = new ConfigurationLoader();
            $config       = $configLoader->load()->getConfig();
            $this->setConfig($config);
            $apiClient->setConfig($config);
        }

        $this->apiClient = $apiClient;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @param $demo
     * @return $this
     */
    public function setDemo($demo)
    {
        $this->demo = !!$demo;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDemo()
    {
        return !!$this->demo;
    }

    /**
     * @param $apiKey
     * @param $secretKey
     *
     * @return Client $this
     */
    public function setAuth($apiKey, $secretKey)
    {
        $this->apiKey    = $apiKey;
        $this->secretKey = $secretKey;

        $this->getApiClient()
            ->setApiKey($this->apiKey)
            ->setSecretKey($this->secretKey);

        return $this;
    }

    /**
     * @return ApiClientInterface
     */
    public function getApiClient()
    {
        return $this->apiClient;
    }

    /**
     * @param ApiClientInterface $apiClient
     *
     * @return Client
     */
    public function setApiClient(ApiClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->apiClient->setConfig($this->config);
        $this->apiClient->setLogger($this->logger);

        return $this;
    }

    /**
     * @param null|callable|object|LoggerInterface $value
     */
    public function setLogger($value)
    {
        if ($value === null || $value instanceof LoggerInterface) {
            $this->logger = $value;
        } else {
            $this->logger = new LoggerWrapper($value);
        }
        if ($this->apiClient !== null) {
            $this->apiClient->setLogger($this->logger);
        }
    }

    /**
     * @return AssetsCollection|null
     * @throws ApiException
     * @throws BadApiRequestException
     * @throws Common\Exceptions\AuthorizeException
     */
    public function getAssets()
    {
        $request = new Request(ApiPath::ASSETS, Http::GET);
        $request->setPublic(true)
                ->setDemo($this->isDemo());
        $response = $this->getApiClient()->call($request);

        $result = null;
        if ($response->getCode() == HttpCode::STATUS_200) {
            $responseArray = $this->decodeData($response);
            $result = new AssetsCollection($responseArray);
        } else {
            $this->handleError($response);
        }

        return $result;
    }

    public function getOHLC($filter = [])
    {
        if (!is_array($filter)) {
            $filter = array();
        }

        $request = new Request(ApiPath::OHLC, Http::GET, $filter);
        $request->setPublic(true)
            ->setDemo($this->isDemo());
        $response = $this->getApiClient()->call($request);

        $result = null;
        if ($response->getCode() == HttpCode::STATUS_200) {
            $responseArray = $this->decodeData($response);
            return $responseArray;
        } else {
            $this->handleError($response);
        }

        return $result;
    }

    /**
     * @param Response $response
     * @return mixed
     */
    private function decodeData(Response $response)
    {
        $resultArray = json_decode($response->getBody());
        if ($resultArray === null) {
            throw new JsonException('Failed to decode response', json_last_error());
        }

        return $resultArray;
    }

    /**
     * @param Response $response
     * @throws ApiException
     * @throws BadApiRequestException
     */
    private function handleError(Response $response)
    {
        if (in_array($response->getCode(), HttpCode::getValidValues())){
            throw new BadApiRequestException($response->getCode(), $response->getHeaders(), $response->getBody());
        }

        throw new ApiException(
            'Unexpected response error code',
            $response->getCode(),
            $response->getHeaders(),
            $response->getBody()
        );
    }
}