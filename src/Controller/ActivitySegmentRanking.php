<?php

namespace Strats\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Strava\API\Client;

class ActivitySegmentRanking
{
    private $strava;

    private $twig;

    public function __construct(Client $strava, \Twig_Environment $twig)
    {
        $this->strava = $strava;
        $this->twig = $twig;
    }

    /**
     * Outputs a league table for an athlete's friends using the last segments
     * There's already an API call for the leaderboard
     *
     * @param Request $request
     * @return Response
     */
    public function activityRanking(Request $request)
    {
        if ($request->getSession()->get('strava_oauth_token')) {
            $athlete = $this->strava->getAthlete();
            // TODO: Allow a particular activity to be chosen
            $summary = array_pop($this->strava->getAthleteActivities(null, null, null, 1));
            $detail = $this->strava->getActivity($summary['id']);
            $data = [];
            foreach ($detail['segment_efforts'] as $effort) {
                $segment_id = $effort['segment']['id'];
                $segment_name = $effort['name'];
                $effort = $this->extractEffortDetails($effort);
                $effort['athlete_name'] = $athlete['firstname'] . ' ' . $athlete['lastname'];
                $data[$segment_id] = [
                    'name' => $segment_name,
                    'efforts' => [$effort],
                ];
            }

            // 200 friends is enough for anyone...
            $friends = $this->strava->getAthleteFriends(null, null, 200);
            foreach ($friends as $friend) {
                foreach (array_keys($data) as $segment_id) {
                    $efforts = $this->strava->getSegmentEffort(
                        $segment_id,
                        $friend['id'],
                        null,
                        null,
                        null,
                        200
                    );

                    if (!$efforts) {
                        continue;
                    }

                    // results already ordered by elapsed_time
                    $fastest = $efforts[0];
                    $fastest = $this->extractEffortDetails($fastest);
                    $fastest['athlete_name'] = $friend['firstname'] . ' ' . $friend['lastname'];
                    $data[$segment_id]['efforts'][] = $fastest;

                    // TODO: The friends most recent would be interesting too.
                }
            }

            foreach ($data as &$segment) {
                foreach ($segment as &$segment_efforts) {
                    usort($segment_efforts, [$this, 'sortSegmentEffortsByDuration']);
                }
            }
            unset($segment_efforts);
        }

        $out = $this->twig->render(
            'ActivitySegmentRanking.twig',
            ['activity_segments' => $data, 'activity' => $summary,]
        );
        $response = new Response($out);
        return $response;
    }

    /**
     * Cuts down segment efforts to just the necessary attribs
     * @param array $effort
     * @return array
     */
    private function extractEffortDetails($effort)
    {
        return [
            'effort_id' => $effort['id'],
            'athlete_id' => $effort['athlete']['id'],
            'duration' => $effort['elapsed_time'],
            'start' => $effort['start_date_local'],
        ];
    }

    /**
     * Sorts segment efforts by duration then by start date
     * @param array $effort1
     * @param array $effort2
     * @return integer
     */
    private function sortSegmentEffortsByDuration($effort1, $effort2)
    {
        if ($effort1['duration'] == $effort2['duration']) {
            if ($effort1['start'] == $effort2['start']) {
                return 0;
            }
            return $effort1['duration'] > $effort2['duration'] ? 1 : -1;
        }
        return $effort1['duration'] > $effort2['duration'] ? 1 : -1;
    }
}
