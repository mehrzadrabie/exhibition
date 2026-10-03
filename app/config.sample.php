<?php
// The installer (install.php) generates app/config.php automatically.
// Only use this file if you want to configure manually: copy to config.php and edit.
return [
    'db' => ['host' => 'localhost', 'port' => '', 'name' => 'dbname', 'user' => 'dbuser', 'pass' => 'secret'],
    'base_url' => 'https://example.com',     // no trailing slash; include sub-folder if any
    'pretty_urls' => true,                    // false if the host has no mod_rewrite
    'app_key' => 'CHANGE-ME-TO-A-LONG-RANDOM-STRING', // signs ticket QR codes – never change after selling tickets
    'debug' => false,
];
