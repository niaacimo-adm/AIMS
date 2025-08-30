<?php
class EmployeeModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createEmployee($data) {
        $query = "INSERT INTO Employee SET 
            picture = :picture,
            id_number = :id_number,
            first_name = :first_name,
            middle_name = :middle_name,
            last_name = :last_name,
            ext_name = :ext_name,
            gender = :gender,
            address = :address,
            bday = :bday,
            email = :email,
            phone_number = :phone_number,
            employment_status_id = :employment_status_id,
            appointment_status_id = :appointment_status_id,
            section_id = :section_id,
            office_id = :office_id,
            position_id = :position_id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(":picture", $data['picture']);
        $stmt->bindParam(":id_number", $data['id_number']);
        $stmt->bindParam(":first_name", $data['first_name']);
        $stmt->bindParam(":middle_name", $data['middle_name']);
        $stmt->bindParam(":last_name", $data['last_name']);
        $stmt->bindParam(":ext_name", $data['ext_name']);
        $stmt->bindParam(":gender", $data['gender']);
        $stmt->bindParam(":address", $data['address']);
        $stmt->bindParam(":bday", $data['bday']);
        $stmt->bindParam(":email", $data['email']);
        $stmt->bindParam(":phone_number", $data['phone_number']);
        $stmt->bindParam(":employment_status_id", $data['employment_status_id']);
        $stmt->bindParam(":appointment_status_id", $data['appointment_status_id']);
        $stmt->bindParam(":section_id", $data['section_id']);
        $stmt->bindParam(":office_id", $data['office_id']);
        $stmt->bindParam(":position_id", $data['position_id']);
        
        return $stmt->execute();
    }

    public function updateEmployeeAssignment($emp_id, $data) {
        $query = "UPDATE Employee SET 
            employment_status_id = :employment_status_id,
            appointment_status_id = :appointment_status_id,
            section_id = :section_id,
            office_id = :office_id,
            position_id = :position_id
            WHERE emp_id = :emp_id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":employment_status_id", $data['employment_status_id']);
        $stmt->bindParam(":appointment_status_id", $data['appointment_status_id']);
        $stmt->bindParam(":section_id", $data['section_id']);
        $stmt->bindParam(":office_id", $data['office_id']);
        $stmt->bindParam(":position_id", $data['position_id']);
        $stmt->bindParam(":emp_id", $emp_id);
        
        return $stmt->execute();
    }

    public function getAllEmployees() {
        $query = "SELECT e.*, 
                 es.status_name as employment_status,
                 ap.status_name as appointment_status,
                 s.section_name,
                 o.office_name,
                 p.position_name
                 FROM Employee e
                 LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
                 LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
                 LEFT JOIN section s ON e.section_id = s.section_id
                 LEFT JOIN office o ON e.office_id = o.office_id
                 LEFT JOIN position p ON e.position_id = p.position_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeeById($emp_id) {
        $query = "SELECT * FROM Employee WHERE emp_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $emp_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>