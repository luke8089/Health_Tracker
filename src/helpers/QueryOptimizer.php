<?php
/**
 * Query Optimizer
 * Provides optimized database query patterns and caching
 */

class QueryOptimizer {
    private $db;
    private $cache;
    private $cacheEnabled = true;
    private $cacheTTL = 300; // 5 minutes
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            $dbHelper = new Database();
            $this->db = $dbHelper->connect();
        }
    }
    
    /**
     * Execute a cached query
     */
    public function cachedQuery($sql, $params = [], $ttl = null) {
        if (!$this->cacheEnabled) {
            return $this->executeQuery($sql, $params);
        }
        
        $cacheKey = $this->generateCacheKey($sql, $params);
        $cached = SimpleCache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $this->executeQuery($sql, $params);
        SimpleCache::set($cacheKey, $result, $ttl ?? $this->cacheTTL);
        
        return $result;
    }
    
    /**
     * Execute query without caching
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute single row query with caching
     */
    public function cachedQueryOne($sql, $params = [], $ttl = null) {
        $results = $this->cachedQuery($sql, $params, $ttl);
        return $results[0] ?? null;
    }
    
    /**
     * Batch insert for better performance
     */
    public function batchInsert($table, $columns, $rows) {
        if (empty($rows)) {
            return false;
        }
        
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($rows), $placeholders));
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $table,
            implode(',', $columns),
            $allPlaceholders
        );
        
        $flatParams = [];
        foreach ($rows as $row) {
            $flatParams = array_merge($flatParams, array_values($row));
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($flatParams);
        } catch (PDOException $e) {
            error_log("Batch Insert Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimized pagination
     */
    public function paginate($baseQuery, $params, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        // Get total count (cached)
        $countQuery = preg_replace('/SELECT .+ FROM/i', 'SELECT COUNT(*) as total FROM', $baseQuery);
        $countResult = $this->cachedQueryOne($countQuery, $params, 60);
        $total = $countResult['total'] ?? 0;
        
        // Get paginated results
        $paginatedQuery = $baseQuery . " LIMIT ? OFFSET ?";
        $paginatedParams = array_merge($params, [$perPage, $offset]);
        $results = $this->cachedQuery($paginatedQuery, $paginatedParams);
        
        return [
            'data' => $results,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }
    
    /**
     * Clear cache for specific patterns
     */
    public function clearCache($pattern = null) {
        if ($pattern === null) {
            SimpleCache::clear();
        } else {
            // Clear specific pattern (implement if needed)
            SimpleCache::clear();
        }
    }
    
    /**
     * Generate cache key from query and params
     */
    private function generateCacheKey($sql, $params) {
        return 'query_' . md5($sql . serialize($params));
    }
    
    /**
     * Enable/disable caching
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
    }
    
    /**
     * Set cache TTL
     */
    public function setCacheTTL($ttl) {
        $this->cacheTTL = $ttl;
    }
}
