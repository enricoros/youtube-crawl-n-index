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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../indexer/jobs_interface.php';

define('METHOD_SEARCH_QUERY', 'q');

$query = isset($_GET[METHOD_SEARCH_QUERY]) ? $_GET[METHOD_SEARCH_QUERY] : '';

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Feed the Trolls</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/foundation.min.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
<div class="header">
    <h3 class="row">Crawling + Indexing Control</h3>
</div>

<div class="row" style="margin-top: 1em;">
    <!-- systems status panel -->
    <div class="panel radius">
        <div>Crawlers and Indexers are
            <pre>
            <?php
            if ($query != '') {
                require_once __DIR__ . '/../indexer/IndexMachine_ElasticSearch.php';
                $indexMachine = new \IndexMachine_ElasticSearch(['173.230.144.120:9199']);

                $search = $indexMachine->search($query, false, 200, 4);
                $jString = json_encode($search, JSON_PRETTY_PRINT);
                echo $jString;
            }
            ?>
            </pre>

            <?php
//            for ($i = 0; $i < $maxWorkers; $i++) {
//                echo "<div class='w-block " . ($i < $activeWorkers ? "w-active" : "") . "'>" . ($i + 1) . "</div>&nbsp;";
//            }
            ?>
            <span class="refresh-button" onclick="location.reload(); return false;">refresh</span>
        </div>
    </div>
    <br>

    <!-- Main Search Box -->
    <div class="row">
        <div class="medium-6 columns">
            <input id="query-input" type="text" placeholder="Closed captions Query ..." value="<?=$query?>">
        </div>
        <div class="medium-6 columns">
            <div id="index-button" class="button tiny round warning" onclick="addToIndex();">+Add to Crawling Queue
            </div>
            &nbsp;
            <div class="button tiny round" onclick="tryYoutube();">Try on Youtube</div>
        </div>
        <div class="medium-12 columns">
            Hint: to search within the close captions and index those videos use
            <i class="set-to-query">"text to search...",cc</i>.
        </div>
    </div>
    <br>

    <!-- unneeded 'what we need to add' section -->
    <div class="row">
        <div class="medium-6 columns">
            <h5>What we need to add:</h5>
            <ul>
                <li>
                    <i class="set-to-query">cult movies</i>, <i class="set-to-query">tv shows</i>,
                    <i class="set-to-query">youtube channels</i>, <i class="set-to-query">cartoons</i>,
                    <i class="set-to-query">memes</i>
                </li>
                <li>
                    <i class="set-to-query">science</i>, <i class="set-to-query">technology</i>,
                    <i class="set-to-query">entertainment</i>, <i class="set-to-query">funny</i>,
                    <i class="set-to-query">futuristic</i>
                </li>
                <li>
                    <i class="set-to-query">Steve Jobs</i>, <i class="set-to-query">Obama</i>,
                    <i class="set-to-query">Elon Musk</i>, <i class="set-to-query">Donald Trump</i>,
                    <i class="set-to-query">PewDiePie</i>
                </li>
            </ul>
        </div>
        <div class="medium-6 columns">
            <h5>To remember:</h5>
            <ul>
                <li>570 wifi base stations</li>
                <li>you are going to fail</li>
            </ul>
        </div>
    </div>

    <!-- footer -->
    <div style="color: #888">
        Each query is searched 4 times by { relevance, viewCount, rating, and date }.<br>
        Search is restricted to videos that are: { close captioned, HD, embeddable, in english }.<br>
        Safe-search is off.
    </div>
    <br>
</div>

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script>
    var $queryInput = $('#query-input');
    var $indexButton = $('#index-button');

    function addToIndex() {
    }

    // enter on query to add to index
    $queryInput.keyup(function (event) {
        if (event.keyCode == 13)
            $indexButton.click();
    });

    // automatic set to query on .set-to-query elements
    $('.set-to-query').click(function () {
        $queryInput.val($(this).text());
    });

    // try to focus the search box asap
//    setTimeout(function() {
//        $queryInput.focus();
//    }, 100);

    function tryYoutube() {
        var query = $queryInput.val();
        if (query.length > 1) {
            // open the new window
            var url = 'https://www.youtube.com/results?search_query=' + encodeURI(query);
            var win = window.open(url, '_blank');
            win.focus();
        }
    }
</script>

</body>
</html>