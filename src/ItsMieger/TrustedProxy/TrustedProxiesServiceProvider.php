<?php
	/**
	 * Created by PhpStorm.
	 * User: chris
	 * Date: 23.03.18
	 * Time: 15:37
	 */

	namespace ItsMieger\TrustedProxy;


	use Illuminate\Support\ServiceProvider;

	class TrustedProxiesServiceProvider extends ServiceProvider
	{
		protected $defer = true;

		/**
		 * Boot the service provider.
		 *
		 * @return void
		 */
		public function boot() {
			if ($this->app->runningInConsole())
				$this->publishes([$this->getConfigSource() => config_path('trustedProxies.php')]);

		}

		/**
		 * Register the service provider.
		 *
		 * @return void
		 */
		public function register() {

			$this->mergeConfigFrom($this->getConfigSource(), 'trustedProxies');

			$this->app->bind(TrustedProxies::class);
		}

		public function provides() {
			return [
				TrustedProxies::class
			];
		}

		protected function getConfigSource() {
			return realpath($raw = __DIR__ . '/../../../config/config.php') ?: $raw;
		}


	}