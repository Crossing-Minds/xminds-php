==========
xminds-php
==========

PHP client for connecting to the Crossing Minds recommendation service.

See the `API Documentation`_ for the Crossing Minds universal recommendation API documentation.

.. _API Documentation: https://docs.api.crossingminds.com/

---------------
Getting Started
---------------

   require_once ("xminds/api/client.php");

   $client = new CrossingMindsApiClient();

   $client->login_individual("username", "password", "database_id");

   $d = $client->get_all_databases();


