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

require_once 'CacheMachine.php';

define('JOBS_VIOLENT', true);
define('JOBS_SPAWN_WORKERS', true);
define('JOBS_DONE_SET_NAME', 'jobs_done');
define('JOBS_RECENT_NAME', 'jobs_recent');
define('JOBS_RECENT_SIZE', 10);
define('JOBS_QUEUE_NAME', 'twhs_jobs');
define('JOBS_COUNT_NAME', 'twhs_workers_count');
define('JOBS_MAX_COUNT', 4);


/* All of the operations here are performed atomically, using REDIS, to
guarantee multi-process safety. */

/**
 * @param $jobArray String[] Array of strings containing Queries.
 * @param $spawnWorkerProcess boolean Se to true (default) to spawn a new jobs executor.
 * @return bool True if the job was added.
 */
function work_queueForWorkers($jobArray, $spawnWorkerProcess = true)
{
    // get redis
    $redis = CacheMachine::getPRedisClientOrDie();

    // push it in the queue
    $length = $redis->rpush(JOBS_QUEUE_NAME, $jobArray);
    if ($length < 1) {
        if (JOBS_VIOLENT)
            die('redis did not take it');
        return false;
    }

    // if requested start a worker
    if ($spawnWorkerProcess == true) {
        // NOTE: for this to work, we also need permissions (to the web server user)
        // to /tmp/index-workers* and /tmp/Google_Client, since another user may be
        // owning them.
        // this just executes the worker.php script, which may eventually bail out immediately
        $spawnCmd =
            'nohup ' .                          // don't die when apache worker dies
            '/usr/bin/php ' .                   // php executable
            __DIR__ . '/worker.php ' .          // php script, in this current dir?
            '>> /tmp/index-workers.log ' .      // append output to /tmp/index-workers.log
            '2>> /tmp/index-workers.err ' .     // append error to /tmp/index-workers.err
            '< /dev/null ' .                    // don't take input
            '&';                                // run in background
        if (JOBS_SPAWN_WORKERS)
            exec($spawnCmd);
        else
            echo "workers spawning (" . $spawnCmd . ") disabled\n";
    }

    // great, the job was pushed
    return true;
}


/**
 * @return null|string The job to be executed, or null if there is no job and we can terminate.
 */
function work_getOneForMe()
{
    // get redis
    $redis = CacheMachine::getPRedisClientOrDie();

    // test if we can execute
    $newCount = $redis->incr(JOBS_COUNT_NAME);

    // if we can't, just give up
    if ($newCount <= JOBS_MAX_COUNT) {
        // if we can, get the job and leave the state as 'incremented'
        $job = $redis->lpop(JOBS_QUEUE_NAME);
        if ($job != null) {
            // also save it to the recent pipe for inspection
            $redis->lpush(JOBS_RECENT_NAME, $job);
            $redis->ltrim(JOBS_RECENT_NAME, 0, JOBS_RECENT_SIZE - 1);
            // also save it to the global set of queries
            $redis->sadd(JOBS_DONE_SET_NAME, [ $job ]);
            // and yes, return the job to be executed :)
            return $job;
        }
    }

    // if no job, or no slots.. consider it done :)
    work_doneWithMyCurrent();
    return null;
}

/**
 * Mandatory! to be called when the worker is done with the job. Releases a slot for the
 * same worker or another one.
 */
function work_doneWithMyCurrent()
{
    // get redis
    $redis = CacheMachine::getPRedisClientOrDie();
    $newCount = $redis->decr(JOBS_COUNT_NAME);

    // sanity check
    if (JOBS_VIOLENT && $newCount < 0) {
        $redis->del([JOBS_COUNT_NAME]);
        die('negative jobs count. impossible. resetting all. goodbye.');
    }
}


// Misc admin functions

function work_admin_getStats()
{
    // get redis
    $redis = CacheMachine::getPRedisClientOrDie();
    return [
        'queued contents' => $redis->lrange(JOBS_QUEUE_NAME, 0, -1),
        'workers active' => $redis->get(JOBS_COUNT_NAME),
        'workers max' => JOBS_MAX_COUNT,
        'workers enabled' => JOBS_SPAWN_WORKERS,
        'unique queries' => $redis->scard(JOBS_DONE_SET_NAME),
        'recent contents' => $redis->lrange(JOBS_RECENT_NAME, 0, -1)
    ];
}

function work_admin_reset_DoNotUse($jobsToo)
{
    // get redis
    $redis = CacheMachine::getPRedisClientOrDie();
    $redis->set(JOBS_COUNT_NAME, 0);
    if ($jobsToo)
        $redis->del([JOBS_QUEUE_NAME]);
}
