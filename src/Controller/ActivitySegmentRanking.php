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
     * Outputs a leaderboard for each of the segments in an activity
     *
     * @param Request $request
     * @param integer $activity_id Null to use most recent activity
     * @return Response
     */
    public function activityRanking(Request $request, $activity_id = null)
    {
        if ($activity_id === null) {
            $summary = array_pop($this->strava->getAthleteActivities(null, null, null, 1));
            $activity_id = $summary['id'];
        }

        $athlete = $this->strava->getAthlete();

        $activity = $this->strava->getActivity($activity_id);
        $data = [];
        foreach ($activity['segment_efforts'] as $effort) {
            $segment_id = $effort['segment']['id'];

            if (array_key_exists($segment_id, $data)) {
                continue;
            }

            $segment_name = $effort['name'];

            // This is the only bit in this class that is friend specfic, could easily make this configurable for
            // different leaderboards
            $leaderboard = $this->strava->getSegmentLeaderboard($segment_id, null, null, null, true);

            $leaderboard = $this->insertActivityeffort($leaderboard, $effort, $athlete);

            $data[$segment_id] = [
                'name' => $segment_name,
                'leaderboard' => $leaderboard,
            ];
        }

        $this->twig->addFilter(new \Twig_SimpleFilter('stravatime', [$this, 'formatStravaSeconds']));

        $out = $this->twig->render(
            'ActivitySegmentRanking.twig',
            ['activity_segments' => $data, 'activity' => $activity,]
        );
        $response = new Response($out);
        return $response;
    }

    /**
     * Inserts this effort into the leaderboard at an appropriate-ish place
     *
     * Doesn't add this effort if it already exists
     *
     * @param array $leaderboard The leaderboard for a segment
     * @param array $effort The segment effort to add, as retrieved from strava API
     * @param array $athlete
     */
    private function insertActivityEffort($leaderboard, $effort, $athlete)
    {
        $insert_at = null;
        $rank = null;

        $athlete_name = $athlete['firstname'] . ' ' . mb_substr($athlete['lastname'], 0, 1) . '.';
        foreach ($leaderboard['entries'] as $place => &$entry) {
            if ($effort['start_date'] === $entry['start_date'] &&
                $athlete_name === $entry['athlete_name']) {

                $entry['current_activity'] = true;
                return $leaderboard;   // This effort is already included, return early without changes
            }

            if ($insert_at === null && $entry['elapsed_time'] >= $effort['elapsed_time']) {
                $insert_at = $place;    // TODO: Not 100% correct - 2nd sort is start_date asc & this adds before
                $rank = $entry['rank'];
            }

            if ($insert_at !== null && $entry['elapsed_time'] != $effort['elapsed_time']) {
                $entry['rank']++;
            }
        }

        $effort_as_entry = [
            "athlete_name" => $athlete_name,
            "athlete_id" => $athlete['id'],
            "athlete_gender" => $athlete['sex'],
            // Docs for activity doesn't mention hr or watts but says the effort is a effort summary which does
            // the activity docs are correct.
            "average_hr" => null,
            "average_watts" => null,
            "distance" => $effort['distance'],
            "elapsed_time" => $effort['elapsed_time'],
            "moving_time" => $effort['moving_time'],
            "start_date" => $effort['start_date'],
            "start_date_local" => $effort['start_date_local'],
            "activity_id" => $effort['activity']['id'],
            "effort_id" => $effort['id'],
            "rank" => $rank,
            "athlete_profile" => $athlete['profile'],
            "current_activity" => true,
        ];

        if ($insert_at === null) {
            $leaderboard['entries'][] = $effort_as_entry;
        } else {
            array_splice($leaderboard['entries'], $insert_at, 0, [$effort_as_entry]);
        }

        return $leaderboard;
    }

    public function formatStravaSeconds($seconds)
    {
        $hours = floor($seconds / 60 / 60);
        $minutes = floor(($seconds - ($hours * 60 * 60)) / 60);
        $seconds = $seconds - (($hours * 60 * 60) + ($minutes * 60));

        if ($hours) {
            return sprintf("%s:%s:%02s", $hours, $minutes, $seconds);
        } elseif ($minutes) {
            return sprintf("%s:%02s", $minutes, $seconds);
        } else {
            return "$seconds seconds";
        }
    }
}
