Vault
=====

Description
-----------

The Vault allows the secure exchange of user secrets (such as
passwords and other credentials) with support engineers.

The system provides a secure storage engine for user secrets, plus a
front-end to allow users to input those secrets -- and engineers to
retrieve them.

It provides a web API to be used by authorized client apps to
interface with the Vault engine. A command line app is also available,
to interfaces with the engine, and can be used to add other client
apps, generate secret requests, among other things.


Requirements
------------

* PHP >= 5.6

* PHP extensions: mysql, openssl, curl

* MySQL database


Installation
------------

See the
[installation instructions available in the doc/ folder](doc/install.md).


Additional Documentation
------------------------

More documentation is available in the `doc` directory:

* [System architecture](doc/architecture.md)

* [The command line interface](doc/cli.md)

* [The Vault client](doc/client.md)
