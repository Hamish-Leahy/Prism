<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Prism\Backend\Application;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create and run the application
$app = new Application();
$app->run();
