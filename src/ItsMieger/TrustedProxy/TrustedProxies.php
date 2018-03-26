<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 23.03.18
	 * Time: 14:31
	 */

	namespace ItsMieger\TrustedProxy;


	use Illuminate\Contracts\Config\Repository;
	use Illuminate\Http\Request;

	class TrustedProxies
	{
		const FORWARDED_HEADER_NAMES = [
			Request::HEADER_FORWARDED         => 'FORWARDED',
			Request::HEADER_X_FORWARDED_FOR   => 'X_FORWARDED_FOR',
			Request::HEADER_X_FORWARDED_HOST  => 'X_FORWARDED_HOST',
			Request::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
			Request::HEADER_X_FORWARDED_PORT  => 'X_FORWARDED_PORT',
		];


		/**
		 * @var Repository
		 */
		protected $config;

		/**
		 * Creates a new instance.
		 * @param Repository $config The configuration repository.
		 */
		public function __construct(Repository $config) {
			$this->config = $config;
		}


		/**
		 * Gets the configuration
		 * @return Repository
		 */
		public function getConfig(): Repository {
			return $this->config;
		}



		/**
		 * @inheritDoc
		 */
		public function handle(Request $request, \Closure $next) {

			$config = $this->config;

			// try to get config preset from preset secret
			$presetUsed = false;
			$presetSecretHeaders = $config->get('trustedProxies.presetSecretHeaders');
			if ($presetSecretHeaders) {
				$presets = $config->get('trustedProxies.presets');

				foreach ($presetSecretHeaders as $currHeader) {
					$currSecret = $request->headers->get($currHeader);

					if ($currSecret) {
						foreach ($presets as $currPreset) {
							if (($currPreset['secret'] ?? null) === $currSecret) {
								$proxies           = $currPreset['proxies'] ?? [];
								$trustLastN        = $currPreset['trustLastProxies'] ?? 0;
								$proxyHeaders      = $currPreset['headers'] ?? [];
								$trustedDirectives = $currPreset['trustDirectives'] ?? [];

								$presetUsed = true;
								break 2;
							}
						}
					}
				}
			}

			// try to use default preset
			if (!$presetUsed) {
				$defaultPreset = $config->get('trustedProxies.defaultPreset');
				if ($defaultPreset) {
					$presets = $config->get('trustedProxies.presets');
					if (isset($presets[$defaultPreset])) {
						$proxies           = $presets[$defaultPreset]['proxies'] ?? [];
						$trustLastN        = $presets[$defaultPreset]['trustLastProxies'] ?? 0;
						$proxyHeaders      = $presets[$defaultPreset]['headers'] ?? [];
						$trustedDirectives = $presets[$defaultPreset]['trustDirectives'] ?? [];

						$presetUsed = true;
					}

				}

			}

			// no preset => use default configuration
			if (!$presetUsed) {
				$proxies           = $config->get('trustedProxies.proxies', []);
				$trustLastN        = $config->get('trustedProxies.trustLastProxies', 0);
				$proxyHeaders      = $config->get('trustedProxies.headers', []);
				$trustedDirectives = $config->get('trustedProxies.trustDirectives', []);
			}

			/** @noinspection PhpUndefinedVariableInspection */
			$this->processRequest($request, $proxies, $trustLastN, $proxyHeaders, $trustedDirectives);

			return $next($request);
		}

		protected function processRequest(Request $request, $trustedProxies, $trustLastN, $proxyHeaders, $trustedDirectives) {

			// override standard headers with custom headers
			foreach ($proxyHeaders as $key => $customHeaderName) {
				if (is_string($customHeaderName) && $customHeaderName != 'true') {

					// get the value for the custom header
					$headerValue = $request->header($customHeaderName);

					// override or remove existing header
					if ($headerValue)
						$request->headers->set(self::FORWARDED_HEADER_NAMES[$key], $headerValue);
					elseif ($request->headers->has(self::FORWARDED_HEADER_NAMES[$key]))
						$request->headers->remove(self::FORWARDED_HEADER_NAMES[$key]);
				}
			}

			// trust last $n proxies?
			if ($trustLastN > 0) {
				// add direct proxy
				$trustedProxies[] = $request->server->get('REMOTE_ADDR');

				// add further proxies from header
				$levels = $trustLastN - 1;
				if ($levels > 0) {
					// forwarded for
					if ($proxyHeaders[Request::HEADER_X_FORWARDED_FOR] ?? null)
						$trustedProxies = array_merge($trustedProxies, array_slice($this->parseForwardedForHeaderIPs($request, $proxyHeaders), -$levels));

					// forwarded
					if ($proxyHeaders[Request::HEADER_FORWARDED] ?? null)
						$trustedProxies = array_merge($trustedProxies, array_slice($this->parseForwardedHeaderIPs($request, $proxyHeaders), -$levels));

				}
			}

			// trust proxies by directive
			if ($proxyHeaders[Request::HEADER_FORWARDED] ?? null && $trustedDirectives)
				$trustedProxies = array_merge($trustedProxies, $this->parseTrustedForwardedHeaderIPs($request, $proxyHeaders, $trustedDirectives));


			$trustedHeaderSet = $this->getTrustedHeaderSet($proxyHeaders);
			$request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
		}

		/**
		 * Gets the headers to trust as bit map
		 * @param string[]|bool[] $headers The headers used to parse the proxy
		 * @return int The trusted headers bit map
		 */
		protected function getTrustedHeaderSet(array $headers) {
			$ret = 0;
			foreach($headers as $headerId => $name) {
				if ($name)
					$ret = $ret | $headerId;
			}

			return $ret;
		}

		protected function getHeaderName(int $headerCode, $proxyHeaders) {
			$headerName = $proxyHeaders[$headerCode] ?? true;

			if ($headerName == true)
				$headerName = self::FORWARDED_HEADER_NAMES[$headerCode];

			return $headerName;
		}

		protected function parseForwardedForHeaderIPs(Request $request, $proxyHeaders) {

			$headerName = $this->getHeaderName(Request::HEADER_X_FORWARDED_FOR, $proxyHeaders);

			// retrieve header
			$value = $request->header($headerName, '');
			if (!$value)
				return [];

			// parse IPs from header
			return array_map('trim', explode(',', $value));
		}

		protected function parseForwardedHeaderIPs(Request $request, $proxyHeaders) {

			$headerName = $this->getHeaderName(Request::HEADER_FORWARDED, $proxyHeaders);

			// retrieve header
			$value = $request->header($headerName, '');

			if (!$value)
				return [];

			$ret = array_filter(array_map(function($proxySet) {
				$proxyAttribute = explode(';', $proxySet);
				foreach($proxyAttribute as $currProxyAttribute) {
					$currAttr = explode('=', $currProxyAttribute, 2);
					$currAttrName = strtolower(trim($currAttr[0]));
					$currAttrValue = strtolower(trim($currAttr[1], " \t\n\r\0\x0B\""));

					if ($currAttrName == 'for') {
						return preg_replace('/^\\[?([a-z0-9:\\.]+)\\]?.*/', '$1', $currAttrValue);
					}
				}

				return null;

			}, explode(',', $value)));

			return $ret;
		}

		protected function parseTrustedForwardedHeaderIPs(Request $request, $proxyHeaders, $trustedDirectives) {

			$headerName = $this->getHeaderName(Request::HEADER_FORWARDED, $proxyHeaders);

			// retrieve header
			$value = $request->header($headerName, '');
			if (!$value)
				return [];

			// lowercase keys for directives
			$trusted = [];
			foreach($trustedDirectives as $key => $trustedValue) {
				$trusted[strtolower(trim($key))] = strtolower(trim($trustedValue, " \t\n\r\0\x0B\""));
			}


			// the last proxy ip
			$lastProxy = $request->server->get('REMOTE_ADDR');

			$ret = array_filter(array_map(function($proxySet) use ($trusted, &$lastProxy) {
				$proxyAttribute = explode(';', $proxySet);

				// check if proxy entry can be trusted
				$trustedProxyIp = null;
				foreach ($proxyAttribute as $currProxyAttribute) {

					$currAttr      = explode('=', $currProxyAttribute, 2);
					$currAttrName  = strtolower(trim($currAttr[0]));
					$currAttrValue = strtolower(trim($currAttr[1], " \t\n\r\0\x0B\""));

					if (($trusted[$currAttrName] ?? null) == $currAttrValue) {
						// we can trust the proxy (the last one) which added the current entry
						$trustedProxyIp = $lastProxy;
						break;
					}

				}

				// extract IP and remember it
				$lastProxy = null;
				foreach ($proxyAttribute as $currProxyAttribute) {
					$currAttr      = explode('=', $currProxyAttribute, 2);
					$currAttrName  = strtolower(trim($currAttr[0]));
					$currAttrValue = strtolower(trim($currAttr[1], " \t\n\r\0\x0B\""));

					if ($currAttrName == 'for') {
						$lastProxy = preg_replace('/^\\[?([a-z0-9:\\.]+)\\]?.*/', '$1', $currAttrValue);
					}
				}


				return $trustedProxyIp;

			}, array_reverse(explode(',', $value))));

			return $ret;
		}

	}