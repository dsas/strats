<?php

namespace Strats\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Iamstuartwilson\StravaApi;

class Home
{
    private $strava;

    private $twig;

    public function __construct(StravaApi $strava, \Twig_Environment $twig)
    {
        $this->strava = $strava;
        $this->twig = $twig;
    }

    /**
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $athlete = $this->strava->get('/athlete');
        $activities = $this->strava->get('/athlete/activities');

        $out = $this->twig->render(
            'Home.twig',
            ['athlete' => $athlete, 'activities' => $activities]
        );
        $response = new Response($out);
        return $response;
    }
}
