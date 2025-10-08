# phpList Core Module

[![Build Status](https://github.com/phpList/core/workflows/phpList%20Core%20Build/badge.svg)](https://github.com/phpList/core/actions)
[![Latest Stable Version](https://poser.pugx.org/phplist/core/v/stable.svg)](https://packagist.org/packages/phpList/core)
[![Total Downloads](https://poser.pugx.org/phplist/core/downloads.svg)](https://packagist.org/packages/phpList/core)
[![Latest Unstable Version](https://poser.pugx.org/phplist/core/v/unstable.svg)](https://packagist.org/packages/phpList/core)
[![License](https://poser.pugx.org/phplist/core/license.svg)](https://packagist.org/packages/phpList/core)

## About phpList

phpList is an open source newsletter manager. This project is a rewrite of the
[original phpList](https://github.com/phpList/phplist3).

## About this package

This is the core module of phpList 4, currently in alpha stage. It provides the following functionality:

* Database access via Doctrine models and repositories (and raw SQL for performance-critical parts)
* Routing (which the web frontend and REST API use)
* Authentication (which the web frontend and REST API use)
* Logging (including Graylog integration for centralized logging)
* Command line interface for maintenance tasks
* Database schema creation and updates
* Asynchronous email sending using Symfony Messenger

Please note that this module does not provide a web frontend or a REST API.
There are separate modules for these purposes:
* [`phpList/web-frontend`](https://github.com/phpList/web-frontend)
* [`phpList/rest-api`](https://github.com/phpList/rest-api)

This module should not be modified locally. It should be updated via Composer.

## Requirements

* PHP 8.1 or higher
* Symfony 6.4 components
* Doctrine ORM 3.3

## Installation

Since this package is only a service required to run a full installation of **phpList 4**, the recommended way of installing this package is to run `composer install` from within the [phpList base distribution](https://github.com/phpList/base-distribution) which requires this package. [`phpList/base-distribution`](https://github.com/phpList/base-distribution) contains detailed installation instructions in its [README](https://github.com/phpList/base-distribution/blob/master/README.md).

## Contributing to this package

Contributions to phpList repositories are highly welcomed! To get started please take a look at the [contribution guide](.github/CONTRIBUTING.md). It contains everything you would need to make your first contribution including how to run local style checks and run tests.

### Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md).
By participating in this project and its community, you are expected to uphold
this code.

## Documentation

* [Class Docs](docs/phpdoc/)
* [Class structure overview](docs/ClassStructure.md)
* [Graphic domain model](docs/DomainModel/DomainModel.svg) and [description of the domain entities](docs/DomainModel/Entities.md)
* [Mailer Transports](docs/mailer-transports.md) - How to use different email providers (Gmail, Amazon SES, Mailchimp, SendGrid)
* [Asynchronous Email Sending](docs/AsyncEmailSending.md) - How to use asynchronous email sending with Symfony Messenger

## Running the web server

The phpList application is configured so that the built-in PHP web server can
run in development and testing mode, while Apache can run in production mode.

Please first set the database credentials in `config/parameters.yml`.

### Development

To run the application in development mode using PHP's built-in server,
use this command:

```bash
bin/console server:run -d public/
```

The server will then listen on `http://127.0.0.1:8000` (or, if port 8000 is
already in use, on the next free port after 8000).

You can stop the server with CTRL + C.

#### Development and Documentation

We use `phpDocumentor` to automatically generate documentation for classes. To make this process efficient and easier, you are required to properly "document" your  `classes`,`properties`, `methods` ... by annotating them with [docblocks](https://docs.phpdoc.org/latest/guide/guides/docblocks.html).

More about generating docs in [PHPDOC.md](PHPDOC.md)

### Testing

Create test db with name phplist in your mysql DB or uncomment sqlite part in config_test.yml file to use in memory DB for functional tests.
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

For changing the database schema, please edit `resources/Database/Schema.sql`
and adapt the corresponding domain model classes and repository classes
accordingly.

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
      "PhpList\\Core\\EmptyStartPageBundle\\PhpListEmptyStartPageBundle"
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

## Email Configuration

phpList supports multiple email transport providers through Symfony Mailer. The following transports are included:

* Gmail
* Amazon SES
* Mailchimp Transactional (Mandrill)
* SendGrid

For detailed configuration instructions, see the [Mailer Transports documentation](docs/mailer-transports.md).

## Copyright

phpList is copyright (C) 2000-2025 [phpList Ltd](https://www.phplist.com/).


### Translations
command to extract translation strings

```bash
php bin/console translation:extract --force en --format=xlf
```
