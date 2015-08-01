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

define('IM_VIOLENT', true);
define('IM_VERBOSE', true);

class IndexMachine_Algolia implements IndexMachine
{
    // cloud console data
    const ALGOLIA_API_KEY = '../../twsh-algolia-apikey.txt';

    private $client;
    private $index;

    function __construct()
    {
        // create the client
        $this->client = new \AlgoliaSearch\Client("2TRUTQVPX8", trim(file_get_contents(self::ALGOLIA_API_KEY)));

        // create or retrieve the index
        $this->index = $this->client->initIndex(IndexMachine::INDEX_NAME);
        //$this->index->clearIndex();
        $this->updateIndexAndSearchSettings();
    }

    public function addOrUpdate($objectIndex, $newObject)
    {
        echo $newObject;


        /*$res = $this->index->saveObject([
            "firstname" => "Jimmie",
            "lastname" => "Barninger",
            "objectID" => "myID1"
        ]);*/
    }

    /// private stuff ahead ///

    private function updateIndexAndSearchSettings()
    {
        // TODO
        /*$this->index->setSettings(array(
            "attributesToIndex" => array("name", "description", "url"),
            "customRanking" => array("desc(vote_count)", "asc(name)")
        ));*/
    }


}