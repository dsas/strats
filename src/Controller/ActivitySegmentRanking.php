<?php

namespace Strats\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Strava\API\Client;

class ActivitySegmentRanking
{
    private $strava;

    public function __construct(Client $strava)
    {
        $this->strava = $strava;
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
            // TODO: Allow a particular activity to be chosen
            $summary = array_pop($this->strava->getAthleteActivities(null, null, null, 1));
            $detail = $this->strava->getActivity($summary['id']);
            $data = [];
            foreach ($detail['segment_efforts'] as $effort) {
                $data[$effort['segment']['id']][] = $this->extractEffortDetails($effort);
            }

            // 200 friends is enough for anyone...
            foreach ($this->strava->getAthleteFriends(null, null, 200) as $friend) {
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
                    $data[$segment_id][] = $this->extractEffortDetails($fastest);

                    // TODO: The friends most recent would be interesting too.
                }
            }

            foreach ($data as &$segment_efforts) {
                usort($segment_efforts, [$this, 'sortSegmentEffortsByDuration']);
            }
            unset($segment_efforts);
        }
        $out = '';
        foreach ($data as $segment_id => $segment_efforts) {
            $out .= "<h2>$segment_id</h2><ol>";
            foreach ($segment_efforts as $effort) {
                $out .= "<li>";
                $out .= $effort['athlete_id'] . " did it in " . $effort['duration'];
                $out .= "</li>";
            }
            $out .= "</ol>";
        }
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