<?php
/**
 * Supabase Client
 * SmartPath Cane - Supabase Integration
 * 
 * This file provides connection and helper methods for Supabase
 */

// Load environment configuration
$env = require __DIR__ . '/../../bootstrap/config/env.php';

class SupabaseClient {
    private string $url;
    private string $publishableKey;
    private string $secretKey;
    private string $jwtSecret;
    
    public function __construct(array $config) {
        $this->url = rtrim($config['SUPABASE_URL'], '/');
        $this->publishableKey = $config['SUPABASE_PUBLISHABLE_KEY'];
        $this->secretKey = $config['SUPABASE_SECRET_KEY'];
        $this->jwtSecret = $config['SUPABASE_JWT_SECRET'] ?? '';
    }
    
    /**
     * Make a request to Supabase REST API
     */
    public function request(string $endpoint, string $method = 'GET', array $data = [], array $headers = []): array {
        $url = $this->url . '/rest/v1/' . ltrim($endpoint, '/');
        
        $defaultHeaders = [
            'apikey: ' . $this->secretKey,
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Supabase request failed: ' . $error);
        }
        
        $decoded = json_decode($response, true);
        
        return [
            'status' => $httpCode,
            'data' => $decoded,
            'raw' => $response
        ];
    }
    
    /**
     * Get data from a table
     */
    public function from(string $table): SupabaseQueryBuilder {
        return new SupabaseQueryBuilder($this, $table);
    }
    
    /**
     * Insert data into a table
     */
    public function insert(string $table, array $data): array {
        return $this->request($table, 'POST', $data);
    }
    
    /**
     * Upsert data into a table (insert or update on conflict)
     */
    public function upsert(string $table, array $data, string $onConflict = ''): array {
        $headers = [];
        if ($onConflict) {
            $headers[] = 'Prefer: resolution=merge-duplicates, return=representation';
        } else {
            $headers[] = 'Prefer: resolution=merge-duplicates, return=representation';
        }
        return $this->request($table, 'POST', $data, $headers);
    }
    
    /**
     * Update data in a table
     */
    public function update(string $table, array $data, string $filter = ''): array {
        $endpoint = $table;
        if ($filter) {
            $endpoint .= '?' . $filter;
        }
        return $this->request($endpoint, 'PATCH', $data);
    }
    
    /**
     * Delete data from a table
     */
    public function delete(string $table, string $filter): array {
        return $this->request($table . '?' . $filter, 'DELETE');
    }
    
    /**
     * Authenticate user with email and password
     */
    public function authSignIn(string $email, string $password): array {
        $url = $this->url . '/auth/v1/token?grant_type=password';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email' => $email,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->publishableKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Sign up a new user
     */
    public function authSignUp(string $email, string $password, array $metadata = []): array {
        $url = $this->url . '/auth/v1/signup';
        
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        if (!empty($metadata)) {
            $data['data'] = $metadata;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->publishableKey,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Get user by JWT token
     */
    public function authGetUser(string $jwt): array {
        $url = $this->url . '/auth/v1/user';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->publishableKey,
            'Authorization: Bearer ' . $jwt
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
}

/**
 * Supabase Query Builder
 */
class SupabaseQueryBuilder {
    private SupabaseClient $client;
    private string $table;
    private array $filters = [];
    private array $select = ['*'];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $order = null;
    
    public function __construct(SupabaseClient $client, string $table) {
        $this->client = $client;
        $this->table = $table;
    }
    
    public function select($columns = ['*']): self {
        $this->select = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function eq(string $column, $value): self {
        $this->filters[] = $column . '=eq.' . urlencode($value);
        return $this;
    }
    
    public function neq(string $column, $value): self {
        $this->filters[] = $column . '=neq.' . urlencode($value);
        return $this;
    }
    
    public function gt(string $column, $value): self {
        $this->filters[] = $column . '=gt.' . urlencode($value);
        return $this;
    }
    
    public function gte(string $column, $value): self {
        $this->filters[] = $column . '=gte.' . urlencode($value);
        return $this;
    }
    
    public function lt(string $column, $value): self {
        $this->filters[] = $column . '=lt.' . urlencode($value);
        return $this;
    }
    
    public function lte(string $column, $value): self {
        $this->filters[] = $column . '=lte.' . urlencode($value);
        return $this;
    }
    
    public function like(string $column, $value): self {
        $this->filters[] = $column . '=like.' . urlencode($value);
        return $this;
    }
    
    public function ilike(string $column, $value): self {
        $this->filters[] = $column . '=ilike.' . urlencode($value);
        return $this;
    }
    
    public function in(string $column, array $values): self {
        $this->filters[] = $column . '=in.(' . implode(',', array_map('urlencode', $values)) . ')';
        return $this;
    }
    
    public function is(string $column, $value): self {
        $this->filters[] = $column . '=is.' . urlencode($value);
        return $this;
    }
    
    public function order(string $column, string $direction = 'asc'): self {
        $this->order = $column . '.' . strtolower($direction);
        return $this;
    }
    
    public function limit(int $count): self {
        $this->limit = $count;
        return $this;
    }
    
    public function offset(int $count): self {
        $this->offset = $count;
        return $this;
    }
    
    public function execute(): array {
        $params = [];
        
        // Select columns
        $params[] = 'select=' . implode(',', $this->select);
        
        // Add filters
        foreach ($this->filters as $filter) {
            $params[] = $filter;
        }
        
        // Order
        if ($this->order) {
            $params[] = 'order=' . $this->order;
        }
        
        // Limit
        if ($this->limit !== null) {
            $params[] = 'limit=' . $this->limit;
        }
        
        // Offset
        if ($this->offset !== null) {
            $params[] = 'offset=' . $this->offset;
        }
        
        $endpoint = $this->table . '?' . implode('&', $params);
        return $this->client->request($endpoint);
    }
    
    public function single(): array {
        $this->limit = 1;
        $result = $this->execute();
        
        if (isset($result['data'][0])) {
            $result['data'] = $result['data'][0];
        }
        
        return $result;
    }
    
    public function delete(): array {
        $params = [];
        
        // Add filters
        foreach ($this->filters as $filter) {
            $params[] = $filter;
        }
        
        $endpoint = $this->table;
        if (!empty($params)) {
            $endpoint .= '?' . implode('&', $params);
        }
        
        return $this->client->request($endpoint, 'DELETE');
    }
}

// Create global Supabase client instance
$supabase = new SupabaseClient($env);

return $supabase;
