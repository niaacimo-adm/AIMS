<?php
session_start();
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!isset($_GET['doc_id'])) {
        throw new Exception("Document ID not provided");
    }
    
    $doc_id = (int)$_GET['doc_id'];
    $current_emp_id = isset($_GET['current_emp_id']) ? (int)$_GET['current_emp_id'] : 0;
    
    $query = "
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
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_emp_id, $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}