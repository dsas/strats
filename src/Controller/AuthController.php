<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Iamstuartwilson\StravaApi;

class AuthController extends AbstractController
{
    /**
     * @Route("/login", name="login", methods={"GET", "HEAD"})
     */
    public function login(Request $request, StravaApi $strava)
    {
        if ($request->getSession()->has('strava_oauth_token')) {
            // Already logged in, redirect them
            throw new Exception('FIX ME');
        }

        $twig_data = [
            'login_url' => $strava->authenticationUrl(
                $this->generateUrl('strava_auth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'auto',
                'activity:read_all'
            )
        ];

        return $this->render(
            'Login.html.twig',
            $twig_data
        );
    }

    /**
     * @Route("/callback", name="strava_auth_callback", methods={"GET", "HEAD"})
     */
    public function callback(Request $request, StravaApi $strava)
    {
        $auth_code = $request->query->get('code', false);
        if ($auth_code) {
            $token = $strava->tokenExchange($auth_code);
            $request->getSession()->set('strava_oauth_token', $token);
            return $this->redirectToRoute('home');
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

    /**
     * @Route("/logout", name="logout", methods={"GET", "HEAD"})
     */
    public function logout(Request $request)
    {
        $request->getSession()->invalidate();
        return new RedirectResponse('/');
    }
}
