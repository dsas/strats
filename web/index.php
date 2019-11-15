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

$app->extend('twig', function ($twig, $app) {
    $twig->addGlobal('is_logged_in', $app['session']->has('strava_oauth_token'));
    return $twig;
});

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app['strava.client'] = $app->share(function () use ($app) {
    $api = new Iamstuartwilson\StravaApi(
        $app['strava.auth.config']['clientId'],
        $app['strava.auth.config']['clientSecret']
    );
    $token = $app['session']->get('strava_oauth_token');
    $api->setAccessToken(
        $token->access_token,
        $token->refresh_token,
        $token->expires_at
    );
    return $api;
});

$app['controller.home'] = $app->share(function () use ($app) {
    return new Strats\Controller\Home($app['strava.client'], $app['twig']);
});

$app['controller.auth'] = $app->share(function () use ($app) {
    return new Strats\Controller\Auth(
        $app['strava.client'],
        $app['twig'],
        $app['strava.auth.config']['redirectUri']
    );
});

$app['controller.asr'] = $app->share(function () use ($app) {
    return new Strats\Controller\ActivitySegmentRanking($app['strava.client'], $app['twig']);
});

$require_login = function (Symfony\Component\HttpFoundation\Request $request) {
    if (!$request->getSession()->get('strava_oauth_token')) {
        return new Symfony\Component\HttpFoundation\RedirectResponse('/login');
    }
};

$app->get('/', "controller.home:index")->bind('home')
                                       ->before($require_login);
$app->get('/login', "controller.auth:login")->bind('login');
$app->get('/logout', "controller.auth:logout")->bind('logout');
$app->get('/callback', "controller.auth:callback");
$app->get('/activity-ranking/{activity_id}', "controller.asr:activityRanking")->bind('activity-ranking')
    ->value('activity_id', null)
    ->before($require_login);

$app->run();
