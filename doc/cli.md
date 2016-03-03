The Vault command line interface
================================

The Vault command line tool `vault.php` is available in the `bin`
directory. It can be used to perform basic engine tasks, and for
debugging.


Adding apps
-----------

Secret requests are tied to client apps, so you must have at least one
client app for this system to be usable.

To create an app, run the following command:

    $ php bin/vault.php app add APP-NAME [PING-URL]

Where *APP-NAME* is a human-readable app name, and *PING-URL* is the
optional ping URL.

This command will output API *key*, *secret* and *Vault secret*, which
should be used by the client app to talk with this Vault.


Generating request
------------------

You can generate a request on the command line on behalf of an
app. The syntax is:

    $ php bin/vault.php request KEY EMAIL

Where *KEY* is the app key, and *EMAIL* is the email address of the
user you want to request a secret from.

**Notice**: the command will stop reading optional instructions from
STDIN. Type Ctrl-D on an empty line to proceed.

**Notice**: Use of the command line tool *only* to generate requests
as a debugging tool, or to check that the system is properly
installed.
