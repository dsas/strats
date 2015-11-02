<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

include_once __DIR__.'/../config.php';

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../app.log',
    'monolog.name' => 'strats',
));

$app['monolog']->addDebug('Debug mode is ' . $app['debug']);

$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app['strava.client'] = $app->share(function () use ($app) {
    $token = $app['session']->get('strava_oauth_token');
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new Strava\API\Service\REST($token, $adapter);
    $client = new Strava\API\Client($service);
    return $client;
});

$app['strava.auth'] = $app->share(function () use ($app) {
    $auth = new Strava\API\OAuth($app['strava.auth.config']);
    $auth->setScopes(['public']);
    return $auth;
});

$app['controller.auth'] = $app->share(function () use ($app) {
    return new Strats\Controller\Auth($app['strava.auth'], $app['strava.client']);
});
$app->get('/', "controller.auth:login");
$app->get('/callback', "controller.auth:callback");

$app['controller.asr'] = $app->share(function () use ($app) {
    return new Strats\Controller\ActivitySegmentRanking($app['strava.client']);
});
$app->get('/activity-ranking', "controller.asr:activityRanking");

$app->run();
