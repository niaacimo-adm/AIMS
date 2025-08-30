<?php
require_once 'models/EmployeeModel.php';
require_once 'models/LookupModel.php';

class AdminController {
    private $employeeModel;
    private $lookupModel;

    public function __construct($db) {
        $this->employeeModel = new EmployeeModel($db);
        $this->lookupModel = new LookupModel($db);
    }

    public function assign($emp_id) {
        $employee = $this->employeeModel->getEmployeeById($emp_id);
        if (!$employee) {
            header("Location: emp.list.php?error=notfound");
            exit;
        }

        // Get lookup data for dropdowns
        $employmentStatuses = $this->lookupModel->getAllEmploymentStatus();
        $appointmentStatuses = $this->lookupModel->getAllAppointmentStatus();
        $offices = $this->lookupModel->getAllOffices();
        $sections = $this->lookupModel->getAllSections();
        $positions = $this->lookupModel->getAllPositions();

        require_once '../views/emp.assign.php';
    }

    public function updateAssignment($emp_id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'employment_status_id' => $_POST['employment_status_id'],
                'appointment_status_id' => $_POST['appointment_status_id'],
                'section_id' => $_POST['section_id'],
                'office_id' => $_POST['office_id'],
                'position_id' => $_POST['position_id']
            ];

            if ($this->employeeModel->updateEmployeeAssignment($emp_id, $data)) {
                header("Location: emp.list.php?success=assignment_updated");
            } else {
                header("Location: emp.assign.php?emp_id=$emp_id&error=1");
            }
        }
    }
}
?>