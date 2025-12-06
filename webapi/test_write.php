<?php
$path = __DIR__ . '/../uploads/test_write.txt';
$result = @file_put_contents($path, "WRITE TEST ".date('c'));
var_dump($result);
var_dump($path);
var_dump(is_writable(dirname($path)));
