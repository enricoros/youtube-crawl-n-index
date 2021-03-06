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

define('YT_VIOLENT', true);
define('YT_VERBOSE', false);
define('YT_LANG_FILTER', 'en');
define('YT_REGION_FILTER', 'US');
define('YT_MIN_VALID_CHARS', 4);    // chars per line
define('YT_MAX_VALID_CHARS', 400);  // chars per line
define('YT_MAX_VALID_DURATION', 20);// seconds
define('YT_MIN_VALID_LINES', 5);    // lines per CC


class YTMachine
{
    // cloud console data
    const SERVICE_ACCOUNT_OAUTH_FILE = '/../../crawler-key-gapi.json';

    // list of permissions we need
    private $scopes = [Google_Service_YouTube::YOUTUBE_FORCE_SSL];

    function __construct()
    {
        self::$googleClient = new Google_Client();
        self::$googleClient->setAuthConfig(__DIR__ . self::SERVICE_ACCOUNT_OAUTH_FILE);
        self::$googleClient->setScopes($this->scopes);
    }

    /**
     * @param $criteria YTSearchCriteria
     * @param $maxCount integer We'll do the searches in small batches, but there may be endless videos.
     * Limit the search to 200 for example.
     * @return YTVideo[]
     */
    function searchVideos($criteria, $maxCount)
    {
        $allVideos = [];
        $nextPageToken = null;
        do {
            // when looping, set the ID of the next page
            $criteria->setPage($nextPageToken);
            // try to go with the right size (will be paginated automatically)
            $criteria->setResultsPageSize($maxCount - sizeof($allVideos));

            $listResults = self::getYoutube()->search->listSearch('id,snippet', $criteria->getArray());

            if (YT_VERBOSE && $nextPageToken == null) {
                $totalResults = $listResults->getPageInfo()->totalResults;
                $perPageResults = $listResults->getPageInfo()->resultsPerPage;
                echo 'Loading ' . $maxCount . ' results from ' . $totalResults . ' in batches of '
                    . $perPageResults . ' for query ' . $criteria->getQuery() . "\n Criteria: ";
                print_r($criteria->getArray());
            }

            // parse the list of results for per-video information
            /* @var $listItems Google_Service_YouTube_SearchResult[] */
            $listItems = $listResults->getItems();
            foreach ($listItems as $item) {
                /* @var $id Google_Service_YouTube_ResourceId */
                $id = $item->getId();
                /* @var $snippet Google_Service_YouTube_SearchResultSnippet */
                $snippet = $item->getSnippet();
                /* @var $thumbs Google_Service_YouTube_ThumbnailDetails */
                $thumbs = $snippet->getThumbnails();
                /* @var $thumb Google_Service_YouTube_Thumbnail */
                //$thumb = $thumbs->getMaxres();
                //if ($thumb == null)
                $thumb = $thumbs->getHigh();
                if ($thumb == null)
                    $thumb = $thumbs->getStandard();

                // create an entry with all the info we have so far
                $video = new YTVideo(
                    $id->getVideoId(), $snippet->getTitle(), $snippet->getDescription(),
                    $snippet->getPublishedAt(), ($thumb != null) ? $thumb->getUrl() : '',
                    $criteria->getLanguage(), $criteria->getRegionCode(), $criteria->getHD()
                );
                array_push($allVideos, $video);
            }

            // check if it's possible to advance to the next page
            $nextPageToken = $listResults->getNextPageToken();
        } while (!empty($nextPageToken) && sizeof($allVideos) < $maxCount);
        return $allVideos;
    }

    /**
     * Merges the second array into the first, excluding any duplicate video (by unique videoIds)
     * @param $into YTVideo[] the destination array, where items are appended
     * @param $from YTVideo[] to insert form
     */
    public function mergeUniqueVideos(&$into, &$from)
    {
        $existingIds = array_map(function ($video) {
            return $video->videoId;
        }, $into);
        foreach ($from as $video) {
            if (!in_array($video->videoId, $existingIds)) {
                array_push($into, $video);
                array_push($existingIds, $video->videoId);
            }
        }
    }

