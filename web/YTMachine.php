<!--
Copyright 2015, Enrico Ros

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
-->
<?php

define('YT_DEBUG', false);
define('YT_LANG_FILTER', 'en');

class YTMachine
{
    // cloud console data
    private $oauth_email_file = '../../thats-what-he-said-2040b1e37d61.email.txt';
    private $oauth_P12_file = '../../thats-what-he-said-2040b1e37d61.p12';

    // list of permissions we need
    private $scopes = ['https://www.googleapis.com/auth/youtube.force-ssl'];
    // how large should a SRT be to be considered OK
    const SUB_OK_THRESHOLD = 500;

    private $client;
    /* @var $youtube Google_Service_YouTube */
    private $youtube;
    /* @var $guz \GuzzleHttp\Client */
    private $guzzle;

    function __construct()
    {
        $credentials = new Google_Auth_AssertionCredentials(
            trim(file_get_contents($this->oauth_email_file)),
            $this->scopes,
            file_get_contents($this->oauth_P12_file)
        );

        $this->client = new Google_Client();
        $this->client->setAssertionCredentials($credentials);
    }

    /**
     * @return Google_Service_YouTube
     */
    function init()
    {
        if ($this->youtube == null) {
            $this->ensureOAuthenticated();
            $this->youtube = new Google_Service_YouTube($this->client);
        }
        if ($this->guzzle == null)
            $this->guzzle = new \GuzzleHttp\Client();
        return $this->youtube;
    }

    /**
     * @param $criteria YTSearchCriteria
     * @param $maxCount integer We'll do the searches in small batches, but there may be endless videos.
     * Limit the search to 200 for example.
     * @return YTVideo[]
     */
    function searchVideos($criteria, $maxCount)
    {
        /* @var $allVideos YTVideo[] */
        $allVideos = [];
        $nextPageToken = null;
        do {
            // when looping, set the ID of the next page
            $criteria->setPage($nextPageToken);
            // try to go with the right size (will be paginated automatically)
            $criteria->setResultsPageSize($maxCount - sizeof($allVideos));

            $this->ensureOAuthenticated();
            $listResults = $this->youtube->search->listSearch('id,snippet', $criteria->getArray());

            if (YT_DEBUG && $nextPageToken == null) {
                $totalResults = $listResults->getPageInfo()->totalResults;
                $perPageResults = $listResults->getPageInfo()->resultsPerPage;
                echo 'Loading ' . $maxCount . ' results from ' . $totalResults . ' in batches of '
                    . $perPageResults . ' for query ' . $criteria->getQuery() . "\n";
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
                    $criteria->getLanguage()
                );
                array_push($allVideos, $video);
            }

            // check if it's possible to advance to the next page
            $nextPageToken = $listResults->getNextPageToken();
        } while (!empty($nextPageToken) && sizeof($allVideos) < $maxCount);
        return $allVideos;
    }


    /**
     * @param $video YTVideo
     */
    public function resolveCaptionsForVideo($video)
    {
        if ($video->captionsResolved)
            return;

        // perform the resolution
        $this->ensureOAuthenticated();
        $captionsList = $this->youtube->captions->listCaptions('snippet', $video->videoId);

        // get all the SRT from this track that match the language
        $video->captionsResolved = true;
        $video->caption = [];
        foreach ($captionsList->getItems() as $item) {
            // ignored: ETag and Kind(const)
            $ccId = $item->getId();
            $cc = $item->getSnippet();
            /* @var $cc Google_Service_YouTube_CaptionSnippet */
            $ccVideoId = $cc->getVideoId();

            // Sanity: abort if the returned CC is for a different video
            if ($ccVideoId != $video->videoId) {
                if (YT_DEBUG)
                    die('wrong video id, got ' . $ccVideoId . ' expecting ' . $video->videoId);
                continue;
            }

            // Sanity: check constant attributes, or stop if unexpected
            $ccStatus = $cc->getStatus();
            if ($ccStatus != 'serving')
                if (YT_DEBUG)
                    die('wrong status. expected serving, got ' . $ccStatus);

            // Base fetching URL: alternative base: 'http://video.google.com/timedtext?v='
            $fetchUrl = 'https://www.youtube.com/api/timedtext?v=' . $ccVideoId;

            // add language
            $ccLang = $cc->getLanguage();
            if (!empty($ccLang)) {
                // FILTER: skip if the language is not what we asked for
                if ($ccLang != $video->language) {
                    // NOTE: in the future we could also stash other languages for later
                    if (YT_DEBUG)
                        echo $ccVideoId . ',  skipping CC for different language: ' . $ccLang . "\n";
                    //continue;
                }
                $fetchUrl .= '&lang=' . $ccLang;
            }

            // add 'name'
            $ccName = $cc->getName();
            if (!empty($ccName))
                $fetchUrl .= '&name=' . $ccName;

            // add kind
            $ccKind = $cc->getTrackKind();
            if ($ccKind == 'standard') {
                // nothing to do here
            } else if ($ccKind == 'ASR') {
                // FILTER: TODO: we don't support the ASR format yet, at all. Always fails.
                if (YT_DEBUG)
                    echo $ccVideoId . ',  skipping ASR tracks (unsupported yet)' . "\n";
                continue;
            } else {
                if (YT_DEBUG)
                    die('unknown track type ' . $ccKind);
                continue;
            }

            // customize the output format. available formats:
            // srv1: <text start="2.501" dur="3.671">
            // srv2: <timedtext><window t="0" id="1" op="define" rc="15" cc="32" ap="7" ah="50" av="95"/><text w="2" t="5538" d="2536">RE</text>
            // srv3: <timedtext format="3"><p t="2501" d="3671" w="2"><s>TH</s><s t="33">E</s>
            // sbv:  [SubViewer] 0:00:02.501,0:00:06.172 \n THE WASHINGTON CORRESPONDENT
            // srt:  [SubRip   ] 1 \n 00:00:02,501 --> 00:00:06,172 \n THE WASHINGTON CORRESPONDENT
            // ttml: [TTML     ] <p begin="00:00:02.501" end="00:00:06.172" region="r3" style="s2"><span begin="00:00:00.000">TH</span>
            // vtt:  [WebVTT   ] 00:00:02.501 --> 00:00:06.172 align:start position:0% line:7% \n THE WASHINGTON CORRESPONDENT
            $fetchUrl .= '&fmt=srv1';

            // Fetch the Caption (and expect a 200:OK code)
            try {
                $msg = $this->guzzle->get($fetchUrl);
                if ($msg->getStatusCode() != 200) {
                    if (YT_DEBUG)
                        die('wrong status code ' . $msg->getStatusCode() . ' on ' . $fetchUrl);
                    continue;
                }
            } catch (\GuzzleHttp\Exception\ClientException $exception) {
                if (YT_DEBUG)
                    die('HTTP request failed: ' . $exception);
                continue;
            }

            // FILTER: Size Heuristic (FIXME): reject semi-empty captions (usually with not much more than the title)
            $ccBody = $msg->getBody();
            $ccXml = $ccBody->getContents();
            $ccXmlSize = $ccBody->getSize();
            if ($ccXmlSize < self::SUB_OK_THRESHOLD) {
                if (YT_DEBUG)
                    echo $ccVideoId . ',  skipping for small size: ' . $ccXmlSize . "\n";
                continue;
            }

            // FILTER: parse and validate XML?
            // TODO

            // save the fully-fetched caption for this video
            $ytCC = new YTCC($ccId, $ccVideoId, $ccXmlSize, $ccXml, $ccName, $cc->getLastUpdated());
            array_push($video->caption, $ytCC);
        }

        // reset the caption if none found
        if (empty($video->caption)) {
            $video->caption = null;
            return;
        }

        // use just best caption amongst those available, chosen by size
        usort($video->caption, function ($a, $b) {
            return $b->xmlSize - $a->xmlSize;
        });
        $video->caption = $video->caption[0];
    }


    // call this before calling other API operations, to make sure we can still go ahead and perform them
    private function ensureOAuthenticated()
    {
        if ($this->client->getAuth()->isAccessTokenExpired())
            $this->client->getAuth()->refreshTokenWithAssertion();
    }
}


