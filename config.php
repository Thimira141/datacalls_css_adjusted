<?php

/**
 * Basic Configurations for the app.
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

// start the session
session_start();

// get auto load php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/autoload.php';
require_once __DIR__ . '/controller/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;


// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Build config object
$config = (object) [
    'magnus' => (object) [
        'api_key'     => $_ENV['MAGNUS_API_KEY'] ?? '',
        'api_secret'  => $_ENV['MAGNUS_API_SECRET'] ?? '',
        'public_url'  => $_ENV['MAGNUS_PUBLIC_URL'] ?? '',
    ],
    'app' => (object) [
        'env'   => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url'   => $_ENV['APP_URL'] ?? '',
    ],
];
$config->pUrl = $config->app->url.'/public';

// database config with 
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'],
    'database'  => $_ENV['DB_NAME'],
    'username'  => $_ENV['DB_USER'],
    'password'  => $_ENV['DB_PASS'],
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$container = new Container();
$dispatcher = new Dispatcher($container);
// Bind the contract to the dispatcher instance
$container->instance(DispatcherContract::class, $dispatcher);
$capsule->setEventDispatcher($dispatcher);
$capsule->setAsGlobal();
$capsule->bootEloquent();