    /**
     * Sorts an array of YTVideos in place.
     * @param $videosArray YTVideo[]
     * @param $order String sorting criteria:
     */
    public function sortVideos(&$videosArray, $order)
    {
        switch ($order) {
            case 'views':
                $sortingFunction = function ($a, $b) {
                    return $b->countViews - $a->countViews;
                };
                break;
            case 'liked':
                $sortingFunction = function ($a, $b) {
                    return $b->pctLikes - $a->pctLikes;
                };
                break;
            case 'disliked':
                $sortingFunction = function ($a, $b) {
                    return $b->pctDislikes - $a->pctDislikes;
                };
                break;
            default:
                die('unsupported sorting order');
        }
        usort($videosArray, $sortingFunction);
    }

    /* @var $guzzle \GuzzleHttp\Client() */
    /* @var $youtube Google_Service_YouTube */
    /* @var $youtubeAnalytics Google_Service_YouTubeAnalytics */
    private static $googleClient;
    private static $guzzle;
    private static $youtube;

    //private static $youtubeAnalytics;

    static function getGuzzle()
    {
        // create on demand
        if (self::$guzzle == null)
            self::$guzzle = new \GuzzleHttp\Client();
        return self::$guzzle;
    }

    static function getYoutube()
    {
        self::ensureOAuthenticated();
        // create on demand
        if (self::$youtube == null)
            self::$youtube = new Google_Service_YouTube(self::$googleClient);
        return self::$youtube;
    }

    /*static function getYoutubeAnalytics()
    {
        self::ensureOAuthenticated();
        // create on demand
        if (self::$youtubeAnalytics == null)
            self::$youtubeAnalytics = new Google_Service_YouTubeAnalytics(self::$googleClient);
        return self::$youtubeAnalytics;
    }*/

    private static function ensureOAuthenticated()
    {
        if (self::$googleClient->isAccessTokenExpired())
            self::$googleClient->useApplicationDefaultCredentials();
    }
}


class YTVideo
{
    // how large should a SRT be to be considered OK
    const SUB_OK_THRESHOLD = 500;

    // first discovered properties
    public $videoId;
    public $title;
    public $description;
    public $publishedAt;
    public $thumbUrl;
    public $language;
    public $regionCode;
    public $isHD;
    public $desktopUrl;

    // after resolveCaptions()
    private $resolvedCaptions = false;
    private $lastResolveCaptionsIssue;
    /* @var $ytCC YTCC */
    public $ytCC = null;

    // after resolveDetails()
    private $resolvedDetails = false;
    private $lastResolveDetailsIssue;
    public $countViews;
    public $countComments;
    public $countLikes;
    public $countDislikes;
    public $countFavorites;
    public $pctComments;
    public $pctLikes;
    public $pctDislikes;
    public $pctFavorites;
    public $channelId;
    public $channelTitle;
    public $duration; // in seconds
    public $tags;


    function __construct($videoId, $title, $description, $publishedAt, $thumbUrl, $language, $regionCode, $isHD)
    {
        $this->videoId = $videoId;
        $this->title = $title;
        $this->description = $description;
        $this->publishedAt = $publishedAt;
        $this->thumbUrl = $thumbUrl;
        $this->language = $language;
        $this->regionCode = $regionCode;
        $this->isHD = $isHD;
        $this->desktopUrl = 'https://www.youtube.com/watch?v=' . $videoId;
    }

