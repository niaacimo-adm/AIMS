<?php
    session_start();
    require_once '../config/database.php';
    require_once '../includes/auth.php'; // Include auth functions
    // At the top with other includes
require_once '../includes/document_functions.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Database connection
    try {
        $database = new Database();
        $conn = $database->getConnection();
    } catch (Exception $e) {
        $_SESSION['error'] = "Database connection failed: " . $e->getMessage();
        header("Location: error.php");
        exit();
    }
    

    // Function to get employee_id from user_id
    if (!function_exists('getEmployeeId')) {
    function getEmployeeId($conn, $user_id) {
        $stmt = $conn->prepare("SELECT employee_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['employee_id'];
        }
        return null;
    }
    }
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_document'])) {
            // Validate inputs
            $required_fields = ['title', 'type_id'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $_SESSION['error'] = "Please fill all required fields";
                    header("Location: doctrack.php");
                    exit;
                }
            }

            // File upload validation
            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = "File upload error";
                header("Location: doctrack.php");
                exit;
            }

            // File type and size validation
            $allowed_types = [
                'application/pdf', 
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            $max_size = 100 * 1024 * 1024; // 100MB - UPDATED from 5MB
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['document_file']['tmp_name']);
            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_types) || $_FILES['document_file']['size'] > $max_size) {
                $_SESSION['error'] = "Invalid file type or size (max 100MB allowed)"; // UPDATED message
                header("Location: doctrack.php");
                exit;
            }

            try {
                // Get employee section and unit information
                $owner_id = getEmployeeId($conn, $_SESSION['user_id']);
                if (!$owner_id) {
                    throw new Exception("User not properly linked to an employee");
                }

                // Verify the employee exists and get section/unit info
                $check_emp = $conn->prepare("SELECT e.emp_id, s.section_code, u.unit_code 
                                           FROM employee e
                                           JOIN section s ON e.section_id = s.section_id
                                           JOIN unit_section u ON e.unit_section_id = u.unit_id
                                           WHERE e.emp_id = ?");
                $check_emp->bind_param("i", $owner_id);
                $check_emp->execute();
                $emp_info = $check_emp->get_result()->fetch_assoc();
                
                if (!$emp_info) {
                    throw new Exception("Invalid owner specified (Employee ID $owner_id not found)");
                }

                // Generate document number in format: ACIMO-(section initials)(unit initials)-(current date)-number
                $section_initials = substr($emp_info['section_code'], 0, 3);
                $unit_initials = substr($emp_info['unit_code'], 0, 3);
                $date_part = date('md'); // MMDD format
                $year_short = date('y'); // Last 2 digits of year

                // Get count of documents created today by this employee
                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM documents 
                                            WHERE owner_id = ? AND DATE(created_at) = CURDATE()");
                $count_stmt->bind_param("i", $owner_id);
                $count_stmt->execute();
                $count = $count_stmt->get_result()->fetch_assoc()['count'] + 1;

                // Generate document number
                $doc_number = 'ACIMO-' . strtoupper($section_initials . '('.$unit_initials.')') . '-' . $date_part . $year_short . '-' . $count;

                // Handle file upload
                $uploadDir = '../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $original_name = basename($_FILES['document_file']['name']);
                $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
                    require_once '../libs/phpqrcode/qrlib.php';
                    $qrDir = '../uploads/qrcodes/';
                    if (!file_exists($qrDir)) {
                        mkdir($qrDir, 0755, true);
                    }
                    $qrFile = 'qr_' . bin2hex(random_bytes(8)) . '.png';
                    $qrPath = $qrDir . $qrFile;

                    $qrContent = "Document: " . $_POST['title'] . "\n";
                    $qrContent .= "Number: " . $doc_number . "\n";
                    $qrContent .= "View: " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                                '/view_document.php?doc_number=' . urlencode($doc_number) . "\n";
                    $qrContent .= "Download: " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                                $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                                '/download_document.php?doc_number=' . urlencode($doc_number);

                    QRcode::png($qrContent, $qrPath, QR_ECLEVEL_L, 10);

                    // Prepare all values for binding
                    $title = $_POST['title'];
                    $type_id = (int)$_POST['type_id'];
                    $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : null;

                    // Insert document with remarks
                    $stmt = $conn->prepare("INSERT INTO documents (doc_number, title, type_id, owner_id, file_path, qr_code, remarks) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiisss", $doc_number, $title, $type_id, $owner_id, $filePath, $qrPath, $remarks);

                     if ($stmt->execute()) {
                        // Add to history
                        $doc_id = $conn->insert_id;
                        $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                                    VALUES (?, ?, 'created', ?)");
                        $details = 'Document created' . ($remarks ? ' with remarks' : '');
                        $history_stmt->bind_param("iis", $doc_id, $owner_id, $details);
                        $history_stmt->execute();
                        
                        $_SESSION['success'] = 'Document created successfully!';
                    } else {
                        // Clean up uploaded files if DB insert fails
                        if (file_exists($filePath)) unlink($filePath);
                        if (file_exists($qrPath)) unlink($qrPath);
                        $_SESSION['error'] = 'Failed to create document.';
                    }
                } else {
                    $_SESSION['error'] = 'File upload failed.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
            }
            header("Location: doctrack.php");
            exit;
        }
        
        if (isset($_POST['update_document'])) {
            // Validate inputs
            if (empty($_POST['doc_id']) || empty($_POST['title']) || empty($_POST['type_id'])) {
                $_SESSION['error'] = "Please fill all required fields";
                header("Location: doctrack.php");
                exit;
            }

            try {
                $doc_id = (int)$_POST['doc_id'];
                
                // Verify document exists and belongs to user (or user has permission)
                $check_stmt = $conn->prepare("SELECT owner_id, file_path FROM documents WHERE doc_id = ?");
                $check_stmt->bind_param("i", $doc_id);
                $check_stmt->execute();
                $document = $check_stmt->get_result()->fetch_assoc();
                
                if (!$document) {
                    throw new Exception("Document not found");
                }
                
                // Get current user's employee_id
                $current_emp_id = getEmployeeId($conn, $_SESSION['user_id']);
                if (!$current_emp_id) {
                    throw new Exception("User not properly linked to an employee");
                }
                
                // Check permission (owner or admin)
                if ($document['owner_id'] != $current_emp_id && $_SESSION['role'] !== 'admin') {
                    throw new Exception("You don't have permission to edit this document");
                }
                
                // Get current document data BEFORE making changes
                $current_doc_stmt = $conn->prepare("SELECT d.*, dt.type_name
                                                  FROM documents d
                                                  JOIN document_types dt ON d.type_id = dt.type_id
                                                  WHERE d.doc_id = ?");
                $current_doc_stmt->bind_param("i", $doc_id);
                $current_doc_stmt->execute();
                $current_document = $current_doc_stmt->get_result()->fetch_assoc();
                
                // Track changes
                $changes = [];
                
                // Handle file upload if new file is provided
                $filePath = $current_document['file_path'];
                if (isset($_FILES['document_file']) && $_FILES['document_file']['size'] > 0) {
                    // File validation
                    $allowed_types = [
                        'application/pdf', 
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ];
                    $max_size = 100 * 1024 * 1024; // 100MB
                    $file_info = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($file_info, $_FILES['document_file']['tmp_name']);
                    finfo_close($file_info);

                    if (!in_array($mime_type, $allowed_types) || $_FILES['document_file']['size'] > $max_size) {
                        throw new Exception("Invalid file type or size (max 100MB allowed)");
                    }
                    
                    $old_file = $current_document['file_path'];
                    
                    $uploadDir = '../uploads/documents/';
                    $original_name = basename($_FILES['document_file']['name']);
                    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                    $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
                        // Delete old file
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                        $changes[] = "Uploaded new file version: " . $original_name;
                    } else {
                        throw new Exception('File upload failed');
                    }
                }
                
                // Prepare update statement
                $stmt = $conn->prepare("UPDATE documents SET title = ?, type_id = ?, file_path = ? 
                                      WHERE doc_id = ?");
                $title = $_POST['title'];
                $type_id = (int)$_POST['type_id'];
                $stmt->bind_param("siisi", $title, $type_id, $filePath, $doc_id);
                
                if ($stmt->execute()) {
                    // Track title change
                    if ($_POST['title'] != $current_document['title']) {
                        $changes[] = "Updated title from '".$current_document['title']."' to '".$_POST['title']."'";
                    }
                    
                    // Track type change
                    if ((int)$_POST['type_id'] != $current_document['type_id']) {
                        $new_type_stmt = $conn->prepare("SELECT type_name FROM document_types WHERE type_id = ?");
                        $new_type_stmt->bind_param("i", $_POST['type_id']);
                        $new_type_stmt->execute();
                        $new_type = $new_type_stmt->get_result()->fetch_assoc()['type_name'];
                        $changes[] = "Updated type from '".$current_document['type_name']."' to '".$new_type."'";
                    }
                    
                    // Add history entries
                    if (!empty($changes)) {
                        foreach ($changes as $change) {
                            $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                                          VALUES (?, ?, 'updated', ?)");
                            $history_stmt->bind_param("iis", $doc_id, $current_emp_id, $change);
                            $history_stmt->execute();
                        }
                        $_SESSION['success'] = 'Document updated successfully with ' . count($changes) . ' changes!';
                    } else {
                        $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                                      VALUES (?, ?, 'updated', 'No changes detected')");
                        $history_stmt->bind_param("ii", $doc_id, $current_emp_id);
                        $history_stmt->execute();
                        $_SESSION['success'] = 'Document saved with no changes detected';
                    }
                } else {
                    throw new Exception('Failed to update document');
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
            }
            header("Location: doctrack.php?view_id=" . (int)$_POST['doc_id']);
            exit;
        }
    }

    if (isset($_GET['delete_id'])) {
        // Delete document
        try {
            $doc_id = (int)$_GET['delete_id'];
            
            // Verify document exists and belongs to user (or user has permission)
            $check_stmt = $conn->prepare("SELECT owner_id, file_path, qr_code FROM documents WHERE doc_id = ?");
            $check_stmt->bind_param("i", $doc_id);
            $check_stmt->execute();
            $document = $check_stmt->get_result()->fetch_assoc();
            
            if (!$document) {
                throw new Exception("Document not found");
            }
            
            // Get current user's employee_id
            $current_emp_id = getEmployeeId($conn, $_SESSION['user_id']);
            if (!$current_emp_id) {
                throw new Exception("User not properly linked to an employee");
            }
            
            // Check permission (owner or admin)
            if ($document['owner_id'] != $current_emp_id && $_SESSION['role'] !== 'admin') {
                throw new Exception("You don't have permission to delete this document");
            }
            
            // Delete files
            $success = true;
            if (file_exists($document['file_path'])) {
                if (!unlink($document['file_path'])) {
                    $success = false;
                }
            }
            if (file_exists($document['qr_code'])) {
                if (!unlink($document['qr_code'])) {
                    $success = false;
                }
            }
            
            // In the delete section (around line 300), modify the transaction block:

            if ($success) {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Delete related records - ADD DOCUMENT_TRANSFERS TO THIS LIST
                    $conn->query("DELETE FROM document_comments WHERE doc_id = $doc_id");
                    $conn->query("DELETE FROM document_history WHERE doc_id = $doc_id");
                    $conn->query("DELETE FROM document_transfers WHERE doc_id = $doc_id"); // ADD THIS LINE
                    $conn->query("DELETE FROM documents WHERE doc_id = $doc_id");
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $_SESSION['success'] = 'Document deleted successfully!';
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    throw $e;
                }
            } else {
                throw new Exception('Failed to delete document files');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }
        header("Location: doctrack.php");
        exit;
    }

    // Get all documents for display
    $documents = [];
    $query = "SELECT d.*, dt.type_name, 
        CONCAT(e.first_name, ' ', e.last_name) AS owner_name,
        e.picture AS owner_picture
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id
        JOIN employee e ON d.owner_id = e.emp_id
        ORDER BY d.created_at DESC";
    $result = $conn->query($query);
    if ($result) {
        $documents = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get document types and statuses for forms
    $types = $conn->query("SELECT * FROM document_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);

    // Get document details for view modal
    $document_details = null;
    $comments = [];
    $history = [];
    if (isset($_GET['view_id'])) {
        $view_id = (int)$_GET['view_id'];
        $query = "SELECT d.*, dt.type_name, 
                CONCAT(e.first_name, ' ', e.last_name) AS owner_name,
                e.picture AS owner_picture,
                (SELECT CONCAT(emp.first_name, ' ', emp.last_name) 
                FROM document_transfers dt 
                JOIN employee emp ON dt.processed_by = emp.emp_id 
                WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                ORDER BY dt.processed_at DESC LIMIT 1) AS processed_by_name,
                (SELECT s.section_name
                FROM document_transfers dt
                JOIN section s ON dt.to_section_id = s.section_id
                WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                ORDER BY dt.processed_at DESC LIMIT 1) AS processed_by_section,
                (SELECT dt.status FROM document_transfers dt 
                WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                ORDER BY dt.processed_at DESC LIMIT 1) AS transfer_status,
                (SELECT dt.processed_at FROM document_transfers dt 
                WHERE dt.doc_id = d.doc_id AND dt.status IN ('accepted', 'rejected') 
                ORDER BY dt.processed_at DESC LIMIT 1) AS processed_at
                FROM documents d
                JOIN document_types dt ON d.type_id = dt.type_id
                JOIN employee e ON d.owner_id = e.emp_id
                WHERE d.doc_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $view_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $document_details = $result->fetch_assoc();
            $show_all_comments = isset($_GET['all_comments']);
            // Replace your current comments query with this:
            $limit = isset($_GET['all_comments']) ? '' : 'LIMIT 3';
            $comments_query = $conn->prepare("
                SELECT 
                    dc.*, 
                    CONCAT(e.first_name, ' ', e.last_name) AS commenter_name, 
                    e.picture,
                    (SELECT COUNT(*) FROM comment_likes WHERE comment_id = dc.comment_id) AS like_count,
                    (SELECT COUNT(*) FROM comment_likes WHERE comment_id = dc.comment_id AND emp_id = ?) > 0 AS user_liked
                FROM document_comments dc
                JOIN employee e ON dc.emp_id = e.emp_id
                WHERE dc.doc_id = ?
                ORDER BY dc.created_at DESC
                $limit
            ");
            $current_emp_id = getEmployeeId($conn, $_SESSION['user_id']);
            $comments_query->bind_param("ii", $current_emp_id, $view_id);
            $comments_query->execute();
            $comments_result = $comments_query->get_result();
            $comments = $comments_result ? $comments_result->fetch_all(MYSQLI_ASSOC) : [];

            // Get total comment count
            $count_query = $conn->prepare("SELECT COUNT(*) as total FROM document_comments WHERE doc_id = ?");
            $count_query->bind_param("i", $view_id);
            $count_query->execute();
            $count_result = $count_query->get_result();
            $total_comments = $count_result ? $count_result->fetch_assoc()['total'] : 0;
            
            // Get history
            $history_query = $conn->prepare("SELECT dh.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.picture
                               FROM document_history dh
                               JOIN employee e ON dh.emp_id = e.emp_id
                               WHERE dh.doc_id = ?
                               ORDER BY dh.created_at DESC");
            $history_query->bind_param("i", $view_id);
            $history_query->execute();
            $history_result = $history_query->get_result();
            $history = $history_result ? $history_result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }

    // Handle comment submission
    if (isset($_POST['add_comment'])) {
        try {
            $doc_id = (int)$_POST['doc_id'];
            $comment = trim($_POST['comment']);
            $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            
            if (empty($doc_id) || empty($comment)) {
                $_SESSION['error'] = 'Please enter a comment';
                header("Location: doctrack.php?view_id=$doc_id");
                exit;
            }
            
            // Get current user's employee_id
            $emp_id = getEmployeeId($conn, $_SESSION['user_id']);
            if (!$emp_id) {
                throw new Exception("User not properly linked to an employee");
            }
            
            $stmt = $conn->prepare("INSERT INTO document_comments (doc_id, emp_id, comment, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $doc_id, $emp_id, $comment, $parent_id);
            
            if ($stmt->execute()) {
                // Add to history
                $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                              VALUES (?, ?, 'commented', 'Comment added')");
                $history_stmt->bind_param("ii", $doc_id, $emp_id);
                $history_stmt->execute();
                
                $_SESSION['success'] = 'Comment added successfully!';
            } else {
                $_SESSION['error'] = 'Failed to add comment.';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }
        header("Location: doctrack.php?view_id=$doc_id");
        exit;
    }

    // Handle like action
   if (isset($_POST['like_comment'])) {
        try {
            $comment_id = (int)$_POST['comment_id'];
            $doc_id = (int)$_POST['doc_id'];
            
            // Get current user's employee_id
            $emp_id = getEmployeeId($conn, $_SESSION['user_id']);
            if (!$emp_id) {
                throw new Exception("User not properly linked to an employee");
            }
            
            // Check if user already liked this comment
            $check_stmt = $conn->prepare("SELECT * FROM comment_likes WHERE comment_id = ? AND emp_id = ?");
            $check_stmt->bind_param("ii", $comment_id, $emp_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                // Unlike
                $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND emp_id = ?");
                $stmt->bind_param("ii", $comment_id, $emp_id);
                $action = 'unliked';
            } else {
                // Like
                $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, emp_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $comment_id, $emp_id);
                $action = 'liked';
            }
            
            if ($stmt->execute()) {
                // Get updated like count
                $count_stmt = $conn->prepare("SELECT COUNT(*) as like_count FROM comment_likes WHERE comment_id = ?");
                $count_stmt->bind_param("i", $comment_id);
                $count_stmt->execute();
                $result = $count_stmt->get_result()->fetch_assoc();
                $like_count = $result['like_count'];
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action' => $action,
                    'like_count' => $like_count
                ]);
                exit;
            } else {
                throw new Exception('Failed to process like');
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
// Handle send_to_section form submission
if (isset($_POST['send_to_section'])) {
    try {
        $doc_id = (int)$_POST['doc_id'];
        $selected_items = $_POST['section_ids'] ?? [];
        
        if (empty($doc_id) || empty($selected_items)) {
            throw new Exception("Please select at least one section, unit, or manager");
        }
        
        // Get current user's employee_id
        $sender_id = getEmployeeId($conn, $_SESSION['user_id']);
        if (!$sender_id) {
            throw new Exception("User not properly linked to an employee");
        }
        
        // Verify document exists and check permissions
        $document = verifyDocumentOwnership($conn, $doc_id, $sender_id, $_SESSION['role']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Process each selected item
            foreach ($selected_items as $item) {
                // Skip if empty
                if (empty($item)) continue;
                
                // Parse the selection (could be section_123, unit_456, manager_789, or imo_office)
                $parts = explode('_', $item);
                $type = $parts[0];
                $id = isset($parts[1]) ? (int)$parts[1] : 0;
                
                // Handle different transfer types
                switch ($type) {
                    case 'imo':
                        // Handle IMO office transfer (to all managers)
                        if ($item === 'imo_office') {
                            transferToIMOOffice($conn, $doc_id, $sender_id);
                        }
                        break;
                        
                    case 'section':
                        // Handle section transfer
                        transferToSection($conn, $doc_id, $sender_id, $id);
                        break;
                        
                    case 'unit':
                        // Handle unit transfer
                        transferToUnit($conn, $doc_id, $sender_id, $id);
                        break;
                        
                    case 'manager':
                        // Handle direct manager transfer
                        transferToManager($conn, $doc_id, $sender_id, $id);
                        break;
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = 'Document sent to selected sections/units/managers successfully!';
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header("Location: doctrack.php?view_id=$doc_id");
    exit;
}

// Helper function to verify document ownership
function verifyDocumentOwnership($conn, $doc_id, $current_emp_id, $user_role) {
    $stmt = $conn->prepare("SELECT owner_id FROM documents WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    if (!$document) {
        throw new Exception("Document not found");
    }
    
    // Check permission (owner or admin)
    if ($document['owner_id'] != $current_emp_id && $user_role !== 'admin') {
        throw new Exception("You don't have permission to transfer this document");
    }
    
    return $document;
}

// Transfer to IMO Office (all managers)
function transferToIMOOffice($conn, $doc_id, $sender_id) {
    // Get all managers and staff
    $query = "SELECT emp_id FROM employee WHERE is_manager = 1 OR is_manager_staff = 1";
    $result = $conn->query($query);
    
    while ($manager = $result->fetch_assoc()) {
        // Skip if transfer already exists
        if (!transferExists($conn, $doc_id, null, $manager['emp_id'])) {
            createTransferRecord($conn, $doc_id, $sender_id, null, null, $manager['emp_id']);
            
            // Add to history
            addDocumentHistory($conn, $doc_id, $sender_id, 'transferred', 
                "Sent to IMO Office (all managers)");
        }
    }
}

// Transfer to a specific section
function transferToSection($conn, $doc_id, $sender_id, $section_id) {
    // Verify section exists
    $section = getSectionInfo($conn, $section_id);
    
    // Skip if transfer already exists
    if (transferExists($conn, $doc_id, $section_id)) {
        return;
    }
    
    // Create transfer record
    createTransferRecord($conn, $doc_id, $sender_id, $section_id);
    
    // Add to history
    addDocumentHistory($conn, $doc_id, $sender_id, 'transferred', 
        "Sent to {$section['section_name']} section");
}

// Transfer to a specific unit
function transferToUnit($conn, $doc_id, $sender_id, $unit_id) {
    // Verify unit exists and get section info
    $unit = getUnitInfo($conn, $unit_id);
    
    // Skip if transfer already exists for this unit's section
    if (transferExists($conn, $doc_id, $unit['section_id'], $unit_id)) {
        return;
    }
    
    // Create transfer record
    createTransferRecord($conn, $doc_id, $sender_id, $unit['section_id'], $unit_id);
    
    // Add to history
    addDocumentHistory($conn, $doc_id, $sender_id, 'transferred', 
        "Sent to {$unit['unit_name']} unit in {$unit['section_name']} section");
}

// Transfer directly to a manager
function transferToManager($conn, $doc_id, $sender_id, $manager_id) {
    // Verify manager exists
    $manager = getEmployeeInfo($conn, $manager_id);
    
    // Skip if transfer already exists
    if (transferExists($conn, $doc_id, null, $manager_id)) {
        return;
    }
    
    // Create transfer record
    createTransferRecord($conn, $doc_id, $sender_id, null, null, $manager_id);
    
    // Add to history
    addDocumentHistory($conn, $doc_id, $sender_id, 'transferred', 
        "Sent directly to {$manager['first_name']} {$manager['last_name']}");
}

// Helper function to check if transfer already exists
function transferExists($conn, $doc_id, $section_id = null, $unit_id = null, $emp_id = null) {
    $query = "SELECT transfer_id FROM document_transfers 
              WHERE doc_id = ? AND status = 'pending'";
    $params = [$doc_id];
    $types = "i";
    
    if ($section_id !== null) {
        $query .= " AND to_section_id = ?";
        $params[] = $section_id;
        $types .= "i";
    }
    
    if ($unit_id !== null) {
        $query .= " AND to_unit_id = ?";
        $params[] = $unit_id;
        $types .= "i";
    }
    
    if ($emp_id !== null) {
        $query .= " AND to_emp_id = ?";
        $params[] = $emp_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

// Helper function to create transfer record
function createTransferRecord($conn, $doc_id, $from_emp_id, $to_section_id = null, $to_unit_id = null, $to_emp_id = null) {
    $stmt = $conn->prepare("
        INSERT INTO document_transfers 
        (doc_id, from_emp_id, to_section_id, to_unit_id, to_emp_id, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiiii", $doc_id, $from_emp_id, $to_section_id, $to_unit_id, $to_emp_id);
    $stmt->execute();
}

// Helper function to add document history
function addDocumentHistory($conn, $doc_id, $emp_id, $action, $details) {
    $stmt = $conn->prepare("
        INSERT INTO document_history 
        (doc_id, emp_id, action, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $doc_id, $emp_id, $action, $details);
    $stmt->execute();
}

// Helper function to get section info
function getSectionInfo($conn, $section_id) {
    $stmt = $conn->prepare("
        SELECT section_id, section_name, section_code 
        FROM section WHERE section_id = ?
    ");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception("Section not found");
    }
    
    return $result;
}

// Helper function to get unit info
function getUnitInfo($conn, $unit_id) {
    $stmt = $conn->prepare("
        SELECT u.unit_id, u.unit_name, u.unit_code, u.section_id,
               s.section_name
        FROM unit_section u
        JOIN section s ON u.section_id = s.section_id
        WHERE u.unit_id = ?
    ");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception("Unit not found");
    }
    
    return $result;
}

// Helper function to get employee info
function getEmployeeInfo($conn, $emp_id) {
    $stmt = $conn->prepare("
        SELECT emp_id, first_name, last_name 
        FROM employee WHERE emp_id = ?
    ");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception("Employee not found");
    }
    
    return $result;
}
    // $del_comments = $conn->prepare("DELETE FROM document_comments WHERE doc_id = ?");
    // $del_comments->bind_param("i", $doc_id);
    // $del_comments->execute();

    // $del_history = $conn->prepare("DELETE FROM document_history WHERE doc_id = ?");
    // $del_history->bind_param("i", $doc_id);
    // $del_history->execute();

    // $del_transfers = $conn->prepare("DELETE FROM document_transfers WHERE doc_id = ?");
    // $del_transfers->bind_param("i", $doc_id);
    // $del_transfers->execute();

    // $del_document = $conn->prepare("DELETE FROM documents WHERE doc_id = ?");
    // $del_document->bind_param("i", $doc_id);
    // $del_document->execute();
    // Get all sections with their units for the transfer form
    $sections = [];
    $section_query = $conn->query("
        SELECT s.section_id, s.section_name, s.section_code, 
            GROUP_CONCAT(u.unit_name SEPARATOR ', ') as unit_name
        FROM section s
        LEFT JOIN unit_section u ON s.section_id = u.section_id
        GROUP BY s.section_id
        ORDER BY s.section_name
    ");
    if ($section_query) {
        $sections = $section_query->fetch_all(MYSQLI_ASSOC);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Document Tracker</title>
  <?php include '../includes/header.php'; ?>
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="../plugins/sweetalert2/sweetalert2.min.css">
  <style>
    .timeline {
        position: relative;
        padding-left: 20px;
    }
    .timeline:before {
        content: '';
        position: absolute;
        left: 30px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    .history-icon {
        margin-right: 8px;
        font-size: 1.1rem;
    }
    .history-item {
        border-left: 3px solid #6c757d;
        padding-left: 15px;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        padding: 12px;
    }
    .history-item:before {
        content: '';
        position: absolute;
        left: -20px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #6c757d;
    }
    .history-details {
        background: #e9ecef;
        padding: 8px 12px;
        border-radius: 4px;
        margin-top: 5px;
        font-size: 0.9rem;
    }
    .pdf-viewer-swal {
        max-width: 900px;
    }
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #007bff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        margin-right: 10px;
        flex-shrink: 0;
    }
    .badge-color {
        color: white;
    }
    .comment-box {
        border: none;
        padding: 8px 12px;
        margin-bottom: 8px;
        background: #f0f2f5;
        border-radius: 18px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .comment-box:hover {
        background: #e9ecef;
    }
    .comment-box.expanded {
        max-height: none; /* Remove height limit when expanded */
    }
    .comment-header, .history-header {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }
    .comment-header {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
    }
    .comment-user-img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        margin-right: 8px;
        object-fit: cover;
    }
    .comment-user-info {
        flex-grow: 1;
    }
    .comment-user, .history-user {
        font-weight: bold;
        margin-right: 10px;
    }
    
    .comment-time, .history-time {
        color: #6c757d;
        font-size: 0.85rem;
    }
    .comment-user-name {
        font-weight: 600;
        font-size: 0.9375rem;
        color: #050505;
        margin-right: 4px;
    }

    .comment-time {
        color: #65676b;
        font-size: 0.8125rem;
    }
    .comment-content {
        margin-left: 40px;
        margin-bottom: 4px;
        font-size: 0.9375rem;
        color: #050505;
        line-height: 1.3333;
    }
    .comment-content.expanded {
        max-height: none; /* Remove height limit when expanded */
    }
    .comment-actions {
        margin-left: 40px;
        display: flex;
        align-items: center;
        font-size: 0.8125rem;
        color: #65676b;
    }
    .comment-action.liked {
        color: #216fdb;
    }
    .comment-action {
        color: #65676b;
        font-weight: 600;
        margin-right: 12px;
        cursor: pointer;
        transition: color 0.2s;
        padding: 2px 0;
    }
    
    .comment-action:hover {
        text-decoration: none;
        color: #216fdb;
    }
    
    .comment-action i {
        margin-right: 4px;
    }
    .read-more {
        position: absolute;
        bottom: 5px;
        right: 10px;
        background: rgba(255,255,255,0.8);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
        cursor: pointer;
        color: #007bff;
    }
    .reply-form {
        margin-left: 40px;
        margin-top: 8px;
        display: none;
    }
    
    .replies {
        margin-left: 32px;
        padding-left: 8px;
        border-left: 2px solid #e4e6eb;
    }
    
    /* Send to section styles */
    .send-section-card {
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .send-section-title {
        font-weight: bold;
        margin-bottom: 10px;
    }
    /* Adjust modal body to prevent scrolling */
    .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
    .history-item {
        border-left: 3px solid #6c757d;
        padding-left: 15px;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        padding: 12px;
    }
    .history-item:last-child {
        padding-bottom: 0;
    }
    
    .history-item .card {
        border-left: 3px solid #007bff;
    }
    .file-size {
        font-size: 0.8rem;
        color: #6c757d;
    }
    #show-more-comments {
        margin: 15px auto;
        display: block;
        width: auto;
        padding: 5px 15px;
    }
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 38px;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border-color: #006fe6;
        color: white;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255,255,255,0.7);
        margin-right: 5px;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: white;
    }
    
    /* Like button styles */
    .liked {
        color: #007bff !important;
    }
    
    .like-count {
        margin-left: 4px;
        font-size: 0.8125rem;
        color: #65676b;
    }
        /* Add to your existing styles */
    .section-transfer-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 10px;
    }

    .section-transfer-item {
        background-color: #f8f9fa;
        transition: all 0.2s ease;
        cursor: pointer;
        margin-bottom: 8px;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }

    .section-transfer-item:hover {
        background-color: #e9ecef;
    }

    .section-transfer-item.selected {
        background-color: #e7f1ff;
        border-color: #86b7fe;
    }

    .section-transfer-item .badge {
        font-size: 0.8rem;
        padding: 5px 8px;
    }

    .section-transfer-item .form-check-input {
        cursor: pointer;
    }
    .section-transfer-item h6 {
        margin-bottom: 0.25rem;
    }
    .section-transfer-item .unit-info {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .section-transfer-item .section-code {
        font-weight: bold;
        margin-right: 5px;
    }
    /* Select2 Multiple Selection Styles */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 38px;
        padding: 5px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border-color: #006fe6;
        color: white;
        padding: 3px 8px;
        margin: 3px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255,255,255,0.7);
        margin-right: 5px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: white;
    }

    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #e9ecef;
        color: #495057;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff;
        color: white;
    }

    .select2-container .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
    }

    /* Section/Unit grouping in dropdown */
    .select2-container--default .select2-results__group {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: bold;
        padding: 6px 8px;
        border-bottom: 1px solid #dee2e6;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <main role="main" class="main-content">
            <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                <div class="row">
                    <div class="col-md-12">
                    <h2 class="h4">All Documents</h2>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="btn-group me-2">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-funnel"></i> Filter
                                </button>
                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Type</h6></li>
                                    <?php foreach ($types as $type): ?>
                                        <li><a class="dropdown-item" href="#" onclick="filterByType(<?= (int)$type['type_id'] ?>)"><?= htmlspecialchars($type['type_name']) ?></a></li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="clearFilters()">Clear Filters</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#newDocumentModal">
                                <i class="fas fa-plus-lg me-1"></i> New Document
                            </button>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="dataTable-1">
                                        <thead>
                                            <tr>
                                                <th>Document #</th>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Owner</th>
                                                <th>Date Upload</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($doc['doc_number']) ?></td>
                                                    <td><a href="#" onclick="viewDocument(<?= (int)$doc['doc_id'] ?>)"><?= htmlspecialchars($doc['title']) ?></a></td>
                                                    <td><?= htmlspecialchars($doc['type_name']) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="mr-3">
                                                                <?php 
                                                                // You'll need to modify the main query to include e.picture for the owner
                                                                $imagePath = !empty($doc['owner_picture']) ? '../dist/img/employees/' . $doc['owner_picture'] : '../dist/img/avatar5.png';
                                                                ?>
                                                                <img src="<?= $imagePath ?>" 
                                                                    class="rounded-circle" width="40" height="40" 
                                                                    alt="<?= htmlspecialchars($doc['owner_name']) ?>">
                                                            </div>
                                                            <?= htmlspecialchars($doc['owner_name']) ?>
                                                        </div>
                                                    </td>
                                                    <td><?= date('Y-m-d', strtotime($doc['created_at'])) ?></td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-three-dots"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <!-- View Action - Available to anyone with view permission -->
                                                                <li>
                                                                    <?php if (isDocumentOwner($conn, $doc['doc_id']) || hasPermission('view_any_document')): ?>
                                                                        <a class="dropdown-item" href="#" onclick="viewDocument(<?= (int)$doc['doc_id'] ?>)">
                                                                            <i class="fas fa-eye"></i> View
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="dropdown-item text-muted disabled">
                                                                            <i class="fas fa-eye"></i> View (No Permission)
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </li>
                                                                
                                                                <!-- Edit Action - Owner or edit_any_document permission -->
                                                                <?php if (isDocumentOwner($conn, $doc['doc_id']) || hasPermission('edit_any_document')): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="#" onclick="editDocument(<?= (int)$doc['doc_id'] ?>)">
                                                                            <i class="fas fa-edit"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Download Action - Owner or download permission -->
                                                                <li>
                                                                    <?php if (isDocumentOwner($conn, $doc['doc_id']) || hasPermission('download_document')): ?>
                                                                        <form action="serve_document.php" method="post" class="d-inline">
                                                                            <input type="hidden" name="doc_id" value="<?= (int)$doc['doc_id'] ?>">
                                                                            <button type="submit" name="action" value="download" class="dropdown-item">
                                                                                <i class="fas fa-download"></i> Download
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <span class="dropdown-item text-muted disabled">
                                                                            <i class="fas fa-download"></i> Download (No Permission)
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </li>
                                                                
                                                                <!-- Delete Action - Owner or delete_any_document permission -->
                                                                <?php if (isDocumentOwner($conn, $doc['doc_id']) || hasPermission('delete_any_document')): ?>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?= (int)$doc['doc_id'] ?>)">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div> 
            </div> 
            </div>
        </main>
      </div>
    </div>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- New Document Modal -->
<div class="modal fade" id="newDocumentModal" tabindex="-1" role="dialog" aria-labelledby="newDocumentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newDocumentModalLabel">Create New Document</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" enctype="multipart/form-data" id="createDocumentForm">
        <div class="modal-body">
          <div class="form-group">
            <label for="title">Document Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="title" name="title" required maxlength="255">
          </div>
          
          <div class="form-group">
            <label for="type_id">Document Type <span class="text-danger">*</span></label>
            <select class="form-control" id="type_id" name="type_id" required>
              <?php foreach ($types as $type): ?>
                <option value="<?= (int)$type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="500"></textarea>
            <small class="text-muted">Optional notes about this document</small>
          </div>
          
          <div class="form-group">
              <label for="document_file">Document File <span class="text-danger">*</span></label>
              <div class="custom-file">
                  <input type="file" class="custom-file-input" id="document_file" name="document_file" required 
                        accept=".pdf,.doc,.docx,.xls,.xlsx">
                  <label class="custom-file-label" for="document_file">Choose file</label>
              </div>
              <small class="form-text text-muted">Allowed file types: PDF, DOC, DOCX, XLS, XLSX. Max size: 100MB</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="create_document">Save Document</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Document Modal -->
<div class="modal fade" id="editDocumentModal" tabindex="-1" role="dialog" aria-labelledby="editDocumentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editDocumentModalLabel">Edit Document</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" enctype="multipart/form-data" id="editDocumentForm">
        <input type="hidden" id="edit_doc_id" name="doc_id">
        <div class="modal-body">
          <div class="form-group">
            <label for="edit_title">Document Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit_title" name="title" required maxlength="255">
          </div>
          
          <div class="form-group">
            <label for="edit_type_id">Document Type <span class="text-danger">*</span></label>
            <select class="form-control" id="edit_type_id" name="type_id" required>
              <?php foreach ($types as $type): ?>
                <option value="<?= (int)$type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
              <label for="edit_document_file">Document File</label>
              <div class="custom-file">
                  <input type="file" class="custom-file-input" id="edit_document_file" name="document_file" 
                        accept=".pdf,.doc,.docx,.xls,.xlsx">
                  <label class="custom-file-label" for="edit_document_file">Choose new file to replace</label>
              </div>
              <small class="form-text text-muted">Allowed file types: PDF, DOC, DOCX, XLS, XLSX. Max size: 100MB</small>
              <div id="current_file" class="mt-2 p-2 bg-light rounded">
                  <strong>Current file:</strong> <span id="current_file_name"></span>
                  <span class="file-size" id="current_file_size"></span>
              </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="update_document">Update Document</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Document Modal -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1" role="dialog" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="viewDocumentModalLabel">Document Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <?php if ($document_details): ?>
          <!-- Document Header Section -->
          <div class="row mb-4">
            <div class="col-md-8">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1"><?= htmlspecialchars($document_details['title']) ?></h3>
                  <p class="text-muted mb-2">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($document_details['doc_number']) ?>
                  </p>
                  <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge bg-secondary">
                      <i class="fas fa-tag"></i> <?= htmlspecialchars($document_details['type_name']) ?>
                    </span>
                    <?php if (!empty($document_details['transfer_status'])): ?>
                      <span class="badge <?= $document_details['transfer_status'] === 'accepted' ? 'bg-success' : 'bg-danger' ?>">
                        <i class="fas fa-exchange-alt"></i> <?= ucfirst($document_details['transfer_status']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" 
                        onclick="generateDocumentTemplate(<?= (int)$document_details['doc_id'] ?>)">
                    <i class="fas fa-file-word"></i> Download Template
                </button>
              </div>
              
              <!-- Document Info Section -->
              <div class="card mb-3">
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-4">
                      <h6 class="text-muted">Owner</h6>
                        <div class="d-flex align-items-center">
                          <img src="<?= !empty($document_details['owner_picture']) ? '../dist/img/employees/' . $document_details['owner_picture'] : '../dist/img/avatar5.png' ?>" 
                               class="rounded-circle mr-2" width="40" height="40" 
                               alt="<?= htmlspecialchars($document_details['owner_name']) ?>">
                          <span><?= htmlspecialchars($document_details['owner_name']) ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                      <div class="mb-3">
                        <h6 class="text-muted">Created</h6>
                        <p><?= date('M d, Y H:i', strtotime($document_details['created_at'])) ?></p>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="mb-3">
                        <h6 class="text-muted">Last Updated</h6>
                        <p><?= date('M d, Y H:i', strtotime($document_details['updated_at'])) ?></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Processing Information Section -->
              <?php if (!empty($document_details['processed_by_name'])): ?>
                <div class="card mb-2">
                  <div class="card-header bg-light">
                    <h6 class="card-title mb-0"><i class="fas fa-user-check mr-2"></i>Processing Information</h6>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-4">
                        <div class="mb-3">
                          <h6 class="text-muted">Processed By</h6>
                          <p><?= htmlspecialchars($document_details['processed_by_name']) ?></p>
                          <p class="small">(<?= htmlspecialchars($document_details['processed_by_section']) ?>)</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="mb-3">
                          <h6 class="text-muted">Processed At</h6>
                          <p><?= date('M d, Y H:i', strtotime($document_details['processed_at'])) ?></p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="mb-3">
                          <h6 class="text-muted">Status</h6>
                          <span class="badge <?= $document_details['transfer_status'] === 'accepted' ? 'bg-success' : 'bg-danger' ?>">
                            <?= ucfirst($document_details['transfer_status']) ?>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              
          

          <!-- Remarks Section -->
          <div class="card mb-3">
            <div class="card-header bg-light">
              <h6 class="card-title mb-0"><i class="fas fa-comment mr-2"></i>Remarks</h6>
            </div>
            <div class="card-body">
              <?= !empty($document_details['remarks']) ? nl2br(htmlspecialchars($document_details['remarks'])) : '<p class="text-muted">No remarks provided</p>' ?>
            </div>
          </div>
            </div>
            
            <!-- QR Code Section -->
            <div class="col-md-4 text-center">
              <img src="<?= htmlspecialchars($document_details['qr_code']) ?>" alt="QR Code" class="img-fluid mb-2" style="max-width: 200px;">
              <p class="small">Scan QR Code to access document</p>
                          <div class="card-body text-center">
                  <?php
                  $file_ext = strtolower(pathinfo($document_details['file_path'], PATHINFO_EXTENSION));
                  $file_size = filesize($document_details['file_path']) / (1024 * 1024); // Convert to MB
                  ?>
                  <div class="file-preview mb-3">
                    <?php if ($file_ext === 'pdf'): ?>
                      <i class="fas fa-file-pdf fa-5x text-danger"></i>
                    <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                      <i class="fas fa-file-word fa-5x text-primary"></i>
                    <?php elseif (in_array($file_ext, ['xls', 'xlsx'])): ?>
                      <i class="fas fa-file-excel fa-5x text-success"></i>
                    <?php else: ?>
                      <i class="fas fa-file fa-5x text-secondary"></i>
                    <?php endif; ?>
                  </div>
                  <h6><?= htmlspecialchars(basename($document_details['file_path'])) ?></h6>
                  <p class="text-muted small"><?= number_format($file_size, 2) ?> MB</p>
                  <p class="text-muted small"><?= strtoupper($file_ext) ?> File</p>
                  
                  <div class="mt-3 d-flex justify-content-center gap-2">
                    <?php if (strtolower($file_ext) === 'pdf'): ?>
                      <button class="btn btn-outline-info btn-sm" onclick="viewPDF('<?= htmlspecialchars($document_details['file_path']) ?>', '<?= htmlspecialchars($document_details['title']) ?>')">
                        <i class="fas fa-eye"></i> View PDF
                      </button>
                    <?php endif; ?>
                    <form action="serve_document.php" method="post" class="d-inline">
                      <input type="hidden" name="doc_id" value="<?= (int)$document_details['doc_id'] ?>">
                      <button type="submit" name="action" value="download" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-download"></i> Download
                      </button>
                    </form>
                  </div>
                </div>
            </div>
          </div>


          <!-- Signature Section (if needed) -->
          <!-- <div class="text-right mt-4">
            <p class="mb-1">_________________________</p>
            <p class="mb-0"><strong>ENGR. MARK CLOYD G. SO</strong></p>
            <p class="mb-0">Acting Division Manager</p>
          </div> -->

         <div class="row mt-4">
            <div class="col-md-12">
              <ul class="nav nav-tabs" id="documentTabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" id="transfers-tab" data-toggle="tab" href="#transfers" role="tab">
                    <i class="fas fa-exchange-alt mr-1"></i> Transfers
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" id="comments-tab" data-toggle="tab" href="#comments" role="tab">
                    <i class="fas fa-comments mr-1"></i> Comments 
                    <?php if ($total_comments > 0): ?>
                      <span class="badge bg-secondary"><?= $total_comments ?></span>
                    <?php endif; ?>
                  </a>
                </li>
              </ul>
              
              <div class="tab-content border border-top-0 p-3" id="documentTabsContent">
                <div class="tab-pane fade show active" id="transfers" role="tabpanel">
                  <div class="send-section-card mb-4">
                      <div class="send-section-title">
                          <i class="fas fa-paper-plane mr-2"></i> Send to Section(s) and Unit(s)
                      </div>
                      <form method="POST" action="doctrack.php" id="sendToSectionForm">
                          <input type="hidden" name="doc_id" value="<?= (int)$document_details['doc_id'] ?>">
                          
                          <!-- IMO Office Checkbox -->
                          <div class="form-check mb-3">
                              <input class="form-check-input" type="checkbox" id="imo_office" name="section_ids[]" value="imo_office">
                              <label class="form-check-label" for="imo_office">
                                  <strong>IMO Office</strong> (Send to all IMO managers and staff)
                              </label>
                          </div>

                          <div id="managers-section" class="section-transfer-item mb-3" style="display: none;">
                              <h5 class="mb-3">IMO Office Managers & Staff</h5>
                              <?php
                              // Get all IMO managers and staff for document transfer
                              try {
                                  // Replace your current manager query with this:
                                  $managers_sql = "SELECT e.emp_id, e.first_name, e.last_name, e.picture, 
                                                  IF(e.is_manager = 1, 'Manager', mos.position) as position,
                                                  mos.responsibilities
                                                  FROM employee e
                                                  LEFT JOIN managers_office_staff mos ON e.emp_id = mos.emp_id
                                                  WHERE e.is_manager = 1 OR mos.id IS NOT NULL
                                                  ORDER BY e.is_manager DESC, e.first_name, e.last_name";
                                          
                                  $managers_result = $conn->query($managers_sql);
                                  
                                  if (!$managers_result) {
                                      throw new Exception("Failed to fetch managers: " . $conn->error);
                                  }
                                  
                                  if ($managers_result->num_rows === 0) {
                                      echo '<div class="alert alert-info">No managers or staff found</div>';
                                  } else {
                                      // Process each manager/staff member
                                      while ($manager = $managers_result->fetch_assoc()) {
                                          // Check for existing pending transfer to this employee
                                          $transfer_check_sql = "SELECT transfer_id FROM document_transfers 
                                                                WHERE doc_id = ? AND to_emp_id = ? AND status = 'pending'";
                                          
                                          $stmt = $conn->prepare($transfer_check_sql);
                                          if (!$stmt) {
                                              throw new Exception("Prepare failed: " . $conn->error);
                                          }
                                          
                                          $stmt->bind_param("ii", $document_details['doc_id'], $manager['emp_id']);
                                          if (!$stmt->execute()) {
                                              throw new Exception("Execute failed: " . $stmt->error);
                                          }
                                          
                                          $transfer_exists = $stmt->get_result()->num_rows > 0;
                                          $stmt->close();
                                          
                                          // Only show if no pending transfer exists
                                          if (!transferExists($conn, $document_details['doc_id'], null, $manager['emp_id'])) {
                                              // Display the manager/staff checkbox
                                              ?>
                                              <div class="form-check mb-2">
                                                  <input class="form-check-input manager-checkbox" 
                                                        type="checkbox" 
                                                        id="manager_<?= $manager['emp_id'] ?>" 
                                                        name="section_ids[]" 
                                                        value="manager_<?= $manager['emp_id'] ?>">
                                                  <label class="form-check-label" for="manager_<?= $manager['emp_id'] ?>">
                                                      <div class="d-flex align-items-center">
                                                          <div class="mr-2">
                                                              <?php if (!empty($manager['picture'])): ?>
                                                                  <img src="../dist/img/employees/<?= htmlspecialchars($manager['picture']) ?>" 
                                                                      class="rounded-circle" width="30" height="30">
                                                              <?php else: ?>
                                                                  <div class="avatar-sm">
                                                                      <?= substr($manager['first_name'], 0, 1) . substr($manager['last_name'], 0, 1) ?>
                                                                  </div>
                                                              <?php endif; ?>
                                                          </div>
                                                          <div>
                                                              <?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?>
                                                              <?php if ($manager['is_manager']): ?>
                                                                  <span class="badge bg-primary ml-2">Manager</span>
                                                              <?php elseif ($manager['is_manager_staff']): ?>
                                                                  <span class="badge bg-secondary ml-2">Staff</span>
                                                              <?php endif; ?>
                                                          </div>
                                                      </div>
                                                  </label>
                                              </div>
                                              <?php
                                          }
                                      }
                                  }
                              } catch (Exception $e) {
                                  // Log error and display user-friendly message
                                  error_log("Document transfer error: " . $e->getMessage());
                                  ?>
                                  <div class="alert alert-danger">
                                      Error loading manager list. Please try again later.
                                      <?php if ($_SESSION['role'] === 'admin'): ?>
                                          <div class="small">Technical details: <?= htmlspecialchars($e->getMessage()) ?></div>
                                      <?php endif; ?>
                                  </div>
                                  <?php
                              }
                              ?>
                          </div>

                          <div class="section-transfer-container">
                              <?php 
                              // Get all sections with their units and employees
                              $sections_query = $conn->query("
                                  SELECT s.section_id, s.section_name, s.section_code, 
                                        e.emp_id as head_id, 
                                        CONCAT(e.first_name, ' ', e.last_name) as head_name,
                                        e.picture as head_picture
                                  FROM section s
                                  LEFT JOIN employee e ON s.head_emp_id = e.emp_id
                                  ORDER BY s.section_name
                              ");

                              while ($section = $sections_query->fetch_assoc()): 
                                  // Check if section has pending transfer
                                  $section_transfer_exists = false;
                                  $check_section = $conn->prepare("SELECT * FROM document_transfers 
                                                                WHERE doc_id = ? AND to_section_id = ? AND to_unit_id IS NULL");
                                  $check_section->bind_param("ii", $document_details['doc_id'], $section['section_id']);
                                  $check_section->execute();
                                  $section_transfer_exists = $check_section->get_result()->num_rows > 0;
                                  
                                  if (!$section_transfer_exists):
                                      // Get section head
                                      $section_head_query = $conn->prepare("
                                          SELECT e.emp_id, e.first_name, e.last_name, e.picture
                                          FROM employee e
                                          JOIN section s ON e.section_id = s.section_id
                                          WHERE e.section_id = ? AND e.emp_id = s.head_emp_id
                                      ");
                                      $section_head_query->bind_param("i", $section['section_id']);
                                      $section_head_query->execute();
                                      $section_head = $section_head_query->get_result()->fetch_assoc();
                              ?>
                              <div class="section-transfer-item">
                                  <div class="d-flex align-items-start">
                                      <div class="form-check mr-2 mt-1">
                                          <input class="form-check-input section-checkbox" type="checkbox" 
                                                id="section_<?= $section['section_id'] ?>" 
                                                name="section_ids[]" 
                                                value="section_<?= $section['section_id'] ?>">
                                      </div>
                                      <div class="flex-grow-1">
                                          <!-- Section with Head -->
                                          <div class="d-flex align-items-center mb-2">
                                              <h6 class="mb-0 font-weight-bold"><?= $section['section_name'] ?></h6>
                                              <?php if ($section_head): ?>
                                                  <div class="ml-3 d-flex align-items-center">
                                                      <img src="<?= !empty($section_head['picture']) ? '../dist/img/employees/' . $section_head['picture'] : '../dist/img/avatar5.png' ?>" 
                                                          class="rounded-circle mr-2" width="30" height="30">
                                                      <small class="text-muted">
                                                          <?= htmlspecialchars($section_head['first_name'] . ' ' . $section_head['last_name']) ?> (Section Head)
                                                      </small>
                                                  </div>
                                              <?php endif; ?>
                                          </div>
                                          
                                          <!-- Units under this section -->
                                          <?php 
                                          // Get units for this section
                                          $units_query = $conn->prepare("
                                              SELECT u.unit_id, u.unit_name, u.unit_code,
                                                    e.emp_id as head_id, 
                                                    CONCAT(e.first_name, ' ', e.last_name) as head_name,
                                                    e.picture as head_picture
                                              FROM unit_section u
                                              LEFT JOIN employee e ON u.head_emp_id = e.emp_id
                                              WHERE u.section_id = ?
                                              ORDER BY u.unit_name
                                          ");
                                          $units_query->bind_param("i", $section['section_id']);
                                          $units_query->execute();
                                          $units = $units_query->get_result()->fetch_all(MYSQLI_ASSOC);
                                          
                                          if (!empty($units)): ?>
                                              <div class="unit-list ml-4">
                                                  <?php foreach ($units as $unit): 
                                                      // Check if unit has pending transfer
                                                      $check_unit = $conn->prepare("SELECT * FROM document_transfers 
                                                                                  WHERE doc_id = ? AND to_unit_id = ?");
                                                      $check_unit->bind_param("ii", $document_details['doc_id'], $unit['unit_id']);
                                                      $check_unit->execute();
                                                      $unit_transfer_exists = $check_unit->get_result()->num_rows > 0;
                                                      
                                                      if (!$unit_transfer_exists):
                                                          // Get unit head
                                                          $unit_head_query = $conn->prepare("
                                                              SELECT e.emp_id, e.first_name, e.last_name, e.picture
                                                              FROM employee e
                                                              JOIN unit_section u ON e.unit_section_id = u.unit_id
                                                              WHERE u.unit_id = ? AND e.emp_id = u.head_emp_id
                                                          ");
                                                          $unit_head_query->bind_param("i", $unit['unit_id']);
                                                          $unit_head_query->execute();
                                                          $unit_head = $unit_head_query->get_result()->fetch_assoc();
                                                  ?>
                                                  <div class="d-flex align-items-start mb-2">
                                                      <div class="form-check mr-2 mt-1">
                                                          <input class="form-check-input unit-checkbox" type="checkbox" 
                                                                id="unit_<?= $unit['unit_id'] ?>" 
                                                                name="section_ids[]" 
                                                                value="unit_<?= $unit['unit_id'] ?>">
                                                      </div>
                                                      <div>
                                                          <div class="d-flex align-items-center">
                                                              <h6 class="mb-0"><?= $unit['unit_name'] ?> (<?= $unit['unit_code'] ?>)</h6>
                                                              <?php if ($unit_head): ?>
                                                                  <div class="ml-3 d-flex align-items-center">
                                                                      <img src="<?= !empty($unit_head['picture']) ? '../dist/img/employees/' . $unit_head['picture'] : '../dist/img/avatar5.png' ?>" 
                                                                          class="rounded-circle mr-2" width="25" height="25">
                                                                      <small class="text-muted">
                                                                          <?= htmlspecialchars($unit_head['first_name'] . ' ' . $unit_head['last_name']) ?> (Unit Head)
                                                                      </small>
                                                                  </div>
                                                              <?php endif; ?>
                                                          </div>
                                                      </div>
                                                  </div>
                                                  <?php endif; endforeach; ?>
                                              </div>
                                          <?php endif; ?>
                                      </div>
                                  </div>
                              </div>
                              <?php endif; endwhile; ?>
                          </div>
                          
                          <button type="submit" class="btn btn-primary mt-3" name="send_to_section">
                              <i class="fas fa-paper-plane"></i> Send to Selected Sections/Units
                          </button>
                      </form>
                  </div>
                  
                  <!-- Transfer History -->
                  <div class="card">
                    <div class="card-header bg-light">
                      <h6 class="card-title mb-0"><i class="fas fa-exchange-alt mr-2"></i>Transfer History</h6>
                    </div>
                    <div class="card-body">
                      <?php 
                      $transfer_history = $conn->prepare("
                        SELECT dt.*, 
                        CONCAT(e1.first_name, ' ', e1.last_name) as sender_name,
                        s1.section_name as from_section,
                        s2.section_name as to_section,
                        u.unit_name,
                        dt.to_unit_id,
                        dt.remarks,
                        CASE 
                          WHEN dt.processed_by IS NOT NULL THEN CONCAT(e2.first_name, ' ', e2.last_name)
                          ELSE NULL
                        END as processed_by_name
                        FROM document_transfers dt
                        JOIN employee e1 ON dt.from_emp_id = e1.emp_id
                        JOIN section s1 ON e1.section_id = s1.section_id
                        JOIN section s2 ON dt.to_section_id = s2.section_id
                        LEFT JOIN unit_section u ON dt.to_unit_id = u.unit_id
                        LEFT JOIN employee e2 ON dt.processed_by = e2.emp_id
                        WHERE dt.doc_id = ?
                        ORDER BY dt.created_at DESC 
                        LIMIT 0, 25
                      ");
                      $transfer_history->bind_param("i", $view_id);
                      $transfer_history->execute();
                      $transfers = $transfer_history->get_result()->fetch_all(MYSQLI_ASSOC);
                      ?>
                      
                      <?php if (!empty($transfers)): ?>
                        <div class="table-responsive">
                          <table class="table table-bordered table-striped" id="transferHistoryTable">
                            <thead>
                              <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($transfers as $transfer): ?>
                                <tr>
                                  <td>
                                    <div class="d-flex align-items-center">
                                      <div class="mr-2">
                                        <i class="fas fa-user-circle"></i>
                                      </div>
                                      <div>
                                        <strong><?= htmlspecialchars($transfer['from_section']) ?></strong>
                                        <div class="small"><?= htmlspecialchars($transfer['sender_name']) ?></div>
                                      </div>
                                    </div>
                                  </td>
                                  <td>
                                    <strong><?= htmlspecialchars($transfer['to_section']) ?></strong>
                                    <?php if (!empty($transfer['to_unit_id'])): ?>
                                      <div class="small">
                                        <i class="fas fa-cube"></i> <?= htmlspecialchars($transfer['unit_name']) ?>
                                      </div>
                                    <?php endif; ?>
                                  </td>
                                  <td>
                                    <span class="badge <?= $transfer['status'] == 'pending' ? 'bg-warning' : 
                                                        ($transfer['status'] == 'accepted' ? 'bg-success' : 
                                                        ($transfer['status'] == 'revised' ? 'bg-info' : 'bg-danger')) ?>">
                                      <?= ucfirst($transfer['status']) ?>
                                    </span>
                                  </td>
                                  <td>
                                    <?= date('M d, Y', strtotime($transfer['created_at'])) ?>
                                    <div class="small"><?= date('H:i', strtotime($transfer['created_at'])) ?></div>
                                  </td>
                                  <td>
                                    <div class="btn-group btn-group-sm">
                                      <?php if (!empty($transfer['remarks'])): ?>
                                        <button class="btn btn-outline-info" 
                                                onclick="showRemarks('<?= htmlspecialchars(addslashes($transfer['remarks'])) ?>')"
                                                title="View Remarks">
                                          <i class="fas fa-eye"></i>
                                        </button>
                                      <?php endif; ?>
                                      <?php if ($transfer['status'] === 'accepted'): ?>
                                        <button class="btn btn-outline-primary" 
                                                onclick="forwardToSection(<?= (int)$transfer['transfer_id'] ?>, <?= (int)$document_details['doc_id'] ?>)"
                                                title="Forward">
                                          <i class="fas fa-share"></i>
                                        </button>
                                      <?php elseif (in_array($transfer['status'], ['revised', 'returned'])): ?>
                                        <button class="btn btn-outline-secondary" 
                                                onclick="transferAgain(<?= (int)$transfer['transfer_id'] ?>, <?= (int)$document_details['doc_id'] ?>)"
                                                title="Transfer Again">
                                          <i class="fas fa-redo"></i>
                                        </button>
                                      <?php endif; ?>
                                    </div>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php else: ?>
                        <div class="alert alert-info mb-0">No transfer history found</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                
                <div class="tab-pane fade" id="comments" role="tabpanel">
                  <?php if (!empty($comments)): ?>
                    <div id="comments-container">
                      <?php 
                      // Organize comments into parents and replies
                      $parent_comments = array_filter($comments, function($c) { return empty($c['parent_id']); });
                      $replies = array_filter($comments, function($c) { return !empty($c['parent_id']); });
                      
                      foreach ($parent_comments as $comment): ?>
                        <div class="comment-box mb-3" id="comment-<?= $comment['comment_id'] ?>">
                          <div class="comment-header">
                            <img src="<?= !empty($comment['picture']) ? '../dist/img/employees/' . $comment['picture'] : '../dist/img/avatar5.png' ?>" 
                                class="comment-user-img" 
                                alt="<?= htmlspecialchars($comment['commenter_name']) ?>">
                            <div class="comment-user-info">
                              <span class="comment-user-name"><?= htmlspecialchars($comment['commenter_name']) ?></span>
                              <span class="comment-time"><?= date('M d, Y \a\t H:i', strtotime($comment['created_at'])) ?></span>
                            </div>
                          </div>
                          <div class="comment-content">
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                          </div>
                          <div class="comment-actions">
                            <a href="#" class="comment-action like-btn <?= isset($comment['user_liked']) && $comment['user_liked'] ? 'liked' : '' ?>" 
                            onclick="likeComment(<?= $comment['comment_id'] ?>, <?= (int)$document_details['doc_id'] ?>)">
                              <i class="fas fa-thumbs-up"></i> Like
                              <?php if (isset($comment['like_count']) && $comment['like_count'] > 0): ?>
                                <span class="like-count"><?= $comment['like_count'] ?></span>
                              <?php endif; ?>
                            </a>
                            <a href="#" class="comment-action" onclick="toggleReplyForm(<?= $comment['comment_id'] ?>)">
                              <i class="fas fa-reply"></i> Reply
                            </a>
                          </div>
                          
                          <!-- Reply form -->
                          <div class="reply-form" id="reply-form-<?= $comment['comment_id'] ?>">
                            <form method="POST" action="doctrack.php">
                              <input type="hidden" name="doc_id" value="<?= (int)$document_details['doc_id'] ?>">
                              <input type="hidden" name="parent_id" value="<?= $comment['comment_id'] ?>">
                              <div class="form-group mb-2">
                                <textarea class="form-control" name="comment" rows="2" required 
                                          placeholder="Write a reply..." maxlength="1000"></textarea>
                              </div>
                              <button type="submit" class="btn btn-primary btn-sm" name="add_comment">
                                <i class="fas fa-paper-plane"></i> Post Reply
                              </button>
                              <button type="button" class="btn btn-outline-secondary btn-sm" 
                                      onclick="toggleReplyForm(<?= $comment['comment_id'] ?>)">
                                Cancel
                              </button>
                            </form>
                          </div>
                        
                        <!-- Replies -->
                        <?php 
                        $comment_replies = array_filter($replies, function($r) use ($comment) { 
                            return $r['parent_id'] == $comment['comment_id']; 
                        });
                        
                        if (!empty($comment_replies)): ?>
                          <div class="replies ml-4 mt-2">
                            <?php foreach ($comment_replies as $reply): ?>
                              <div class="comment-box mb-2" id="comment-<?= $reply['comment_id'] ?>">
                                <div class="comment-header">
                                  <img src="<?= !empty($reply['picture']) ? '../dist/img/employees/' . $reply['picture'] : '../dist/img/avatar5.png' ?>" 
                                      class="comment-user-img" 
                                      alt="<?= htmlspecialchars($reply['commenter_name']) ?>">
                                  <div class="comment-user-info">
                                    <span class="comment-user-name"><?= htmlspecialchars($reply['commenter_name']) ?></span>
                                    <span class="comment-time"><?= date('M d, Y H:i', strtotime($reply['created_at'])) ?></span>
                                  </div>
                                </div>
                                <div class="comment-content">
                                  <?= nl2br(htmlspecialchars($reply['comment'])) ?>
                                </div>
                                <div class="comment-actions">
                                  <a href="#" class="comment-action like-btn <?= isset($reply['user_liked']) && $reply['user_liked'] ? 'liked' : '' ?>" 
                                  onclick="likeComment(<?= $reply['comment_id'] ?>, <?= (int)$document_details['doc_id'] ?>)">
                                    <i class="fas fa-thumbs-up"></i> Like
                                    <?php if (isset($reply['like_count']) && $reply['like_count'] > 0): ?>
                                      <span class="like-count"><?= $reply['like_count'] ?></span>
                                    <?php endif; ?>
                                  </a>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  
                  <?php if ($total_comments > count($comments) && !$show_all_comments): ?>
                    <div class="text-center mt-3">
                      <button id="show-more-comments" class="btn btn-outline-primary btn-sm" 
                              onclick="loadAllComments(<?= (int)$document_details['doc_id'] ?>)">
                        Show all <?= $total_comments ?> comments
                      </button>
                    </div>
                  <?php elseif ($show_all_comments): ?>
                    <div class="text-center mt-3">
                      <button id="show-less-comments" class="btn btn-outline-secondary btn-sm" 
                              onclick="loadLessComments(<?= (int)$document_details['doc_id'] ?>)">
                        Show Less
                      </button>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="alert alert-info">No comments yet. Be the first to comment!</div>
                <?php endif; ?>
                
                <!-- Main comment form -->
                <form method="POST" action="doctrack.php" class="mt-4">
                  <input type="hidden" name="doc_id" value="<?= (int)$document_details['doc_id'] ?>">
                  <div class="form-group">
                    <textarea class="form-control" id="comment" name="comment" rows="3" required 
                              maxlength="1000" placeholder="Write a comment..."></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary" name="add_comment">
                    <i class="fas fa-paper-plane"></i> Post Comment
                  </button>
                </form>
              </div>
            </div>
          </div>

        <?php else: ?>
          <div class="alert alert-danger">Document not found.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- Document History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="historyModalLabel">Document History</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="timeline">
          <?php if (!empty($history)): ?>
            <?php foreach ($history as $item): ?>
              <div class="history-item">
                <div class="d-flex">
                  <!-- Employee Picture -->
                    <div class="mr-3">
                        <?php 
                        $imagePath = !empty($comment['picture']) ? '../dist/img/employees/' . $comment['picture'] : '../dist/img/avatar5.png';
                        ?>
                        <img src="<?= $imagePath ?>" 
                            class="rounded-circle" width="50" height="50" 
                            alt="<?= htmlspecialchars($comment['commenter_name']) ?>">
                    </div>
                  
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <h6 class="mb-0 font-weight-bold"><?= htmlspecialchars($item['employee_name']) ?></h6>
                      <small class="text-muted"><?= date('M d, Y H:i', strtotime($item['created_at'])) ?></small>
                    </div>
                    
                    <div class="d-flex align-items-center mb-2">
                      <?php if ($item['action'] === 'updated'): ?>
                        <span class="badge badge-info mr-2"><i class="fas fa-edit"></i> Updated</span>
                      <?php elseif ($item['action'] === 'created'): ?>
                        <span class="badge badge-success mr-2"><i class="fas fa-plus"></i> Created</span>
                      <?php elseif ($item['action'] === 'commented'): ?>
                        <span class="badge badge-primary mr-2"><i class="fas fa-comment"></i> Commented</span>
                      <?php elseif ($item['action'] === 'deleted'): ?>
                        <span class="badge badge-danger mr-2"><i class="fas fa-trash"></i> Deleted</span>
                      <?php else: ?>
                        <span class="badge badge-secondary mr-2"><i class="fas fa-info-circle"></i> Action</span>
                      <?php endif; ?>
                    </div>
                    
                    <?php if ($item['details']): ?>
                      <div class="card bg-light">
                        <div class="card-body p-2">
                          <p class="mb-0"><?= htmlspecialchars($item['details']) ?></p>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-info">No history available for this document.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
    function viewDocument(doc_id) {
        window.location.href = 'doctrack.php?view_id=' + doc_id;
    }
    function editDocument(doc_id) {
        // Fetch document details via AJAX
        $.ajax({
            url: 'get_document.php',
            type: 'GET',
            data: { doc_id: doc_id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#edit_doc_id').val(response.data.doc_id);
                    $('#edit_title').val(response.data.title);
                    $('#edit_type_id').val(response.data.type_id);
                    
                    // Show current file info
                    var fileName = response.data.file_path.split('/').pop();
                    var fileSize = (response.data.file_size / (1024 * 1024)).toFixed(2); // Convert to MB
                    $('#current_file_name').text(fileName);
                    $('#current_file_size').text('(' + fileSize + ' MB)');
                    
                    // Set the current file path as a data attribute
                    $('#editDocumentForm').data('current-file', response.data.file_path);
                    
                    $('#editDocumentModal').modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to fetch document details'
                });
            }
        });
    }
    $(document).ready(function() {
        initTransferSection();
        $('.section-transfer-item').on('click', function(e) {
            // Don't toggle if clicking on the checkbox directly
            if ($(e.target).is('input[type="checkbox"]')) return;
            
            const checkbox = $(this).find('input[type="checkbox"]');
            if (checkbox.length) {
                checkbox.prop('checked', !checkbox.prop('checked'));
                $(this).toggleClass('bg-light', checkbox.prop('checked'));
            }
        });
        // Initialize DataTables after DOM is ready
        var table = $('#dataTable-1').DataTable({
            autoWidth: true,
            "lengthMenu": [
                [16, 32, 64, -1],
                [16, 32, 64, "All"]
            ],
            "order": [[5, "desc"]] // Default sort by date uploaded
        });
        var table = $('#dataTable-2').DataTable({
            autoWidth: true,
            "lengthMenu": [
                [16, 32, 64, -1],
                [16, 32, 64, "All"]
            ],
            "order": [[5, "desc"]] // Default sort by date uploaded
        });
        // Show view modal if view_id is in URL
        <?php if (isset($_GET['view_id'])): ?>
            $('#viewDocumentModal').modal('show');
        <?php endif; ?>
        
        // Update custom file input label
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });
    });
    function viewPDF(filePath, title) {
        // Extract just the filename for display
        var fileName = filePath.split('/').pop();
        
        Swal.fire({
            title: title,
            html: `
                <div class="text-center">
                    <iframe src="${filePath}" width="100%" height="500px" style="border: none;"></iframe>
                    <div class="mt-3">
                        <a href="${filePath}" class="btn btn-primary" download="${fileName}">
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                    </div>
                </div>
            `,
            width: '80%',
            showCloseButton: true,
            showConfirmButton: false,
            customClass: {
                popup: 'pdf-viewer-swal'
            }
        });
    }

    
    function confirmDelete(doc_id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'doctrack.php?delete_id=' + doc_id;
            }
        });
    }
    
    
    function filterByType(type_id) {
        // Implement filtering by type
        window.location.href = 'doctrack.php?type_id=' + type_id;
    }
    
    function clearFilters() {
        window.location.href = 'doctrack.php';
    }
    function showHistoryModal() {
        $('#historyModal').modal('show');
    }
     // New function to toggle reply form
    function toggleReplyForm(commentId) {
        const form = document.getElementById('reply-form-' + commentId);
        if (form.style.display === 'block') {
            form.style.display = 'none';
        } else {
            // Hide any other open reply forms
            document.querySelectorAll('.reply-form').forEach(f => {
                f.style.display = 'none';
            });
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
    function likeComment(commentId, docId) {
        const likeBtn = $(`#comment-${commentId} .like-btn`);
        const likeCountSpan = likeBtn.find('.like-count');
        
        // Add loading state
        likeBtn.prop('disabled', true);
        likeBtn.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'doctrack.php',
            type: 'POST',
            data: {
                like_comment: 1,
                comment_id: commentId,
                doc_id: docId
            },
            dataType: 'json',
            success: function(response) {
                // Restore button state
                likeBtn.prop('disabled', false);
                
                if (response.success) {
                    // Toggle liked class
                    likeBtn.toggleClass('liked');
                    likeBtn.html('<i class="fas fa-thumbs-up"></i> Like');
                    
                    // Update like count
                    if (response.like_count > 0) {
                        if (likeCountSpan.length) {
                            likeCountSpan.text(response.like_count);
                        } else {
                            likeBtn.append(`<span class="like-count">${response.like_count}</span>`);
                        }
                    } else {
                        likeCountSpan.remove();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to process like'
                    });
                }
            },
            error: function() {
                likeBtn.prop('disabled', false);
                likeBtn.html('<i class="fas fa-thumbs-up"></i> Like');
                if (likeCountSpan.length) {
                    likeBtn.append(likeCountSpan);
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to connect to server'
                });
            }
        });
    }
    // Enhanced loadAllComments function with like support
    function loadAllComments(doc_id) {
        window.location.href = 'doctrack.php?view_id=' + doc_id + '&all_comments=1';
        $.ajax({
            url: 'get_comments.php',
            type: 'GET',
            data: { 
                doc_id: doc_id, 
                all: 1,
                current_emp_id: <?= getEmployeeId($conn, $_SESSION['user_id']) ?? 0 ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear existing comments
                    $('#comments-container').empty();
                    
                    // Add all comments with proper threading
                    response.comments.forEach(function(comment) {
                        if (!comment.parent_id) {
                            // Parent comment
                            var commentHtml = `
                                <div class="comment-box" id="comment-${comment.comment_id}">
                                    <div class="comment-header">
                                        <img src="${comment.picture ? '../dist/img/employees/' + comment.picture : '../dist/img/avatar5.png'}" 
                                            class="comment-user-img" 
                                            alt="${comment.commenter_name}">
                                        <div class="comment-user-info">
                                            <span class="comment-user-name">${comment.commenter_name}</span>
                                            <span class="comment-time">${comment.created_at}</span>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        ${comment.comment.replace(/\n/g, '<br>')}
                                    </div>
                                    <div class="comment-actions">
                                        <a href="#" class="comment-action" onclick="toggleReplyForm(${comment.comment_id})">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                        <a href="#" class="comment-action ${comment.user_liked ? 'liked' : ''}" 
                                           id="like-btn-${comment.comment_id}"
                                           onclick="likeComment(${comment.comment_id}, ${doc_id})">
                                            <i class="fas fa-thumbs-up"></i> Like
                                            <span class="like-count" id="like-count-${comment.comment_id}">
                                                ${comment.like_count > 0 ? comment.like_count : ''}
                                            </span>
                                        </a>
                                    </div>
                                    
                                    <div class="reply-form" id="reply-form-${comment.comment_id}">
                                        <form method="POST" action="doctrack.php">
                                            <input type="hidden" name="doc_id" value="${doc_id}">
                                            <input type="hidden" name="parent_id" value="${comment.comment_id}">
                                            <div class="form-group">
                                                <textarea class="form-control" name="comment" rows="2" required 
                                                          placeholder="Write a reply..." maxlength="1000"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm" name="add_comment">
                                                <i class="fas fa-paper-plane"></i> Post Reply
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" 
                                                    onclick="toggleReplyForm(${comment.comment_id})">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>
                            `;
                            
                            // Add replies if any
                            const replies = response.comments.filter(r => r.parent_id == comment.comment_id);
                            if (replies.length > 0) {
                                commentHtml += `<div class="replies">`;
                                replies.forEach(reply => {
                                    commentHtml += `
                                        <div class="comment-box" id="comment-${reply.comment_id}">
                                            <div class="comment-header">
                                                <img src="${reply.picture ? '../dist/img/employees/' + reply.picture : '../dist/img/avatar5.png'}" 
                                                    class="comment-user-img" 
                                                    alt="${reply.commenter_name}">
                                                <div class="comment-user-info">
                                                    <span class="comment-user-name">${reply.commenter_name}</span>
                                                    <span class="comment-time">${reply.created_at}</span>
                                                </div>
                                            </div>
                                            <div class="comment-content">
                                                ${reply.comment.replace(/\n/g, '<br>')}
                                            </div>
                                            <div class="comment-actions">
                                                <a href="#" class="comment-action ${reply.user_liked ? 'liked' : ''}" 
                                                   id="like-btn-${reply.comment_id}"
                                                   onclick="likeComment(${reply.comment_id}, ${doc_id})">
                                                    <i class="fas fa-thumbs-up"></i> Like
                                                    <span class="like-count" id="like-count-${reply.comment_id}">
                                                        ${reply.like_count > 0 ? reply.like_count : ''}
                                                    </span>
                                                </a>
                                            </div>
                                        </div>
                                    `;
                                });
                                commentHtml += `</div>`;
                            }
                            
                            commentHtml += `</div>`;
                            $('#comments-container').append(commentHtml);
                        }
                    });
                    
                    // Hide the "Show more" button
                    $('#show-more-comments').hide();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load comments'
                });
            }
        });
    }
    function loadLessComments(doc_id) {
        window.location.href = 'doctrack.php?view_id=' + doc_id;
    }
    function initTransferSection() {
        // Highlight selected sections
        $('.section-transfer-item').on('click', function(e) {
            if ($(e.target).is('input[type="checkbox"]')) return;
            
            const checkbox = $(this).find('input[type="checkbox"]');
            if (checkbox.length) {
                checkbox.prop('checked', !checkbox.prop('checked'));
                $(this).toggleClass('bg-light', checkbox.prop('checked'));
            }
        });

        // Validate before submitting
    
    }
    // Show SweetAlert for success/error messages
    <?php if (isset($_SESSION['swal_success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= addslashes($_SESSION['swal_success']) ?>'
        });
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['swal_error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($_SESSION['swal_error']) ?>'
        });
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>
    function transferAgain(transferId, docId) {
        // Show confirmation dialog
        Swal.fire({
            title: 'Transfer Again?',
            text: "This will create a new transfer request to the same section/unit",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, transfer again'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX request to transfer again
                $.ajax({
                    url: 'transfer_again.php',
                    type: 'POST',
                    data: {
                        transfer_id: transferId,
                        doc_id: docId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message
                            }).then(() => {
                                // Refresh the page to show the new transfer
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to process transfer'
                        });
                    }
                });
            }
        });
    }
    $(document).ready(function() {
        // Initialize Select2 for section/unit selection
        $('.select2-multiple').select2({
            placeholder: "Select sections and/or units",
            allowClear: true,
            width: 'resolve',
            templateResult: formatSelection,
            templateSelection: formatSelection
        });
        
        // Function to format the display of options
        function formatSelection(state) {
            if (!state.id) {
                return state.text;
            }
            
            // For sections
            if (state.id.startsWith('section_')) {
                return $('<span><i class="fas fa-building mr-2"></i>' + state.text + '</span>');
            }
            // For units
            else if (state.id.startsWith('unit_')) {
                return $('<span><i class="fas fa-cube mr-2"></i>' + state.text + '</span>');
            }
            
            return state.text;
        }
        
         // Handle section checkbox - check/uncheck all units when section is checked
        $('.section-checkbox').change(function() {
            const sectionItem = $(this).closest('.section-transfer-item');
            const isChecked = $(this).is(':checked');
            
            // Check/uncheck all units in this section
            sectionItem.find('.unit-checkbox').prop('checked', isChecked);
            
            // Highlight the section
            if (isChecked) {
                sectionItem.addClass('border-primary');
            } else {
                sectionItem.removeClass('border-primary');
            }
        });
        
        // Handle unit checkbox - if any unit is unchecked, uncheck the section
        $('.unit-checkbox').change(function() {
            const sectionItem = $(this).closest('.section-transfer-item');
            const sectionCheckbox = sectionItem.find('.section-checkbox');
            const anyUnitUnchecked = sectionItem.find('.unit-checkbox:not(:checked)').length > 0;
            
            if (anyUnitUnchecked) {
                sectionCheckbox.prop('checked', false);
                sectionItem.removeClass('border-primary');
            }
        });
        $('#imo_office').change(function() {
            if ($(this).is(':checked')) {
                $('#managers-section').show();
            } else {
                $('#managers-section').hide();
                $('.manager-checkbox').prop('checked', false);
            }
        });
        $('#sendToSectionForm').submit(function(e) {
            if ($('input[name="section_ids[]"]:checked').length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select at least one section or unit to send the document to'
                });
            }
        });

        function forwardToSection(transferId, docId) {
            Swal.fire({
                title: 'Forward Document',
                html: `
                    <div class="form-group">
                        <label for="forwardSection">Select Section/Unit to Forward To:</label>
                        <select class="form-control" id="forwardSection" style="width: 100%;">
                            <?php foreach ($sections as $section): ?>
                                <optgroup label="<?= htmlspecialchars($section['section_name']) ?>">
                                    <option value="section_<?= (int)$section['section_id'] ?>">
                                        Entire Section
                                    </option>
                                    <?php 
                                    $units_query = $conn->prepare("SELECT * FROM unit_section WHERE section_id = ?");
                                    $units_query->bind_param("i", $section['section_id']);
                                    $units_query->execute();
                                    $units = $units_query->get_result()->fetch_all(MYSQLI_ASSOC);
                                    foreach ($units as $unit): ?>
                                        <option value="unit_<?= (int)$unit['unit_id'] ?>">
                                            <?= htmlspecialchars($unit['unit_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Forward',
                preConfirm: () => {
                    const section = document.getElementById('forwardSection').value;
                    if (!section) {
                        Swal.showValidationMessage('Please select a section or unit');
                        return false;
                    }
                    return { section: section };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX request to forward the document
                    $.ajax({
                        url: 'forward_document.php',
                        type: 'POST',
                        data: {
                            transfer_id: transferId,
                            doc_id: docId,
                            section_id: result.value.section
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: response.message
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to forward document'
                            });
                        }
                    });
                }
            });
        }

    });
    function showRemarks(remarks) {
        Swal.fire({
            title: 'Transfer Remarks',
            html: `<div class="text-left p-3">${remarks}</div>`,
            confirmButtonText: 'Close',
            width: '600px'
        });
    }
    function generateDocumentTemplate(doc_id) {
        window.location.href = 'generate_template.php?doc_id=' + doc_id;
    }
</script>
</body>
</html>