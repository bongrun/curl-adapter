<?php

namespace bongrun\adapter;

/**
 * Interface CurlAdapterInterface
 * @package bongrun\adapter
 */
interface CurlAdapterInterface
{
    /**
     * @param $baseUrl
     */
    public function setBaseUrl($baseUrl);

    /**
     * @param $uri
     * @param $params
     * @return $this
     */
    public function get($uri, $params = []);

    /**
     * @param $uri
     * @param array $params
     * @return $this
     */
    public function post($uri, $params = []);

    /**
     * @return \GuzzleHttp\Psr7\Stream|\Psr\Http\Message\StreamInterface
     */
    public function getResponseBody();
}