    public function resolveDetails()
    {
        // do it at most once
        if ($this->resolvedDetails)
            return;
        $this->resolvedDetails = true;
        $this->lastResolveDetailsIssue = '';

        /* @var $item Google_Service_YouTube_Video
         * @var $snip Google_Service_YouTube_VideoSnippet
         * @var $stat Google_Service_YouTube_VideoStatistics
         * @var $cDet Google_Service_YouTube_VideoContentDetails
         */
        // NOTE: could add topicDetails in the future
        $details = YTMachine::getYoutube()->videos->listVideos('statistics,snippet,contentDetails', ['id' => $this->videoId]);
        $items = $details->getItems();
        if (YT_VIOLENT && sizeof($items) != 1)
            die('Expecting 1 item, got ' . sizeof($items));
        $item = $items[0];

        // counters (all properties are detailed here: https://developers.google.com/youtube/v3/docs/videos)
        /* @var $stat Google_Service_YouTube_VideoStatistics */
        $stat = $item->getStatistics();
        /* @var $sus Google_Service_YouTube_VideoStatus
        $sus = $item->getStatus();
        if ($sus->getEmbeddable() == false) {
            print_r($sus);
            die('NOT EMB');
        }*/
        $this->countViews = intval($stat->getViewCount());
        $this->countComments = intval($stat->getCommentCount());
        $this->countLikes = intval($stat->getLikeCount());
        $this->countDislikes = intval($stat->getDislikeCount());
        $this->countFavorites = intval($stat->getFavoriteCount());
        $this->pctComments = $this->countViews > 0 ? (100 * $this->countComments / $this->countViews) : 0;
        $this->pctLikes = $this->countViews > 0 ? (100 * $this->countLikes / $this->countViews) : 0;
        $this->pctDislikes = $this->countViews > 0 ? (100 * $this->countDislikes / $this->countViews) : 0;
        $this->pctFavorites = $this->countViews > 0 ? (100 * $this->countFavorites / $this->countViews) : 0;

        // additional info (tags, channel)
        $snip = $item->getSnippet();
        $this->channelId = $snip->getChannelId();
        $this->channelTitle = $snip->getChannelTitle();
        $this->tags = $snip->getTags();

        // duration (ignoring: definition(hd), dimension(2d), contentRating, countryRestriction, regionRestriction)
        $cDet = $item->getContentDetails();
        if ($cDet->getCaption() == "false") {
            if (YT_VIOLENT)
                die('video.contentDetails.caption:false on ' . $this->videoId);
            $this->lastResolveDetailsIssue .= ' c';
        }
        $dt = new DateInterval($cDet->getDuration());
        $this->duration = $dt->h * 3600 + $dt->i * 60 + $dt->s;
    }

