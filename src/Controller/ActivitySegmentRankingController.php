<?php

namespace App\Controller;

use Iamstuartwilson\StravaApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActivitySegmentRankingController extends AbstractController
{
    /**
     * Outputs a leaderboard for each of the segments in an activity
     *
     * @Route("/activity-ranking/{activity_id}", name="activity", methods={"GET", "HEAD"})
     * @param Request $request
     * @param integer $activity_id Null to use most recent activity
     * @return Response
     */
    public function activityRanking(Request $request, StravaApi $strava, $activity_id = null)
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

        if ($activity_id === null) {
            $activities = $strava->get('/athlete/activities?oer_page=1');
            $summary = end($activities);
            $activity_id = $summary->id;
        }

        $athlete = $strava->get('/athlete');

        $activity = $strava->get('/activities/' . $activity_id);
        $data = [];
        foreach ($activity->segment_efforts as $effort) {
            $segment_id = $effort->segment->id;

            if (array_key_exists($segment_id, $data)) {
                continue;
            }

            $segment_name = $effort->name;

            // This is the only bit in this class that is friend specfic, could easily make this configurable for
            // different leaderboards
            $leaderboard = $strava->get("/segments/${segment_id}/leaderboard?following=1");

            $leaderboard = $this->insertActivityeffort($leaderboard, $effort, $athlete);

            $data[$segment_id] = [
                'name' => $segment_name,
                'leaderboard' => $leaderboard,
            ];
        }

        return $this->render(
            'ActivitySegmentRanking.html.twig',
            ['activity_segments' => $data, 'activity' => $activity,]
        );
    }

    /**
     * Inserts this effort into the leaderboard at an appropriate-ish place
     *
     * Doesn't add this effort if it already exists
     *
     * @param object $leaderboard The leaderboard for a segment
     * @param object $effort The segment effort to add, as retrieved from strava API
     * @param object $athlete
     */
    private function insertActivityEffort($leaderboard, $effort, $athlete)
    {
        $insert_at = null;
        $rank = null;

        $athlete_name = $athlete->firstname . ' ' . mb_substr($athlete->lastname, 0, 1) . '.';
        foreach ($leaderboard->entries as $place => &$entry) {
            $same_name = $athlete_name === $entry->athlete_name;
            $entry->current_athlete = $same_name;

            if ($effort->start_date === $entry->start_date && $same_name) {
                $entry->current_activity = true;
                return $leaderboard;   // This effort is already included, return early without changes
            }

            if ($insert_at === null && $entry->elapsed_time >= $effort->elapsed_time) {
                $insert_at = $place;    // TODO: Not 100% correct - 2nd sort is start_date asc & this adds before
                $rank = $entry->rank;
            }

            if ($insert_at !== null && $entry->elapsed_time != $effort->elapsed_time) {
                $entry->rank++;
            }
        }

        $effort_as_entry = (object) [
            "athlete_name" => $athlete_name,
            "athlete_id" => $athlete->id,
            "athlete_gender" => $athlete->sex,
            // Docs for activity doesn't mention hr or watts but says the effort is a effort summary which does
            // the activity docs are correct.
            "average_hr" => null,
            "average_watts" => null,
            "distance" => $effort->distance,
            "elapsed_time" => $effort->elapsed_time,
            "moving_time" => $effort->moving_time,
            "start_date" => $effort->start_date,
            "start_date_local" => $effort->start_date_local,
            "activity_id" => $effort->activity->id,
            "effort_id" => $effort->id,
            "rank" => $rank,
            "athlete_profile" => $athlete->profile,
            "current_activity" => true,
            "current_athlete" => true,
        ];

        if ($insert_at === null) {
            $leaderboard->entries[] = $effort_as_entry;
        } else {
            array_splice($leaderboard->entries, $insert_at, 0, [$effort_as_entry]);
        }

        return $leaderboard;
    }
}
