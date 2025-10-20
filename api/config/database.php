<?php

class Database {
    private $host;
    private $port;
    private $username;
    private $password;
    private $database;
    private $connection;
    
    public function __construct() {
        // Load environment variables
        $this->loadEnv();
        
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? '3306';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        $this->database = $_ENV['DB_NAME'] ?? 'nust_timetable';
    }
    
    // Load environment variables from .env file
    private function loadEnv() {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    
    // Create database connection
    public function connect() {
        if ($this->connection === null) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    // Get database connection instance
    public function getConnection() {
        return $this->connect();
    }
    
    // Close database connection
    public function close() {
        $this->connection = null;
    }
    
    // Execute query and return results
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }
    
    // Get last inserted ID
    public function lastInsertId() {
        return $this->connect()->lastInsertId();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->connect()->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->connect()->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->connect()->rollBack();
    }
}

// Helper function to get database instance
function getDB() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database;
}

// Set JSON headers and handle CORS
function setJsonHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

//Send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Send error response
function sendError($message, $status = 400) {
    sendResponse(['error' => $message], $status);
}

// Get request body as JSON
function getRequestBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Validate required fields
function validateRequired($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("Field '$field' is required");
        }
    }
}

// Sanitize input data
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}