    /**
     * @return bool True if successful
     */
    public function resolveCaptions()
    {
        // do it at most once
        if ($this->resolvedCaptions)
            return $this->ytCC != null;
        $this->resolvedCaptions = true;
        $this->lastResolveCaptionsIssue = '';

        // perform the resolution
        // FIXME: SPEED BOTTLENECK (1/second)
        $captionsList = YTMachine::getYoutube()->captions->listCaptions('snippet', $this->videoId);

        // get all the SRT from this track that match the language
        $ytCCs = [];
        foreach ($captionsList->getItems() as $item) {
            /* @var $item Google_Service_YouTube_Caption */
            /* @var $cc Google_Service_YouTube_CaptionSnippet */
            $cc = $item->getSnippet();
            $ccId = $item->getId();

            // Sanity: abort if the returned CC is for a different video
            $ccVideoId = $cc->getVideoId();
            if ($ccVideoId != $this->videoId) {
                if (YT_VIOLENT)
                    die('wrong video id, got ' . $ccVideoId . ' expecting ' . $this->videoId);
                $this->lastResolveCaptionsIssue .= ' i';
                continue;
            }

            // Sanity: check constant attributes, or stop if unexpected
            $ccStatus = $cc->getStatus();
            if ($ccStatus != 'serving') {
                if (YT_VERBOSE) {
                    echo('{wrong cc status: ' . $ccStatus);
                    if ($ccStatus == 'failure')
                        echo('(' . $cc->getFailureReason() . ')');
                    echo('}');
                }
                $this->lastResolveCaptionsIssue .= ' s';
                continue;
            }

            // base fetching query
            $fetchQuery = 'v=' . $ccVideoId;

            // add kind
            $ccKind = $cc->getTrackKind();
            if ($ccKind == 'standard') {
                // nothing to do here
            } else if ($ccKind == 'ASR') {
                // FILTER: TODO: we don't support the ASR format yet, at all. Always fails.
                if (YT_VERBOSE)
                    echo $ccVideoId . ',  skipping ASR tracks (unsupported yet)' . "\n";
                $this->lastResolveCaptionsIssue .= ' k-asr';
                continue;
            } else {
                if (YT_VIOLENT)
                    die('unknown track type ' . $ccKind);
                $this->lastResolveCaptionsIssue .= ' k-' . $ccKind;
                continue;
            }

            // add language
            $ccLang = $cc->getLanguage();
            if (!empty($ccLang)) {
                // FILTER: skip if the language is not what we asked for
                if ($ccLang != $this->language) {
                    // NOTE: in the future we could also stash other languages for later
                    if (YT_VERBOSE)
                        echo $ccVideoId . ',  skipping CC for different language: ' . $ccLang . "\n";
                    $this->lastResolveCaptionsIssue .= ' l-' . $ccLang;
                    continue;
                }
                $fetchQuery .= '&lang=' . $ccLang;
            }

            // add 'name'
            $ccTrackName = $cc->getName();
            if (!empty($ccTrackName))
                $fetchQuery .= '&name=' . $ccTrackName;

            // customize the output format. available formats:
            // srv1: <text start="2.501" dur="3.671">
            // srv2: <timedtext><window t="0" id="1" op="define" rc="15" cc="32" ap="7" ah="50" av="95"/><text w="2" t="5538" d="2536">RE</text>
            // srv3: <timedtext format="3"><p t="2501" d="3671" w="2"><s>TH</s><s t="33">E</s>
            // sbv:  [SubViewer] 0:00:02.501,0:00:06.172 \n THE WASHINGTON CORRESPONDENT
            // srt:  [SubRip   ] 1 \n 00:00:02,501 --> 00:00:06,172 \n THE WASHINGTON CORRESPONDENT
            // ttml: [TTML     ] <p begin="00:00:02.501" end="00:00:06.172" region="r3" style="s2"><span begin="00:00:00.000">TH</span>
            // vtt:  [WebVTT   ] 00:00:02.501 --> 00:00:06.172 align:start position:0% line:7% \n THE WASHINGTON CORRESPONDENT
            $fetchQuery .= '&fmt=srv1';

            // Fetch the Caption from cache
            $ccString = CacheMachine::retrieveValue('cc_' . $fetchQuery);

            // Fetch the Caption (and expect a 200:OK code)
            if ($ccString == null) {
                try {
                    $response = YTMachine::getGuzzle()->get('https://www.youtube.com/api/timedtext?' . $fetchQuery);
                    if ($response->getStatusCode() != 200) {
                        if (YT_VIOLENT)
                            die('wrong status code ' . $response->getStatusCode() . ' on ' . $fetchQuery);
                        $this->lastResolveCaptionsIssue .= ' h1-' . $response->getStatusCode();
                        continue;
                    }
                    $ccString = $response->getBody()->getContents();
                    CacheMachine::storeValue('cc_' . $fetchQuery, $ccString, null);
                } catch (\GuzzleHttp\Exception\ClientException $exception) {
                    if (YT_VIOLENT)
                        die('HTTP request failed: ' . $exception);
                    $this->lastResolveCaptionsIssue .= ' h2-"' . $exception->getMessage() . '"';
                    continue;
                }
            }

            // FILTER: Size Heuristic (FIXME): reject semi-empty captions (usually with not much more than the title)
            $ccStringSize = $ccString != null ? strlen($ccString) : 0;
            if ($ccStringSize < self::SUB_OK_THRESHOLD) {
                if (YT_VERBOSE)
                    echo $ccVideoId . ',  skipping for small size: ' . $ccStringSize . "\n";
                $this->lastResolveCaptionsIssue .= ' S-' . $ccStringSize;
                continue;
            }

            // FILTER: parse and validate XML
            try {
                $ccTranscript = new SimpleXMLElement($ccString);
                if ($ccTranscript->getName() != 'transcript') {
                    if (YT_VIOLENT)
                        die('expected a transcript root element, got a ' . $ccTranscript->getName() . ' instead ');
                    $this->lastResolveCaptionsIssue .= ' x-1';
                    continue;
                }
            } catch (Exception $e) {
                if (YT_VERBOSE)
                    echo 'skipping for xml parsing error' . $ccVideoId . "\n";
                $this->lastResolveCaptionsIssue .= ' x-2';
                continue;
            }

            // Break a SRT into individual Lines
            $lines = [];
            $maxLength = 0;
            foreach ($ccTranscript->text AS $line) {
                $text = trim($this->fixSrv1Caption(strval($line)));
                $textLength = strlen($text);

                // TODO: totally need to do a better job here to convert all of the possible conventions
                // of the subtitles into some plain text english (there are names, unicode chars, html tags, etc..)

                // fix quoted strings
                if ($textLength > YT_MIN_VALID_CHARS && substr($text, 0, 1) == '"' && substr($text, -1) == '"') {
                    $text = trim(substr($text, 1, -1));
                    $textLength = strlen($text);
                }

                // remove "- " at the beginning
                if ($textLength > YT_MIN_VALID_CHARS && substr($text, 0, 2) == '- ') {
                    $text = trim(substr($text, 2));
                    $textLength = strlen($text);
                }

                // skip lines with less than YT_MIN_VALID_CHARS chars
                if ($textLength < YT_MIN_VALID_CHARS || $textLength > YT_MAX_VALID_CHARS)
                    continue;

                // FILTER: videos that have start but not duration are usually 1-liners
                $attributes = $line->attributes();
                $start = $attributes['start'];
                $duration = $attributes['dur'];
                if (empty($start) || empty($duration)) {
                    if (YT_VERBOSE)
                        echo 'skipping cc line for start or duration empty on ' . $ccVideoId . " body: " . $ccString . "\n";
                    continue;
                }
                if (floatval($duration) > YT_MAX_VALID_DURATION) {
                    if (YT_VERBOSE)
                        echo 'skipping cc line for duration ' . $ccVideoId . "\n";
                    continue;
                }
                // skip "( ... )" strings, since parenthesis are not real speech
                if (substr($text, 0, 1) == '(' && substr($text, -1) == ')') {
                    if (YT_VERBOSE)
                        echo 'skipping cc line for (parenthesis) ' . $ccVideoId . "\n";
                    continue;
                }

                // add the line
                array_push($lines, [
                    't' => $text,
                    //'tr' => $text,
                    's' => floatval($start),
                    'd' => floatval($duration),
                    'e' => floatval($start) + floatval($duration)
                ]);
                if ($textLength > $maxLength)
                    $maxLength = $textLength;
            }

            // FILTER: almost-empty docs, or docs with at most 3 letters per line
            if ($maxLength < YT_MIN_VALID_CHARS || sizeof($lines) < YT_MIN_VALID_LINES) {
                if (YT_VERBOSE)
                    echo 'skipping for emptiness  ' . sizeof($lines) . " lines and " . $maxLength . " max chars per line\n";
                $this->lastResolveCaptionsIssue .= ' e';
                continue;
            }

            // save the fully-fetched caption
            array_push($ytCCs,
                new YTCC($ccId, $ccVideoId, $ccTrackName, $ccStringSize, $ccString, $lines, $cc->getLastUpdated())
            );
        }

        // use just best caption amongst those available, chosen by size
        usort($ytCCs, function ($a, $b) {
            return $b->ccSize - $a->ccSize;
        });

        // pick the best (if any), or null
        $this->ytCC = empty($ytCCs) ? null : $ytCCs[0];
        return $this->ytCC != null;
    }

