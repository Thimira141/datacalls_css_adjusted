<?php
require_once "./assets/backend/magnusBilling.php";

$magnusBilling = new MagnusBilling('8x9vqM4JWnxUbDZGJm9HHlqKD8R8vvJ3', 'xJdpyCjiVrSrabu2fnN53BNdGCDc0O6B');
$magnusBilling->public_url = "http://72.60.25.185/mbilling"; // Your MagnusBilling URL

//read data from user module
$result = $magnusBilling->read('user');