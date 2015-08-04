This folder contains the source code for the backend architecture.

The purpose of the backend is to crawl for usable videos, download the subtitles, and send
all the information to the indexer (a web service). That's it.

## Frontend(s)
There is a Web frontend to the backend [index.php](index.php), which generates some html
that acts as a view/controller to the main crawling+indexing engine.

There is a commandline frontend to the backend [crawl.php], which shows the status and
allows to add things to be queried.

## Crawling + Downloading + Indexing
The YouTube crawler + processor + Search indexer works by atomically de-queuing some work
item and processing it. There can be more than one in parallel.

## Needs:
This operation needs REDIS. Which is used for:
 1. local cache for downloaded subtitles (key example: cc_v=n_mTiDeQvWg&lang=en&name=English&fmt=srv1)
 2. remembers if a video was processed already (key example: use_Yqv3ebAFluQ) to stop further processing
 3. atomic work orders and IPC:
   * work orders are in a Queue
   * executor processes use atomic process counter to know whether to run or not
