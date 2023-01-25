# WFO Plant List - Publication Platform

This is an application to serve the twice yearly data releases of the World Flora Online Plant List through an API and simple UI for name matching.

Although this is primarily designed to be hosted by the WFO as part of its online offering should also be feasible to install and run all or part of it locally if needed. You are encouraged to install a local instance if your intended usage might adversely affect the performance of the main server e.g. you are name matching over a large corpus of text.

Forks, ports and suggestions for improvements are very welcome!

## Dependencies

* SOLR Index - 
* PHP 8.*
* Composer packages.
  * "easyrdf/easyrdf": "*",
  * "ml/json-ld": "*",
  * "webonyx/graphql-php": "*"
  

### Composer

Install composer from https://getcomposer.org/download/ 

Then run `php composer.phar update` to install dependencies.

### Solr

You need an instance of a SOLR server either locally or available over the network. Refer to the SOLR documentation if you are unsure.

Create a core in the server called "wfo2". There are various ways to do this but running

`./solr create -c wfo2`

in the SOLR bin directory will usually do the trick. Disable authentication temporarily if needed.

Using the SOLR graphical interface add a copy field to that core copies from * to \_text\_

Import data releases from WFO Plant list. The latest version is always available from Zenodo on the DOI https://doi.org/10.5281/zenodo.7467360 as a file called something like plant_list_20xx-xx.json.zip.

It can be imported into the index with the following command.

`curl -H 'Content-type:application/json' 'http://localhost:8983/solr/wfo2/update?commit=true' -X POST -T plant_list_2022-12.json`

Import will take up to an hour depending on resources available.

You can install as many previous versions of the data release are useful. Those prior to December 2022 are available here (FIXME).





