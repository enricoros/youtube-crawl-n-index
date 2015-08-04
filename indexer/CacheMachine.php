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

class CacheMachine
{
    /**
     * @param $key String
     * @return bool true if the key exists (and redis is up)
     */
    public static function hasKey($key)
    {
        $client = self::getPRedisClient();
        return $client != null && $client->exists($key);
    }

    /**
     * @param $key String
     * @return null|string
     */
    public static function retrieveValue($key)
    {
        $client = self::getPRedisClient();
        return $client != null ? $client->get($key) : null;
    }

    /**
     * @param $key String
     * @param $value
     * @param $expiration String Human time expiration ('1 day')
     * @return bool True if storage was successful
     */
    public static function storeValue($key, $value, $expiration)
    {
        $client = self::getPRedisClient();
        if ($client != null && $client->set($key, $value) == 'OK') {
            if ($expiration != null)
                $client->expire($key, $expiration);
            return true;
        }
        return false;
    }

    /**
     * @return \Predis\Client The Redis client, or null.
     */
    public static function getPRedisClientOrDie()
    {
        $redis = CacheMachine::getPRedisClient();
        if ($redis == null)
            die('redis is down');
        return $redis;
    }


    /**
     * @return null|\Predis\Client The Redis client, or null.
     */
    private static function getPRedisClient()
    {
        // just once
        if (self::$sPRedisClient == null) {
            $client = new Predis\Client(
                array(
                    'scheme' => 'tcp',
                    'host' => '127.0.0.1',
                    'port' => 6379
                )
            );
            try {
                $client->connect();
            } catch (Exception $e) {
                // connection unsuccessful... do nothing
                return null;
            }
            self::$sPRedisClient = $client;
        }
        return self::$sPRedisClient;
    }

    private static $sPRedisClient = null;

}