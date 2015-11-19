<?php

namespace Strats\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Strava\API\OAuth;
use Strava\API\Client;

class Auth
{
    private $oauth;
    private $strava;

    public function __construct(OAuth $oauth, Client $strava)
    {
        $this->oauth = $oauth;
        $this->strava = $strava;
    }

    public function login(Request $request)
    {
        if ($request->getSession()->get('strava_oauth_token')) {
            // Get some details, say hello and link to stuff
            $athlete = $this->strava->getAthlete();
            $out = print_r($athlete, true);
        } else {
            $out = '<a href="'.$this->oauth->getAuthorizationUrl().'">connect</a>';
        }
        $response = new Response($out);
        return $response;
    }

    public function callback(Request $request)
    {
        $auth_code = $request->query->get('code', false);
        if ($auth_code) {
            $token = $this->oauth->getAccessToken(
                'authorization_code',
                ['code' => $auth_code]
            );
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
