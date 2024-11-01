<?php

namespace SmartrMail\Dependencies\Providers\Controllers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SmartrMail\Controllers\AdminController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminControllerProvider implements ServiceProviderInterface
{
    public function register( Container $container ) {
        $container[ AdminController::class ] = function ( $container ) {
            return new AdminController(
                $container['config']['register.url'],
                $container['config']['options.name'],
                $container['config']['uri']
            );
        };
    }
}