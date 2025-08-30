<?php
class Position {
    private $conn;
    private $table = 'position';

    public $position_id;
    public $position_name;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY position_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " SET position_name = :position_name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':position_name', $this->position_name);
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table . " SET position_name = :position_name WHERE position_id = :position_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':position_name', $this->position_name);
        $stmt->bindParam(':position_id', $this->position_id);
        return $stmt->execute();
    }

    // In your Position->delete() method:
    public function delete() {
        // First nullify the position_id for affected employees
        $nullifyQuery = "UPDATE employee SET position_id = NULL WHERE position_id = ?";
        $stmt = $this->conn->prepare($nullifyQuery);
        $stmt->execute([$this->position_id]);
        
        // Then delete the position
        $deleteQuery = "DELETE FROM position WHERE position_id = ?";
        $stmt = $this->conn->prepare($deleteQuery);
        return $stmt->execute([$this->position_id]);
    }
}
?>