# ItsMieger Trusted Proxies for laravel
This library allows easy and flexible configuration of trusted proxies in laravel. In addition to laravel's
default trusted proxy middleware, it allows to configure configuration presets, trusting a configured
number of proxies before the current server and to use custom proxy header names.

Setting a trusted proxy allows for correct URL generation, redirecting, session handling and logging in
Laravel when behind a proxy. This is useful if your web servers sit behind a load balancer, HTTP cache, or
other intermediary (reverse) proxy.

The underlying Symfony request implementation used in laravel supports trusting proxy data based on proxy
IPs. This package configures which proxies to trust.

## Installation

After updating composer, add the service provider to the providers array in config/app.php

	ItsMieger\TrustedProxy\TrustedProxiesServiceProvider::class
	
After that, you may add the middleware to your routes:

	ItsMieger\TrustedProxy\TrustedProxies::class
	
## Configuration

There are several ways how to configure trusted proxies. They are explained below. You may combine the
different configuration methods to fulfill your requirements best.

Take a special look at presets which become very handy if you are working in different environments or even your
proxy stack might change dynamically.

### Trusted forwarded headers 
Proxy information may be passed using the following headers:

* FORWARDED
* X-FORWARDED-FOR
* X-FORWARDED-HOST
* X-FORWARDED-PROTO
* X-FORWARDED-PORT

However not all proxies support all of these headers. Therefore you must configure which headers are used
by your proxy and therefore can be trusted. You can do so using the `headers`-option. The following example
demonstrates the appropriate configuration if using an **AWS Elastic load balancer**:

	'headers' => [
		\Illuminate\Http\Request::HEADER_FORWARDED         => false,
		\Illuminate\Http\Request::HEADER_X_FORWARDED_FOR   => true,
		\Illuminate\Http\Request::HEADER_X_FORWARDED_HOST  => false,
		\Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO => true,
		\Illuminate\Http\Request::HEADER_X_FORWARDED_PORT  => true,
	],
	
`Forwarded`- and `X-Forwarded-Host`-headers are untrusted, since ELB does not use this headers. All others
are set to to `true` (trusted).

#### Custom header names
If you have a custom header which holds proxy information, you may set the corresponding header to the
name of the custom header instead of `true`:

	'headers' => [
		/* ... */	
		\Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO => 'X-My-Proto-Header',
	],
	
Note: To implement custom headers, the middleware simply copies the value to the original headers. That's
the only way to make Symfony parse custom headers. The downside of this is, that the request headers
do not represent the original state anymore. 

### Trust proxies by IP
If you know the IPs of your proxies, you simply may list them using the `proxies`-option:

	'proxies' => ['192.168.0.11'],
	'proxies' => ['192.168.0.11', '10.38.0.0/16', '2001:DB8::21f:5bff:febf:ce22:8a2e'],

### Trust last `n` proxies
If you don't know the IPs of your proxies or the constantly change, you may specify how many trusted
proxies are in front of your server:

	'trustLastProxies' => 2,
	
This means the direct proxy and the one in front of it are trusted.

### Trust directives
The `Forwarded`-header allows proxies to set custom directives. This may for example be a secret which
can be used to authenticate the given proxy data:

`Forwarded: for=12.34.56.78, for=23.45.67.89;secret=egah2CGj55fSJFs, for=10.1.2.3`

The example shows the `secret=egah2CGj55fSJFs` directive in the header which was added by `10.1.2.3`.

You may configure this proxy data to be trusted by using the `trustDirectives`-option as follows:

	'trustDirectives' => [
		'secret' => 'egah2CGj55fSJFs'
	],

All proxy data which contains a directive with a value as defined in `trustDirectives` is trusted.


### Configuration presets
Configuration presets allow to define multiple trusted proxy configurations and select the appropriate
one depending on environment variables or headers.

You may configure a preset named `my-preset` as demonstrated below:

	'presets' => [
		'my-preset' => [
			'trustLastProxies' => 0,
			'proxies'          => [],
			'headers'          => [],
			'trustDirectives'  => [],
		],
		'another-preset' => [ /* ... */ ],
	]

As you see, configuration options in presets are the same as used for the base level configuration.


#### Select preset by environment

To select a preset based on your environment, simply set the `TRUSTED_PROXY_DEFAULT_PRESET`
environment variable to the preset name. According to the example above this would be:

	TRUSTED_PROXY_DEFAULT_PRESET=my-preset
	
	
#### Select preset by headers

To be more flexible and pick the correct preset only depending on your proxy stack, you may define
headers which tell which preset to use.

Since headers can not always be trusted, they must contain a secret instead of the preset name.
Therefore you have to add a `secret`-key to the presets you want to use:

	'presets' => [
		'my-preset' => [
			'secret'           => 'very-secret-value'
			'trustLastProxies' => 0,
			'proxies'          => [],
			'headers'          => [],
			'trustDirectives'  => [],
		],
		'another-preset' => [ /* ... */ ],
	]
	
Then you define a header which determines the preset to use:

	'presetSecretHeaders' => ['X-My-Preset-Header'],
	
The header now must contain the secret of the preset:

`X-My-Preset-Header: very-secret-value`

If you define multiple preset headers, they are tried in the order as defined in the configuration
file. The first preset which is found, will be used.