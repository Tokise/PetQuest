<?php

// Session configuration
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'petquest');

// Site configuration
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/PetQuest');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/PetQuest/uploads');
define('QR_PATH', UPLOAD_PATH . '/qrcodes');
define('PETS_PATH', UPLOAD_PATH . '/pets');
define('COVER_PATH', UPLOAD_PATH . '/cover');
define('PROFILE_PATH', UPLOAD_PATH . '/profile');

// Create upload directories if they don't exist
$directories = [
    UPLOAD_PATH,
    QR_PATH,
    PETS_PATH,
    COVER_PATH,
    PROFILE_PATH
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    if (!is_writable($dir)) {
        chmod($dir, 0777);
    }
}


