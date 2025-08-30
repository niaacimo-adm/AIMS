<?php
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload
        $picturePath = '';
        if (isset($_FILES['picture'])) {
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
            if (!move_uploaded_file($_FILES["picture"]["tmp_name"], $targetFile)) {
                throw new Exception("Sorry, there was an error uploading your file.");
            }
            
            $picturePath = $fileName;
        }

        // Prepare SQL query
        $query = "INSERT INTO Employee (
            picture, id_number, first_name, middle_name, last_name, ext_name,
            gender, address, bday, email, phone_number, employment_status_id,
            appointment_status_id, section_id, office_id, position_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($query);

        // Bind parameters
        $stmt->bind_param("sssssssssssiiii", 
            $picturePath,
            $_POST['id_number'],
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['ext_name'],
            $_POST['gender'],
            $_POST['address'],
            $_POST['bday'],
            $_POST['email'],
            $_POST['phone_number'],
            $_POST['employment_status_id'],
            $_POST['appointment_status_id'],
            $_POST['section_id'],
            $_POST['office_id'],
            $_POST['position_id']
        );

        // Execute query
        if ($stmt->execute()) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'title' => 'Success!',
                'message' => 'Employee created successfully!'
            ];
        } else {
            throw new Exception("Failed to create employee: " . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error!',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }

    // Redirect back to the form
    header("Location: emp.create.php");
    exit();
} else {
    // If not a POST request, redirect to form
    header("Location: emp.create.php");
    exit();
}
?>