<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 23.03.18
	 * Time: 14:40
	 *
	 * Tests are copied and modified from: fideloper/TrustedProxy (Chris Fidao)
	 */

	namespace ItsMiegerTrustedProxiesTests\Cases;


	use Illuminate\Http\Request;
	use ItsMieger\TrustedProxy\TrustedProxies;
	use PHPUnit\Framework\TestCase;

	class TrustedProxiesTest extends TestCase
	{
		/**
		 * Test that Symfony does indeed NOT trust X-Forwarded-*
		 * headers when not given trusted proxies
		 *
		 * This re-tests Symfony's Request class, but hopefully provides
		 * some clarify to developers looking at the tests.
		 *
		 * Also, thanks for looking at the tests.
		 */
		public function test_request_does_not_trust() {
			$req = $this->createProxiedRequest();

			$this->assertEquals('192.168.10.10', $req->getClientIp(), 'Assert untrusted proxy x-forwarded-for header not used');
			$this->assertEquals('http', $req->getScheme(), 'Assert untrusted proxy x-forwarded-proto header not used');
			$this->assertEquals('localhost', $req->getHost(), 'Assert untrusted proxy x-forwarded-host header not used');
			$this->assertEquals(8888, $req->getPort(), 'Assert untrusted proxy x-forwarded-port header not used');
		}

		/**
		 * Test that Symfony DOES indeed trust X-Forwarded-*
		 * headers when given trusted proxies
		 *
		 * Again, this re-tests Symfony's Request class.
		 */
		public function test_does_trust_trusted_proxy() {
			$req = $this->createProxiedRequest();
			$req->setTrustedProxies(['192.168.10.10'], Request::HEADER_X_FORWARDED_ALL);

			$this->assertEquals('173.174.200.38', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
			$this->assertEquals('https', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
			$this->assertEquals('serversforhackers.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
			$this->assertEquals(443, $req->getPort(), 'Assert trusted proxy x-forwarded-port header used');
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_x_headers() {
			$headers = [
				Request::HEADER_X_FORWARDED_FOR   => 'X_MY_FORWARDED_FOR',
				Request::HEADER_X_FORWARDED_HOST  => 'X_MY_FORWARDED_HOST',
				Request::HEADER_X_FORWARDED_PROTO => 'X_MY_FORWARDED_PROTO',
				Request::HEADER_X_FORWARDED_PORT  => 'X_MY_FORWARDED_PORT',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED_FOR' => '193.200.0.0',
				'HTTP_X_MY_FORWARDED_HOST' => 'overridden.host.com',
				'HTTP_X_MY_FORWARDED_PROTO' => 'http',
				'HTTP_X_MY_FORWARDED_PORT' => 1234,
			]);

			$trustedProxy = $this->createTrustedProxy($headers, 1, []);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
				$this->assertEquals(1234, $req->getPort(), 'Assert trusted proxy x-forwarded-port header used');
			});
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_x_headers__preset_default() {
			$headers = [
				Request::HEADER_X_FORWARDED_FOR   => 'X_MY_FORWARDED_FOR',
				Request::HEADER_X_FORWARDED_HOST  => 'X_MY_FORWARDED_HOST',
				Request::HEADER_X_FORWARDED_PROTO => 'X_MY_FORWARDED_PROTO',
				Request::HEADER_X_FORWARDED_PORT  => 'X_MY_FORWARDED_PORT',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED_FOR' => '193.200.0.0',
				'HTTP_X_MY_FORWARDED_HOST' => 'overridden.host.com',
				'HTTP_X_MY_FORWARDED_PROTO' => 'http',
				'HTTP_X_MY_FORWARDED_PORT' => 1234,
			]);

			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
					'trustLastProxies' => 1,
					'headers' => $headers,
				]
			]);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
				$this->assertEquals(1234, $req->getPort(), 'Assert trusted proxy x-forwarded-port header used');
			});
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_x_headers__preset_by_header() {
			$headers = [
				Request::HEADER_X_FORWARDED_FOR   => 'X_MY_FORWARDED_FOR',
				Request::HEADER_X_FORWARDED_HOST  => 'X_MY_FORWARDED_HOST',
				Request::HEADER_X_FORWARDED_PROTO => 'X_MY_FORWARDED_PROTO',
				Request::HEADER_X_FORWARDED_PORT  => 'X_MY_FORWARDED_PORT',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED_FOR' => '193.200.0.0',
				'HTTP_X_MY_FORWARDED_HOST' => 'overridden.host.com',
				'HTTP_X_MY_FORWARDED_PROTO' => 'http',
				'HTTP_X_MY_FORWARDED_PORT' => 1234,
				'HTTP_X_MY_PRESET_HEADER' => 'secretValue',
				'HTTP_X_MY_OTHER_HEADER' => 'dce',
			]);

			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
				],
				'preset1' => [
					'secret' => 'abc'
				],
				'preset2' => [
					'secret' => 'secretValue',
					'trustLastProxies' => 1,
					'headers' => $headers,
				]
			], ['X_MY_OTHER_HEADER', 'x-my-preset-header']);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
				$this->assertEquals(1234, $req->getPort(), 'Assert trusted proxy x-forwarded-port header used');
			});
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_forward_header() {
			$headers = [
				Request::HEADER_FORWARDED   => 'X_MY_FORWARDED',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED' => 'for=193.200.0.0;host=overridden.host.com;proto=http',
			]);

			$trustedProxy = $this->createTrustedProxy($headers, 1, []);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
			});
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_forward_header__preset_default() {
			$headers = [
				Request::HEADER_FORWARDED   => 'X_MY_FORWARDED',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED' => 'for=193.200.0.0;host=overridden.host.com;proto=http',
			]);

			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
					'trustLastProxies' => 1,
					'headers'          => $headers,
				]
			]);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
			});
		}

		/**
		 * Test that headers can be overridden
		 *
		 */
		public function test_override_forward_header__preset_by_header() {
			$headers = [
				Request::HEADER_FORWARDED   => 'X_MY_FORWARDED',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_MY_FORWARDED' => 'for=193.200.0.0;host=overridden.host.com;proto=http',
				'HTTP_X_MY_PRESET_HEADER' => 'secretValue',
				'HTTP_X_MY_OTHER_HEADER'  => 'dce',
			]);

			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
				],
				'preset1'       => [
					'secret' => 'abc'
				],
				'preset2'       => [
					'secret'           => 'secretValue',
					'trustLastProxies' => 1,
					'headers'          => $headers,
				]
			], ['X_MY_OTHER_HEADER', 'x-my-preset-header']);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('193.200.0.0', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
				$this->assertEquals('http', $req->getScheme(), 'Assert trusted proxy x-forwarded-proto header used');
				$this->assertEquals('overridden.host.com', $req->getHost(), 'Assert trusted proxy x-forwarded-host header used');
			});
		}

		/**
		 * Test that overridden headers reset existing headers if overridden header is empty
		 *
		 */
		public function test_override_resets_even_if_empty() {
			$headers = [
				Request::HEADER_X_FORWARDED_FOR => 'X_MY_FORWARDED_FOR',
			];

			$req = $this->createProxiedRequest([
				'HTTP_X_FORWARDED_FOR' => '192.168.4.5',
			]);

			$trustedProxy = $this->createTrustedProxy($headers, 1, []);

			$trustedProxy->handle($req, function (Request $req) {
				$this->assertEquals('192.168.10.10', $req->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');

			});
		}

		/**
		 * Test the next most typical usage of TrustedProxies:
		 * Trusted X-Forwarded-For header, wilcard for TrustedProxies
		 */
		public function test_trusted_proxy_sets_trusted_proxies_with_wildcard() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 1, []);
			$request      = $this->createProxiedRequest();

			$trustedProxy->handle($request, function (Request $request) {
				$this->assertEquals('173.174.200.38', $request->getClientIp(), 'Assert trusted proxy x-forwarded-for header used with wildcard proxy setting');
			});
		}


		/**
		 * Test the most typical usage of TrustProxies:
		 * Trusted X-Forwarded-For header
		 */
		public function test_trusted_proxy_sets_trusted_proxies() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 0, ['192.168.10.10']);
			$request      = $this->createProxiedRequest();

			$trustedProxy->handle($request, function (Request $request) {
				$this->assertEquals('173.174.200.38', $request->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
			});
		}

		/**
		 * Test the most typical usage of TrustProxies:
		 * Trusted X-Forwarded-For header
		 */
		public function test_trusted_proxy_sets_trusted_proxies__preset_default() {
			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
					'proxies' => ['192.168.10.10'],
					'headers' => TrustedProxies::FORWARDED_HEADER_NAMES,
				]
			]);

			$request      = $this->createProxiedRequest();

			$trustedProxy->handle($request, function (Request $request) {
				$this->assertEquals('173.174.200.38', $request->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
			});
		}

		/**
		 * Test the most typical usage of TrustProxies:
		 * Trusted X-Forwarded-For header
		 */
		public function test_trusted_proxy_sets_trusted_proxies__preset_by_header() {
			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
				],
				'preset1'       => [
					'secret' => 'abc'
				],
				'preset2'       => [
					'secret'           => 'secretValue',
					'proxies' => ['192.168.10.10'],
					'headers' => TrustedProxies::FORWARDED_HEADER_NAMES,
				]
			], ['X_MY_OTHER_HEADER', 'x-my-preset-header']);

			$request      = $this->createProxiedRequest([
				'HTTP_X_MY_PRESET_HEADER' => 'secretValue',
				'HTTP_X_MY_OTHER_HEADER'  => 'dce',
			]);

			$trustedProxy->handle($request, function (Request $request) {
				$this->assertEquals('173.174.200.38', $request->getClientIp(), 'Assert trusted proxy x-forwarded-for header used');
			});
		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses
		 */
		public function test_get_client_ips() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 0, ['192.168.10.10']);

			$forwardedFor = [
				'192.0.2.2',
				'192.0.2.2, 192.0.2.199',
				'192.0.2.2, 192.0.2.199, 99.99.99.99',
				'192.0.2.2,192.0.2.199',
			];

			foreach ($forwardedFor as $forwardedForHeader) {
				$request = $this->createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedForHeader) {
					$ips = $request->getClientIps();
					$this->assertEquals('192.0.2.2', end($ips), 'Assert sets the ' . $forwardedForHeader);
				});
			}
		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with some of those being trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_some_of_which_are_trusted() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 0, ['192.168.10.10', '192.0.2.199']);

			$forwardedFor = [
				'192.0.2.2',
				'192.0.2.2, 192.0.2.199',
				'99.99.99.99, 192.0.2.2, 192.0.2.199',
				'192.0.2.2,192.0.2.199',
			];

			foreach ($forwardedFor as $forwardedForHeader) {
				$request = $this->createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedForHeader) {
					$this->assertEquals('192.0.2.2', $request->getClientIp(), 'Assert sets the ' . $forwardedForHeader);
				});
			}
		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with * wildcard trusting of all proxies
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_all_proxies_are_trusted() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 1);

			$forwardedFor = [
				'192.0.2.2',
				'192.0.2.199, 192.0.2.2',
				'192.0.2.199,192.0.2.2',
				'99.99.99.99,192.0.2.199,192.0.2.2',
			];

			foreach ($forwardedFor as $forwardedForHeader) {
				$request = $this->createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedForHeader) {
					$this->assertEquals('192.0.2.2', $request->getClientIp(), 'Assert sets the ' . $forwardedForHeader);
				});
			}
		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_last_1_proxies_are_trusted_forwarded_for() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 1, []);

			$forwardedFor = [
				'192.0.2.2',
				'192.0.2.199, 192.0.2.2',
				'192.0.2.199,192.0.2.2',
				'99.99.99.99,192.0.2.199,192.0.2.2',
			];

			foreach ($forwardedFor as $forwardedForHeader) {
				$request = $this->createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedForHeader) {
					$this->assertEquals('192.0.2.2', $request->getClientIp(), 'Assert sets the ' . $forwardedForHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_last_1_proxies_are_trusted_forwarded() {
			$trustedProxy = $this->createTrustedProxy([Request::HEADER_FORWARDED => true], 1, []);

			$forwarded = [
				'for=23.45.67.89;host=example.com;proto=https' => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https, for=23.45.67.89' => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https, for=2001:db8:cafe::17' => '2001:db8:cafe::17',
				'for=12.34.56.78;host=example.com;proto=https, for="2001:db8:cafe::17"' => '2001:db8:cafe::17',
				'for=12.34.56.78;host=example.com;proto=https, for="[2001:db8:cafe::17]"' => '2001:db8:cafe::17',
				'for=12.34.56.78;host=example.com;proto=https, for="[2001:db8:cafe::17]:4711"' => '2001:db8:cafe::17',
			];

			foreach ($forwarded as $forwardedHeader => $expectedValue) {
				$request = $this->createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedHeader, $expectedValue) {
					$this->assertEquals($expectedValue, $request->getClientIp(), 'Assert sets the ' . $forwardedHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last 2 proxies trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_last_2_proxies_are_trusted_forwarded_for() {
			$trustedProxy = $this->createTrustedProxy(TrustedProxies::FORWARDED_HEADER_NAMES, 2, []);

			$forwardedFor = [
				'192.0.2.2'                         => '192.0.2.2',
				'192.0.2.199, 192.0.2.2'            => '192.0.2.199',
				'192.0.2.199,192.0.2.2'             => '192.0.2.199',
				'99.99.99.99,192.0.2.199,192.0.2.2' => '192.0.2.199',
			];

			foreach ($forwardedFor as $forwardedForHeader => $expectedIp) {
				$request = $this->createProxiedRequest(['HTTP_X_FORWARDED_FOR' => $forwardedForHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedForHeader, $expectedIp) {
					$this->assertEquals($expectedIp, $request->getClientIp(), 'Assert sets the ' . $forwardedForHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_last_2_proxies_are_trusted_forwarded() {
			$trustedProxy = $this->createTrustedProxy([Request::HEADER_FORWARDED => true], 2, []);

			$forwarded = [
				'for=23.45.67.89;host=example.com;proto=https'                                 => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https, for=23.45.67.89'                => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https, for=2001:db8:cafe::17'          => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https, for="2001:db8:cafe::17"'        => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https, for="[2001:db8:cafe::17]"'      => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https, for="[2001:db8:cafe::17]:4711"' => '12.34.56.78',
			];

			foreach ($forwarded as $forwardedHeader => $expectedValue) {
				$request = $this->createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedHeader, $expectedValue) {
					$this->assertEquals($expectedValue, $request->getClientIp(), 'Assert sets the ' . $forwardedHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_trusted_directives() {
			$trustedProxy = $this->createTrustedProxy([Request::HEADER_FORWARDED => true], 0, [], ['sec1' => 'A', 'SEC2' => '"B"']);

			$forwarded = [
				'for=23.45.67.89;host=example.com;proto=https'                                               => '192.168.10.10',
				'for=23.45.67.89;host=example.com;proto=https;sec1=A'                                        => '23.45.67.89',
				'for=23.45.67.89;host=example.com;proto=https;sec2=B'                                        => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=23.45.67.89;sec2="B"'              => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=2001:db8:cafe::17;sec2=b'          => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A,for="2001:db8:cafe::17";sec2=b'        => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]";sec2=b'      => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]:4711";sec2=b' => '12.34.56.78',
			];

			foreach ($forwarded as $forwardedHeader => $expectedValue) {
				$request = $this->createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedHeader, $expectedValue) {
					$this->assertEquals($expectedValue, $request->getClientIp(), 'Assert sets the ' . $forwardedHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_trusted_directives__preset_default() {
			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
					'headers' => [Request::HEADER_FORWARDED => true ],
				    'trustDirectives' => ['sec1' => 'A', 'SEC2' => '"B"']
				]
			]);

			$forwarded = [
				'for=23.45.67.89;host=example.com;proto=https'                                               => '192.168.10.10',
				'for=23.45.67.89;host=example.com;proto=https;sec1=A'                                        => '23.45.67.89',
				'for=23.45.67.89;host=example.com;proto=https;sec2=B'                                        => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=23.45.67.89;sec2="B"'              => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=2001:db8:cafe::17;sec2=b'          => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A,for="2001:db8:cafe::17";sec2=b'        => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]";sec2=b'      => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]:4711";sec2=b' => '12.34.56.78',
			];

			foreach ($forwarded as $forwardedHeader => $expectedValue) {
				$request = $this->createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedHeader, $expectedValue) {
					$this->assertEquals($expectedValue, $request->getClientIp(), 'Assert sets the ' . $forwardedHeader);
				});
			}

		}

		/**
		 * Test X-Forwarded-For header with multiple IP addresses, with last proxy trusted
		 */
		public function test_get_client_ip_with_muliple_ip_addresses_trusted_directives__preset_by_header() {
			$trustedProxy = $this->createTrustedProxy([], 0, [], [], 'defaultPreset', [
				'defaultPreset' => [
				],
				'preset1'       => [
					'secret' => 'abc'
				],
				'preset2'       => [
					'secret'           => 'secretValue',
					'headers'         => [Request::HEADER_FORWARDED => true],
					'trustDirectives' => ['sec1' => 'A', 'SEC2' => '"B"']
				]
			], ['X_MY_OTHER_HEADER', 'x-my-preset-header']);

			$forwarded = [
				'for=23.45.67.89;host=example.com;proto=https'                                               => '192.168.10.10',
				'for=23.45.67.89;host=example.com;proto=https;sec1=A'                                        => '23.45.67.89',
				'for=23.45.67.89;host=example.com;proto=https;sec2=B'                                        => '23.45.67.89',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=23.45.67.89;sec2="B"'              => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for=2001:db8:cafe::17;sec2=b'          => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A,for="2001:db8:cafe::17";sec2=b'        => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]";sec2=b'      => '12.34.56.78',
				'for=12.34.56.78;host=example.com;proto=https;sec1=A, for="[2001:db8:cafe::17]:4711";sec2=b' => '12.34.56.78',
			];

			foreach ($forwarded as $forwardedHeader => $expectedValue) {
				$request = $this->createProxiedRequest(['HTTP_FORWARDED' => $forwardedHeader, 'HTTP_X_MY_PRESET_HEADER' => 'secretValue', 'HTTP_X_MY_OTHER_HEADER' => 'dce',]);

				$trustedProxy->handle($request, function (Request $request) use ($forwardedHeader, $expectedValue) {
					$this->assertEquals($expectedValue, $request->getClientIp(), 'Assert sets the ' . $forwardedHeader);
				});
			}

		}

		/**
		 * Test distrusting a header.
		 */
		public function test_can_distrust_headers() {
			$trustedProxy = $this->createTrustedProxy([Request::HEADER_FORWARDED => true], 0, ['192.168.10.10']);

			$request = $this->createProxiedRequest([
				'HTTP_FORWARDED'         => 'for=173.174.200.40:443; proto=https; host=serversforhackers.com',
				'HTTP_X_FORWARDED_FOR'   => '173.174.200.38',
				'HTTP_X_FORWARDED_HOST'  => 'svrs4hkrs.com',
				'HTTP_X_FORWARDED_PORT'  => '80',
				'HTTP_X_FORWARDED_PROTO' => 'http',
			]);

			$trustedProxy->handle($request, function (Request $request) {
				$this->assertEquals('173.174.200.40', $request->getClientIp(),
					'Assert trusted proxy used forwarded header for IP');
				$this->assertEquals('https', $request->getScheme(),
					'Assert trusted proxy used forwarded header for scheme');
				$this->assertEquals('serversforhackers.com', $request->getHost(),
					'Assert trusted proxy used forwarded header for host');
				$this->assertEquals(443, $request->getPort(), 'Assert trusted proxy used forwarded header for port');
			});
		}



		################################################################
		# Utility Functions
		################################################################

		/**
		 * Fake an HTTP request by generating a Symfony Request object.
		 *
		 * @param array $serverOverRides
		 *
		 * @return Request
		 */
		protected function createProxiedRequest($serverOverRides = []) {
			// Add some X-Forwarded headers and over-ride
			// defaults, simulating a request made over a proxy
			$serverOverRides = array_replace([
				'HTTP_X_FORWARDED_FOR'   => '173.174.200.38',         // X-Forwarded-For   -- getClientIp()
				'HTTP_X_FORWARDED_HOST'  => 'serversforhackers.com', // X-Forwarded-Host  -- getHosts()
				'HTTP_X_FORWARDED_PORT'  => '443',                   // X-Forwarded-Port  -- getPort()
				'HTTP_X_FORWARDED_PROTO' => 'https',                // X-Forwarded-Proto -- getScheme() / isSecure()
				'SERVER_PORT'            => 8888,
				'HTTP_HOST'              => 'localhost',
				'REMOTE_ADDR'            => '192.168.10.10',
			], $serverOverRides);

			// Create a fake request made over "http", one that we'd get over a proxy
			// which is likely something like this:
			$request = Request::create('http://localhost:8888/tag/proxy', 'GET', [], [], [], $serverOverRides, null);
			// Need to make sure these haven't already been set
			$request->setTrustedProxies([], Request::HEADER_X_FORWARDED_ALL);

			return $request;
		}

		/**
		 * Retrieve a TrustProxies object, with dependencies mocked.
		 *
		 * @param array $trustedHeaders
		 * @param array $trustedProxies
		 * @param int $trustLastProxies
		 *
		 * @return TrustedProxies
		 */
		protected function createTrustedProxy($trustedHeaders, $trustLastProxies, $trustedProxies = [], $trustedDirectives = [], $defaultPreset = null, $presets = [], $presetSecretHeaders = []) {
			// Mock TrustProxies dependencies and calls for config values
			$config = \Mockery::mock('Illuminate\Contracts\Config\Repository')
				->shouldReceive('get')
				->with('trustedProxies.headers', [])
				->andReturn($trustedHeaders)
				->shouldReceive('get')
				->with('trustedProxies.proxies', [])
				->andReturn($trustedProxies)
				->shouldReceive('get')
				->with('trustedProxies.trustDirectives', [])
				->andReturn($trustedDirectives)
				->shouldReceive('get')
				->with('trustedProxies.trustLastProxies', 0)
				->andReturn($trustLastProxies)
				->shouldReceive('get')
				->with('trustedProxies.defaultPreset')
				->andReturn($defaultPreset)
				->shouldReceive('get')
				->with('trustedProxies.presetSecretHeaders')
				->andReturn($presetSecretHeaders)
				->shouldReceive('get')
				->with('trustedProxies.presets')
				->andReturn($presets)
				->getMock();

			return new TrustedProxies($config);
		}
	}