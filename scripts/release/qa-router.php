<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file('/workspace/backend/public'.$path) && ! str_ends_with($path, '.php')) return false;
$app = require __DIR__.'/qa-bootstrap.php';
$app->handleRequest(Illuminate\Http\Request::capture());
