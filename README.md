Basic installation
------------------

1. `composer install`

2. `cp config.ini.dist config.ini`

3. Edit config.ini (you will need a database connection)

4. Run `php bin/install.php`


Adding apps
-----------

To add an app, run the following command:

    $ php bin/vault.php app add 'Here goes the app name'


Generating request
------------------

The syntax is:

    $ php bin/vault.php request *KEY* *EMAIL*

Where *KEY* is the app key, and *EMAIL* is the email address of the
user we are requesting a secret from.

Notice: the command will stop reading optional instructions from
STDIN. Type Ctrl-D on an empty line to proceed.
