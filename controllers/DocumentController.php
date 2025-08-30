<?php
require_once '../models/DocumentModel.php';

class DocumentController {
    private $model;

    public function __construct() {
        $this->model = new DocumentModel();
    }

    public function index() {
        $filter = [];
        
        if (isset($_GET['status_id'])) {
            $filter['status_id'] = $_GET['status_id'];
        }
        
        if (isset($_GET['type_id'])) {
            $filter['type_id'] = $_GET['type_id'];
        }
        
        $documents = $this->model->getAllDocuments($filter);
        $types = $this->model->getDocumentTypes();
        $statuses = $this->model->getDocumentStatuses();
        
        include '../views/documents/index.php';
    }

    public function view($doc_id) {
        $document = $this->model->getDocumentById($doc_id);
        $comments = $this->model->getDocumentComments($doc_id);
        $history = $this->model->getDocumentHistory($doc_id);
        
        include '../views/documents/view.php';
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => $_POST['title'],
                'type_id' => $_POST['type_id'],
                'status_id' => $_POST['status_id'],
                'owner_id' => $_SESSION['emp_id']
            ];
            
            $doc_id = $this->model->createDocument($data, $_FILES['document_file']);
            
            if ($doc_id) {
                $_SESSION['success'] = 'Document created successfully!';
                header("Location: view.php?id=$doc_id");
                exit;
            } else {
                $_SESSION['error'] = 'Failed to create document.';
            }
        }
        
        $types = $this->model->getDocumentTypes();
        $statuses = $this->model->getDocumentStatuses();
        
        include '../views/documents/create.php';
    }

    public function edit($doc_id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => $_POST['title'],
                'type_id' => $_POST['type_id'],
                'status_id' => $_POST['status_id']
            ];
            
            $file = isset($_FILES['document_file']) ? $_FILES['document_file'] : null;
            
            if ($this->model->updateDocument($doc_id, $data, $file)) {
                $_SESSION['success'] = 'Document updated successfully!';
                header("Location: view.php?id=$doc_id");
                exit;
            } else {
                $_SESSION['error'] = 'Failed to update document.';
            }
        }
        
        $document = $this->model->getDocumentById($doc_id);
        $types = $this->model->getDocumentTypes();
        $statuses = $this->model->getDocumentStatuses();
        
        include '../views/documents/edit.php';
    }

    public function delete($doc_id) {
        if ($this->model->deleteDocument($doc_id)) {
            $_SESSION['success'] = 'Document deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete document.';
        }
        
        header("Location: index.php");
        exit;
    }

    public function addComment($doc_id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment']);
            
            if (!empty($comment)) {
                if ($this->model->addComment($doc_id, $_SESSION['emp_id'], $comment)) {
                    $_SESSION['success'] = 'Comment added successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to add comment.';
                }
            }
            
            header("Location: view.php?id=$doc_id");
            exit;
        }
    }

    public function download($doc_id) {
        $document = $this->model->getDocumentById($doc_id);
        
        if ($document && file_exists($document['file_path'])) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($document['file_path']).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($document['file_path']));
            readfile($document['file_path']);
            exit;
        } else {
            $_SESSION['error'] = 'File not found.';
            header("Location: view.php?id=$doc_id");
            exit;
        }
    }
}
?>