<?php

namespace bongrun\adapter;

use DiDom\Document;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RedirectMiddleware;
use GuzzleHttp\TransferStats;
use interfaces\ProxyDataInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class CurlAdapter
{
    /** @var ProxyDataInterface */
    private $proxy;

    private $userAgent;

    /** @var Client */
    private $client;
    /** @var Response */
    private $response;
    private $cookies;
    private $baseUrl;
    /** @var Uri */
    private $currentUrl;

    private $lastUri;
    private $lastParams;

    /**
     * CurlAdapter constructor.
     * @param null $baseUrl
     * @param $userAgent
     */
    public function __construct($baseUrl = null, $userAgent = null)
    {
        $this->setBaseUrl($baseUrl);
        $this->userAgent = $userAgent;
    }

    /**
     * @param $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->client = new Client(['base_uri' => $baseUrl]);
    }

    /**
     * @param ProxyDataInterface $proxy
     * @return $this
     */
    public function setProxy(ProxyDataInterface $proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @return $this
     */
    public function clearProxy()
    {
        $this->proxy = null;
        return $this;
    }

    public function setCookies($cookies = [])
    {
        if (!isset($this->cookies[$this->baseUrl])) {
            $this->cookies[$this->baseUrl] = new CookieJar();
        }
        foreach ($cookies as $cookie) {
            if ($cookie['name'] != 'referer') {
                $this->cookies[$this->baseUrl]->setCookie(new SetCookie([
                    'Domain' => $cookie['domain'],
                    'Name' => $cookie['name'],
                    'Value' => $cookie['value'],
                    'Discard' => true
                ]));
            }
        }
    }

    /**
     * @param $uri
     * @param $params
     * @return $this
     */
    public function get($uri, $params = [])
    {
        if ($uri === $this->lastUri && $params === $this->lastParams) {
            return $this;
        }
        try {
            $this->response = $this->client->request('GET', $uri, array_merge($this->getOptionsDefault(), [
                'query' => $params
            ]));
        } catch (ClientException $e) {
            $this->response = $e->getResponse();
        }
        $this->lastUri = $uri;
        $this->lastParams = $params;
        return $this;
    }

    /**
     * @param $uri
     * @param array $params
     * @return $this
     */
    public function post($uri, $params = [])
    {
        if ($uri === $this->lastUri && $params === $this->lastParams) {
            return $this;
        }
        try {
            $allParams['form_params'] = $params;
            $this->response = $this->client->request('POST', $uri, array_merge($this->getOptionsDefault(), $allParams));
        } catch (ClientException $e) {
            $this->response = $e->getResponse();
        }
        $this->lastUri = $uri;
        $this->lastParams = $params;
        return $this;
    }

    public function file($uri, array $files = [])
    {
        $multipart = [];
        foreach ($files as $name => $file) {
            $multipart[] = [
                'name' => $name,
                'contents' => fopen($file, 'r'),
                'filename' => basename($file),
            ];
        }
        $this->response = $this->client->request('POST', $uri, array_merge($this->getOptionsDefault(), [
            'multipart' => $multipart,
        ]));
        return $this;
    }

    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return \GuzzleHttp\Psr7\Stream|\Psr\Http\Message\StreamInterface
     */
    public function getResponseBody()
    {
        return $this->response->getBody();
    }

    public function getResponseHeaders()
    {
        return $this->response->getHeaders();
    }

    public function getResponseHeaders1()
    {
        return $this->response->getHeaderLine('X-Guzzle-Redirect-History');
    }

    public function getResponseHeaders2()
    {
        return $this->response->getHeaderLine('X-Guzzle-Redirect-Status-History');
    }

    /**
     * @return Uri
     */
    public function getCurrentUri()
    {
        return $this->currentUrl;
    }

    /**
     * @return string
     */
    public function getCurrentUriPath()
    {
        return $this->currentUrl->getPath();
    }

    /**
     * @return Document
     */
    public function getDocument()
    {
        return new Document((string)$this->response->getBody());
    }

    /**
     * @return array
     */
    protected function getOptionsDefault()
    {
        $options = [];
        if ($this->proxy instanceof ProxyDataInterface && $this->proxy->getIp() && $this->proxy->getPort()) {
            $options['proxy'] = 'http://' . $this->proxy->getString();
        }
        $options['headers'] = [
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 9_0 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/9.0 Mobile/13A340 Safari/600.1.4',
        ];
        if (isset($this->cookies[$this->baseUrl])) {
            $options['cookies'] = $this->cookies[$this->baseUrl];
        }
        $that = $this;
        $options['on_stats'] = function (TransferStats $stats) use (&$that) {
            $that->currentUrl = $stats->getEffectiveUri();
        };
        $onRedirect = function (
            RequestInterface $request,
            ResponseInterface $response,
            UriInterface $uri
        ) {
            echo 'Redirecting! ' . $request->getUri() . ' to ' . $uri . "\n";
        };
        $options['allow_redirects'] = [
            'max'             => 10,        // allow at most 10 redirects.
            'strict'          => true,      // use "strict" RFC compliant redirects.
            'referer'         => true,      // add a Referer header
//            'protocols'       => ['https'], // only allow https URLs
            'on_redirect'     => $onRedirect,
            'track_redirects' => true
        ];
        $options['debug'] = true;
        return $options;
    }

    public function isJson()
    {
        json_decode((string)$this->getResponseBody());
        return (json_last_error() == JSON_ERROR_NONE);
    }
}