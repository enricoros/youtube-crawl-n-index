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
require_once 'Cacher.php';
require_once 'YTMachine.php';

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
<form method="GET">
    <input type="text" id="queryText" name="queryText" placeholder="search outside...">
    <input type="text" id="captionText" name="captionText" placeholder="search inside...">
    <input type="submit" value="GO!">
</form>

<?php
// create the YouTube Machine
$yt = new \YTMachine();

/* @var $videoLeads YTVideo[] */
/* @var $goodVideos YTVideo[] */
$videoLeads = [];

// NEED a Twitter pump here... we need serious memes
$someQueries = [
    'Steve Jobs', 'Obama', 'Elon Musk',
    'Donald Trump', 'PewDiePie', 'fails 2016',
    'cursing', 'loses control'
];

// use the query from the web page, if set
if (isset($_GET['queryText']))
    $someQueries = explode(',', $_GET['queryText']);

foreach ($someQueries as $query) {

    $criteria = new YTSearchCriteria(trim($query));
    $videos = $yt->searchVideos($criteria, 10);
    $yt->mergeUniqueVideos($videoLeads, $videos);

}

// fetch all the captions (and also more details for videos with captions.. and drop the rest)
$goodVideos = [];
foreach ($videoLeads as $video) {

    if ($video->resolveCaptions()) {
        $video->resolveDetails();
        array_push($goodVideos, $video);
        echo '.';
    } else
        echo 'x';

}

$yt->sortVideos($goodVideos, 'disliked');

if (isset($_GET['captionText']) && !empty($_GET['captionText'])) {
    $goodVideos = array_filter($goodVideos, function ($video) {
        return stripos(strval(json_encode($video->ytCC->xml)), $_GET['captionText']);
    });
}

//echo 'ok: ' . sizeof($goodVideos) . ' over: ' . sizeof($videoLeads) . "\n";
?>
test
<?php
foreach ($goodVideos as $video) {
    echo '<br><img src="' . $video->thumbUrl . '" width="40"  /><br><predator>' . /*strval(json_encode($video->ytCC->xml)) .*/'</predator>';
}
?>
test
</body>
</html>
