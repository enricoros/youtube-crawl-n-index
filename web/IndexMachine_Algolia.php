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

require_once 'IndexMachine.php';

define('IM_VIOLENT', true);
define('IM_VERBOSE', true);

class IndexMachine_Algolia implements IndexMachine
{
    // cloud console data
    const ALGOLIA_API_KEY = '../../twsh-algolia-apikey.txt';

    private $client;
    private $index;

    function __construct($indexName)
    {
        // create the client
        $this->client = new \AlgoliaSearch\Client("2TRUTQVPX8", trim(file_get_contents(self::ALGOLIA_API_KEY)));

        // create or retrieve the index
        if (!isset($indexName) || empty($indexName))
            $indexName = self::INDEX_NAME;
        $this->index = $this->client->initIndex($indexName);
        //$this->index->clearIndex();
        $this->updateIndexAndSearchSettings();
    }

    /**
     * @inheritdoc
     */
    public function addOrUpdate($objectID, $ytVideo)
    {
        try {
            $res = $this->index->saveObject($ytVideo->toSearchable($objectID));
        } catch (\AlgoliaSearch\AlgoliaException $e) {
            // failed because it's too big
            if (strpos($e->getMessage(), 'Record is too big') == 0)
                return false;
            // other failure reasons
            return false;
        }
        return $res['objectID'] == $objectID;
    }

    /// private stuff ahead ///

    private function updateIndexAndSearchSettings()
    {
        // TODO
        $this->index->setSettings(array(
            "attributesToIndex" => [ "unordered(text.t)" /*, "tags", "description", "title"*/ ],
            "customRanking" => [ "desc(pct_comments)", "desc(countViews)" ],
            "unretrievableAttributes" => [],
            "attributesForFaceting" => [ "_tags" ],
            "highlightPreTag" => "<em>",
            "highlightPostTag" => "</em>"
        ));
    }

    public function echoIndexSettings()
    {
        echo "<pre>";
        print_r($this->index->getSettings());
        echo "</pre>\n";
    }

}