class YTVideo
{
    // all string properties
    public $videoId;
    public $title;
    public $description;
    public $publishedAt;
    public $thumbUrl;
    public $language;

    public $caption = null;
    public $captionsResolved = false;

    function __construct($videoId, $title, $description, $publishedAt, $thumbUrl, $language)
    {
        $this->videoId = $videoId;
        $this->title = $title;
        $this->description = $description;
        $this->publishedAt = $publishedAt;
        $this->thumbUrl = $thumbUrl;
        $this->language = $language;
    }
}

class YTCC
{
    private $id;
    private $videoId;
    public $xmlSize;
    public $xmlData;
    private $trackName;
    private $lastUpdated;

    function __construct($id, $videoId, $xmlSize, $xmlData, $trackName, $lastUpdated)
    {
        $this->id = $id;
        $this->videoId = $videoId;
        $this->xmlSize = $xmlSize;
        $this->xmlData = $xmlData;
        $this->trackName = $trackName;
        $this->lastUpdated = $lastUpdated;
        if (YT_DEBUG)
            echo $videoId . ',' . $xmlSize . ',' . $trackName . ',' . $lastUpdated . ',' . $id . "\n";
    }
}

class YTSearchCriteria
{
    private $criteria = [];

    function __construct($query)
    {
        $this->criteria['q'] = $query;
        $this->criteria['type'] = 'video';
        $this->criteria['videoCaption'] = 'closedCaption';
        $this->criteria['safeSearch'] = 'none';
        $this->criteria['videoEmbeddable'] = 'true';
        $this->setLanguage(YT_LANG_FILTER);
        $this->setResultsPageSize(40);
        $this->setHD(true);
    }

    /**
     * @param $lang String from http://www.loc.gov/standards/iso639-2/php/code_list.php, e.g. 'en'
     * @return YTSearchCriteria
     */
    function setLanguage($lang)
    {
        $this->criteria['relevanceLanguage'] = $lang;
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
        if (!array_key_exists($order, $valid))
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
}
