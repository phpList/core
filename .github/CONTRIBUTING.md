# Contributing to this project

:+1::tada: Many thanks for taking the time to contribute! :tada::+1:

When you contribute, please take the following things into account:


## Contributor Code of Conduct

Please note that this project is released with a
[Contributor Code of Conduct](../CODE_OF_CONDUCT.md). By participating in this
project, you agree to abide by its terms.


## Reporting an issue

We have provided an issue template that will help you create helpful tickets.


## Do not report an issue if …

* … you are asking how to use some feature. Please use
  [the phpList community](https://www.phplist.org/users/) for this purpose.
* … your issue is about a security vulnerability. Please
  [contact us directly](mailto:info@phplist.com) to report security issues.


## Avoid duplicated issues

Before you report an issue, please search through the existing issues here on
GitHub to see if your issue is already reported or fixed to make sure you are
not reporting a duplicated issue.

Also please make sure you have the latest version of this package and check if
the issue still exists.


# Contribute code, bug fixes or documentation (pull requests)

Third-party contributions are essential for keeping the project great.

We want to keep it as easy as possible to contribute changes that get things
working in your environment.

There are a few guidelines that we need contributors to follow so that we can
have a chance of keeping on top of things:

1. Make sure you have a [GitHub account](https://github.com/join).
2. [Fork this Git repository](https://guides.github.com/activities/forking/).
3. Clone your forked repository and install the development dependencies doing
   a `composer install`.
4. Add a local remote "upstream" so you will be able to
   [synchronize your fork with the original repository](https://help.github.com/articles/syncing-a-fork/).
5. Create a local branch for your changes.
6. Add unit tests for your changes (if your changes are code-related).
   These tests should fail without your changes.
7. Add your changes. Your added unit tests now should pass, and no other tests
   should be broken. Check that your changes follow the
   [coding style](#coding-style).
8. Add a changelog entry.
9. [Commit](#git-commits) and push your changes.
10. [Create a pull request](https://help.github.com/articles/about-pull-requests/)
    for your changes. Check that the Travis build is green. (If it is not, fix the
    problems listed by Travis.)
    We have provided a template for pull requests as well.
11. [Request a review](https://help.github.com/articles/about-pull-request-reviews/).
11. Together with your reviewer, polish your changes until they are ready to be
    merged.


## Unit-test your changes

Please cover all changes with unit tests and make sure that your code does not
break any existing tests. We will only merge pull request that include full
code coverage of the fixed bugs and the new features.

To run the existing PHPUnit tests, run this command:

    vendor/bin/phpunit Tests/


## Coding Style

Please use the same coding style ([PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md))
as the rest of the code. Indentation for all files is four spaces.

We will only merge pull requests that follow the project's coding style.

Please check your code with the provided PHP_CodeSniffer standard:

    vendor/bin/phpcs --standard=PSR2 Classes/ Tests/

Please make your code clean, well-readable and easy to understand.
