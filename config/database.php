<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'sahur';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
                
                if ($this->conn->connect_error) {
                    throw new Exception("Connection failed: " . $this->conn->connect_error);
                }
            } catch(Exception $e) {
                error_log("Connection error: " . $e->getMessage());
                throw $e;
            }
        }
        return $this->conn;
    }
}

$database = new Database();
$conn = $database->getConnection();
?>