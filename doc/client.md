The Vault reference client app
==============================

This package contains a reference client for the Vault system. It
allows one to setup a system to securely exchange secret information
using only e-mail.

This app is only a thin client to the Vault engine. Although it is
distributed together with Vault, it communicates with the engine only
by using Vault API.


Requirements
------------

* A web server with support for PHP >= 5.6

* The following PHP extensions: openssl, curl

* A WordPress.com OAuth2 app ID and secret

* A Vault API key, secret, and Vault secret



Installation
------------

Installing the client app is straightforward. Here's the steps:

1. Run `composer install` in the project root to bring in all the
dependencies.

2. `cp client.ini.dist client.ini`

3. Edit client.ini and edit/review the settings.

4. Setup a web address in your web server. Point the document root to
   the `client/` directory that s present in the Vault root.
