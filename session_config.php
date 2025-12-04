<?php
// Secure session configuration
// This file should be included before session_start() in all pages

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}
?>




