<?php
require_once __DIR__ . '/../config.php';
use controller\MagnusBilling;

// require_once __DIR__ . "/magnusBilling.php";

$magnusBilling = new MagnusBilling(env('MAGNUS_API_KEY'), env('MAGNUS_API_SECRET'));
$magnusBilling->public_url = env('MAGNUS_PUBLIC_URL'); // Your MagnusBilling URL

//read data from user module
$result = $magnusBilling->read('user');
