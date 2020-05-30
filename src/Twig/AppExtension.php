<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('stravatime', [$this, 'formatStravaSeconds'])
        ];
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
