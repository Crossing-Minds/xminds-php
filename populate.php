<?php

require_once ("xminds/api/client.php");

$client = new CrossingMindsApiClient(['host'=>"https://staging-api.crossingminds.com/"]);
$client->login_individual("tim.sheerman-chase@toptal.com", "uHz459KNbqwkDICNuQ0l", "44RtT3DMxeZSP6Wh50ilCg"); //7NweEa_OXE6n6BcLCh4cyg

$d = $client->get_all_databases();

$users = [];
for($i=1; $i<100; $i++)
{
	array_push($users, [
	  "user_id"=> $i,
	  "age"=> rand(16, 85),
	  "subscriptions"=> ["channel1", "channel2"]
	]);
}
$ret = $client->create_or_update_users_bulk($users);

$items = [];
for($i=1; $i<100; $i++)
{
	array_push($items, [
              "item_id"=> $i,
              "feature1"=> (int)(300+$i/2),
          ]);
}
$ret = $client->create_or_update_items_bulk($items);

$ratings = [];
for($i=0; $i<1000; $i++)
{
	array_push($ratings, ['user_id'=> rand(1, 1000), 'item_id'=> rand(1, 100), 
		'rating'=> rand(0,90)/10.0+1.0, 'timestamp'=> 1588812345]);
}
$ret = $client->create_or_update_ratings_bulk($ratings);

print_r ($ret);



?>
