<?php

require_once ("xminds/api/client.php");

$client = new CrossingMindsApiClient(['host'=>"https://staging-api.crossingminds.com/", 'serializer'=>"json"]);
//$client->login_root("root-php-client@test-php.com", "In2p23NKJiPTpQPR");
$client->login_individual("tim.sheerman-chase@toptal.com", "uHz459KNbqwkDICNuQ0l", "44RtT3DMxeZSP6Wh50ilCg"); //7NweEa_OXE6n6BcLCh4cyg
//$client->login_service("Tim Service Account", "fW68l5NZYj622K4jTstB", "44RtT3DMxeZSP6Wh50ilCg"); //7NweEa_OXE6n6BcLCh4cyg

//print ($client->jwt_token());

//$d = $client->get_all_databases();

//print_r ($d);

//$ret = $client->login_refresh_token();
//$ret = $client->create_individual_account("Tim", "Sheerman-Chase", "tim.sheerman-chase@toptal.com", "uHz459KNbqwkDICNuQ0l");
//$ret = $client->create_individual_account("Tim 2", "Sheerman-Chase 2", "orders2008@sheerman-chase.org.uk", "e7NR1J6ov8BGajz2gMU9"); //znCy44vyTWnr9WduH5KIsw
//$ret = $client->create_service_account("Tim Service Account", "fW68l5NZYj622K4jTstB"); //TFPQ_NmQgCkhEGFeLhlt-w
//$ret = $client->create_database("Tim test db", "Testing");
//$ret = $client->resend_verification_code("orders2008@sheerman-chase.org.uk");
//$ret = $client->verify_account("7592816a9f4817b2b7240c9946017bc84573bd68", "orders2008@sheerman-chase.org.uk");
//$ret = $client->list_accounts();
//$ret = $client->get_organization();
//$ret = $client->create_or_partial_update_organization(['name'=>'test', 'id'=>1001]);
//$ret = $client->partial_update_organization_preview(['name'=>'test 2', 'id'=>1002]);
//$ret = $client->get_database();
//$ret = $client->partial_update_database("very descriptive text");
//$ret = $client->partial_update_database_preview("very descriptive text");
//$ret = $client->delete_database();
//$ret = $client->status();
//$ret = $client->create_user_property("age", "uint32");
//$ret = $client->create_user_property("subscriptions", "unicode", true);
//$ret = $client->list_user_properties();
//$ret = $client->get_user_property("foo");
//$ret = $client->delete_user_property("foo");
//$ret = $client->get_user("464737");
//$ret = $client->create_or_update_user(['user_id'=> '35257', 'foo'=> 357246]);
//$ret = $client->partial_update_user(['user_id'=> '35257', 'foo'=> 643567]);
/*$ret = $client->create_or_update_users_bulk([
          [
              "user_id"=> "123e4567-e89b-12d3-a456-426614174000",
              "age"=> 25,
              "subscriptions"=> ["channel1", "channel2"]
          ],
          [
              "user_id"=> "c3391d83-553b-40e7-818e-fcf658ec397d",
              "age"=> 32,
              "subscriptions"=> ["channel1"]
          ]
      ]);*/

/*$ret = $client->create_or_update_users_bulk([
          [
              "user_id"=> 784567,
              "age"=> 25
          ],
          [
              "user_id"=> 464737,
              "age"=> 32
          ]
      ],[
          [
              "name"=> "subscriptions",
              "array"=> [
                  ["user_index"=> 0, "value_id"=> "channel1"],
                  ["user_index"=> 0, "value_id"=> "channel2"],
                  ["user_index"=> 1, "value_id"=> "channel1"]
              ]
          ]
      ]);*/

/*$ret = $client->partial_update_users_bulk([
          [
              "user_id"=> 784567,
              "age"=> 27,
          ],
          [
              "user_id"=> 464737,
              "age"=> 34,
          ]
      ]);*/

/*$ret = $client->partial_update_users_bulk([
          [
              "user_id"=> 784567,
          ],
          [
              "user_id"=> 464737,
          ]
      ],[
          [
              "name"=> "subscriptions",
              "array"=> [
                  ["user_index"=> 0, "value_id"=> "channel3"],
                  ["user_index"=> 0, "value_id"=> "channel5"],
                  ["user_index"=> 1, "value_id"=> "channel4"]
              ]
          ]
      ]);*/

//$ret = $client->create_item_property("feature1", "uint32");
//$ret = $client->get_item_property("feature1");
//$ret = $client->list_item_properties();
//$ret = $client->delete_item_property("feature1");

//$ret = $client->create_or_update_item(['item_id'=>4264735, 'feature1'=>68543]);
//$ret = $client->get_item(4264735);

//$ret = $client->list_items([4264735]);
$ret = $client->list_items_paginated(); // Not working?
/*
$ret = $client->create_or_update_items_bulk([
          [
              "item_id"=> 4242576,
              "feature1"=> 25,
          ],
          [
              "item_id"=> 3585426,
              "feature1"=> 32,
          ]
      ]);*/

print_r ($ret);



?>
