<?php
/**
 * Database Configuration for cPanel MySQL
 * Update these credentials with your cPanel MySQL details
 */

class Database {
    private static $instance = null;
    private $connection = null;
    
    // PostgreSQL Configuration for Replit
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Get PostgreSQL credentials from environment
        $this->host = getenv('PGHOST') ?: 'localhost';
        $this->port = getenv('PGPORT') ?: '5432';
        $this->dbname = getenv('PGDATABASE') ?: 'hvac_system';
        $this->username = getenv('PGUSER') ?: 'postgres';
        $this->password = getenv('PGPASSWORD') ?: '';
        
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }
    
    public function __clone() {
        throw new Exception("Cannot clone singleton Database class");
    }
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton Database class");
    }
}

// Global function for backward compatibility
function getPDO() {
    return Database::getInstance();
}

/**
 * Get database connection
 * @return PDO database connection
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Execute a prepared statement safely
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for the query
 * @return PDOStatement
 */
function executeQuery($query, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row
 * @param string $query SQL query
 * @param array $params Parameters
 * @return array|false
 */
function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

/**
 * Fetch multiple rows
 * @param string $query SQL query
 * @param array $params Parameters
 * @return array
 */
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

/**
 * Insert data and return last insert ID
 * @param string $query SQL insert query
 * @param array $params Parameters
 * @return int Last insert ID
 */
function insertAndGetId($query, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $db->lastInsertId();
}
?>