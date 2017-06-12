# phpList 4 core module

[![Build Status](https://travis-ci.org/phpList/phplist4-core.svg?branch=master)](https://travis-ci.org/phpList/phplist4-core)
[![Latest Stable Version](https://poser.pugx.org/phplist/phplist4-core/v/stable.svg)](https://packagist.org/packages/phpList/phplist4-core)
[![Total Downloads](https://poser.pugx.org/phplist/phplist4-core/downloads.svg)](https://packagist.org/packages/phpList/phplist4-core)
[![Latest Unstable Version](https://poser.pugx.org/phplist/phplist4-core/v/unstable.svg)](https://packagist.org/packages/phpList/phplist4-core)
[![License](https://poser.pugx.org/phplist/phplist4-core/license.svg)](https://packagist.org/packages/phpList/phplist4-core)


## About phpList

phpList is an open source newsletter manager. This project is a rewrite of the
[original phpList](https://github.com/phpList/phplist3).


## About this package

This is the phpList 4 core module. It will have the following responsibilities:

* provide access to the DB via Doctrine models and repositories (and raw SQL
  for performance-critical parts that do not need the models)
* routing (which the web frontend and REST API will use)
* authentication (which the web frontend and REST API will use)
* logging
* a script for tasks to be called from the command line (or a cron job)
* tasks to create and update the DB schema

Please note that this module does not provide a web frontend or a REST API.
There are the separate modules `phpList/web-frontend` and `phpList/rest-api`
for these tasks.

This module should not be modified locally. It should be updated via Composer.


## Installation

Please install this package via Composer from within the
[phpList base distribution](https://github.com/phpList/base-distribution),
which also has more detailed installation instructions in the README.


## Changing the database schema

Any changes to the database schema must always be done both in phpList 3 and
phpList 4 so that both versions always have the same schema.


## phpList 4 plugins

In phpList 4, plugins are called modules that are Composer packages having the
type `phplist-module`.

More documentation for this will follow.


## Copyright

phpList is copyright (C) 2000-2017 [phpList Ltd](http://www.phplist.com/).
