<?php

require_once __DIR__ . '/../../config.php';

use controller\MagnusBilling;

$magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
$magnusBilling->public_url = env('MAGNUS_PUBLIC_URL');


$newUser = [
    'username' => 'testuserIVR',
    'password' => 'securepass123IVR',
    'credit' => 10.00,
    'name' => 'Test User',
    'email' => 'testuser@example.com',
    'active' => 1,
    'id_plan' => 1, // Replace with your actual plan ID
    'id_group' => 3,
    'language' => 'en',
    'callerid' => '2002052500', // Optional: set outbound caller ID
    'callingcard_pin' => '2002052500'
];

// $response = $magnusBilling->create('user', $newUser);
// echo 'L-24:$response:create user<pre>', print_r($response) . '</pre>';

// $plans = $magnusBilling->read('plan');
// echo 'L-27:$plans:retrieve plans<pre>', print_r($plans) . '</pre>';

//get ID for username 24320 
// $id_user = $magnusBilling->getId('user', 'username', 'testuserIVR');
// $id_user = $magnusBilling->read('user');
// echo '<pre>',print_r($id_user).'</pre>';
// $user_id = 24;

//set the filter to get calls from $id_user
// $magnusBilling->setFilter('id_user', $id_user, 'eq', 'numeric');
// $user = $magnusBilling->read('call',1);
// $magnusBilling->clearFilter();

// echo 'L-30:$user:user read<pre>', print_r($user) . '</pre>';

$newSip = [
    'id_user' => 24,
    'username' => 'testuserIVR',
    'secret' => 'securepass123IVR',
    'context' => 'from-user',
    'callerid' => '2002052500',
];

// $response = $magnusBilling->create('sip', $newSip);
// echo 'L-41:$response:create sip<pre>', print_r($response) . '</pre>';



