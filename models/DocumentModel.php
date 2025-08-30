<?php
require_once '../config/database.php';

class DocumentModel {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Get all documents
    public function getAllDocuments($filter = []) {
        $query = "SELECT d.*, dt.type_name,
                 CONCAT(e.first_name, ' ', e.last_name) AS owner_name
                 FROM documents d
                 JOIN document_types dt ON d.type_id = dt.type_id
                 JOIN employee e ON d.owner_id = e.emp_id";
        
        $conditions = [];
        $params = [];
        
        if (!empty($filter['type_id'])) {
            $conditions[] = "d.type_id = ?";
            $params[] = $filter['type_id'];
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY d.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get document by ID
    public function getDocumentById($doc_id) {
        $query = "SELECT d.*, dt.type_name,
                 CONCAT(e.first_name, ' ', e.last_name) AS owner_name
                 FROM documents d
                 JOIN document_types dt ON d.type_id = dt.type_id
                 JOIN employee e ON d.owner_id = e.emp_id
                 WHERE d.doc_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    // Create new document
    public function createDocument($data, $file) {
        // Generate document number
        $doc_number = $this->generateDocumentNumber($data['type_id']);
        
        // Handle file upload
        $uploadDir = '../uploads/documents/';
        $fileName = time() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return false;
        }
        
        // Generate QR code
        require_once '../libs/phpqrcode/qrlib.php';
        $qrDir = '../uploads/qrcodes/';
        $qrFile = 'qr_' . time() . '.png';
        $qrPath = $qrDir . $qrFile;
        
        QRcode::png($doc_number, $qrPath, QR_ECLEVEL_L, 10);
        
        // Insert document
        $query = "INSERT INTO documents (doc_number, title, type_id, owner_id, file_path, qr_code)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssiiiss", $doc_number, $data['title'], $data['type_id'], $data['owner_id'], $filePath, $qrPath);
        
        if ($stmt->execute()) {
            $doc_id = $this->db->insert_id;
            
            // Add to history
            $this->addHistory($doc_id, $_SESSION['user_id'], 'created', 'Document created');
            
            return $doc_id;
        }
        
        return false;
    }

    // Update document
    public function updateDocument($doc_id, $data, $file = null) {
        $document = $this->getDocumentById($doc_id);
        
        $filePath = $document['file_path'];
        
        if ($file && $file['error'] == UPLOAD_ERR_OK) {
            // Delete old file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Upload new file
            $uploadDir = '../uploads/documents/';
            $fileName = time() . '_' . basename($file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return false;
            }
        }
        
        $query = "UPDATE documents SET title = ?, type_id = ?, file_path = ?
                  WHERE doc_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("siisi", $data['title'], $data['type_id'],
                          $filePath, $doc_id);
        
        if ($stmt->execute()) {
            // Add to history
            $this->addHistory($doc_id, $_SESSION['user_id'], 'updated', 'Document updated');
            return true;
        }
        
        return false;
    }

    // Delete document
    public function deleteDocument($doc_id) {
        $document = $this->getDocumentById($doc_id);
        
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        if (file_exists($document['qr_code'])) {
            unlink($document['qr_code']);
        }
        
        // Delete comments first
        $this->db->query("DELETE FROM document_comments WHERE doc_id = $doc_id");
        // Delete history
        $this->db->query("DELETE FROM document_history WHERE doc_id = $doc_id");
        
        $query = "DELETE FROM documents WHERE doc_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $doc_id);
        
        return $stmt->execute();
    }

    // Get document comments
    public function getDocumentComments($doc_id) {
        $query = "SELECT dc.*, CONCAT(e.first_name, ' ', e.last_name) AS commenter_name
                 FROM document_comments dc
                 JOIN employee e ON dc.emp_id = e.emp_id
                 WHERE dc.doc_id = ?
                 ORDER BY dc.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Add comment to document
    public function addComment($doc_id, $emp_id, $comment) {
        $query = "INSERT INTO document_comments (doc_id, emp_id, comment)
                  VALUES (?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iis", $doc_id, $emp_id, $comment);
        
        if ($stmt->execute()) {
            // Add to history
            $this->addHistory($doc_id, $emp_id, 'commented', 'Comment added');
            return true;
        }
        
        return false;
    }

    // Get document history
    public function getDocumentHistory($doc_id) {
        $query = "SELECT dh.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
                 FROM document_history dh
                 JOIN employee e ON dh.emp_id = e.emp_id
                 WHERE dh.doc_id = ?
                 ORDER BY dh.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Add to history
    private function addHistory($doc_id, $emp_id, $action, $details = null) {
        $query = "INSERT INTO document_history (doc_id, emp_id, action, details)
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiss", $doc_id, $emp_id, $action, $details);
        $stmt->execute();
    }

    // Generate document number
    private function generateDocumentNumber($type_id) {
        $prefix = '';
        $type = $this->db->query("SELECT type_name FROM document_types WHERE type_id = $type_id")->fetch_assoc();
        
        switch(strtoupper($type['type_name'])) {
            case 'POLICY': $prefix = 'POL'; break;
            case 'PROCEDURE': $prefix = 'PRO'; break;
            case 'FORM': $prefix = 'FRM'; break;
            case 'REPORT': $prefix = 'RPT'; break;
            default: $prefix = 'DOC';
        }
        
        $year = date('Y');
        $count = $this->db->query("SELECT COUNT(*) as count FROM documents WHERE type_id = $type_id")->fetch_assoc()['count'] + 1;
        
        return $prefix . '-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    // Get all document types
    public function getDocumentTypes() {
        $query = "SELECT * FROM document_types ORDER BY type_name";
        $result = $this->db->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

}
?>