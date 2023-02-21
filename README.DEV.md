# Installation
If you are installing the plugin through a built zip file, then follow the standard installation from the [readme.txt](readme.txt) file.

If you are cloning the plugin directly from Github, or downloading it in any other way other then from a built resource, then you will need to run composer to install the plugins composer dependencies.

For this you will need to have the following:
* [PHP 7.2+](https://www.php.net/manual/en/install.php).
* [Composer](https://getcomposer.org/doc/00-intro.md).

After these are installed you can run `composer install` or `composer install --no-dev` from the plugins directory in your CLI. The first command will install all packages, including the once only required for development, and the second will install only packages required for production.
