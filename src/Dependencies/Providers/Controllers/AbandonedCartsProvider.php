<?php

namespace SmartrMail\Dependencies\Providers\Controllers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SmartrMail\Controllers\AbandonedCarts;
use SmartrMail\Controllers\CartLinkController;
use SmartrMail\Services\Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartsProvider implements ServiceProviderInterface
{
	public function register( Container $container ) {
		$container[ AbandonedCarts::class ] = function ($container) {
			return new AbandonedCarts(
                            $container['config']['usermeta'],
                            $container[ CartLinkController::class ],
                            $container[ Tokens::class ],
                            $container['config']['time.abandoned']
                        );
		};
	}
}
