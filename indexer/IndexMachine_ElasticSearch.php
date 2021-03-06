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
require_once 'IndexMachine.php';

define('IM_VIOLENT', true);
define('IM_VERBOSE', false);
// WARNING: if you set this to true, it will destroy and recreate the index for every instance!
define('FORCE_RE_CREATE_INDEX', false);

class IndexMachine_ElasticSearch implements IndexMachine
{
    const INDEX_NAME = 'jam';
    const INDEX_TYPE = 'ccv3';

    /* @var $client \Elasticsearch\Client */
    private $client;

    /**
     * @param $hosts array
     */
    function __construct($hosts)
    {
        // create and connect the client
        $this->client = Elasticsearch\ClientBuilder::create()
            ->setHosts($hosts)
            //->setLogger(Elasticsearch\ClientBuilder::defaultLogger('/tmp/log.log'))
            ->build();

        // create and configure the index, if missing
        $this->createIndex();
    }

    /**
     * @param $objectID String the objectId
     * @param $ytVideo YTVideo a valid object to index - transformation will happen inside, not outside
     * @return bool true if the operation was successful
     */
    public function addOrUpdate($objectID, $ytVideo)
    {
        $params = [
            'id' => $objectID,
            'index' => self::INDEX_NAME,
            'type' => self::INDEX_TYPE,
            'body' => $ytVideo->toElastic()
        ];
        $response = $this->client->index(
            $params
        );
        // creation ok
        if ($response['created'] == 1)
            return true;
        // update ok
        if ($response['_version'] > 1)
            return true;

        // or handle error
        if (IM_VERBOSE) {
            echo "Error creating/updating object " . $objectID . ": ";
            print_r($response);
        }
        if (IM_VIOLENT)
            die('Fix creation.');
        return false;
    }

    public function search($query, $matchPhrase, $maxVideos, $maxSnippetsPerVideo)
    {
        // query DSL
        $paramsDSL = [
            '_source' => ['content', 'channel', 'stats' /*, 'tags'*/],
            'query' => [
                'nested' => [
                    'path' => 'cc.text',
                    'score_mode' => 'avg',
                    'query' => [
                        ($matchPhrase ? 'match_phrase' : 'match') => [
                            'cc.text.t' => $query
                        ]
                    ],
                    "inner_hits" => [
                        "size" => $maxSnippetsPerVideo
                    ]
                ]
            ]
        ];
        print_r(json_encode($paramsDSL), JSON_PRETTY_PRINT);

        // search query
        $params = [
            'index' => self::INDEX_NAME,
            'type' => self::INDEX_TYPE,
            'size' => $maxVideos,
            'body' => $paramsDSL
        ];
        $r = $this->client->search($params);

        // transform JSON, extract Snippets, Videos
        $dVideos = [];
        $dSnippets = [];
        foreach ($r['hits']['hits'] as $docsHit) {
            $docYtVideoId = $docsHit['_id'];
            $docAvgScore = $docsHit['_score'];
            $docYtVideo = array_merge([
                'id' => $docYtVideoId,
                'avgSnippetScore' => $docAvgScore
            ], $docsHit['_source']);
            if (array_key_exists($docYtVideoId, $dVideos))
                if (IM_VIOLENT)
                    die('FIXME: duplicate video id?');
            $dVideos[$docYtVideoId] = $docYtVideo;

            $textHits = $docsHit['inner_hits']['cc.text']['hits']['hits'];
            foreach ($textHits as $i => $textHit) {
                $dSnippets[] = [
                    'video_id' => $textHit['_id'],
                    'score' => $textHit['_score'],
                    't' => $textHit['_source']['t'],
                    's' => $textHit['_source']['s'],
                    'd' => $textHit['_source']['d'],
                    'e' => $textHit['_source']['e']
                ];
            }
        }
        // sort snippets in order of descending score
        usort($dSnippets, function ($a, $b) {
            return $b['score'] > $a['score'] ? 1 : -1;
        });

        // this is the contract with the web json consumer
        $d = [
            'stats' => [
                'duration' => $r['took'] / 1000.0,
                'hits' => $r['hits']['total'],
                'videosCount' => sizeof($dVideos),
                'snippetsCount' => sizeof($dSnippets),
//                'timeout' => $r['timed_out'],
                'maxAvgVideoScore' => $r['hits']['max_score']
            ],
            'snippets' => $dSnippets,
            'videos' => $dVideos
        ];

        return $d;
    }


    private function tempSearch()
    {
        /*
{
  "query": {
    "nested": {
      "path": "cc.text",
      "query": {
        "match": {
          "cc.text.t": "any word matched"
        }
      }
    }
  }
}
{
  "query": {
    "nested": {
      "path": "cc.text",
      "query": {
        "match": {
          "cc.text.t": {
            "query": "thank you",
            "operator": "and"
          }
        }
      }
    }
  }
}

{
  "_source": [
    "content",
    "channel",
    "stats"
  ],
  "query": {
    "nested": {
      "path": "cc.text",
      "score_mode": "avg",
      "query": {
        "match_phrase": {
          "cc.text.t": "i love you back"
        }
      },
      "inner_hits": {}
    }
  }
}
            */
    }

    private function createIndex()
    {
        // skip if it already exists
        if ($this->client->indices()->exists(['index' => self::INDEX_NAME])) {
            if (IM_VERBOSE)
                echo "Index " . self::INDEX_NAME . " already exists. Skipping creation.\n";
            // WARNING: the following will destroy all data
            if (FORCE_RE_CREATE_INDEX)
                $this->client->indices()->delete(['index' => self::INDEX_NAME]);
            else
                return true;
        }
        $params = [
            'index' => self::INDEX_NAME,
            'body' => [
                'settings' => [
                    'number_of_shards' => 4,
                    'number_of_replicas' => 0
                ],
                'mappings' => [
                    self::INDEX_TYPE => [
                        '_source' => [
                            'enabled' => true
                        ],
                        'properties' => [
                            'content' => [
                                'type' => 'object',
                                'properties' => [
                                    'publishedAt' => [
                                        'type' => 'date',
                                        'format' => 'strict_date_optional_time||epoch_millis'
                                    ]
                                ]
                            ],
                            'channel' => [
                                'type' => 'object'
                            ],
                            'stats' => [
                                'type' => 'object'
                            ],
                            'cc' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => [
                                        'type' => 'nested',
                                        'properties' => [
                                            't' => [
                                                'type' => 'string',
                                                'analyzer' => 'standard'
                                            ],
                                            'tr' => [
                                                'type' => 'string',
                                                'index' => 'not_analyzed'
                                            ],
                                            's' => [
                                                'type' => 'float'
                                            ],
                                            'e' => [
                                                'type' => 'float'
                                            ],
                                            'd' => [
                                                'type' => 'float'
                                            ]
                                        ]
                                    ],
                                    'lastUpdates' => [
                                        'type' => 'date',
                                        'format' => 'strict_date_optional_time||epoch_millis'
                                    ],
                                    'trackName' => [
                                        'enabled' => false
                                    ]
                                ]
                            ],
                            'tags' => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]];
        $response = $this->client->indices()->create($params);
        return $response['acknowledged'] == 1;
    }

}
