<?php
require_once 'config/database.php';
require_once 'controllers/EmployeeController.php';
require_once 'controllers/AdminController.php';

// Database connection
$database = new Database();
$db = $database->getConnection();

// Initialize controllers
$employeeController = new EmployeeController($db);
$adminController = new AdminController($db);

// Routing
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$emp_id = isset($_GET['emp_id']) ? $_GET['emp_id'] : null;

switch ($action) {
    case 'create':
        $employeeController->create();
        break;
    case 'store':
        $employeeController->store();
        break;
    case 'assign':
        if ($emp_id) {
            $adminController->assign($emp_id);
        } else {
            header("Location: emp.list.php?error=invalid_id");
        }
        break;
    case 'update_assignment':
        if ($emp_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminController->updateAssignment($emp_id);
        } else {
            header("Location: emp.list.php?error=invalid_request");
        }
        break;
    case 'index':
    default:
        $employeeController->index();
        break;
}
?>