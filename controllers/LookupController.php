<?php
require_once '../models/LookupModel.php';

class LookupController {
    private $model;
    private $type;

    public function __construct($db, $type) {
        $this->model = new LookupModel($db, $type . 's'); // Pluralize table name
        $this->type = $type;
    }

    public function index() {
        $items = $this->model->getAll();
        include '../views/index.php';
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'];
            if ($this->model->create($name)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => ucfirst($this->type) . ' created successfully!'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to create ' . $this->type];
            }
            header("Location: index.php?type=" . $this->type);
            exit();
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            if ($this->model->update($id, $name)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => ucfirst($this->type) . ' updated successfully!'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to update ' . $this->type];
            }
            header("Location: index.php?type=" . $this->type);
            exit();
        }
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            if ($this->model->delete($id)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => ucfirst($this->type) . ' deleted successfully!'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to delete ' . $this->type];
            }
            header("Location: index.php?type=" . $this->type);
            exit();
        }
    }
}
?>