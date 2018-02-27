# phpList core module

[![Build Status](https://travis-ci.org/phpList/core.svg?branch=master)](https://travis-ci.org/phpList/core)
[![Code Coverage](https://scrutinizer-ci.com/g/phpList/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/phpList/core/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpList/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpList/core/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/phplist/core/v/stable.svg)](https://packagist.org/packages/phpList/core)
[![Total Downloads](https://poser.pugx.org/phplist/core/downloads.svg)](https://packagist.org/packages/phpList/core)
[![Latest Unstable Version](https://poser.pugx.org/phplist/core/v/unstable.svg)](https://packagist.org/packages/phpList/core)
[![License](https://poser.pugx.org/phplist/core/license.svg)](https://packagist.org/packages/phpList/core)


## About phpList

phpList is an open source newsletter manager. This project is a rewrite of the
[original phpList](https://github.com/phpList/phplist3).


## About this package

This is the core module of the successor to phpList 3. It will have the 
following responsibilities:

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


## Contributing to this package

Please read the [contribution guide](.github/CONTRIBUTING.md) on how to
contribute and how to run the unit tests and style checks locally.

### Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md).
By participating in this project and its community, you are expected to uphold
this code.


## Structure

* [class structure overview](docs/ClassStructure.md)
* [graphic domain model](docs/DomainModel/DomainModel.svg) and
  a [description of the domain entities](docs/DomainModel/Entities.md)


## Running the web server

The phpList application is configured so that the built-in PHP web server can
run in development and testing mode, while Apache can run in production mode.

Please first set the database credentials in `Configuration/parameters.yml`.

### Development

For running the application in development mode using the built-in PHP server,
use this command:

```bash
bin/console server:run -d public/
```

The server will then listen on `http://127.0.0.1:8000` (or, if port 8000 is
already in use, on the next free port after 8000).

You can stop the server with CTRL + C.

### Testing

To run the server in testing mode (which normally will only be needed for the
automated tests, provide the `--env` option:

```bash
bin/console server:run -d public/ --env=test
```

### Production

For documentation on running the application in production mode using Apache,
please see the
[phpList base distribution README](https://github.com/phpList/base-distribution).


## Changing the database schema

Any changes to the database schema must always be done both in phpList 3 and
later versions so that both versions always have the same schema.

For changing the database schema, please edit `Database/Schema.sql` and adapt
the corresponding domain model classes and repository classes accordingly.


## Developing phpList modules (plugins)

In phpList, plugins are called **modules**. They are Composer packages which
have the type `phplist-module`.

### Bundle and route configuration

If your module provides any Symfony bundles, the bundle class names need to be
listed in the `extra` section of the module's `composer.json` like this:

```json
"extra": {
    "phplist/core": {
        "bundles": [
            "Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle",
            "PhpList\\PhpList4\\EmptyStartPageBundle\\PhpListEmptyStartPageBundle"
        ]
    }
}
```

Please note that the key of the section with `extra` needs to always be
`phplist/core`, not the name of your module package. Please have a
look at the
[`composer.json` in the `rest-api` module](https://github.com/phpList/rest-api/blob/master/composer.json)
for an example.

Similarly, if your module provides any routes, those also need to be listed in
the `extra` section of the module's `composer.json` like this:

```json
"extra": {
    "phplist/core": {
        "routes": {
            "homepage": {
                "resource": "@PhpListEmptyStartPageBundle/Controller/",
                "type": "annotation"
            }
        }
    }
}
```

You can also provide system configuration for your module:

```json
"extra": {
    "phplist/core": {
        "configuration": {
            "framework": {
                "templating": {
                    "engines": [
                        "twig"
                    ]
                }
            }
        }
    }
}
```

It is recommended to define the routes using
[annotations](https://symfony.com/doc/current/routing.html#routing-examples)
in the controller classes so that the route configuration in the composer.json
is minimal.

### Accessing the database

For accessing the phpList database tables from a module, please use the
[Doctrine](http://www.doctrine-project.org/) model and repository classes
stored in `src/Domain/` in the `phplist/core` package (this
package).

For accessing a repository, please have it injected via
[dependency injection](https://symfony.com/doc/current/components/dependency_injection.html).
Please do not get the repository directly from the entity manager as this would
skip dependency injection for that repository, causing those methods to break
that rely on other services having been injected.

Currently, only a few database tables are mapped as models/repositories. If you
need a mode or a repository method that still is missing, please
[submit a pull request](https://github.com/phpList/core/pulls) or
[file an issue](https://github.com/phpList/core/issues).


## Accessing the phpList data from third-party applications

To access the phpList data from a third-party application (i.e., not from a
phpList module), please use the
[REST API](https://github.com/phpList/rest-api).


## Copyright

phpList is copyright (C) 2000-2018 [phpList Ltd](https://www.phplist.com/).
