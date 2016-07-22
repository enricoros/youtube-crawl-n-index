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

require_once __DIR__ . '/../indexer/jobs_interface.php';

define('METHOD_ADD_QUERY', 'add_query');
define('METHOD_SET_INPUT', 'queryText');

// Special: Add To Workers: used by ajax to add a query to the workers
if (isset($_GET[METHOD_ADD_QUERY])) {
    $query = $_GET[METHOD_ADD_QUERY];
    work_queueForWorkers([$query], true);
    // the following content will be returned to the Ajax call
    die('added.');
}
$startInputValue = isset($_GET[METHOD_SET_INPUT]) ? $_GET[METHOD_SET_INPUT] : '';

// Statistics
$stats = work_admin_getStats();
$queriesQueue = $stats['queued contents'];
$activeWorkers = $stats['workers active'];
$maxWorkers = $stats['workers max'];
$online = $stats['workers enabled'];
$uniqueQueries = $stats['unique queries'];
$recentQueue = $stats['recent contents'];

// to see all use '?raw'
$isAdmin = isset($_GET['raw']) || isset($_GET['xxx']);
if (!$isAdmin)
    unset($stats);
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
            <?php
            if ($online)
                echo "<span class='w-active'>enabled</span>. ";
            else
                echo "<span class='w-inactive'>DISABLED</span>. ";
            echo ($activeWorkers < 1) ? "No busy workers: " : ($activeWorkers . " are busy: ");
            echo "<&nbsp;";
            for ($i = 0; $i < $maxWorkers; $i++) {
                echo "<div class='w-block " . ($i < $activeWorkers ? "w-active" : "") . "'>" . ($i + 1) . "</div>&nbsp;";
            }
            echo ">.\n";
            ?>
            <span class="refresh-button" onclick="location.reload(); return false;">refresh</span>
        </div>
    </div>
    <br>

    <!-- Main Search Box -->
    <div class="row">
        <div class="medium-6 columns">
            <input id="query-input" type="text" placeholder="Closed captions Query ..." value="<?=$startInputValue?>">
        </div>
        <div class="medium-6 columns">
            <div id="index-button" class="button tiny round warning" onclick="addToIndex();">+Add to Crawling Queue
            </div>
            &nbsp;
            <div class="button tiny round" onclick="performSearch();">Search JAM</div>
            <div class="button tiny round" onclick="tryYoutube();">Try on Youtube</div>
        </div>
        <div class="medium-12 columns">
            Hint: to search within the close captions and index those videos use
            <i class="set-to-query">"text to search...",cc</i>.
        </div>
    </div>
    <br>

    <?php if (!empty($queriesQueue)) { ?>
        <div class="queued-commands">
            <h5>In the queue (<?= $activeWorkers ?> more are being processed)</h5>
            <ul>
                <?php foreach ($queriesQueue as $queuedQuery)
                    echo "<li class='set-to-query'>" . $queuedQuery . "</li>\n";
                ?>
            </ul>
        </div>
    <?php } ?>
    <?php if (!empty($recentQueue) && $isAdmin) { ?>
        <div class="queued-commands">
            <h5>Recent queries (executing or done) [<?=$uniqueQueries?> global unique]:</h5>
            <ul>
                <?php foreach ($recentQueue as $recentQuery)
                    echo "<li class='set-to-query'>" . $recentQuery . "</li>\n";
                ?>
            </ul>
        </div>
    <?php } ?>
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

    <?php if (isset($stats)) {?>
        <pre class='panel'><?php print_r($stats);?></pre>
    <?php }?>
</div>

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script>
    var $queryInput = $('#query-input');
    var $indexButton = $('#index-button');

    function addToIndex() {
        var query = $queryInput.val();
        if (query.length > 1) {
            var url = 'index.php?<?=METHOD_ADD_QUERY?>=' + encodeURI(query);
            $.ajax(url)
                .done(function () {
                    setTimeout(function () {
                        location.reload();
                    }, 200);
                })
                .fail(function (xhr, status, msg) {
                    alert(status + ": can't add to the index: " + msg);
                });
        }
    }

    function performSearch() {
        var query = $queryInput.val();
        if (query.length > 1)
            window.location.href = window.location.origin + '/crawler/search.php?q=' + encodeURI(query);
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
    setTimeout(function() {
        $queryInput.focus();
    }, 100);

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