    /**
     * @return string The shortcode reason for the failure
     */
    public function getLastCaptionIssue() {
        return $this->lastResolveCaptionsIssue;
    }

    /**
     * @param $objectID String the ID of the object in the Search system
     * @return array Object to be uploaded to the Indexing - this defines our search ABI
     * @deprecated remove after the full conversion to Elastic
     */
    public function toSearchable($objectID)
    {
        $videoObj = [
            "objectID" => $objectID,

            "videoId" => $this->videoId,
            "title" => $this->title,
            "description" => $this->description,
            "publishedAt" => $this->publishedAt,
            "thumbUrl" => $this->thumbUrl,
            "language" => $this->language,
            "regionCode" => $this->regionCode,
            "isHD" => $this->isHD,
            "desktopUrl" => $this->desktopUrl,

            "countViews" => $this->countViews,
            "countComments" => $this->countComments,
            "countLikes" => $this->countLikes,
            "countDislikes" => $this->countDislikes,
            "countFavorites" => $this->countFavorites,
            "pctComments" => $this->pctComments,
            "pctLikes" => $this->pctLikes,
            "pctDislikes" => $this->pctDislikes,
            "pctFavorites" => $this->pctFavorites,

            "channelId" => $this->channelId,
            "channelTitle" => $this->channelTitle,

            "duration" => $this->duration,

            // the '_tags' is an Algolia default mapping
            "_tags" => $this->tags
        ];
        return array_merge($videoObj, $this->ytCC->toSearchable());
    }

