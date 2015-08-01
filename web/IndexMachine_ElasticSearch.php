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

class IndexMachine_ElasticSearch implements IndexMachine
{
    const IM_TYPE = 'youtube';

    private $client;
    private $defaultIndex;

    function __construct($hostname)
    {
        $this->client = new Elasticsearch\Client([
            'hosts' => [$hostname]
        ]);
        $this->defaultIndex = new TypedIndex(IndexMachine::INDEX_NAME, self::IM_TYPE, $this->client);
    }

    public function addOrUpdate($index, $content)
    {
        $params = [
            'index' => IM_INDEX,
            'type' => IM_TYPE,
            'id' => $index,
            'ignore' => [400, 404]
        ];
        echo $this->client->get($params);
    }

}

class TypedIndex
{
    private $client;
    private $indexParams;
    private $typeParams;

    /**
     * @param $indexName String
     * @param $typeName String
     * @param $client Elasticsearch\Client
     */
    public function __construct($indexName, $typeName, $client)
    {
        $this->client = $client;
        $this->indexParams = [
            'index' => $indexName
            /*, 'ignore' => [400, 404]*/
        ];
        $this->typeParams = [
            'index' => $indexName,
            'type' => $typeName
            /*, 'ignore' => [400, 404]*/
        ];
        $this->ensureIsThere();
        $this->singleDocumentIndex();
    }


    public function singleDocumentIndex()
    {
        $index = $client->initIndex('YourIndexName');

        $params = $this->typeParams;
        /*$params = [
            'body' => [ 'test' => 'enrico' ],
            'index' =>
        ];*/

        //$params['index'] = 'my_index';
        //$params['type']  = 'my_type';
        $params['id'] = 'my_id';

        $p2 = $this->typeParams;
        $p3 = $params;

// Document will be indexed to my_index/my_type/my_id
        //$ret = $client->index($params);
    }


    public function getName()
    {
        return $this->indexParams['index'];
    }

    public function getType()
    {
        return $this->typeParams['type'];
    }


    private function ensureIsThere()
    {
        if (!$this->client->indices()->exists($this->indexParams))
            $this->client->indices()->create($this->indexParams);
        $this->drop();
    }

    private function drop()
    {
        if ($this->client->indices()->exists($this->indexParams))
            $this->client->indices()->delete($this->indexParams);
    }


    /*        $arr =

            $updateParams['index']          = 'my_index';
            $updateParams['type']           = 'my_type';
            $updateParams['id']             = 'my_id';
            $updateParams['body']['doc']    = array('my_key' => 'new_value');

            $retUpdate = $client->update($updateParams);
        }*/

}
