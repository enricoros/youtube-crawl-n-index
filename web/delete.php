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

require_once __DIR__ . '/../indexer/IndexMachine_Algolia.php';

define('METHOD_DEL_CHANNEL', 'del_channelId');
define('METHOD_DEL_OBJECT', 'del_objectId');

$isActive = isset($_GET[METHOD_DEL_CHANNEL]) || isset($_GET[METHOD_DEL_OBJECT]);

$isAdmin = isset($_GET['raw']) || isset($_GET['xxx']);
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
    <h3 class="row">Indexer Deletion</h3>
</div>

<div class="row" style="margin-top: 1em;">

    <!-- Main Operations Box -->
    <div class="row">
        <div class="medium-6 columns">
            <input id="query-input1" type="text" placeholder="Video Channel Id ...">
        </div>
        <div class="medium-6 columns">
            <div id="index-button1" class="button tiny round warning">Delete by ChannelId</div>
        </div>
    </div>
    <div class="row">
        <div class="medium-6 columns">
            <input id="query-input2" type="text" placeholder="Video Object Id ...">
        </div>
        <div class="medium-6 columns">
            <div id="index-button2" class="button tiny round warning">Delete by ObjectId</div>
        </div>
    </div>
    <br>

    <div class="queued-commands">
        <?php
        if ($isActive) {
            $indexMachine = new \IndexMachine_Algolia(isset($_GET['index']) ? 'yt_' . $_GET['index'] : '');
            $index = $indexMachine->getIndex();
        }
        if (isset($_GET[METHOD_DEL_CHANNEL])) {
            $channelId = trim($_GET[METHOD_DEL_CHANNEL]);
            $answer = $index->search($channelId, [
                'hitsPerPage' => 50,
                'restrictSearchableAttributes' => ['channelId'],
                'attributesToRetrieve' => [
                    'title', 'description', 'publishedAt', 'thumbUrl', 'desktopUrl',
                    'language', 'duration', 'channelTitle',
                    'countViews', 'countComments', 'countLikes', 'countDislikes', 'countFavorites'
                ],
                'attributesToHighlight' => [],
                'typoTolerance' => false,
                'queryType' => 'prefixNone',
                'advancedSyntax' => true
            ]);
            if ($answer['nbHits'] > 1000)
                die("<h4>Too many hits, stopping the destruction.</h4>");

            $hits = $answer['hits'];
            echo "<h4>Deleting " . sizeof($hits) . " out of " . $answer['nbHits'] . "</h4>";

            echo "<ul>";
            foreach ($hits as $hit) {
                $objectID = $hit['objectID'];
                $index->deleteObject($objectID);
                echo "<li>" . $objectID . ": " . $hit['title'] . "</li>\n";
            }
            echo "</ul>";
        }
        if (isset($_GET[METHOD_DEL_OBJECT])) {
            $objectID = trim($_GET[METHOD_DEL_OBJECT]);
            if (empty($objectID))
                die('<h4>This Object ID seems invalid.</h4>');
            $answer = $index->deleteObject($objectID);
            echo '<h5>Done for ' . $objectID . '. Hopefully.</h5>';
        }
        ?>
        <pre>
            <?php if ($isAdmin) print_r($answer); ?>
        </pre>
    </div>
</div>

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script>
    function link(inputId, buttonId, method) {
        $input = $('#' + inputId);
        $button = $('#' + buttonId);
        $input.keyup(function (event) {
            if (event.keyCode == 13)
                $button.click();
        });
        $button.click(function () {
            var query = $input.val();
            if (query.length > 1)
                location.search = method + '=' + encodeURI(query);
        });
    }
    link('query-input1', 'index-button1', '<?=METHOD_DEL_CHANNEL?>');
    link('query-input2', 'index-button2', '<?=METHOD_DEL_OBJECT?>');
</script>

</body>
</html>