    public function toElastic()
    {
        return [
//            'jam' => [], // variables/dynamic attributes for the jam operation
            'content' => [
                'title' => $this->title,
                'duration' => $this->duration,
                'description' => $this->description,
                'language' => $this->language,
                'regionCode' => $this->regionCode,
                'isHD' => $this->isHD,
                'desktopUrl' => $this->desktopUrl,
                'thumbUrl' => $this->thumbUrl,
                'publishedAt' => $this->publishedAt
            ],
            'channel' => [
                'id' => $this->channelId,
                'title' => $this->channelTitle
            ],
            'stats' => [
                'countViews' => $this->countViews,
                'countComments' => $this->countComments,
                'countLikes' => $this->countLikes,
                'countDislikes' => $this->countDislikes,
                'countFavorites' => $this->countFavorites,
                'pctComments' => $this->pctComments,
                'pctLikes' => $this->pctLikes,
                'pctDislikes' => $this->pctDislikes,
                'pctFavorites' => $this->pctFavorites
            ],
            'cc' => $this->ytCC->toElastic(),
            'tags' => $this->tags
        ];
    }

    private function fixSrv1Caption($line)
    {
        $line = str_replace('&#39;', "'", $line);
        $line = str_replace('&quot;', '"', $line);
        $line = str_replace('&gt;', '>', $line);
        $line = str_replace('&lt;', '<', $line);
        $line = str_replace('&nbsp;', ' ', $line);
        $line = str_replace("\n", ' ', $line);
        $line = str_replace('&amp;', '&', $line);
        if (YT_VERBOSE && stripos($line, '&') > 0)
            echo "\nfix parsing of <" . $line . ">\n";
        return $line;
    }
}

class YTCC
{
    private $ccId;
    //private $videoIdRef;
    private $ccTrackName;
    public $ccSize; // public to be used as ranking criteria in sort function
    //public $ccString;
    private $text;
    private $lastUpdated;


    function __construct($ccId, $videoId, $ccTrackName, $ccSize, $ccString, $lines, $lastUpdated)
    {
        $this->ccId = $ccId;
        //$this->videoIdRef = $videoId;
        $this->ccTrackName = $ccTrackName;
        $this->ccSize = $ccSize;
        //$this->ccString = $ccString;
        $this->text = $lines;
        $this->lastUpdated = $lastUpdated;

        if (YT_VERBOSE)
            echo $videoId . ',' . $ccSize . ',' . $ccTrackName . ',' . $lastUpdated . ',' . $ccId . "\n";
    }

