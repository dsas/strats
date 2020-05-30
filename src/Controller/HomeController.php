<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Iamstuartwilson\StravaApi;

class HomeController extends AbstractController
{
    /**
     *
     * @Route("/", name="home", methods={"GET", "HEAD"})
     * @param Request $request
     * @param StravaApi $strava
     * @return Response
     */
    public function index(Request $request, StravaApi $strava)
    {
        $token = $request->getSession()->get('strava_oauth_token');
        if ($token === null) {
            return $this->redirectToRoute('login');
        }

        $strava->setAccessToken(
            $token->access_token,
            $token->refresh_token,
            $token->expires_at
        );

        $athlete = $strava->get('/athlete');
        $activities = $strava->get('/athlete/activities');

        return $this->render(
            'Home.html.twig',
            ['athlete' => $athlete, 'activities' => $activities]
        );
    }
}
