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
const VIDEOS_PER_QUERY = 100;
const FORCE_REINDEX = false;

// quickly bail out if there is no job for me
require_once 'jobs_interface.php';
$workingQuery = work_getOneForMe();
if ($workingQuery == null)
    die("no jobs for me\n");

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'YTMachine.php';
require_once 'IndexMachine_Algolia.php';

// create the global objects
$ytMachine = new \YTMachine();
$indexMachine = new \IndexMachine_Algolia(isset($_GET['index']) ? 'yt_' . $_GET['index'] : '');

// loop until there's work
do {
    // let's go with the current query
    echo "crawling for: " . $workingQuery . "\n";
    $someQueries = [ $workingQuery ];

    // search youtube for N queries, for M (4) ordering criteria
    /* @var $videoLeads YTVideo[] */
    $videoLeads = [];
    $orders = ['relevance', 'viewCount', 'rating', 'date'];
    foreach ($someQueries as $query) {
        foreach ($orders as $order) {

            // search for the current Query, using each of the 4 criteria...
            $criteria = new YTSearchCriteria(trim($query));
            $criteria->setOrder($order);
            $videos = $ytMachine->searchVideos($criteria, VIDEOS_PER_QUERY);
            // ...and merge into 1 list
            $ytMachine->mergeUniqueVideos($videoLeads, $videos);

        }
    }
    echo 'found ' . sizeof($videoLeads) . " yt video leads.\n";
    echo "processing:\n[";

    // process each video: get caption, get details, send to index
    /* @var $videoLeads YTVideo[] */
    $newVideos = [];
    $n = 1;
    shuffle($videoLeads);
    foreach ($videoLeads as $video) {
        // (cosmetic) add some page breaks
        if ($n++ >= 50) {
            $n = 0;
            echo ",\n";
        }

        // OPTIMIZATION: skip resolving if we already did it in the past and
        // the video has already been indexed (or we know it can't be).
        $videoUsableKey = 'use_' . $video->videoId;
        if (!FORCE_REINDEX && CacheMachine::hasKey($videoUsableKey)) {
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
        if (!$indexMachine->addOrUpdate($video->videoId, $video)) {
            echo 'S';
            CacheMachine::storeValue($videoUsableKey, false, null);
            continue;
        }

        // video processed, all details are present, subtitles downloaded and indexed
        array_push($newVideos, $video);
        CacheMachine::storeValue($videoUsableKey, true, null);
        echo '.';
    }
    echo "]\n";
    echo 'indexed: ' . sizeof($newVideos) . "\n";

    // done
    work_doneWithMyCurrent();

} while ($workingQuery = work_getOneForMe());
