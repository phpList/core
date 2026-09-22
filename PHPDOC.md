# Generating class documentation

We use [phpDocumentor](https://phpdoc.org) to generate API docs from the docblocks on
our classes, properties, and methods. Output settings (title, output path) are defined
in [`phpdoc.xml`](phpdoc.xml); the generated docs are written to `docs/phpdocumentor/`
and are not committed to the repository.

## Install phpDocumentor

phpDocumentor ships as a standalone `.phar`. Install it once, globally:

```bash
wget https://phpdoc.org/phpDocumentor.phar -O /usr/local/bin/phpDocumentor
chmod +x /usr/local/bin/phpDocumentor
```

If you'd rather not install it globally, download the `.phar` anywhere and call it
by its full path in the steps below.

## Generate the docs

```bash
composer run-php-documentor
```

This runs `phpDocumentor -d 'src,tests'`, using the output path from `phpdoc.xml`.
Open `docs/phpdocumentor/index.html` in a browser to view the result.