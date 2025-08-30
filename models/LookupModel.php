<?php
class LookupModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllEmploymentStatus() {
        $query = "SELECT * FROM employment_status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAppointmentStatus() {
        $query = "SELECT * FROM appointment_status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllOffices() {
        $query = "SELECT * FROM office";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllSections() {
        $query = "SELECT * FROM section";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPositions() {
        $query = "SELECT * FROM position";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>