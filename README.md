### Youtube Crawler, Close Caption downloader and Indexer

This project handles all the backend needs for the operation. A good name for it has not been
thought through yet.

In this folder just run `php composer.phar update` to fetch all the dependencies,
then take a look at the [README.md](indexer/README.md) in the [indexer](indexer/)
folder to understand more of the architecture.

To deploy, you need:
 * php with the curl extension
 * redis
 * an accessible Elasticsearch instance
 * a valid API security for Algolia (the current indexing service), and editing the corresponding PHP file
 * to serve the indexer/ folder with Apache, or you can use the commandline executable to start indexing and check the status

#### Web Console Screenshot
![Web Console Screenshot](web-screenshot.png?raw=true "Optional Title")

#### ./console.php Screenshots
n/a.. it's text.

Read more on [indexer/README.md](indexer/README.md).


