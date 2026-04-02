<?php

namespace Dell\WpShieldpp;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalHttp\Curl;
use PayPalHttp\HttpException;
use PayPalHttp\HttpRequest;
use PayPalHttp\HttpResponse;
use PayPalHttp\IOException;

class ProxyPayPalHttpClient extends PayPalHttpClient
{
    private ?string $proxy = null;      // host:port
    private ?string $proxyAuth = null;  // username:password
    /**
     * The cURL proxy type to use for requests.
     *
     * Valid values:
     *  - CURLPROXY_HTTP
     *  - CURLPROXY_HTTPS
     *  - CURLPROXY_HTTPS2
     *  - CURLPROXY_HTTP_1_0
     *  - CURLPROXY_SOCKS4
     *  - CURLPROXY_SOCKS4A
     *  - CURLPROXY_SOCKS5
     *  - CURLPROXY_SOCKS5_HOSTNAME
     *
     * @var int
     */
    private int $proxyType = CURLPROXY_HTTP;

    /**
     * Set proxy configuration
     *
     * @param string $proxy
     * @param string|null $auth
     * @param int $type
     */
    public function setProxy(string $proxy, ?string $auth = null, int $type = CURLPROXY_HTTP)
    {
        $this->proxy = $proxy;
        $this->proxyAuth = $auth;
        $this->proxyType = $type;
    }

    /**
     * The method that takes an HTTP request, serializes the request, makes a call to given environment, and deserialize response
     *
     * @param HttpRequest $httpRequest
     * @return HttpResponse
     *
     * @throws HttpException
     * @throws IOException
     */
    public function execute($httpRequest)
    {
        $requestCpy = clone $httpRequest;
        $curl = new Curl();

        foreach ($this->injectors as $inj) {
            $inj->inject($requestCpy);
        }

        $url = $this->environment->baseUrl() . $requestCpy->path;
        $formattedHeaders = $this->prepareHeaders($requestCpy->headers);
        if (!array_key_exists("user-agent", $formattedHeaders)) {
            $requestCpy->headers["user-agent"] = $this->userAgent();
        }

        $body = "";
        if (!is_null($requestCpy->body)) {
            $rawHeaders = $requestCpy->headers;
            $requestCpy->headers = $formattedHeaders;
            $body = $this->encoder->serializeRequest($requestCpy);
            $requestCpy->headers = $this->mapHeaders($rawHeaders,$requestCpy->headers);
        }

        $curl->setOpt(CURLOPT_URL, $url);
        $curl->setOpt(CURLOPT_CUSTOMREQUEST, $requestCpy->verb);
        $curl->setOpt(CURLOPT_HTTPHEADER, $this->serializeHeaders($requestCpy->headers));
        $curl->setOpt(CURLOPT_RETURNTRANSFER, 1);
        $curl->setOpt(CURLOPT_HEADER, 0);

        if (!is_null($requestCpy->body)) {
            $curl->setOpt(CURLOPT_POSTFIELDS, $body);
        }

        if (strpos($this->environment->baseUrl(), "https://") === 0) {
            $curl->setOpt(CURLOPT_SSL_VERIFYPEER, true);
            $curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
        }

        if ($caCertPath = $this->getCACertFilePath()) {
            $curl->setOpt(CURLOPT_CAINFO, $caCertPath);
        }

        // --- Add proxy support ---
        if ($this->proxy) {
            $curl->setOpt(CURLOPT_PROXY, $this->proxy);
            $curl->setOpt(CURLOPT_PROXYTYPE, $this->proxyType);

            if ($this->proxyAuth) {
                $curl->setOpt(CURLOPT_PROXYUSERPWD, $this->proxyAuth);
            }
        }

        $response = $this->parseResponse($curl);
        $curl->close();

        return $response;
    }


    private function serializeHeaders($headers)
    {
        $headerArray = [];
        if ($headers) {
            foreach ($headers as $key => $val) {
                $headerArray[] = $key . ": " . $val;
            }
        }

        return $headerArray;
    }

    private function parseResponse($curl)
    {
        $headers = [];
        $curl->setOpt(CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);

                $k = "";
                $v = "";

                $this->deserializeHeader($header, $k, $v);
                $headers[$k] = $v;

                return $len;
            });

        $responseData = $curl->exec();
        $statusCode = $curl->getInfo(CURLINFO_HTTP_CODE);
        $errorCode = $curl->errNo();
        $error = $curl->error();

        if ($errorCode > 0) {
            throw new IOException($error, $errorCode);
        }

        $body = $responseData;

        if ($statusCode >= 200 && $statusCode < 300) {
            $responseBody = NULL;

            if (!empty($body)) {
                $responseBody = $this->encoder->deserializeResponse($body, $this->prepareHeaders($headers));
            }

            return new HttpResponse(
                $errorCode === 0 ? $statusCode : $errorCode,
                $responseBody,
                $headers
            );
        } else {
            throw new HttpException($body, $statusCode, $headers);
        }
    }

    private function deserializeHeader($header, &$key, &$value)
    {
        if (strlen($header) > 0) {
            if (empty($header) || strpos($header, ':') === false) {
                return NULL;
            }

            list($k, $v) = explode(":", $header);
            $key = trim($k);
            $value = trim($v);
        }
    }
}