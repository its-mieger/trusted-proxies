<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 26.03.18
	 * Time: 14:23
	 */

	namespace ItsMiegerTrustedProxiesTests\Cases;


	use ItsMieger\TrustedProxy\TrustedProxies;
	use ItsMieger\TrustedProxy\TrustedProxiesServiceProvider;
	use Orchestra\Testbench\TestCase;

	class TrustedProxiesServiceProviderTest extends TestCase
	{

		/**
		 * Load package service provider
		 * @param  \Illuminate\Foundation\Application $app
		 * @return array
		 */
		protected function getPackageProviders($app) {
			return [
				TrustedProxiesServiceProvider::class
			];
		}

		protected function getEnvironmentSetUp($app) {
			putenv('TRUSTED_PROXY_DEFAULT_PRESET=my-default-preset');

			parent::getEnvironmentSetUp($app);
		}


		public function testTrustedProxiesRegistration() {

			/** @var TrustedProxies $resolved */
			$resolved = resolve(TrustedProxies::class);

			$this->assertInstanceOf(TrustedProxies::class, $resolved);

			$this->assertNotEmpty($resolved->getConfig()->get('trustedProxies'));
		}

		public function testDefaultPresetReadFromEnv() {

			/** @var TrustedProxies $resolved */
			$resolved = resolve(TrustedProxies::class);


			$this->assertEquals('my-default-preset', $resolved->getConfig()->get('trustedProxies.defaultPreset'));
		}
	}