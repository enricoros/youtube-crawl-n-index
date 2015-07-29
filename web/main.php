<?php
/* Copyright 2015, Enrico Ros

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License. */

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'YTMachine.php';

session_start();

// create the YouTube Machine
$yt = new \YTMachine();

/* @var $videoLeads YTVideo[] */
$videoLeads = [];

// NEED a Twitter pump here... we need serious memes
$someQueries = [
    'Steve Jobs', 'Obama', 'Elon Musk',
    'Donald Trump', 'PewDiePie', 'fails 2016',
    'cursing', 'loses control'
];
foreach ($someQueries as $query) {

    $criteria = new YTSearchCriteria($query);
    $videos = $yt->searchVideos($criteria, 50);
    $yt->mergeUniqueVideos($videoLeads, $videos);

}

// fetch all the captions (and also more details for videos with captions.. and drop the rest)
$goodVideos = [];
foreach ($videoLeads as $video) {

    $video->resolveCaptions();
    if ($video->ytCC != null) {
        $video->resolveDetails();
        array_push($goodVideos, $video);
        echo '.';
    } else
        echo 'x';

}
// sort videos by views...
/*usort($goodVideos, function ($a, $b) {
    return $b->countViews - $a->countViews;
});*/

// ...or sort videos by dislikes! (trolling the trolls here :)
usort($goodVideos, function ($a, $b) {
    return $b->countDislikesPct - $a->countDislikesPct;
});

echo 'ok: ' . sizeof($goodVideos) . ' over: ' . sizeof($videos) . "\n";
