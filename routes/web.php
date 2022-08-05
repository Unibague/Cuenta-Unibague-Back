<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use App\Helpers\LDAPH\EasyLDAP;

$router->get('/', function () use ($router) {
    return 'Hola';
});


$router->get('/test', function () use ($router) {

    \Illuminate\Support\Facades\Mail::to('juan.ospina@unibague.edu.co')->send(new \App\Mail\RecoverPassword('token'));
});

//Change password router
$router->post('/changePassword', 'AccountController@changePassword');
$router->post('/verifyToken', 'AccountController@verifyToken');

$router->post('/rememberEmail', 'AccountController@rememberEmail');
$router->post('/recoverPassword', 'AccountController@recoverPassword');
$router->post('/changeAlternateEmail', 'AccountController@changeAlternateEmail');

