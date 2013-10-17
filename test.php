<?php

  require 'vendor/predis/predis/autoload.php';
  Predis\Autoloader::register();
  try {
    $redis = new Predis\Client('127.0.0.1:6379');
    echo "Successfully connected to Redis";
  }catch (Exception $e) {
      echo "Couldn't connected to Redis";
      echo $e->getMessage();
  }
?>
