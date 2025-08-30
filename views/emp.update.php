<?php
require_once '../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get employee ID
        $emp_id = $_POST['emp_id'] ?? null;
        if (!$emp_id) {
            throw new Exception("Employee ID is required");
        }

        // Initialize update fields and values
        $fields = [];
        $types = '';
        $values = [];
        
        // Add basic fields
        $basicFields = [
            'id_number', 'first_name', 'last_name', 'middle_name', 
            'ext_name', 'gender', 'address', 'bday', 'email', 'phone_number'
        ];
        
        foreach ($basicFields as $field) {
            if (isset($_POST[$field])) {
                $fields[] = $field;
                $types .= 's';
                $values[] = $_POST[$field];
            }
        }

        // Handle file upload if a new picture is provided
        if (!empty($_FILES['picture']['name'])) {
            $targetDir = "../dist/img/employees/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES["picture"]["name"]);
            $targetFile = $targetDir . $fileName;
            
            // Check if file is an actual image
            $check = getimagesize($_FILES["picture"]["tmp_name"]);
            if ($check === false) {
                throw new Exception("File is not an image.");
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES["picture"]["tmp_name"], $targetFile)) {
                $fields[] = 'picture';
                $types .= 's';
                $values[] = $fileName;
                
                // Delete old picture if it exists
                if (!empty($_POST['old_picture'])) {
                    $oldPicture = "../dist/img/employees/" . $_POST['old_picture'];
                    if (file_exists($oldPicture)) {
                        unlink($oldPicture);
                    }
                }
            } else {
                throw new Exception("Sorry, there was an error uploading your file.");
            }
        }

        // Build the UPDATE query
        $setParts = array_map(function($field) { return "$field = ?"; }, $fields);
        $setClause = implode(', ', $setParts);

        $query = "UPDATE employee SET $setClause WHERE emp_id = ?";
        $stmt = $db->prepare($query);

        // Add emp_id to values
        $types .= 'i';
        $values[] = $emp_id;

        // Bind parameters
        $stmt->bind_param($types, ...$values);

        // Execute the update
        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Employee updated successfully!'
            ];
            header("Location: emp.list.php");
            exit();
        } else {
            throw new Exception("Failed to update employee: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => $e->getMessage()
        ];
        header("Location: emp.edit.php?emp_id=" . urlencode($emp_id));
        exit();
    }
} else {
    $_SESSION['toast'] = [
        'type' => 'error',
        'message' => 'Invalid request method.'
    ];
    header("Location: emp.list.php");
    exit();
}
?>