#!/usr/bin/env php
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

require_once 'jobs_interface.php';

if (!isset($argv) || sizeof($argv) < 2)
    die("We require at least one command line argument:\n" .
        "  add some_query   adds the query to the jobs schedule (e.g., literally: \"\\\"that's what she said\\\",cc\")\n" .
        "  status           shows the status of the jobs\n" .
        "  clear            resets the workers to 0 (but does NOT kill any running worker!)\n" .
        "\n");

// handle operations
$count = sizeof($argv);
switch ($argv[1]) {
    case 'a':
    case 'add':
        if ($count < 3)
            die("for 'add', we need which string to add. double-quoted and with double-quotes slashed.\n");
        $query = $argv[2];
        echo "adding: [ '" . $query . "' ] to the index... ";
        echo work_queueForWorkers([$query], true) ? "ok\n" : "ERROR\n";
        break;

    case 's':
    case 'status':
        break;

    case 'c':
    case 'clear':
        die("WARNING: re-execute the script with 'reallyclear', but be sure to stop all PHP workers before!\n");

    case 'rc':
    case 'reallyclear':
        work_admin_reset_DoNotUse($count == 3 && $argv[2]=='all');
        break;

    default:
        die("unknown command " . $argv[1] . "\n");
}

// show status at the end of many commands
print_r(work_admin_getStats());
