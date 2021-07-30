<?php

require_once ("xminds/api/client.php");

$client = new CrossingMindsApiClient(['host'=>"https://staging-api.crossingminds.com/"]);
$client->login_individual("tim.sheerman-chase@toptal.com", "uHz459KNbqwkDICNuQ0l", "6OCbeqY7xpThY4hZPjWX-A");

$d = $client->get_all_databases();
print_r ($d);

$ret = $client->create_user_property("age", "uint32");
$ret = $client->create_user_property("subscriptions", "unicode", true);

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
print_r ($ret);

$ret = $client->create_item_property("feature1", "uint32");
$ret = $client->create_item_property("feature2", "unicode", true);

$items = [];
for($i=1; $i<100; $i++)
{
    array_push($items, [
              "item_id"=> $i,
              "feature1"=> (int)(300+$i/2),
          ]);
}
$ret = $client->create_or_update_items_bulk($items);
print_r ($ret);

$ratings = [];
for($i=0; $i<1000; $i++)
{
    array_push($ratings, ['user_id'=> rand(1, 100), 'item_id'=> rand(1, 100), 
        'rating'=> rand(0,90)/10.0+1.0, 'timestamp'=> 1588812345]);
}
$ret = $client->create_or_update_ratings_bulk($ratings);

print_r ($ret);



?>
