<?php
/**
 * SmartPath Cane - HTTP Enforcer
 * 
 * Some IoT hardware modules (like older ESP8266/SIM800L) struggle with
 * SSL handshakes. This script forces any HTTPS request down to HTTP
 * to ensure the hardware can successfully communicate.
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    // Rebuild the URL using HTTP
    $httpUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Redirect to the non-secure HTTP version
    header("Location: $httpUrl", true, 301);
    exit;
}
