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

interface IndexMachine
{
    const INDEX_NAME = 'yt_cc';

    /**
     * @param $objectID String the objectId
     * @param $ytVideo YTVideo a valid object to index - transformation will happen inside, not outside
     * @return bool true if the operation was successful
     */
    public function addOrUpdate($objectID, $ytVideo);

}
