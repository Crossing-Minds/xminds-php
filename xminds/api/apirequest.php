<?php
/*
xminds.api.apirequest
~~~~~~~~~~~~~~~~~~~~~

This module implements the low level request logic of Crossing Minds API:
* headers and JTW token autentication
* serialization
* translation from HTTP status code to XMindsError
*/

require_once ("exceptions.php");
/*
import json
import logging
import pickle
import zlib

import requests

from .. import __version__
from ..compat import PYV
from .exceptions import ServerError, XMindsError
*/

class _BaseCrossingMindsApiRequest
{
    private $HOST = 'https://api.crossingminds.com';
    private $HEADERS = [];
    private $API_VERSION = 'v1';
    private $DEFAULT_TIMEOUT = 6;
    private $_REQUEST_KWARGS = [];

    function __construct($headers, $api_kwargs)
    {
        $this->host = $api_kwargs['host'] ?? $this->HOST;
        $this->headers = $headers ?? $this->HEADERS;
        $this->headers = array_merge($this->headers, $api_kwargs['headers'] ?? []);
        $this->api_version = $api_kwargs['api_version'] ?? $this->API_VERSION;
        $this->_jwt_token = null;
    }

    function get($path, $params=[], $kwargs=[])
    {
        return $this->_request('GET', $path, $params, [], $kwargs);
    }

    function put($path, $data=[], $kwargs=[])
    {
        return $this->_request('PUT', $path, [], $data, $kwargs);
    }

    function post($path, $data=[], $kwargs=[])
    {
        return $this->_request('POST', $path, [], $data, $kwargs);
    }

    function patch($path, $data=[], $kwargs=[])
    {
        return $this->_request('PATCH', $path, [], $data, $kwargs);
    }

    function delete($path, $data=[], $kwargs=[])
    {
        return $this->_request('DELETE', $path, [], $data, $kwargs);
    }

    function jwt_token()
    {
        return $this->_jwt_token;
    }

    function set_jwt_token($jwt_token)
    {
        $this->_jwt_token = $jwt_token;
        $this->headers = array_merge($this->headers, ['Authorization'=> 'Bearer '.$jwt_token]);
    }

    function clear_jwt_token()
    {
        $this->_jwt_token = null;
        $this->headers = array_merge($this->headers, ['Authorization'=> null]);
    }

    function _request($method, $path, $params=[], $data=[], $kwargs=[])
    {
        $timeout = $kwargs['timeout'] ?? $this->DEFAULT_TIMEOUT;
        $url = $this->host.'/'.$this->api_version.'/'.$path;

        if ($path and substr($path, -1) != "/")
            $url .= '/';
/*
        request_kwargs = {
            'timeout': timeout or self.DEFAULT_TIMEOUT,
            **self._REQUEST_KWARGS
        }
*/
        $headerMod = $this->headers;
        $headerString = "";
        foreach($headerMod as $k => $v)
            $headerString .= $k.": ".$v."\r\n";

        print_r(['header',$headerMod]);
        print_r(['data',$data]);
        print_r(['params',$params]);

        $options = array(
                'http' => array(
                'header'  => $headerString,
                'method'  => $method,
                'ignore_errors' => true,
            )
        );
        if(count($data))
            $options['http']['content'] = $this->_serialize_data($data);
        if(count($params))
            $url .= "?".http_build_query($params);

        $context  = stream_context_create($options);
        //print ($url."\n");
        $resp = file_get_contents($url, false, $context);

        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $status_code = (int)$match[1];

        if ($status_code >= 500)
        {
            $exc_payload = $this->_parse_response($resp, True);
			print_r([$status_code, $resp]);
            //if ($exc_payload)
            //    logging.error(exc_payload);
            throw new ServerError();
        }
        else if ($status_code >= 400)
        {
            $data = $this->_parse_response($resp, True);
            print_r([$status_code, $resp]);
            //try:
            //    exc = XMindsError.from_code(data.get('error_code', 0), data.get('error_data'))
            //except (KeyError, AttributeError, TypeError, ValueError):
            //    exc = ServerError({'response': data})
            throw new Exception();
        }

        $data = $this->_parse_response($resp);

        return $data;
    }

    function _serialize_data($data)
    {
        throw new NotImplementedError();
    }

    function _parse_response($response, $fallback=False)
    {
        throw new NotImplementedError();
    }
/*
    @staticmethod
    def _parse_token(headers):
        if 'Authorization' not in headers:
            return null

        authorization_header = headers['Authorization']
        token_start_index = authorization_header.index('Bearer') + len('Bearer ')
        jwt = authorization_header[token_start_index:]
        return jwt
*/
}

class CrossingMindsApiJsonRequest extends _BaseCrossingMindsApiRequest
{
    private $HEADERS = [
        'User-Agent'=>'CrossingMinds/0.1 (PHP; JSON)',
        'Content-type'=> 'application/json',
        'Accept'=> 'application/json',
    ];

    function __construct($api_kwargs)
    {
        parent::__construct($this->HEADERS, $api_kwargs);
    }

    function _parse_response($response, $fallback=False)
    {
        if($response == "")
            return null;

        if($fallback)
        {
            try
            {
                return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (Exception $err)
            {
                return $response;
            }
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    function _serialize_data($data)
    {
        return json_encode($data);
    }
}

?>
