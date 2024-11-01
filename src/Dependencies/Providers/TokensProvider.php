<?php

namespace SmartrMail\Dependencies\Providers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SmartrMail\Services\Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TokensProvider implements ServiceProviderInterface
{
	public function register( Container $container ) {
		$container[ Tokens::class ] = function () {
			return new Tokens();
		};
	}
}