    /**
     * @return array Object to be uploaded to the Indexing - this defines our search ABI
     * @deprecated Remove after migration to Elastic
     */
    public function toSearchable()
    {
        return [
            "ccId" => $this->ccId,
            "ccTrackName" => $this->ccTrackName,
            "ccSize" => $this->ccSize,
            "lastUpdated" => $this->lastUpdated,
            "text" => $this->text
        ];
    }

    /**
     * @return array Object to be uploaded to the Indexing - this defines our search ABI
     */
    public function toElastic()
    {
        return [
            'id' => $this->ccId,
            'lastUpdated' => $this->lastUpdated,
            'size' => $this->ccSize,
            'trackName' => $this->ccTrackName,
            'text' => $this->text
        ];
    }

}

class YTSearchCriteria
{
    private $criteria = [];

    function __construct($query, $findOnlyHD)
    {
        $this->criteria['q'] = $query;
        $this->criteria['type'] = 'video';
        // NOTE: this is a major reducer, even over yt captioned video searches (130->13!)
        // could try with this off and see how many positives will be evicted later
        $this->criteria['videoCaption'] = 'closedCaption';
        $this->criteria['videoEmbeddable'] = 'true';
        $this->criteria['safeSearch'] = 'none';
        // need something like this
        //$this->criteria['paidContent'] = 'false';
        $this->setLanguage(YT_LANG_FILTER, YT_REGION_FILTER);
        $this->setResultsPageSize(40);
        $this->setHD($findOnlyHD);
    }

    /**
     * @param $lang String from http://www.loc.gov/standards/iso639-2/php/code_list.php, e.g. 'en'
     * @param $region String from "ISO 3166-1 alpha-2" codes
     * @return YTSearchCriteria
     */
    function setLanguage($lang, $region)
    {
        $this->criteria['relevanceLanguage'] = $lang;
        $this->criteria['regionCode'] = $region;
        return $this;
    }

    /**
     * @param $on bool to enable/disable HD videos (default: disabled)
     * @return YTSearchCriteria
     */
    function setHD($on)
    {
        if ($on)
            $this->criteria['videoDefinition'] = $on ? 'high' : 'any';  /* or standard? */
        else
            unset($this->criteria['videoDefinition']);
        return $this;
    }

    /**
     * @param $pageToken String that identifies the page to load (returned by prev/next page on other calls)
     * @return YTSearchCriteria
     */
    function setPage($pageToken)
    {
        if (!empty($pageToken))
            $this->criteria['pageToken'] = $pageToken;
        else
            unset($this->criteria['pageToken']);
        return $this;
    }

    /**
     * @param $order String one of 'date', 'rating', 'relevance' (default), 'title', 'videoCount', 'viewCount'
     * @return YTSearchCriteria
     */
    function setOrder($order)
    {
        $valid = ['date', 'rating', 'relevance' /* def. */, 'title', 'videoCount', 'viewCount'];
        if (!in_array($order, $valid))
            die('wrong order: ' . $order);
        $this->criteria['order'] = $order;
        return $this;
    }

    /**
     * @param $from String RFC 3339 (older)
     * @param $to String RFC 3339 (newer)
     * @return YTSearchCriteria
     */
    function setTimeInterval($from, $to)
    {
        if ($from != null)
            $this->criteria['publishedAfter'] = $from;
        if ($to != null)
            $this->criteria['publishedBefore'] = $to;
        return $this;
    }

    /**
     * @param $num integer the page size (int the 1..50 interval)
     * @return YTSearchCriteria
     */
    function setResultsPageSize($num)
    {
        $this->criteria['maxResults'] = max(1, min(50, $num));
        return $this;
    }

    // used by YTMachine
    function getArray()
    {
        return $this->criteria;
    }

    function getQuery()
    {
        return $this->criteria['q'];
    }

    function getLanguage()
    {
        return $this->criteria['relevanceLanguage'];
    }

    public function getRegionCode()
    {
        return $this->criteria['regionCode'];
    }

    public function getHD()
    {
        return isset($this->criteria['videoDefinition']) && $this->criteria['videoDefinition'] == "high";
    }
}
