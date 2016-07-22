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
    <h3 class="row">Search jam::ccv3</h3>
</div>

<div class="row" style="margin-top: 1em;">

    <!-- Main Search Box -->
    <div class="row">
        <div class="medium-6 columns">
            <input id="query-input" type="text" placeholder="Search for the jam ..." value="<?= $query ?>">
        </div>
        <div class="medium-6 columns">
            <div id="index-button" class="button tiny round warning" onclick="performSearch();">Search</div>
            <span class="button tiny round" onclick="location.reload(); return false;">refresh</span>
        </div>
    </div>

    <!-- unneeded 'what we need to add' section -->
    <div class="row">
        <div class="medium-12 columns">
            <h5>Search for:</h5>
            <ul>
                <li>
                    <i class="set-to-query">I love you back</i>, <i class="set-to-query">knock knock knock</i>,
                    <i class="set-to-query">570 wifi base stations</i>, <i class="set-to-query">rapists</i>,
                    <i class="set-to-query">you are going to fail</i>
                </li>
            </ul>
        </div>
    </div>

    <!-- search outputpanel -->
    <div class="panel radius">
        <div>
            <?php
            if ($query != '') {
                require_once __DIR__ . '/../indexer/IndexMachine_ElasticSearch.php';
                $indexMachine = new \IndexMachine_ElasticSearch(['173.230.144.120:9199']);

                $search = $indexMachine->search($query, true, 200, 4);
                $strictSnipperCount = $search['stats']['snippetsCount'];
                if ($strictSnipperCount < 10) {
                    echo 'Repeating the search but with more relax, since we only got ' . $strictSnipperCount . ' ..';
                    $search = $indexMachine->search($query, false, 200, 4);
                }

                $jString = json_encode($search, JSON_PRETTY_PRINT);
                echo '<pre>' . $jString . '</pre>';
            } else {
                echo "Search output";
            }
            //            for ($i = 0; $i < $maxWorkers; $i++) {
            //                echo "<div class='w-block " . ($i < $activeWorkers ? "w-active" : "") . "'>" . ($i + 1) . "</div>&nbsp;";
            //            }
            ?>
        </div>
    </div>
</div>

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script>
    var $queryInput = $('#query-input');
    var $indexButton = $('#index-button');

    function performSearch() {
        var query = $queryInput.val();
        if (query.length > 1)
            window.location.search = '<?=METHOD_SEARCH_QUERY?>=' + encodeURI(query);
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
    setTimeout(function () {
        $queryInput.focus();
    }, 100);
</script>

</body>
</html>