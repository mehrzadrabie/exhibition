<?php
/**
 * Front controller. Every request (except static files) lands here.
 */
define('ROOT', __DIR__);
define('APP', ROOT . '/app');

// Used by the installer to detect whether URL rewriting works
if (substr(strtok(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', '?'), -15) === '/__rewrite_test') {
    header('Content-Type: text/plain');
    exit('ok');
}

if (!is_file(APP . '/config.php')) {
    header('Location: install.php');
    exit;
}

require APP . '/bootstrap.php';
require APP . '/routes.php';
