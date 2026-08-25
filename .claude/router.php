<?php

/**
 * @file
 * Front controller router for PHP's built-in server.
 *
 * Serves real files straight from web/ and hands everything else to Drupal's
 * index.php, so clean URLs and language prefixes work without Apache.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__) . '/web';
$file = $root . $path;

// Let the server deliver existing static assets itself.
if ($path !== '/' && is_file($file)) {
  return FALSE;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
