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

// configuration
const SKIP_ALREADY_INDEXED = true;
const VIDEOS_PER_QUERY = 100;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'CacheMachine.php';
require_once 'YTMachine.php';
require_once 'IndexMachine_Algolia.php';
session_start();
?>

<!doctype html>
<html>
<head>
    <title>Feed the indexer</title>
    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/normalize.css">
</head>
<body>
<p>
    Note: this page will add new videos to the overall index, if none are returned, it means that
    probably they have already been indexed.
</p>
<form method="GET">
    <input type="text" id="queryText" name="queryText" placeholder="search outside...">
    <input type="text" id="captionText" name="captionText" placeholder="search inside...">
    <input type="submit" value="GO!">
</form>
<div>
    Some Queries:
    <ul>
        <li>570 wifi base stations</li>
        <li>you are going to fail</li>
        <li>...</li>
    </ul>
</div>


<?php
/* @var $videoLeads YTVideo[] */
/* @var $newVideos YTVideo[] */

// create the global objects
$yt = new \YTMachine();
$im = new \IndexMachine_Algolia();

// A. start with the YT search query

// NEED a Twitter pump here... we need serious memes
$someQueries = [
    'Steve Jobs', 'Obama', 'Elon Musk',
    'Donald Trump', 'PewDiePie',
    'loses control'
];

// use the query from the web page, if set
if (isset($_GET['queryText']))
    $someQueries = explode(',', $_GET['queryText']);


// B. perform the YT search

// for all the query strings, search N videos, for M orders
$videoLeads = [];
$orders = [ 'relevance', 'viewCount', 'rating', 'date' ];
foreach ($someQueries as $query) {

    foreach ($orders as $order) {

        $criteria = new YTSearchCriteria(trim($query));
        $criteria->setOrder($order);
        $videos = $yt->searchVideos($criteria, VIDEOS_PER_QUERY);
        $yt->mergeUniqueVideos($videoLeads, $videos);

    }

}
shuffle($videoLeads);
echo '<div>processing ' . sizeof($videoLeads) . ' video leads' . "</div>\n";


// C. process each video: get caption, get details, send to index

echo '[';
$newVideos = [];
foreach ($videoLeads as $video) {

    // OPTIMIZATION: skip resolving if we already did it in the past and
    // the video has already been indexed (or we know it can't be).
    $videoUsableKey = 'use_' . $video->videoId;
    if (SKIP_ALREADY_INDEXED && CacheMachine::hasKey($videoUsableKey)) {
        echo ' ';
        continue;
    }

    // resolve the captions, and skip if failed
    if (!$video->resolveCaptions()) {
        echo 'C';
        CacheMachine::storeValue($videoUsableKey, false, null);
        continue;
    }

    // also resolve details: numbers of views, etc.
    $video->resolveDetails();

    // send it to the Index (to be indexed)
    if (!$im->addOrUpdate($video->videoId, $video))
        echo 'S';

    // video processed
    array_push($newVideos, $video);
    CacheMachine::storeValue($videoUsableKey, true, null);
    echo '.';

}
echo "]\n";


// TEMP: manual filter just for rendering in the web page - will replace this with Index search
if (isset($_GET['captionText']) && !empty($_GET['captionText'])) {
    $newVideos = array_filter($newVideos, function ($video) {
        return stripos(strval(json_encode($video->ytCC->xml)), $_GET['captionText']);
    });
}

// TEMP: play emotions :)
$yt->sortVideos($newVideos, 'disliked');

// TEMP: show images of the newly fetched
echo '<div>new and usable: ' . sizeof($newVideos) . ' over: ' . sizeof($videoLeads) . "</div>\n";
foreach ($newVideos as $video)
    echo '<div><img src="' . $video->thumbUrl . '" width="140"  />' . /*strval(json_encode($video->ytCC->xml)) .*/"</div>\n";
?>

</body>
</html>
