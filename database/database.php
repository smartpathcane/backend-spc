<?php
/**
 * Database Connection
 * SmartPath Cane - Supabase Only
 */

require_once __DIR__ . '/supabase/supabase.php';

/**
 * Database Class
 * Provides access to Supabase database
 */
class Database {
    private static ?SupabaseClient $client = null;
    
    /**
     * Get Supabase client instance
     */
    public static function getClient(): SupabaseClient {
        global $supabase;
        if (self::$client === null) {
            self::$client = $supabase;
        }
        return self::$client;
    }
    
    /**
     * Query a table
     */
    public static function from(string $table): SupabaseQueryBuilder {
        return self::getClient()->from($table);
    }
    
    /**
     * Insert data into a table
     */
    public static function insert(string $table, array $data): array {
        return self::getClient()->insert($table, $data);
    }
    
    /**
     * Update data in a table
     */
    public static function update(string $table, array $data, string $filter = ''): array {
        return self::getClient()->update($table, $data, $filter);
    }
    
    /**
     * Delete data from a table
     */
    public static function delete(string $table, string $filter): array {
        return self::getClient()->delete($table, $filter);
    }
    
    /**
     * Authenticate user
     */
    public static function authSignIn(string $email, string $password): array {
        return self::getClient()->authSignIn($email, $password);
    }
    
    /**
     * Sign up new user
     */
    public static function authSignUp(string $email, string $password, array $metadata = []): array {
        return self::getClient()->authSignUp($email, $password, $metadata);
    }
    
    /**
     * Get user by JWT
     */
    public static function authGetUser(string $jwt): array {
        return self::getClient()->authGetUser($jwt);
    }
}

// Export Supabase client
global $supabase;
return $supabase;
