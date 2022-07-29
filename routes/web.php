<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use App\Helpers\LDAPH\EasyLDAP;

$router->get('/', function () use ($router) {
    return 'Hola';
});

$router->post('/changePassword', 'AccountController@changePassword');
$router->post('/rememberEmail', 'AccountController@rememberEmail');
$router->post('/recoverPassword', 'AccountController@recoverPassword');
$router->post('/changeAlternateEmail', 'AccountController@changeAlternateEmail');

