<?php

namespace SmartrMail\Dependencies\Providers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SmartrMail\Services\ProductHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductHelperServiceProvider implements ServiceProviderInterface
{
	public function register( Container $container ) {
		$container[ ProductHelper::class ] = function () {
			return new ProductHelper();
		};
	}
}
