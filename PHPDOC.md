# Class Documentation with PHPDoc 

We use [phpdoc](phpdoc.org) to automatically generate documentation for our annotated classes.

So to be able to generate or update our class docs you would need to download and install `phpDocumentor` globally (for system wide use) as shown below:

1. `cd ~` [*Optional : it's recommended to navigate to your home dir before downloading `phpDocumentor` as shown in step 2*]
2. `wget https://phpdoc.org/phpDocumentor.phar`
3. `chmod +x phpDocumentor.phar`
4. `mv phpDocumentor.phar /usr/local/bin/phpDocumentor`

*Possibility : In case you don't want to install `phpDocumentor` globally you can skip step 4, however you would need to run `phpDocumentor` from whatever path it was installed in.*

*Tip : You might need to run step four as root on some systems. That is : `sudo mv phpDocumentor.phar /usr/local/bin/phpDocumentor`*

## Generate Docs

If you did install `phpDocumentor` globally as specified above then you can generate class docs as follows.
Run : `composer run-php-documentor`



*Note : `composer generate docs` would only work if you installed `phpDocumentor` globally, if you did not run : `custom/path/phpDocumentor -d 'src,tests' -t docs/phpdoc` to generate docs*

*Where `custom/path/`  is the location where you downloaded `phpDocumentor`*
