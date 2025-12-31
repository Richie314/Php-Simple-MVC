<?php

require_once dirname(path: __DIR__) . '/vendor/autoload.php';

use Richie314\SimpleMvc\Routers\Router;
use Richie314\SimpleMvc\Demo\DemoController;

$router = new Router(
    pathPrefix: '/demo', 
    applicationInstallationPath: $_SERVER['DOCUMENT_ROOT'] . '/demo',
);
$router->AddController(controller: DemoController::class, route_base: '/');

$router->Dispatch(uri: $_SERVER['REQUEST_URI']);