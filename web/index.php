<?php

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

include_once __DIR__.'/../config.php';

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../app.log',
    'monolog.name' => 'strats',
));
$app['logger']->addDebug('Debug mode is ' . $app['debug']);

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__ .'/../views',
    'twig.options' => ['debug' => $app['debug'],],
]);

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
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

$app['controller.asr'] = $app->share(function () use ($app) {
    return new Strats\Controller\ActivitySegmentRanking($app['strava.client'], $app['twig']);
});

$app->get('/', "controller.auth:login")->bind('home');
$require_login = function (Symfony\Component\HttpFoundation\Request $request) {
    if (!$request->getSession()->get('strava_oauth_token')) {
        return new Symfony\Component\HttpFoundation\RedirectResponse('/login');
    }
};

$app->get('/logout', "controller.auth:logout")->bind('logout');
$app->get('/callback', "controller.auth:callback");
$app->get('/activity-ranking/{activity_id}', "controller.asr:activityRanking")->bind('activity-ranking')
    ->value('activity_id', null)
    ->before($require_login);

$app->run();
