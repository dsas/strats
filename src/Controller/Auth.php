<?php

namespace Strats\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Iamstuartwilson\StravaApi;

class Auth
{
    private $strava;
    private $twig;
    private $redirectUrl;

    public function __construct(StravaApi $strava, \Twig_Environment $twig, $redirectUrl)
    {
        $this->strava = $strava;
        $this->twig = $twig;
        $this->redirectUrl = $redirectUrl;
    }

    public function login(Request $request)
    {
        if ($request->getSession()->get('strava_oauth_token')) {
            // Get some details, say hello and link to stuff
            $athlete = $this->strava->getAthlete();
            $out = print_r($athlete, true);
        } else {
            $twig_data = [
                'login_url' => $this->strava->authenticationUrl(
                    $this->redirectUrl,
                    'auto',
                    'activity:read_all'
                )
            ];
            $out = $this->twig->render(
                'Login.twig',
                $twig_data
            );
        }
        $response = new Response($out);
        return $response;
    }

    public function callback(Request $request)
    {
        $auth_code = $request->query->get('code', false);
        if ($auth_code) {
            $token = $this->strava->tokenExchange($auth_code);
            $request->getSession()->set('strava_oauth_token', $token);
            return new RedirectResponse('/');
        } else {
            $error = $request->query->get('error');
            if ($error == 'access_denied') {
                // TODO: Explain to the user
            } else {
                // TODO: fail gracefully
            }
        }

        throw new NotFoundHttpException();
    }

    public function logout(Request $request)
    {
        $request->getSession()->invalidate();
        return new RedirectResponse('/');
    }
}
