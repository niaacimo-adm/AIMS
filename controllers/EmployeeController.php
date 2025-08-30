<?php
require_once 'models/EmployeeModel.php';
require_once 'models/LookupModel.php';

class EmployeeController {
    private $employeeModel;
    private $lookupModel;

    public function __construct($db) {
        $this->employeeModel = new EmployeeModel($db);
        $this->lookupModel = new LookupModel($db);
    }

    public function create() {
        // Get lookup data for dropdowns
        $employmentStatuses = $this->lookupModel->getAllEmploymentStatus();
        $appointmentStatuses = $this->lookupModel->getAllAppointmentStatus();
        $offices = $this->lookupModel->getAllOffices();
        $sections = $this->lookupModel->getAllSections();
        $positions = $this->lookupModel->getAllPositions();

        // Load the view
        require_once 'views/emp.create.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle file upload for picture
            $picture = '';
            if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
                $targetDir = "../dist/img/employees/";
                $targetFile = $targetDir . basename($_FILES["picture"]["name"]);
                move_uploaded_file($_FILES["picture"]["tmp_name"], $targetFile);
                $picture = "../dist/img/employees/" . basename($_FILES["picture"]["name"]);
            }

            $data = [
                'picture' => $picture,
                'id_number' => $_POST['id_number'],
                'first_name' => $_POST['first_name'],
                'middle_name' => $_POST['middle_name'],
                'last_name' => $_POST['last_name'],
                'ext_name' => $_POST['ext_name'],
                'gender' => $_POST['gender'],
                'address' => $_POST['address'],
                'bday' => $_POST['bday'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phone_number'],
                'employment_status_id' => $_POST['employment_status_id'],
                'appointment_status_id' => $_POST['appointment_status_id'],
                'section_id' => $_POST['section_id'],
                'office_id' => $_POST['office_id'],
                'position_id' => $_POST['position_id']
            ];

            if ($this->employeeModel->createEmployee($data)) {
                header("Location: emp.list.php?success=1");
            } else {
                header("Location: emp.create.php?error=1");
            }
        }
    }

    public function index() {
        $employees = $this->employeeModel->getAllEmployees();
        require_once 'views/emp.list.php';
    }
}
?>