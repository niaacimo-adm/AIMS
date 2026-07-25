<?php
// Base URL used when generating QR codes so they resolve on other LAN devices
// (phones, tablets) instead of baking in "localhost", which only means
// "this device" and fails on anything else scanning it.
if (!defined('SCAN_BASE_URL')) {
    define('SCAN_BASE_URL', 'http://192.168.0.230/AIMS');
}

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