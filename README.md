The Vault
=========

Description
-----------

The Vault allows the secure exchange of user secrets (such as
passwords and other credentials) with support engineers.

It provides a secure storage engine for user secrets, and a thin
front-end to allow users to input those secrets, and engineers to
retrieve them. It also provides a RESTful API that can be used by
authorized client apps to interface with the Vault engine. Lastly, it
provides a command line app that interfaces with the engine, and can
be used to add other client apps, and generate secret requests.


Requirements
------------

* PHP >= 5.6

* PHP extensions: mysql, openssl, curl

* MySQL database


Installation
------------

1. `composer install`

2. `cp config.ini.dist config.ini`

3. Edit config.ini and edit/review the settings.

4. Run `php bin/install.php`. If everything goes well, your system is
ready to be used.


Web server setup
----------------

The engine requires two separate web addresses to provide service --
one for the input/unlock front-end, and other for API access. The
document root for these addresses are below, respectively:

* www/ - input/unlock front-end

* api/ - API

The system was tested under Apache. It requires only standard PHP
serving from the web software, so it may be straightforward to install
it under another web software. A `.htaccess` file is provided under
each of the directories above, that promptly configure the service to
work under Apache, but which also may be used as a starting point to
configure other web software.


Maintenance
-----------

To properly expire secrets and requests a maintenance task must be run
periodically. The following line can be used to configure cron(8) to
run the maintenance task:

    */5 * * * *   www-data  php ROOT/bin/vault.php maintenance

Change *ROOT* to the root path where the system is installed.
