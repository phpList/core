# phpList


[![Build Status](https://travis-ci.org/phpList/phpList.svg?branch=master)](https://travis-ci.org/phpList/phpList)
[![Latest Stable Version](https://poser.pugx.org/phplist/phplist4-core/v/stable.svg)](https://packagist.org/packages/phpList/phpList)
[![Total Downloads](https://poser.pugx.org/phplist/phplist4-core/downloads.svg)](https://packagist.org/packages/phpList/phpList)
[![Latest Unstable Version](https://poser.pugx.org/phplist/phplist4-core/v/unstable.svg)](https://packagist.org/packages/phpList/phpList)
[![License](https://poser.pugx.org/phplist/phplist4-core/license.svg)](https://packagist.org/packages/phpList/phpList)


## About phpList


PhpList is an open source newsletter manager. This project is a rewrite of the
[original phpList](https://github.com/phpList/phplist3).


## About this package

This is the phpList 4 core. It will have the following responsibilities:

* provide access to the DB via Doctrine models and repositories (and raw SQL
  for performance-critical parts that do not need the models)
* routing (which the web frontend and REST API will use)
* authentication (which the web frontend and REST API will use)
* logging
* a script for tasks to be called from the command line (or a cron job)
* tasks to create and update the DB schema

This package should not be modified locally. It should be updated via Composer.

## Copyright

phpList is copyright (C) 2000-2017 [phpList Ltd](http://www.phplist.com/).
