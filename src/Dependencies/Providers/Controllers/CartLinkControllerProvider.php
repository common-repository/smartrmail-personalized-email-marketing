<?php

namespace SmartrMail\Dependencies\Providers\Controllers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SmartrMail\Controllers\CartLinkController;
use SmartrMail\Services\ProductHelper;
use SmartrMail\Services\Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CartLinkControllerProvider implements ServiceProviderInterface
{
    public function register( Container $container ) {
        $container[ CartLinkController::class ] = function ( $container ) {
            return new CartLinkController(
                $container[ ProductHelper::class ],
                $container[ Tokens::class ],
                $container[ 'config' ]['time.abandoned']
            );
        };
    }
}