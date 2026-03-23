<?php
// includes/database.php
// Database Connection Class - FIXED VERSION with consistent parameter handling

class Database {
    private static $instance = null;
    private $connection;
    private $statement;
    
    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database Connection Failed: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
public function query($sql, $params = []) {
    try {
        // Ensure that we always use consistent parameter types
        // If any named parameters are detected, we use them as-is
        // Otherwise, positional parameters will be used
        $isNamed = false;
        foreach (array_keys($params) as $key) {
            if (is_string($key)) {
                $isNamed = true;
                break;
            }
        }

        $this->statement = $this->connection->prepare($sql);

        if ($isNamed) {
            // Bind named parameters explicitly
            foreach ($params as $key => $value) {
                // PDO expects named params to have leading colon
                $paramKey = (strpos($key, ':') === 0) ? $key : ':' . $key;
                $this->statement->bindValue($paramKey, $value);
            }
            $this->statement->execute();
        } else {
            // Positional parameters
            $this->statement->execute(array_values($params));
        }

        return $this->statement;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
        throw $e;
    }
}
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchColumn($sql, $params = []) {
        $result = $this->query($sql, $params)->fetchColumn();
        return $result;
    }
    
    /**
     * Insert a record using named parameters
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    /**
     * Update records - FIXED to handle both named and positional parameters properly
     * Use this with named parameters for the SET clause and positional for WHERE
     */
public function update($table, $data, $where, $whereParams = []) {
    // Build SET clause with named parameters
    $setFields = [];
    foreach ($data as $field => $value) {
        $setFields[] = "$field = :$field";
    }
    $setClause = implode(', ', $setFields);

    // Build WHERE clause with named params too (we prefix keys if needed)
    $whereClause = $where; // assume $where already has named params like ":id"
    
    $sql = "UPDATE $table SET $setClause WHERE $whereClause";

    // Merge data for SET and whereParams for WHERE
    $params = array_merge($data, $whereParams);

    // Execute the query with named parameters
    return $this->query($sql, $params)->rowCount();
}
    
    /**
     * Alternative update method using ONLY positional parameters (recommended)
     * Use this for simplicity and consistency
     */
    public function updatePositional($table, $data, $where, $whereParams = []) {
        // Build SET clause with positional parameters
        $setFields = [];
        foreach (array_keys($data) as $field) {
            $setFields[] = "$field = ?";
        }
        $setClause = implode(', ', $setFields);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        // Merge data values with where parameters (all positional)
        $params = array_merge(array_values($data), $whereParams);
        
        $this->query($sql, $params);
        return $this->statement->rowCount();
    }
    
    /**
     * Delete records using positional parameters
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
        return $this->statement->rowCount();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollBack() {
        return $this->connection->rollBack();
    }
}

// Global database function
function db() {
    return Database::getInstance();
}

// Get site setting
function getSetting($key, $default = '') {
    try {
        $result = db()->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Get all settings
function getAllSettings() {
    try {
        $settings = [];
        $result = db()->fetchAll("SELECT setting_key, setting_value FROM site_settings");
        foreach ($result as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

// Get projects
function getProjects($limit = null, $featured = false) {
    try {
        $sql = "SELECT * FROM projects WHERE status = 'published'";
        if ($featured) {
            $sql .= " AND is_featured = 1";
        }
        $sql .= " ORDER BY display_order ASC, created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return db()->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

// Get single project
function getProject($slug) {
    try {
        return db()->fetch("SELECT * FROM projects WHERE slug = ? AND status = 'published'", [$slug]);
    } catch (Exception $e) {
        return null;
    }
}

// Get skills
function getSkills($category = null) {
    try {
        $sql = "SELECT * FROM skills WHERE is_visible = 1";
        if ($category) {
            $sql .= " AND category = ?";
        }
        $sql .= " ORDER BY display_order ASC";
        
        return $category ? db()->fetchAll($sql, [$category]) : db()->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

// Get testimonials
function getTestimonials($limit = null) {
    try {
        $sql = "SELECT t.*, p.title as project_title 
                FROM testimonials t 
                LEFT JOIN projects p ON t.project_id = p.id 
                WHERE t.status = 'approved' 
                ORDER BY t.is_featured DESC, t.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return db()->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

// Save contact message
function saveContactMessage($data) {
    try {
        $data['ip_address'] = getClientIP();
        return db()->insert('contact_messages', $data);
    } catch (Exception $e) {
        return false;
    }
}

// Get unread messages count
function getUnreadMessagesCount() {
    try {
        $result = db()->fetch("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}


?>