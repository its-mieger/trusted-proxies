<?php

	return [

		/*
		 * Set the number of trusted proxies before this server.
		 *
		 * If you specify eg. 2, the proxy directly connecting
		 * to your server and the proxy before are trusted.
		 *
		 * If set to another value than 0, the 'proxies' configuration is ignored
		 *
		 */
		'trustLastProxies' => 0,

		/*
		 * Set trusted proxy IP addresses.
		 *
		 * Both IPv4 and IPv6 addresses are
		 * supported, along with CIDR notation.
		 *
		 */
		'proxies' => [],

		/*
		 * Which headers to use to detect proxy related data (For, Host, Proto, Port)
		 *
		 * The Request::HEADER_* constants are used as key. Headers which should be used
		 * to detect proxy data must be set to true. Headers set to false will be ignored.
		 *
		 * To use custom header names, you may set the header name as string value instead
		 * of true. This way the custom header value will be copied to the corresponding
		 * default header.
		 *
		 */
		'headers' => [
			\Illuminate\Http\Request::HEADER_FORWARDED         => false,
			\Illuminate\Http\Request::HEADER_X_FORWARDED_FOR   => false,
			\Illuminate\Http\Request::HEADER_X_FORWARDED_HOST  => false,
			\Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO => false,
			\Illuminate\Http\Request::HEADER_X_FORWARDED_PORT  => false,
		],

		/*
		 * The "forwarded"-header supports multiple directives. You may specify
		 * directives which identify trusted proxy data:
		 *
		 * for=12.34.56.78, for=23.45.67.89;secret=egah2CGj55fSJFs, for=10.1.2.3
		 *
		 * According to the example above, you can specify ['secret' => 'egah2CGj55fSJFs']
		 * so that the 23.45.67.89 entry made by '10.1.2.3' is trusted.
		 *
		 */
	    'trustDirectives' => [],


		/**
		 * Defines the trusted proxy configuration preset to use
		 * if not overridden by a preset header.
		 *
		 * If null, the base level configuration is used.
		 */
	    'defaultPreset' => env('TRUSTED_PROXY_DEFAULT_PRESET'),

	    /*
	     * Defines headers which select the configuration preset to use.
	     *
	     * The key must hold the header value and the value the secret
	     * as defined in the preset. E.g.:
	     *
	     * ['x-my-proxy-preset' => 'Xeemolee7tahng1C']
	     *
	     * If multiple preset headers are defined, they are tried in
	     * the order as defined here. The first matching preset is used.	     *
	     *
	     */
	    'presetSecretHeaders' => [],


		/**
		 * Allows to define multiple proxy configuration presets which
		 * are selectable based on header or environment values.
		 *
		 * See example configuration from below. The key `my-preset`
		 * may be referenced using `defaultPreset` and the `secret`
		 * inside must be the same as passed by one of the
		 * `presetSecretHeaders`-headers
		 *
		 */
	    'presets' => [
	    	'my-preset' => [
			    'secret'           => 'Xeemolee7tahng1C',
			    'trustLastProxies' => 0,
			    'proxies'          => [],
			    'headers'          => [],
		    ]
	    ]
	];
