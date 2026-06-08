<?php
$c = new mysqli('localhost', 'root', '', 'thrift_store');
$res = $c->query('SELECT id, title, latitude, longitude, category, price FROM products');
print_r($res->fetch_all(MYSQLI_ASSOC));
