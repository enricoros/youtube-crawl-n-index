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
?>

<!doctype html>
<html>
<head>
    <title>Feed the Trolls</title>
    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/normalize.css">
</head>
<body>
<h3>Crawling + Indexing Control</h3>

<section>

    <p>
        This page will add new captioned videos to the overall index (if not already present).<br>
        Enter a comma-separated list of query snippets below, and the corresponding videos will be
        indexed in the minutes after.<br>
        Each 'query' is searched 4 times, by 'relevance', 'viewCount', 'rating', and 'date'.<br>
        Search is restricted to video that are: Close Captioned, HD, embeddable, 'en'glish,. Safesearch Off.<br>
    </p>
    
    <form class="live-search" onsubmit="fullSearch(); return false;">
        <div class="medium-9 columns">
            <input type="text" name="query">
        </div>
        <div class="medium-3 columns">
            <input class="button" type="submit" value="Jam It Up">
        </div>
        <div class="medium-12 columns" style="margin-top: 0.5em">
            <input type="checkbox" name="exact" id="chk-exact" checked="">
            <label for="chk-exact">Exact match</label>
        </div>
        <div id="go-container" class="medium-12 columns" style="margin-top: 0.5em; display: none">
            <div class="button" onclick="playVideoReel()">GROOVE NEXT</div>
        </div>
    </form>

    <form method="GET" >
        <input type="text" id="queryText" name="queryText"
               placeholder="search videos..." <?php if (isset($_GET['queryText'])) echo 'value="' . $_GET['queryText'] . '"'; ?>>
        <input type="submit" value="GO!">
    </form>
    <ul>
        <li>cult movies, tv shows, youtube channels, cartoons, memes</li>
        <li>science, technology, entertainment, funny, futuristic, chemistry, physics</li>
        <li>Steve Jobs, Obama ,Elon Musk, Donald Trump, PewDiePie</li>
        <li>570 wifi base stations</li>
        <li>you are going to fail</li>
        <li>...</li>
    </ul>


    <pre>
<?php


?>
    </pre>
</section>

</body>
</html>
