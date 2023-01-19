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
