<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

class LeaveFunctions {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get employee's section head
     */
    public function getSectionHead($emp_id) {
        $query = "SELECT s.head_emp_id, e.first_name, e.last_name, e.email 
                 FROM section s 
                 JOIN employee emp ON emp.section_id = s.section_id 
                 JOIN employee e ON e.emp_id = s.head_emp_id 
                 WHERE emp.emp_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Calculate working days between two dates (excluding weekends)
     */
    public function calculateWorkingDays($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); // Include end date
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        $workingDays = 0;
        foreach ($period as $date) {
            $dayOfWeek = $date->format('N'); // 1 (Mon) to 7 (Sun)
            if ($dayOfWeek < 6) { // Monday to Friday
                $workingDays++;
            }
        }
        
        return $workingDays;
    }
    
    /**
     * Get employee's leave balance
     */
    public function getLeaveBalance($emp_id, $leave_type_id, $year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        $query = "SELECT balance FROM leave_balances 
                 WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iii", $emp_id, $leave_type_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['balance'];
        }
        
        // Return default balance if not found
        return $this->getDefaultLeaveBalance($leave_type_id);
    }
    
    /**
     * Get default leave balance based on leave type
     */
    private function getDefaultLeaveBalance($leave_type_id) {
        $default_balances = [
            1 => 15,  // Vacation Leave
            2 => 5,   // Mandatory Leave
            3 => 15,  // Sick Leave
            4 => 105, // Maternity
            5 => 7,   // Paternity
            6 => 3,   // Special Privilege
            7 => 7,   // Solo Parent
            8 => 10,  // Study Leave
            9 => 10,  // VAWC
            10 => 30, // Rehabilitation
            11 => 2,  // Women
            12 => 5,  // Calamity
            13 => 0,  // Terminal
            14 => 30  // Adoption
        ];
        
        return $default_balances[$leave_type_id] ?? 0;
    }
    
    /**
     * Check if employee has pending leave during the period
     */
    public function hasOverlappingLeave($emp_id, $start_date, $end_date, $exclude_leave_id = null) {
        $query = "SELECT COUNT(*) as count FROM leave_requests 
                WHERE emp_id = ? 
                AND status IN ('pending', 'approved')
                AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) 
                    OR (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))";
        
        if ($exclude_leave_id) {
            $query .= " AND leave_id != ?";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($exclude_leave_id) {
            $stmt->bind_param("issssssi", $emp_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $exclude_leave_id);
        } else {
            $stmt->bind_param("issssss", $emp_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }
    
    /**
     * Submit leave request
     */
    public function submitLeaveRequest($data) {
        // Calculate working days
        $total_days = $this->calculateWorkingDays($data['start_date'], $data['end_date']);
        
        // Get section head
        $section_head = $this->getSectionHead($data['emp_id']);
        
        $query = "INSERT INTO leave_requests 
                (emp_id, leave_type_id, start_date, end_date, total_days, particulars, remarks, 
                medical_certificate, section_head_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iississsi", 
            $data['emp_id'], 
            $data['leave_type_id'], 
            $data['start_date'], 
            $data['end_date'], 
            $total_days,
            $data['particulars'],
            $data['remarks'],
            $data['medical_certificate'],
            $section_head['head_emp_id']
        );
        
        if ($stmt->execute()) {
            $leave_id = $this->db->insert_id;
            
            // Send notification to section head
            $this->sendNotificationToSectionHead($section_head, $data['emp_id'], $leave_id);
            
            // Also send notification to admin users
            $this->sendNotificationToAdminUsers($data['emp_id'], $leave_id);
            
            return $leave_id;
        }
        
        return false;
    }

    /**
     * Send notification to admin users
     */
    private function sendNotificationToAdminUsers($emp_id, $leave_id) {
        $employee_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
        $stmt = $this->db->prepare($employee_query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
        $message = "New leave request #{$leave_id} from {$employee_name} needs approval.";
        
        $link = "leave_approval.php?leave_id={$leave_id}";

        // Get all admin users
        $admin_query = "SELECT u.employee_id 
                    FROM users u 
                    JOIN user_roles ur ON u.role_id = ur.id 
                    WHERE ur.name = 'Administrator' OR ur.id = 1";
        $result = $this->db->query($admin_query);
        
        while ($admin = $result->fetch_assoc()) {
            $notification_query = "INSERT INTO admin_notifications 
                                (admin_emp_id, message, type, link, is_read, created_at) 
                                VALUES (?, ?, 'leave_request', ?, 0, NOW())";
            
            $stmt = $this->db->prepare($notification_query);
            $stmt->bind_param("iss", $admin['employee_id'], $message, $link);
            $stmt->execute();
        }
        
        return true;
    }

    /**
     * Send notification to section head
     */
    private function sendNotificationToSectionHead($section_head, $emp_id, $leave_id) {
        $employee_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
        $stmt = $this->db->prepare($employee_query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
        $message = "Leave request #{$leave_id} from {$employee_name} needs your approval.";
        
        $link = "leave_approval.php?leave_id={$leave_id}";
        
        if (!$section_head || !isset($section_head['head_emp_id'])) {
            error_log("Section head not found for employee: $emp_id");
            return false;
        }
        
        $notification_query = "INSERT INTO admin_notifications 
                            (admin_emp_id, message, type, link, is_read, created_at) 
                            VALUES (?, ?, 'leave_request', ?, 0, NOW())";
        
        $stmt = $this->db->prepare($notification_query);
        $stmt->bind_param("iss", $section_head['head_emp_id'], $message, $link);
        
        return $stmt->execute();
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($user_emp_id) {
        $query = "SELECT * FROM admin_notifications 
                WHERE admin_emp_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }

    /**
     * Get unread notification count
     */
    public function getUnreadNotificationCount($user_emp_id) {
        $query = "SELECT COUNT(*) as unread_count FROM admin_notifications 
                WHERE admin_emp_id = ? AND is_read = 0";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['unread_count'] ?? 0;
    }
    
    /**
     * Get leave requests for employee
     */
    public function getEmployeeLeaveRequests($emp_id, $status = null) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                 sh.first_name as sh_first_name, sh.last_name as sh_last_name,
                 a.first_name as approver_first_name, a.last_name as approver_last_name
                 FROM leave_requests lr
                 JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                 LEFT JOIN employee sh ON lr.section_head_id = sh.emp_id
                 LEFT JOIN employee a ON lr.approved_by = a.emp_id
                 WHERE lr.emp_id = ?";
        
        if ($status) {
            $query .= " AND lr.status = ?";
        }
        
        $query .= " ORDER BY lr.applied_date DESC";
        
        $stmt = $this->db->prepare($query);
        
        if ($status) {
            $stmt->bind_param("is", $emp_id, $status);
        } else {
            $stmt->bind_param("i", $emp_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        return $leaves;
    }
    
    /**
     * Get leave requests for section head approval - FIXED VERSION
     */
    public function getPendingSectionHeadApprovals($section_head_id) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name, e.position_id,
                p.position_name, e.section_id
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                LEFT JOIN position p ON e.position_id = p.position_id
                WHERE lr.section_head_id = ? 
                AND lr.status = 'pending'
                ORDER BY lr.applied_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $section_head_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        error_log("Found " . count($leaves) . " pending approvals for section head: " . $section_head_id);
        
        return $leaves;
    }
    
    /**
     * Section head approves/rejects leave
     */
    public function sectionHeadAction($leave_id, $section_head_id, $action, $remarks = '') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        $query = "UPDATE leave_requests 
                SET status = ?,
                    approved_by = ?,
                    section_head_remarks = ?,
                    section_head_date = NOW()
                WHERE leave_id = ? AND section_head_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sisii", $status, $section_head_id, $remarks, $leave_id, $section_head_id);
        
        if ($stmt->execute() && $action == 'approve') {
            // Automatically deduct leave balance upon approval
            $this->updateLeaveBalanceOnApproval($leave_id);
        }
        
        return $stmt->affected_rows > 0;
    }
    
    /**
     * Update leave balance after approval
     */
    private function updateLeaveBalance($leave_id) {
        // Get leave details
        $query = "SELECT emp_id, leave_type_id, total_days, YEAR(start_date) as year 
                FROM leave_requests WHERE leave_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $leave = $result->fetch_assoc();
        
        if (!$leave) {
            error_log("Leave request not found for ID: $leave_id");
            return false;
        }
        
        // Check if balance record exists
        $check_query = "SELECT * FROM leave_balances 
                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bind_param("iii", $leave['emp_id'], $leave['leave_type_id'], $leave['year']);
        $check_stmt->execute();
        $balance_result = $check_stmt->get_result();
        
        if ($balance_result->num_rows > 0) {
            // Update existing balance
            $update_query = "UPDATE leave_balances 
                            SET used_credits = used_credits + ?, 
                                balance = total_credits - (used_credits + ?)
                            WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bind_param("ddiii", 
                $leave['total_days'], $leave['total_days'],
                $leave['emp_id'], $leave['leave_type_id'], $leave['year']
            );
            return $update_stmt->execute();
        } else {
            // Insert new balance record
            $default_balance = $this->getDefaultLeaveBalance($leave['leave_type_id']);
            $insert_query = "INSERT INTO leave_balances 
                            (emp_id, leave_type_id, year, total_credits, used_credits, balance) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $this->db->prepare($insert_query);
            $used_credits = $leave['total_days'];
            $balance = $default_balance - $used_credits;
            $insert_stmt->bind_param("iiiidd", 
                $leave['emp_id'], $leave['leave_type_id'], $leave['year'],
                $default_balance, $used_credits, $balance
            );
            return $insert_stmt->execute();
        }
    }
    
    /**
     * Get all leave types
     */
    public function getLeaveTypes() {
        $query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name";
        $result = $this->db->query($query);
        
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        
        return $types;
    }

    /**
     * Get section head recent actions
     */
    public function getSectionHeadRecentActions($section_head_id, $limit = 10) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name,
                CASE 
                    WHEN lr.section_head_approved = 1 THEN 'approved'
                    WHEN lr.status = 'rejected' THEN 'rejected'
                    ELSE 'pending'
                END as action_status
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                WHERE lr.section_head_id = ?
                ORDER BY lr.section_head_date DESC, lr.applied_date DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $section_head_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $actions = [];
        while ($row = $result->fetch_assoc()) {
            $actions[] = $row;
        }
        
        return $actions;
    }

    /**
     * Get pending leave requests for manager approval
     */
    public function getPendingManagerApprovals($manager_id) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name, e.position_id,
                p.position_name, s.section_name,
                sh.first_name as sh_first_name, sh.last_name as sh_last_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                LEFT JOIN position p ON e.position_id = p.position_id
                LEFT JOIN section s ON e.section_id = s.section_id
                LEFT JOIN employee sh ON lr.section_head_id = sh.emp_id
                WHERE lr.manager_id = ? 
                AND lr.section_head_approved = 1
                AND lr.manager_approved IS NULL
                AND lr.status = 'pending'
                ORDER BY lr.applied_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $manager_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        return $leaves;
    }

    /**
     * Get pending leave requests for admin approval
     */
    public function getPendingAdminApprovals() {
        // Admin sees all pending requests
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name, e.position_id,
                p.position_name, s.section_name,
                sh.first_name as sh_first_name, sh.last_name as sh_last_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                LEFT JOIN position p ON e.position_id = p.position_id
                LEFT JOIN section s ON e.section_id = s.section_id
                LEFT JOIN employee sh ON lr.section_head_id = sh.emp_id
                WHERE lr.status = 'pending'
                ORDER BY lr.applied_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaves = [];
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
        
        error_log("Admin pending approvals count: " . count($leaves));
        
        return $leaves;
    }

    /**
     * Manager approves/rejects leave
     */
    public function managerAction($leave_id, $manager_id, $action, $remarks = '') {
        $query = "UPDATE leave_requests 
                SET manager_approved = ?, 
                    manager_remarks = ?,
                    manager_date = NOW()
                WHERE leave_id = ? AND manager_id = ?";
        
        $approved = ($action == 'approve') ? 1 : 0;
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("isii", $approved, $remarks, $leave_id, $manager_id);
        
        return $stmt->execute();
    }

    /**
     * Admin approves/rejects leave
     */
    public function adminAction($leave_id, $admin_id, $action, $remarks = '') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        $query = "UPDATE leave_requests 
                SET status = ?,
                    approved_by = ?,
                    admin_remarks = ?
                WHERE leave_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sisi", $status, $admin_id, $remarks, $leave_id);
        
        if ($stmt->execute() && $action == 'approve') {
            // Automatically deduct leave balance upon approval
            $this->updateLeaveBalanceOnApproval($leave_id);
        }
        
        return $stmt->affected_rows > 0;
    }

    /**
     * Get manager recent actions
     */
    public function getManagerRecentActions($manager_id, $limit = 10) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name,
                CASE 
                    WHEN lr.manager_approved = 1 THEN 'approved'
                    WHEN lr.status = 'rejected' THEN 'rejected'
                    ELSE 'pending'
                END as action_status
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                WHERE lr.manager_id = ?
                ORDER BY lr.manager_date DESC, lr.applied_date DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $manager_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $actions = [];
        while ($row = $result->fetch_assoc()) {
            $actions[] = $row;
        }
        
        return $actions;
    }

    /**
     * Get admin recent actions
     */
    public function getAdminRecentActions($limit = 10) {
        $query = "SELECT lr.*, lt.leave_name, lt.leave_code,
                e.first_name, e.last_name,
                CASE 
                    WHEN lr.admin_approved = 1 THEN 'approved'
                    WHEN lr.status = 'rejected' THEN 'rejected'
                    ELSE 'pending'
                END as action_status
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN employee e ON lr.emp_id = e.emp_id
                WHERE lr.admin_approved IS NOT NULL
                ORDER BY lr.admin_date DESC, lr.applied_date DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $actions = [];
        while ($row = $result->fetch_assoc()) {
            $actions[] = $row;
        }
        
        return $actions;
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notification_id, $user_emp_id) {
        $query = "UPDATE admin_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE notification_id = ? AND admin_emp_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $notification_id, $user_emp_id);
        return $stmt->execute();
    }

    /**
     * Get notification by ID
     */
    public function getNotificationById($notification_id) {
        $query = "SELECT * FROM admin_notifications WHERE notification_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

      public function getAdminNotificationCount() {
        $query = "SELECT COUNT(*) as unread_count FROM admin_notifications 
                WHERE is_read = 0";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['unread_count'] ?? 0;
    }

    /**
     * Get all notifications for admin users
     */
    public function getAdminNotifications($limit = 10) {
        $query = "SELECT * FROM admin_notifications 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }

    /**
     * Automatically update leave balance when leave is approved
     */
    public function updateLeaveBalanceOnApproval($leave_id) {
        // Get leave details
        $query = "SELECT emp_id, leave_type_id, total_days, YEAR(start_date) as year 
                FROM leave_requests WHERE leave_id = ? AND status = 'approved'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $leave = $result->fetch_assoc();
        
        if (!$leave) {
            error_log("Approved leave request not found for ID: $leave_id");
            return false;
        }
        
        return $this->deductLeaveBalance(
            $leave['emp_id'], 
            $leave['leave_type_id'], 
            $leave['total_days'], 
            $leave['year']
        );
    }

    /**
     * Deduct leave balance for an employee
     */
    private function deductLeaveBalance($emp_id, $leave_type_id, $days, $year) {
        // Check if balance record exists
        $check_query = "SELECT * FROM leave_balances 
                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bind_param("iii", $emp_id, $leave_type_id, $year);
        $check_stmt->execute();
        $balance_result = $check_stmt->get_result();
        
        if ($balance_result->num_rows > 0) {
            // Update existing balance
            $update_query = "UPDATE leave_balances 
                            SET used_credits = used_credits + ?, 
                                balance = total_credits - (used_credits + ?)
                            WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bind_param("ddiii", $days, $days, $emp_id, $leave_type_id, $year);
            return $update_stmt->execute();
        } else {
            // Insert new balance record
            $default_balance = $this->getDefaultLeaveBalance($leave_type_id);
            $insert_query = "INSERT INTO leave_balances 
                            (emp_id, leave_type_id, year, total_credits, used_credits, balance) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $this->db->prepare($insert_query);
            $used_credits = $days;
            $balance = $default_balance - $used_credits;
            $insert_stmt->bind_param("iiiidd", 
                $emp_id, $leave_type_id, $year,
                $default_balance, $used_credits, $balance
            );
            return $insert_stmt->execute();
        }
    }

    /**
     * Manually update leave balance for all employees (annual reset or manual adjustment)
     */
    public function manuallyUpdateAllLeaveBalances($year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        // FIXED: Use correct column name for active employees
        $employees_query = "SELECT emp_id FROM employee WHERE employment_status_id = 1";
        $employees_result = $this->db->query($employees_query);
        
        $updated_count = 0;
        $leave_types = $this->getLeaveTypes();
        
        while ($employee = $employees_result->fetch_assoc()) {
            foreach ($leave_types as $leave_type) {
                if ($this->initializeEmployeeLeaveBalance($employee['emp_id'], $leave_type['leave_type_id'], $year)) {
                    $updated_count++;
                }
            }
        }
        
        return $updated_count;
    }

    /**
     * Initialize or reset leave balance for an employee
     */
    private function initializeEmployeeLeaveBalance($emp_id, $leave_type_id, $year) {
        $default_balance = $this->getDefaultLeaveBalance($leave_type_id);
        
        // Check if record exists
        $check_query = "SELECT * FROM leave_balances 
                        WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bind_param("iii", $emp_id, $leave_type_id, $year);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record - reset used credits but keep total credits
            $update_query = "UPDATE leave_balances 
                            SET used_credits = 0, 
                                balance = total_credits,
                                updated_at = NOW()
                            WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bind_param("iii", $emp_id, $leave_type_id, $year);
            return $update_stmt->execute();
        } else {
            // Insert new record
            $insert_query = "INSERT INTO leave_balances 
                            (emp_id, leave_type_id, year, total_credits, used_credits, balance) 
                            VALUES (?, ?, ?, ?, 0, ?)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bind_param("iiiid", $emp_id, $leave_type_id, $year, $default_balance, $default_balance);
            return $insert_stmt->execute();
        }
    }

    /**
     * Get leave balance summary for an employee
     */
    public function getEmployeeLeaveBalanceSummary($emp_id, $year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        $query = "SELECT lb.*, lt.leave_name, lt.leave_code 
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lb.emp_id = ? AND lb.year = ?
                ORDER BY lt.leave_name";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $emp_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $balances = [];
        while ($row = $result->fetch_assoc()) {
            $balances[] = $row;
        }
        
        return $balances;
    }

    /**
     * Manual adjustment of leave balance (for HR/admin use)
     */
    public function manuallyAdjustLeaveBalance($emp_id, $leave_type_id, $new_balance, $year = null, $remarks = '') {
        if ($year === null) {
            $year = date('Y');
        }
        
        // Validate new balance
        if ($new_balance < 0) {
            throw new Exception("Leave balance cannot be negative");
        }
        
        // Check if record exists
        $check_query = "SELECT * FROM leave_balances 
                        WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bind_param("iii", $emp_id, $leave_type_id, $year);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record - FIXED: Use 'updated_at' instead of 'last_updated'
            $update_query = "UPDATE leave_balances 
                            SET total_credits = ?,
                                balance = ?,
                                updated_at = NOW()
                            WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bind_param("ddiii", $new_balance, $new_balance, $emp_id, $leave_type_id, $year);
            $success = $update_stmt->execute();
        } else {
            // Insert new record
            $insert_query = "INSERT INTO leave_balances 
                            (emp_id, leave_type_id, year, total_credits, used_credits, balance) 
                            VALUES (?, ?, ?, ?, 0, ?)";
            $insert_stmt = $this->db->prepare($insert_query);
            $insert_stmt->bind_param("iiiid", $emp_id, $leave_type_id, $year, $new_balance, $new_balance);
            $success = $insert_stmt->execute();
        }
        
        // Log the manual adjustment
        if ($success) {
            $this->logBalanceAdjustment($emp_id, $leave_type_id, $new_balance, $year, $remarks);
        }
        
        return $success;
    }

    /**
     * Log balance adjustments for audit trail
     */
    private function logBalanceAdjustment($emp_id, $leave_type_id, $new_balance, $year, $remarks) {
        $query = "INSERT INTO leave_balance_logs 
                (emp_id, leave_type_id, year, new_balance, remarks, adjusted_by, adjusted_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $adjusted_by = $_SESSION['emp_id'] ?? 0; // Current user's emp_id
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiisii", $emp_id, $leave_type_id, $year, $new_balance, $remarks, $adjusted_by);
        return $stmt->execute();
    }

    /**
     * Annual leave balance reset (to be run at the beginning of each year)
     */
    public function annualLeaveBalanceReset($year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        // FIXED: Use correct column name for active employees
        $employees_query = "SELECT emp_id FROM employee WHERE employment_status_id = 1";
        $employees_result = $this->db->query($employees_query);
        
        $reset_count = 0;
        $leave_types = $this->getLeaveTypes();
        
        while ($employee = $employees_result->fetch_assoc()) {
            foreach ($leave_types as $leave_type) {
                $default_balance = $this->getDefaultLeaveBalance($leave_type['leave_type_id']);
                
                // Check if record exists for this year
                $check_query = "SELECT * FROM leave_balances 
                                WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
                $check_stmt = $this->db->prepare($check_query);
                $check_stmt->bind_param("iii", $employee['emp_id'], $leave_type['leave_type_id'], $year);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing record - FIXED: Use 'updated_at' instead of 'last_updated'
                    $update_query = "UPDATE leave_balances 
                                    SET total_credits = ?,
                                        used_credits = 0,
                                        balance = ?,
                                        updated_at = NOW()
                                    WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
                    $update_stmt = $this->db->prepare($update_query);
                    $update_stmt->bind_param("ddiii", $default_balance, $default_balance, 
                                        $employee['emp_id'], $leave_type['leave_type_id'], $year);
                    if ($update_stmt->execute()) {
                        $reset_count++;
                    }
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO leave_balances 
                                    (emp_id, leave_type_id, year, total_credits, used_credits, balance) 
                                    VALUES (?, ?, ?, ?, 0, ?)";
                    $insert_stmt = $this->db->prepare($insert_query);
                    $insert_stmt->bind_param("iiiid", $employee['emp_id'], $leave_type['leave_type_id'], 
                                        $year, $default_balance, $default_balance);
                    if ($insert_stmt->execute()) {
                        $reset_count++;
                    }
                }
            }
        }
        
        return $reset_count;
    }
    /**
     * Format date for leave form display
     */
    public function formatDateForForm($date) {
        return date('F j, Y', strtotime($date));
    }

    /**
     * Get leave type display name for forms
     */
    public function getLeaveTypeDisplayName($leave_code) {
        $display_names = [
            'VL' => 'VACATION LEAVE',
            'MFL' => 'MANDATORY LEAVE',
            'SL' => 'SICK LEAVE',
            'MATL' => 'MATERNITY LEAVE',
            'PATL' => 'PATERNITY LEAVE',
            'SPL' => 'SPECIAL PRIVILEGE LEAVE',
            'SOLO' => 'SOLO PARENT LEAVE',
            'STUL' => 'STUDY LEAVE',
            'VAWC' => '10-DAY VAWC LEAVE',
            'REHAB' => 'REHABILITATION PRIVILEGE',
            'WOMEN' => 'SPECIAL LEAVE BENEFITS FOR WOMEN',
            'CALAMITY' => 'SPECIAL EMERGENCY (CALAMITY) LEAVE',
            'TERMINAL' => 'TERMINAL LEAVE',
            'ADOPT' => 'ADOPTION LEAVE'
        ];
        
        return $display_names[$leave_code] ?? $leave_code;
    }
}
?>