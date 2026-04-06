<?php
/**
 * Environment Configuration
 * SmartPath Cane - Backend (Hostinger)
 * Database: Supabase
 */

// Detect environment
$isProduction = $_SERVER['HTTP_HOST'] !== 'localhost' && 
                $_SERVER['HTTP_HOST'] !== '127.0.0.1';

return [
    // Application Settings
    'APP_NAME' => 'SmartPath Cane',
    'APP_ENV' => $isProduction ? 'production' : 'development',
    'APP_DEBUG' => !$isProduction,
    'APP_URL' => $isProduction 
        ? 'https://https://floralwhite-raccoon-333018.hostingersite.com/'  // TODO: Replace with your Hostinger domain
        : 'http://localhost/smartpathcane',
    
    // Frontend URL (Netlify)
    'FRONTEND_URL' => $isProduction
        ? 'https://smartpath-cane.netlify.app'  // TODO: Replace with your Netlify URL
        : 'http://localhost:3000',
    
    // Supabase Configuration (Database)
    'SUPABASE_URL' => 'https://ksoukgxagrpaleedqqua.supabase.co',
    'SUPABASE_PUBLISHABLE_KEY' => 'sb_publishable_Ku5jRYPcCchUGsET6gRNBw_wzqfFQg7',
    'SUPABASE_SECRET_KEY' => 'sb_secret_Q1ODVLEB1ab2QVVbg_cc5A_kXi8T1qH',
    'SUPABASE_JWT_SECRET' => '',
    
    // Security
    'JWT_SECRET' => $isProduction 
        ? $_ENV['JWT_SECRET'] ?? 'your-production-jwt-secret' 
        : 'dev-jwt-secret-key',
    'JWT_EXPIRATION' => 86400, // 24 hours in seconds
    
    // CORS Settings - Restrict to Netlify frontend in production
    'CORS_ALLOWED_ORIGINS' => $isProduction
        ? 'https://smartpath-cane.netlify.app'  // TODO: Replace with your Netlify URL
        : '*',
    'CORS_ALLOWED_METHODS' => 'GET, POST, PUT, DELETE, OPTIONS',
    'CORS_ALLOWED_HEADERS' => 'Content-Type, Authorization, X-Requested-With',

    // Web app maintenance (dashboard / landing). Set to true to show the maintenance page on the frontend.
    // Cane API routes (/api/cane/*) are not blocked so devices can still report location/SOS.
    'MAINTENANCE_MODE' => true,
    'MAINTENANCE_MESSAGE' => 'We are upgrading SmartPath Cane. Please check back soon.',
];
