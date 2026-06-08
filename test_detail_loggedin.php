<?php
session_start();
$_SESSION['user_id'] = 1; // assume user ID 1 is logged in
$_GET['id'] = 8; // pant product id
require 'backend/get_product_detail.php';
?>
