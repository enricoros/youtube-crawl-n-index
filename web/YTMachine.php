<?php

define('YT_DEBUG', true);

class YTMachine
{
    // cloud console data
    private $oauth_email_file = '../../thats-what-he-said-2040b1e37d61.email.txt';
    private $oauth_P12_file = '../../thats-what-he-said-2040b1e37d61.p12';

    // list of permissions we need
    private $scopes = ['https://www.googleapis.com/auth/youtube.force-ssl'];

    private $client;
    /* @var $youtube Google_Service_YouTube */
    private $youtube;

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
                    . $perPageResults . ' for query ' . $criteria->getQuery() . '\\n';
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
                    $snippet->getPublishedAt(), ($thumb != null) ? $thumb->getUrl() : ''
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
    public function resolveCaptions($video)
    {
        $this->ensureOAuthenticated();
        $captions = $this->youtube->captions->listCaptions('snippet', $video->videoId);
        $items = $captions->getItems();
        foreach ($items as $item) {
            // ETag ignored and Kind (const) ignored
            $captionId = $item->getId();
            /* @var $captionSnippet Google_Service_YouTube_CaptionSnippet */
            $captionSnippet = $item->getSnippet();
            $captionSnippet->get


        }
    }

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

    public $hasCaption;

    function __construct($videoId, $title, $description, $publishedAt, $thumbUrl)
    {
        $this->videoId = $videoId;
        $this->title = $title;
        $this->description = $description;
        $this->publishedAt = $publishedAt;
        $this->thumbUrl = $thumbUrl;
        $this->hasCaption = true;
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
        $this->setLanguage('en');
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

    public function getQuery()
    {
        return $this->criteria['q'];
    }
}
