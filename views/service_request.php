<?php
    session_start();
    $is_admin = ($_SESSION['role_id'] ?? 0) == 1;
    if (isset($_GET['clear_conflicts'])) {
        unset($_SESSION['conflicts']);
        unset($_SESSION['request_data']);
        // Clear any conflict-related GET parameters
        if (isset($_GET['conflict'])) {
            header("Location: service_request.php");
        } else {
            header("Location: service_request.php");
        }
        exit();
    }
    require_once '../config/database.php';
    require '../vendor/autoload.php';

    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    error_log("Current requests in database: " . print_r($db->query("SELECT request_no, date_of_travel, time_departure, time_return FROM service_requests")->fetch_all(MYSQLI_ASSOC), true));

    function hasScheduleConflicts($db, $request_id) {
        $query = "SELECT date_of_travel, date_of_travel_end, time_departure, time_return, vehicle_id, driver_emp_id, status 
                FROM service_requests WHERE request_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request) {
            return false;
        }
        
        // If the request is completed, it should not have any conflicts
        if ($request['status'] === 'completed') {
            return false;
        }
        
        // Use date_of_travel_end if available, otherwise use date_of_travel
        $date_end = $request['date_of_travel_end'] ?? $request['date_of_travel'];
        
        // Fetch approved passengers
        $passenger_query = "SELECT emp_id FROM service_request_passengers WHERE request_id = ? AND approved = 1";
        $passenger_stmt = $db->prepare($passenger_query);
        $passenger_stmt->bind_param("i", $request_id);
        $passenger_stmt->execute();
        $passengers = array_column($passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'emp_id');
        
        // Check for conflicts with date range
        $conflicts = checkScheduleConflicts($db, $request['date_of_travel'], $date_end, 
                                        $request['time_departure'], $request['time_return'], 
                                        $request['vehicle_id'], $request['driver_emp_id'], 
                                        $passengers, $request_id, true);
        
        return !empty($conflicts);
    }

    function getRequestDetails($db, $request_id, $is_admin) {
        $query = "SELECT sr.*, 
                CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                CONCAT(drv.first_name, ' ', drv.last_name) AS driver_name,
                v.property_no, v.plate_no, v.model,
                o.office_name
                FROM service_requests sr
                JOIN employee req ON sr.requesting_emp_id = req.emp_id
                LEFT JOIN employee sup ON sr.supervisor_emp_id = sup.emp_id
                LEFT JOIN employee drv ON sr.driver_emp_id = drv.emp_id
                LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
                LEFT JOIN office o ON req.office_id = o.office_id
                WHERE sr.request_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request) {
            return "<p class='text-danger'>Request not found.</p>";
        }
        
        // Check if this request has conflicts - ensure this runs for all statuses
        $has_conflicts = hasScheduleConflicts($db, $request_id);
        
        // Get the actual conflict details
        $conflict_details = '';
        if ($has_conflicts) {
            // Fetch the request details to pass to checkScheduleConflicts
            $query = "SELECT date_of_travel, date_of_travel_end, time_departure, time_return, 
                    vehicle_id, driver_emp_id, status 
                    FROM service_requests WHERE request_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request_details = $stmt->get_result()->fetch_assoc();
            
            if ($request_details) {
                // Use date_of_travel_end if available, otherwise use date_of_travel
                $date_end = $request_details['date_of_travel_end'] ?? $request_details['date_of_travel'];
                
                // Fetch approved passengers
                $passenger_query = "SELECT emp_id FROM service_request_passengers 
                                WHERE request_id = ? AND approved = 1";
                $passenger_stmt = $db->prepare($passenger_query);
                $passenger_stmt->bind_param("i", $request_id);
                $passenger_stmt->execute();
                $passengers = array_column($passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'emp_id');
                
                // Get the actual conflicts
                $conflicts = checkScheduleConflicts($db, $request_details['date_of_travel'], $date_end, 
                                                $request_details['time_departure'], $request_details['time_return'], 
                                                $request_details['vehicle_id'], $request_details['driver_emp_id'], 
                                                $passengers, $request_id, true);
                
                if (!empty($conflicts)) {
                    $conflict_details = "<div class='alert alert-danger mt-3'><h6>Conflict Details:</h6><ul>";
                    foreach ($conflicts as $conflict) {
                        $conflict_details .= "<li>{$conflict['message']}</li>";
                    }
                    $conflict_details .= "</ul></div>";
                }
            }
        }
        
        // Fetch passengers with approval status
        $passenger_query = "SELECT p.emp_id, CONCAT(e.first_name, ' ', e.last_name) AS passenger_name, 
                        p.approved, CONCAT(apr.first_name, ' ', apr.last_name) AS approved_by_name,
                        p.approved_at, p.rejected, p.rejected_by, p.rejected_at,
                        CONCAT(rej.first_name, ' ', rej.last_name) AS rejected_by_name
                        FROM service_request_passengers p
                        JOIN employee e ON p.emp_id = e.emp_id
                        LEFT JOIN employee apr ON p.approved_by = apr.emp_id
                        LEFT JOIN employee rej ON p.rejected_by = rej.emp_id
                        WHERE p.request_id = ?";
        $passenger_stmt = $db->prepare($passenger_query);
        $passenger_stmt->bind_param("i", $request_id);
        $passenger_stmt->execute();
        $passengers = $passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $has_pending_passengers = false;
        $pending_passenger_count = 0;
        
        foreach ($passengers as $passenger) {
            if (!$passenger['approved'] && !$passenger['rejected']) {
                $has_pending_passengers = true;
                $pending_passenger_count++;
            }
        }
        $approve_all_button = '';
        if ($has_pending_passengers && ($_SESSION['emp_id'] == $request['supervisor_emp_id'] || $is_admin)) {
            $approve_all_button = "
            <div class='mt-3'>
                <form method='POST' action='service_request.php' class='d-inline'>
                    <input type='hidden' name='request_id' value='{$request_id}'>
                    <button type='submit' name='approve_all_passengers' class='btn btn-success btn-sm'>
                        <i class='fas fa-check-double'></i> Approve All Passengers ({$pending_passenger_count} pending)
                    </button>
                </form>
            </div>";
        }
        $passenger_list = '';
        if (count($passengers)) {
            foreach ($passengers as $passenger) {
                $status = '';
                if ($passenger['approved']) {
                    $status = "<span class='badge badge-success'>Approved by {$passenger['approved_by_name']} at " . 
                        date('m/d/Y h:i A', strtotime($passenger['approved_at'])) . "</span>";
                } elseif ($passenger['rejected']) {
                    $status = "<span class='badge badge-danger'>Rejected by {$passenger['rejected_by_name']} at " . 
                        date('m/d/Y h:i A', strtotime($passenger['rejected_at'])) . "</span>";
                } else {
                    $status = "<span class='badge badge-warning'>Pending Approval</span>";
                }
                
                // Add approve/reject buttons for pending approvals (for supervisors/admins)
                $action_buttons = '';
                if (!$passenger['approved'] && !$passenger['rejected'] && ($_SESSION['emp_id'] == $request['supervisor_emp_id'] || $is_admin)) {
                    $action_buttons = " 
                    <button class='btn btn-success btn-sm approve-passenger' 
                        data-request-id='{$request_id}' 
                        data-emp-id='{$passenger['emp_id']}' 
                        data-passenger-name='{$passenger['passenger_name']}'
                        title='Approve Passenger'>
                        <i class='fas fa-check'></i>
                    </button>
                    <button class='btn btn-danger btn-sm reject-passenger' 
                        data-request-id='{$request_id}' 
                        data-emp-id='{$passenger['emp_id']}' 
                        data-passenger-name='{$passenger['passenger_name']}'
                        title='Reject Passenger'>
                        <i class='fas fa-times'></i>
                    </button>";
                }
                
                $passenger_list .= "<li>{$passenger['passenger_name']} - {$status}{$action_buttons}</li>";
            }
            $passenger_list = "<ul>{$passenger_list}</ul>";
        } else {
            $passenger_list = 'None';
        }
        
        // Format the details - make sure the conflict alert is shown with details
        $details = "
            <div style='text-align: left;'>
                <h4>Transport Request #{$request['request_no']}</h4>
                " . ($has_conflicts ? "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> This request has schedule conflicts!</div>" . $conflict_details : "") . "
                <p><strong>Requester:</strong> {$request['requester_name']}</p>
                <p><strong>Office:</strong> " . ($request['office_name'] ?? 'N/A') . "</p>
                <p><strong>Supervisor:</strong> " . ($request['supervisor_name'] ?? 'N/A') . "</p>
                <p><strong>Date Requested:</strong> " . date('m/d/Y', strtotime($request['date_requested'])) . "</p>
                <p><strong>Date of Travel:</strong> " . date('m/d/Y', strtotime($request['date_of_travel'])) . " - " . date('m/d/Y', strtotime($request['date_of_travel_end']))."</p>
                <p><strong>Time:</strong> " . date('h:i A', strtotime($request['time_departure'])) . " - " . date('h:i A', strtotime($request['time_return'])) . "</p>
                <p><strong>Destination:</strong> " . htmlspecialchars($request['destination']) . "</p>
                <p><strong>Purpose:</strong> " . htmlspecialchars($request['purpose']) . "</p>
                <p><strong>Vehicle:</strong> " . ($request['model'] ? "{$request['model']} ({$request['plate_no']})" : 'N/A') . "</p>
                <p><strong>Property No.:</strong> " . ($request['property_no'] ?? 'N/A') . "</p>
                <p><strong>Driver:</strong> " . ($request['driver_name'] ?? 'N/A') . "</p>
                <p><strong>Passengers:</strong> {$passenger_list}</p>
                    {$approve_all_button}
                <p><strong>Status:</strong> " . ucfirst($request['status']) . ($has_conflicts ? " <span class='badge badge-danger'>Conflict</span>" : "") . "</p>
                " . ($request['date_completed'] ? "<p><strong>Date Completed:</strong> " . date('m/d/Y h:i A', strtotime($request['date_completed'])) . "</p>" : "") . "
                <p><strong>Remarks:</strong> " . (empty($request['remarks']) ? 'None' : htmlspecialchars($request['remarks'])) . "</p>
            </div>";
            
        return $details;
    }
    function hasPendingPassengers($db, $request_id) {
        $query = "SELECT COUNT(*) as pending_count 
                FROM service_request_passengers 
                WHERE request_id = ? 
                AND approved = 0 
                AND (rejected = 0 OR rejected IS NULL)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['pending_count'] > 0;
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_request'])) {
            try {
                $db->begin_transaction();

                // Get form values
                $requesting_emp_id = $_POST['requesting_emp_id'] ?? $_SESSION['emp_id'];
                $date_of_travel = $_POST['date_of_travel_start']; // Use start date from range
                $date_of_travel_end = $_POST['date_of_travel_end']; // ADD THIS LINE
                $time_departure = $_POST['time_departure'];
                $time_return = $_POST['time_return'];
                $destination = $_POST['destination'];
                $purpose = $_POST['purpose'];
                $vehicle_id = $_POST['vehicle_id'];
                $driver_emp_id = $_POST['driver_emp_id'];
                $passengers = $_POST['passengers'] ?? [];

                // For new requests, status is pending, so skip conflict check unless explicitly requested
                $conflicts = checkScheduleConflicts($db, $date_of_travel, $date_of_travel_end, 
                                    $time_departure, $time_return, $vehicle_id, 
                                    $driver_emp_id, $passengers);

                if (!empty($conflicts)) {
                    $_SESSION['conflicts'] = $conflicts;
                    $_SESSION['request_data'] = $_POST;
                    $db->rollback();
                    header("Location: service_request.php?conflict=1");
                    exit();
                }

                // Generate request number and insert as before
                $year = date('Y');
                $query = "SELECT request_no FROM service_requests WHERE request_no LIKE '%-$year' ORDER BY CAST(SUBSTRING(request_no, 1, 3) AS UNSIGNED) DESC LIMIT 1";
                $result = $db->query($query);
                $last_request_no = $result->fetch_assoc()['request_no'] ?? null;
                $sequence = 1;
                if ($last_request_no) {
                    $sequence = (int)substr($last_request_no, 0, 3) + 1;
                }
                $request_no = str_pad($sequence, 3, '0', STR_PAD_LEFT) . '-' . $year;

                $query = "INSERT INTO service_requests 
                    (request_no, requesting_emp_id, supervisor_emp_id, date_requested, date_of_travel, 
                    date_of_travel_end, time_departure, time_return, destination, purpose, vehicle_id, driver_emp_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                $current_date = date('Y-m-d');

                $stmt = $db->prepare($query);
                $stmt->bind_param("siisssssssii", 
                    $request_no,
                    $requesting_emp_id,
                    $_POST['supervisor_emp_id'],
                    $current_date,
                    $date_of_travel,
                    $date_of_travel_end, // Add this
                    $time_departure,
                    $time_return,
                    $destination,
                    $purpose,
                    $vehicle_id,
                    $driver_emp_id
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error creating request: " . $stmt->error);
                }

                $request_id = $db->insert_id;

                if (!empty($_POST['passengers'])) {
                    $passenger_query = "INSERT INTO service_request_passengers (request_id, emp_id) VALUES (?, ?)";
                    $passenger_stmt = $db->prepare($passenger_query);

                    foreach ($_POST['passengers'] as $emp_id) {
                        $passenger_stmt->bind_param("ii", $request_id, $emp_id);
                        if (!$passenger_stmt->execute()) {
                            throw new Exception("Error adding passenger: " . $passenger_stmt->error);
                        }
                    }
                }

                $db->commit();

                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Transport request created successfully!'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
        }elseif (isset($_POST['update_request'])) {
            try {
                $db->begin_transaction();

                $requesting_emp_id = $_POST['requesting_emp_id'] ?? $edit_request['requesting_emp_id'];
                $date_of_travel = $_POST['date_of_travel_start'];
                $date_of_travel_end = $_POST['date_of_travel_end'];   
                $time_departure = $_POST['time_departure'];
                $time_return = $_POST['time_return'];
                $destination = $_POST['destination'];
                $purpose = $_POST['purpose'];
                $vehicle_id = $_POST['vehicle_id'];
                $driver_emp_id = $_POST['driver_emp_id'];
                $passengers = $_POST['passengers'] ?? [];
                $request_id = $_POST['request_id'];

                // Check if the request is approved
                $query = "SELECT status FROM service_requests WHERE request_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $current_status = $stmt->get_result()->fetch_assoc()['status'];

                // Only check conflicts if the request is approved
                $conflicts = [];
                if ($current_status === 'approved') {
                    $conflicts = checkScheduleConflicts($db, $date_of_travel, $date_of_travel_end, 
                                    $time_departure, $time_return, $vehicle_id, 
                                    $driver_emp_id, $passengers);
                }

                if (!empty($conflicts)) {
                    $_SESSION['conflicts'] = $conflicts;
                    $_SESSION['request_data'] = $_POST;
                    $db->rollback();
                    header("Location: service_request.php?conflict=1");
                    exit();
                }

                $query = "UPDATE service_requests SET 
                        requesting_emp_id = ?,
                        supervisor_emp_id = ?,
                        date_of_travel = ?,
                        date_of_travel_end = ?,
                        time_departure = ?,
                        time_return = ?,
                        destination = ?,
                        purpose = ?,
                        vehicle_id = ?,
                        driver_emp_id = ?
                        WHERE request_id = ?";

                $stmt = $db->prepare($query);
                $stmt->bind_param("iisssssssii", 
                    $requesting_emp_id,
                    $_POST['supervisor_emp_id'],
                    $date_of_travel,
                    $date_of_travel_end,
                    $time_departure,
                    $time_return,
                    $destination,
                    $purpose,
                    $vehicle_id,
                    $driver_emp_id,
                    $request_id
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error updating request: " . $stmt->error);
                }

                // Get currently approved passengers (these should not be modified)
                $approved_passengers_query = "SELECT emp_id FROM service_request_passengers 
                                            WHERE request_id = ? AND approved = 1";
                $approved_stmt = $db->prepare($approved_passengers_query);
                $approved_stmt->bind_param("i", $request_id);
                $approved_stmt->execute();
                $approved_passengers = array_column($approved_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'emp_id');

                // Get current passengers
                $current_passengers_query = "SELECT emp_id FROM service_request_passengers WHERE request_id = ?";
                $current_stmt = $db->prepare($current_passengers_query);
                $current_stmt->bind_param("i", $request_id);
                $current_stmt->execute();
                $current_passengers = array_column($current_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'emp_id');

                // Passengers to add (new ones that aren't approved)
                $passengers_to_add = array_diff($passengers, $current_passengers);
                
                // Passengers to remove (ones not in new list, not approved, and not in approved list)
                $passengers_to_remove = array_diff($current_passengers, $passengers);
                $passengers_to_remove = array_diff($passengers_to_remove, $approved_passengers); // Don't remove approved ones

                // Remove passengers that should be removed
                if (!empty($passengers_to_remove)) {
                    $placeholders = implode(',', array_fill(0, count($passengers_to_remove), '?'));
                    $delete_query = "DELETE FROM service_request_passengers 
                                WHERE request_id = ? AND emp_id IN ($placeholders) 
                                AND approved = 0 AND (rejected = 0 OR rejected IS NULL)";
                    $delete_stmt = $db->prepare($delete_query);
                    
                    $params = array_merge([$request_id], array_values($passengers_to_remove));
                    $types = str_repeat('i', count($params));
                    
                    $delete_stmt->bind_param($types, ...$params);
                    $delete_stmt->execute();
                }

                // Add new passengers (only if they're not approved already)
                if (!empty($passengers_to_add)) {
                    $passenger_query = "INSERT INTO service_request_passengers (request_id, emp_id) VALUES (?, ?)";
                    $passenger_stmt = $db->prepare($passenger_query);
                    
                    foreach ($passengers_to_add as $emp_id) {
                        // Only add if not already approved
                        if (!in_array($emp_id, $approved_passengers)) {
                            $passenger_stmt->bind_param("ii", $request_id, $emp_id);
                            if (!$passenger_stmt->execute()) {
                                throw new Exception("Error adding passenger: " . $passenger_stmt->error);
                            }
                        }
                    }
                }

                $db->commit();

                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Transport request updated successfully!'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
        } elseif (isset($_POST['delete_request'])) {
            try {
                $query = "DELETE FROM service_requests WHERE request_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $_POST['request_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Transport request deleted successfully!'
                    ];
                } else {
                    throw new Exception("Error deleting request: " . $stmt->error);
                }
            } catch (Exception $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
        } elseif (isset($_POST['approve_request'])) {
        try {
            $request_id = $_POST['request_id'];

            // Check if there are any pending passengers
            if (hasPendingPassengers($db, $request_id)) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot approve request. There are passengers still pending approval.'
                    ]);
                    exit();
                } else {
                    throw new Exception("Cannot approve request. There are passengers still pending approval.");
                }
            }

            // Fetch request details to check for conflicts - ADD date_of_travel_end
            $query = "SELECT date_of_travel, date_of_travel_end, time_departure, time_return, vehicle_id, driver_emp_id 
                        FROM service_requests WHERE request_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();

            // Use date_of_travel_end if available, otherwise use date_of_travel
            $date_end = $request['date_of_travel_end'] ?? $request['date_of_travel'];

            // Fetch approved passengers
            $passenger_query = "SELECT emp_id FROM service_request_passengers WHERE request_id = ? AND approved = 1";
            $passenger_stmt = $db->prepare($passenger_query);
            $passenger_stmt->bind_param("i", $request_id);
            $passenger_stmt->execute();
            $passengers = array_column($passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'emp_id');

            // Check for conflicts - FIXED: Pass both start and end dates
            $conflicts = checkScheduleConflicts($db, $request['date_of_travel'], $date_end, 
                                    $request['time_departure'], $request['time_return'], 
                                    $request['vehicle_id'], $request['driver_emp_id'], 
                                    $passengers, $request_id, true);

            if (!empty($conflicts)) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot approve request due to schedule conflicts.',
                        'conflicts' => $conflicts
                    ]);
                    exit();
                } else {
                    $_SESSION['conflicts'] = $conflicts;
                    $_SESSION['request_data'] = [
                        'request_id' => $request_id
                    ];
                    header("Location: service_request.php?conflict=1");
                    exit();
                }
            }

            // Proceed with approval
            $query = "UPDATE service_requests SET 
                        status = 'approved',
                        approved_by = ?,
                        approved_at = NOW()
                        WHERE request_id = ?";

            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $_SESSION['emp_id'], $request_id);

            if ($stmt->execute()) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transport request approved!'
                    ]);
                    exit();
                } else {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Transport request approved!'
                    ];
                }
            } else {
                throw new Exception("Error approving request: " . $stmt->error);
            }
        } catch (Exception $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit();
            } else {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            header("Location: service_request.php");
            exit();
        }

        } elseif (isset($_POST['reject_request'])) {
            try {
                $query = "UPDATE service_requests SET 
                        status = 'rejected',
                        approved_by = ?,
                        approved_at = NOW(),
                        remarks = ?
                        WHERE request_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param("isi", $_SESSION['emp_id'], $_POST['remarks'], $_POST['request_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Transport request rejected!'
                    ];
                } else {
                    throw new Exception("Error rejecting request: " . $stmt->error);
                }
            } catch (Exception $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
        } elseif (isset($_POST['complete_request'])) {
            try {
                $query = "UPDATE service_requests SET 
                    status = 'completed',
                    date_completed = NOW()
                    WHERE request_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $_POST['request_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Transport request marked as completed!'
                    ];
                } else {
                    throw new Exception("Error completing request: " . $stmt->error);
                }
            } catch (Exception $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
        } elseif (isset($_POST['print_request'])) {
            $request_id = $_POST['request_id'];
            generateRequestExcel($db, $request_id);
            exit();
        } elseif (isset($_POST['view_request'])) {
            $request_id = $_POST['request_id'];
            echo getRequestDetails($db, $request_id, $is_admin);
            exit();
        }
        
    }
    // Handle delete all requests (admin only)
    if (isset($_POST['delete_all_requests']) && $is_admin) {
        try {
            $db->begin_transaction();
            
            // First delete all passengers
            $delete_passengers = "DELETE FROM service_request_passengers";
            if (!$db->query($delete_passengers)) {
                throw new Exception("Error clearing passengers: " . $db->error);
            }
            
            // Then delete all requests
            $delete_requests = "DELETE FROM service_requests";
            if (!$db->query($delete_requests)) {
                throw new Exception("Error deleting requests: " . $db->error);
            }
            
            $db->commit();
            
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'All transport requests deleted successfully!'
            ];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: service_request.php");
        exit();
    }
    // Add reject passenger handler
    if (isset($_POST['reject_passenger'])) {
        try {
                $query = "UPDATE service_request_passengers SET 
                        rejected = 1,
                        rejected_by = ?,
                        rejected_at = NOW()
                        WHERE request_id = ? AND emp_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param("iii", $_SESSION['emp_id'], $_POST['request_id'], $_POST['emp_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Passenger rejected successfully!'
                    ];
                } else {
                    throw new Exception("Error rejecting passenger: " . $stmt->error);
                }
            } catch (Exception $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
    }
    if (isset($_POST['approve_passenger'])) {
        try {
                $query = "UPDATE service_request_passengers SET 
                        approved = 1,
                        approved_by = ?,
                        approved_at = NOW()
                        WHERE request_id = ? AND emp_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param("iii", $_SESSION['emp_id'], $_POST['request_id'], $_POST['emp_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Passenger approved successfully!'
                    ];
                } else {
                    throw new Exception("Error approving passenger: " . $stmt->error);
                }
            } catch (Exception $e) {
                $_SESSION['toast'] = [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            header("Location: service_request.php");
            exit();
    }

    if (isset($_POST['approve_all_passengers'])) {
        try {
            $request_id = $_POST['request_id'];
            
            $query = "UPDATE service_request_passengers SET 
                    approved = 1,
                    approved_by = ?,
                    approved_at = NOW()
                    WHERE request_id = ? 
                    AND approved = 0 
                    AND (rejected = 0 OR rejected IS NULL)";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $_SESSION['emp_id'], $request_id);
            
            if ($stmt->execute()) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'All pending passengers approved successfully!'
                ];
            } else {
                throw new Exception("Error approving all passengers: " . $stmt->error);
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }
        header("Location: service_request.php");
        exit();
    }

    function checkScheduleConflicts($db, $date_start, $date_end, $time_start, $time_end, $vehicle_id, $driver_id, $passengers, $exclude_request_id = null, $check_for_approval = false) {
        $conflicts = [];

        if ($exclude_request_id) {
            $query = "SELECT status FROM service_requests WHERE request_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $exclude_request_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            // If the excluded request is completed, no conflicts
            if ($result && $result['status'] === 'completed') {
                return $conflicts;
            }
        }

        error_log("Conflict check called with:");
        error_log("Date Start: $date_start, Date End: $date_end, Time Start: $time_start, Time End: $time_end");
        error_log("Vehicle: $vehicle_id, Driver: $driver_id");
        error_log("Passengers: " . print_r($passengers, true));
        error_log("Exclude Request ID: " . ($exclude_request_id ?? 'null'));
        error_log("Check for Approval: " . ($check_for_approval ? 'true' : 'false'));

        // Skip conflict checks for pending requests unless explicitly checking for approval
        if (!$check_for_approval) {
            // If the request is pending, don't check conflicts unless it's being approved
            if ($exclude_request_id) {
                $query = "SELECT status FROM service_requests WHERE request_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $exclude_request_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result && $result['status'] === 'pending') {
                    return $conflicts; // No conflicts for pending requests
                }
            }
        }

        // Validate time inputs
        if (empty($date_start) || empty($date_end) || empty($time_start) || empty($time_end)) {
            $conflicts[] = ['message' => "Date and time fields are required."];
            return $conflicts;
        }

        // Convert dates and times to datetime for comparison
        try {
            $start_datetime = new DateTime("$date_start $time_start");
            $end_datetime = new DateTime("$date_end $time_end");
        } catch (Exception $e) {
            $conflicts[] = ['message' => "Invalid date or time format."];
            return $conflicts;
        }

        // Ensure end datetime is after start datetime
        if ($end_datetime <= $start_datetime) {
            $conflicts[] = ['message' => "Invalid time range: Return date/time must be after departure date/time."];
            return $conflicts;
        }

        // Check if start date is in the past
        $current_date = new DateTime();
        $travel_start_date = new DateTime($date_start);
        if ($travel_start_date < $current_date->setTime(0, 0, 0)) {
            $conflicts[] = ['message' => "Cannot schedule travel for a past date."];
        }

        // Check vehicle, driver, and passenger conflicts with date ranges
        $vehicle_conflicts = checkVehicleConflicts($db, $vehicle_id, $date_start, $date_end, $time_start, $time_end, $exclude_request_id);
        $conflicts = array_merge($conflicts, $vehicle_conflicts);

        $driver_conflicts = checkDriverConflicts($db, $driver_id, $date_start, $date_end, $time_start, $time_end, $exclude_request_id);
        $conflicts = array_merge($conflicts, $driver_conflicts);

        $passenger_conflicts = checkPassengerConflicts($db, $passengers, $date_start, $date_end, $time_start, $time_end, $exclude_request_id);
        $conflicts = array_merge($conflicts, $passenger_conflicts);

        return $conflicts;
    }

    // Helper function for vehicle conflicts
    function checkVehicleConflicts($db, $vehicle_id, $date_start, $date_end, $time_start, $time_end, $exclude_request_id = null) {
        $conflicts = [];
        
        if (empty($vehicle_id)) {
            return $conflicts;
        }
        
        $vehicle_query = "SELECT sr.request_id, sr.request_no, sr.date_of_travel, 
                sr.date_of_travel_end, sr.time_departure, sr.time_return, v.model, v.plate_no,
                CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                sr.status
                FROM service_requests sr
                JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
                JOIN employee req ON sr.requesting_emp_id = req.emp_id
                WHERE sr.vehicle_id = ?
                AND sr.status = 'approved'  -- Only check approved requests for conflicts
                AND sr.status != 'completed'  -- ADD THIS LINE: Exclude completed requests
                AND (
                    -- New request starts during existing request
                    (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                    -- New request ends during existing request
                    (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                    -- New request completely contains existing request
                    (sr.date_of_travel >= ? AND sr.date_of_travel_end <= ?)
                )
                AND (
                    -- Time overlap check (only if dates overlap)
                    (sr.time_departure <= ? AND sr.time_return >= ?) OR
                    (sr.time_departure BETWEEN ? AND ?) OR
                    (sr.time_return BETWEEN ? AND ?) OR
                    (? BETWEEN sr.time_departure AND sr.time_return) OR
                    (? BETWEEN sr.time_departure AND sr.time_return)
                )";
        
        if ($exclude_request_id) {
            $vehicle_query .= " AND sr.request_id != ?";
        }
        
        $stmt = $db->prepare($vehicle_query);
        
        if ($exclude_request_id) {
            $stmt->bind_param("issssssssssssssi", $vehicle_id, 
                $date_start, $date_start,  // New request starts during existing
                $date_end, $date_end,      // New request ends during existing
                $date_start, $date_end,    // New request contains existing
                $time_end, $time_start,    // Time overlap checks
                $time_start, $time_end,
                $time_start, $time_end,
                $time_start, $time_end,
                $exclude_request_id);
        } else {
            $stmt->bind_param("issssssssssssss", $vehicle_id, 
                $date_start, $date_start,
                $date_end, $date_end,
                $date_start, $date_end,
                $time_end, $time_start,
                $time_start, $time_end,
                $time_start, $time_end,
                $time_start, $time_end);
        }
        
        $stmt->execute();
        $vehicle_conflicts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($vehicle_conflicts as $conflict) {
            $date_range = date('m/d/Y', strtotime($conflict['date_of_travel']));
            if ($conflict['date_of_travel_end'] && $conflict['date_of_travel_end'] != $conflict['date_of_travel']) {
                $date_range .= " - " . date('m/d/Y', strtotime($conflict['date_of_travel_end']));
            }
            
            $conflicts[] = [
                'type' => 'vehicle',
                'resource_id' => $vehicle_id,
                'message' => "Vehicle {$conflict['model']} ({$conflict['plate_no']}) is already booked for Request #{$conflict['request_no']} " .
                            "by {$conflict['requester_name']} on {$date_range} from " .
                            date('h:i A', strtotime($conflict['time_departure'])) . " to " .
                            date('h:i A', strtotime($conflict['time_return'])) . "."
            ];
        }
        
        return $conflicts;
    }

    // Helper function for driver conflicts
    function checkDriverConflicts($db, $driver_id, $date_start, $date_end, $time_start, $time_end, $exclude_request_id = null) {
        $conflicts = [];
        
        if (empty($driver_id)) {
            return $conflicts;
        }
        
        $driver_query = "SELECT sr.request_id, sr.request_no, sr.date_of_travel, 
                sr.date_of_travel_end, sr.time_departure, sr.time_return, 
                CONCAT(e.first_name, ' ', e.last_name) AS driver_name,
                CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                v.model as vehicle_model,
                sr.status
                FROM service_requests sr
                JOIN employee e ON sr.driver_emp_id = e.emp_id
                JOIN employee req ON sr.requesting_emp_id = req.emp_id
                LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
                WHERE sr.driver_emp_id = ?
                AND sr.status = 'approved'  -- Only check approved requests for conflicts
                AND sr.status != 'completed'  -- ADD THIS LINE: Exclude completed requests
                AND (
                    -- New request starts during existing request
                    (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                    -- New request ends during existing request
                    (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                    -- New request completely contains existing request
                    (sr.date_of_travel >= ? AND sr.date_of_travel_end <= ?)
                )
                AND (
                    -- Time overlap check (only if dates overlap)
                    (sr.time_departure <= ? AND sr.time_return >= ?) OR
                    (sr.time_departure BETWEEN ? AND ?) OR
                    (sr.time_return BETWEEN ? AND ?) OR
                    (? BETWEEN sr.time_departure AND sr.time_return) OR
                    (? BETWEEN sr.time_departure AND sr.time_return)
                )";
        
        if ($exclude_request_id) {
            $driver_query .= " AND sr.request_id != ?";
        }
        
        $stmt = $db->prepare($driver_query);
        
        if ($exclude_request_id) {
            $stmt->bind_param("issssssssssssssi", $driver_id, 
                $date_start, $date_start,
                $date_end, $date_end,
                $date_start, $date_end,
                $time_end, $time_start,
                $time_start, $time_end,
                $time_start, $time_end,
                $time_start, $time_end,
                $exclude_request_id);
        } else {
            $stmt->bind_param("issssssssssssss", $driver_id, 
                $date_start, $date_start,
                $date_end, $date_end,
                $date_start, $date_end,
                $time_end, $time_start,
                $time_start, $time_end,
                $time_start, $time_end,
                $time_start, $time_end);
        }
        
        $stmt->execute();
        $driver_conflicts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($driver_conflicts as $conflict) {
            $date_range = date('m/d/Y', strtotime($conflict['date_of_travel']));
            if ($conflict['date_of_travel_end'] && $conflict['date_of_travel_end'] != $conflict['date_of_travel']) {
                $date_range .= " - " . date('m/d/Y', strtotime($conflict['date_of_travel_end']));
            }
            
            $vehicle_info = $conflict['vehicle_model'] ? " with vehicle {$conflict['vehicle_model']}" : "";
            $conflicts[] = [
                'type' => 'driver',
                'resource_id' => $driver_id,
                'message' => "Driver {$conflict['driver_name']} is already assigned to Request #{$conflict['request_no']}" .
                            "{$vehicle_info} by {$conflict['requester_name']} on {$date_range} from " .
                            date('h:i A', strtotime($conflict['time_departure'])) . " to " .
                            date('h:i A', strtotime($conflict['time_return'])) . "."
            ];
        }
        
        return $conflicts;
    }

    // Helper function for passenger conflicts
    function checkPassengerConflicts($db, $passengers, $date_start, $date_end, $time_start, $time_end, $exclude_request_id = null) {
        $conflicts = [];
        
        if (empty($passengers)) {
            return $conflicts;
        }
        
        $placeholders = implode(',', array_fill(0, count($passengers), '?'));
        $types = str_repeat('i', count($passengers));
        
        $passenger_query = "SELECT sr.request_id, sr.request_no, sr.date_of_travel, 
                    sr.date_of_travel_end, sr.time_departure, sr.time_return, 
                    CONCAT(e.first_name, ' ', e.last_name) AS passenger_name,
                    CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                    v.model as vehicle_model,
                    srp.emp_id,
                    sr.status
                    FROM service_request_passengers srp
                    JOIN service_requests sr ON srp.request_id = sr.request_id
                    JOIN employee e ON srp.emp_id = e.emp_id
                    JOIN employee req ON sr.requesting_emp_id = req.emp_id
                    LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
                    WHERE srp.emp_id IN ($placeholders)
                    AND srp.approved = 1
                    AND sr.status = 'approved'  -- Only check approved requests for conflicts
                    AND sr.status != 'completed'  -- ADD THIS LINE: Exclude completed requests
                    AND (
                        -- New request starts during existing request
                        (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                        -- New request ends during existing request
                        (sr.date_of_travel <= ? AND sr.date_of_travel_end >= ?) OR
                        -- New request completely contains existing request
                        (sr.date_of_travel >= ? AND sr.date_of_travel_end <= ?)
                    )
                    AND (
                        -- Time overlap check (only if dates overlap)
                        (sr.time_departure <= ? AND sr.time_return >= ?) OR
                        (sr.time_departure BETWEEN ? AND ?) OR
                        (sr.time_return BETWEEN ? AND ?) OR
                        (? BETWEEN sr.time_departure AND sr.time_return) OR
                        (? BETWEEN sr.time_departure AND sr.time_return)
                    )";
        
        if ($exclude_request_id) {
            $passenger_query .= " AND sr.request_id != ?";
            $types .= 'ssssssssssssssi';
        } else {
            $types .= 'ssssssssssssss';
        }
        
        $stmt = $db->prepare($passenger_query);
        
        $params = array_merge($passengers, [
            $date_start, $date_start,  // New request starts during existing
            $date_end, $date_end,      // New request ends during existing
            $date_start, $date_end,    // New request contains existing
            $time_end, $time_start,    // Time overlap checks
            $time_start, $time_end,
            $time_start, $time_end,
            $time_start, $time_end
        ]);
        
        if ($exclude_request_id) {
            $params[] = $exclude_request_id;
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $passenger_conflicts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($passenger_conflicts as $conflict) {
            $date_range = date('m/d/Y', strtotime($conflict['date_of_travel']));
            if ($conflict['date_of_travel_end'] && $conflict['date_of_travel_end'] != $conflict['date_of_travel']) {
                $date_range .= " - " . date('m/d/Y', strtotime($conflict['date_of_travel_end']));
            }
            
            $vehicle_info = $conflict['vehicle_model'] ? " with vehicle {$conflict['vehicle_model']}" : "";
            $conflicts[] = [
                'type' => 'passenger',
                'resource_id' => $conflict['emp_id'],
                'message' => "Passenger {$conflict['passenger_name']} is already booked for Request #{$conflict['request_no']}" .
                            "{$vehicle_info} by {$conflict['requester_name']} on {$date_range} from " .
                            date('h:i A', strtotime($conflict['time_departure'])) . " to " .
                            date('h:i A', strtotime($conflict['time_return'])) . "."
            ];
        }
        
        return $conflicts;
    }

    // Add this function near the other conflict checking functions
    function checkTimeConflict($time_start1, $time_end1, $time_start2, $time_end2) {
        $start1 = strtotime($time_start1);
        $end1 = strtotime($time_end1);
        $start2 = strtotime($time_start2);
        $end2 = strtotime($time_end2);
        
        // Check if time ranges overlap
        return ($start1 < $end2 && $end1 > $start2);
    }

    function generateRequestExcel($db, $request_id) {
        try {
            $query = "SELECT sr.*, 
                CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                CONCAT(drv.first_name, ' ', drv.last_name) AS driver_name,
                v.property_no, v.plate_no, v.model,
                o.office_name
                FROM service_requests sr
                JOIN employee req ON sr.requesting_emp_id = req.emp_id
                LEFT JOIN employee sup ON sr.supervisor_emp_id = sup.emp_id
                LEFT JOIN employee drv ON sr.driver_emp_id = drv.emp_id
                LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
                LEFT JOIN office o ON req.office_id = o.office_id
                WHERE sr.request_id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            if (!$request) {
                throw new Exception("Request not found");
            }
            
            // Update: Only fetch approved passengers
            $passenger_query = "SELECT CONCAT(e.first_name, ' ', e.last_name) AS passenger_name 
                            FROM service_request_passengers p
                            JOIN employee e ON p.emp_id = e.emp_id
                            WHERE p.request_id = ? AND p.approved = 1 AND (p.rejected = 0 OR p.rejected IS NULL)";
            $passenger_stmt = $db->prepare($passenger_query);
            $passenger_stmt->bind_param("i", $request_id);
            $passenger_stmt->execute();
            $passengers = $passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("../public/templates/TRANSPORT-REQUEST1.xlsx");
            $sheet = $spreadsheet->getActiveSheet();
            
            $sheet->setCellValue('L9', $request['request_no']);
            $sheet->setCellValue('B9', date('m/d/Y', strtotime($request['date_requested'])));
            $sheet->setCellValue('B10', 'Date');
            $sheet->setCellValue('B15', strtoupper($request['requester_name']));
            $sheet->setCellValue('J13', date('m/d/Y', strtotime($request['date_of_travel'])));
            $sheet->setCellValue('J14', $request['destination']);
            $sheet->setCellValue('H15', $request['purpose']);
            $sheet->setCellValue('B20', strtoupper($request['supervisor_name'] ?? ''));
            
            $passenger_rows = ['B19', 'B20', 'B21', 'B22', 'B23', 'B24', 'B25', 'B26', 'B27', 'B28'];
            for ($i = 0; $i < min(10, count($passengers)); $i++) {
                $sheet->setCellValue($passenger_rows[$i], strtoupper($passengers[$i]['passenger_name']));
            }
            
            $sheet->setCellValue('J20', strtoupper($request['model'] ?? ''));
            $sheet->setCellValue('G27', $request['property_no'] ?? '');
            $sheet->setCellValue('J22', $request['plate_no'] ?? '');
            $sheet->setCellValue('J23', strtoupper($request['driver_name'] ?? ''));
            
            $sheet->getStyle('D40')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D42')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D43')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D42')->getFont()->setUnderline(true);
            
            $filename = "Transport_Request_" . $request['request_no'] . ".xlsx";
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');
            
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        } catch (Exception $e) {
            error_log($e->getMessage());
            echo "Error generating Excel: " . $e->getMessage();
        }
    }

    // Fetch all service requests
    $query = "SELECT sr.*, 
            CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
            CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
            CONCAT(drv.first_name, ' ', drv.last_name) AS driver_name,
            v.property_no, v.plate_no, v.model,
            o.office_name
            FROM service_requests sr
            JOIN employee req ON sr.requesting_emp_id = req.emp_id
            LEFT JOIN employee sup ON sr.supervisor_emp_id = sup.emp_id
            LEFT JOIN employee drv ON sr.driver_emp_id = drv.emp_id
            LEFT JOIN vehicles v ON sr.vehicle_id = v.vehicle_id
            LEFT JOIN office o ON req.office_id = o.office_id
            ORDER BY sr.date_of_travel DESC, sr.time_departure DESC";
    $requests = $db->query($query)->fetch_all(MYSQLI_ASSOC);

    // Check for conflicts in each request
    foreach ($requests as &$request) {
        $request['has_conflicts'] = hasScheduleConflicts($db, $request['request_id']);
    }
    unset($request); // Break the reference

    // Fetch reference data for forms
    $employees = $db->query("SELECT emp_id, CONCAT(first_name, ' ', last_name) AS full_name FROM employee ORDER BY last_name, first_name")->fetch_all(MYSQLI_ASSOC);
    $drivers = $db->query("SELECT emp_id, CONCAT(first_name, ' ', last_name) AS full_name FROM employee WHERE position_id IN (21, 23) ORDER BY last_name, first_name")->fetch_all(MYSQLI_ASSOC);
    $vehicles = $db->query("SELECT * FROM vehicles WHERE status = 'available' ORDER BY model, property_no")->fetch_all(MYSQLI_ASSOC);
    // Get supervisors (section heads and unit heads)
    $supervisors = $db->query("
        SELECT emp_id, CONCAT(first_name, ' ', last_name) AS full_name, 'Manager' as role 
        FROM employee 
        WHERE is_manager = 1
        UNION
        SELECT emp_id, CONCAT(first_name, ' ', last_name) AS full_name, 'Section Head' as role 
        FROM employee 
        WHERE emp_id IN (SELECT head_emp_id FROM section WHERE head_emp_id IS NOT NULL)
        UNION
        SELECT emp_id, CONCAT(first_name, ' ', last_name) AS full_name, 'Unit Head' as role 
        FROM employee 
        WHERE emp_id IN (SELECT head_emp_id FROM unit_section WHERE head_emp_id IS NOT NULL)
        ORDER BY full_name
    ")->fetch_all(MYSQLI_ASSOC);
// Fetch reference data for forms

    // Get request details for edit (if requested)
    $edit_request = null;
    $edit_passengers = [];
    if (isset($_GET['edit'])) {
        $query = "SELECT * FROM service_requests WHERE request_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $_GET['edit']);
        $stmt->execute();
        $edit_request = $stmt->get_result()->fetch_assoc();
        
        if ($edit_request) {
            $passenger_query = "SELECT emp_id FROM service_request_passengers WHERE request_id = ?";
            $passenger_stmt = $db->prepare($passenger_query);
            $passenger_stmt->bind_param("i", $_GET['edit']);
            $passenger_stmt->execute();
            $edit_passengers = $passenger_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $edit_passengers = array_column($edit_passengers, 'emp_id');
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NIA-Albay | Transport Request Management</title>
  <?php include '../includes/header.php'; ?>
  
  <!-- Select2 -->
  <!-- <link rel="stylesheet" href="../plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="../plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css"> -->
  
  <!-- daterange picker -->
  <link rel="stylesheet" href="../plugins/daterangepicker/daterangepicker.css">
  
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="../plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  
  <style>
        .request-photo {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
    }
    .action-btns .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .status-badge {
        font-size: 0.8rem;
        padding: 0.35rem 0.5rem;
    }
    .conflict-alert {
        border-left: 4px solid #dc3545;
    }
    .select2-container--default .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border-color: #006fe6;
        color: white;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255,255,255,0.7);
    }
    
    /* CSS for SweetAlert conflict display */
    .swal2-conflict-container {
        max-height: 300px;
        overflow-y: auto;
        text-align: left;
        margin: 15px 0;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
        border-left: 4px solid #dc3545;
    }
    .swal2-conflict-item {
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .swal2-conflict-item:last-child {
        border-bottom: none;
    }
    .swal2-conflict-type {
        font-weight: bold;
        color: #dc3545;
    }
    .swal2-conflict-message {
        margin-left: 10px;
    }
    .select2-container--default .select2-results__option[aria-disabled=true] {
    background-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_service.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Transport Request Management</h1>
          </div>
          <div class="col-sm-6">
            <?php if ($is_admin): ?>
            <form method="POST" action="service_request.php" class="float-right" id="deleteAllForm">
              <input type="hidden" name="delete_all_requests" value="1">
              <button type="button" class="btn btn-danger" id="deleteAllBtn">
                <i class="fas fa-trash"></i> Delete All Requests
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  <div class="modal fade" id="deleteAllModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteAllModalLabel">Confirm Delete All</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete ALL transport requests? This action cannot be undone and will remove all requests and passenger data.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteAll">Delete All Requests</button>
        </div>
      </div>
    </div>
  </div>
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Transport Requests</h3>
                <button class="btn btn-primary float-right" data-toggle="modal" data-target="#addRequestModal">
                  <i class="fas fa-plus"></i> New Request
                </button>
              </div>
              <div class="card-body">
                <?php if (isset($_SESSION['conflicts'])): 
                    // Filter out conflicts that only involve completed requests
                    $active_conflicts = [];
                    foreach ($_SESSION['conflicts'] as $conflict) {
                        // Extract request number from conflict message
                        if (preg_match('/Request #(\d+-\d+)/', $conflict['message'], $matches)) {
                            $request_no = $matches[1];
                            
                            // Check if this request is completed
                            $query = "SELECT status FROM service_requests WHERE request_no = ?";
                            $stmt = $db->prepare($query);
                            $stmt->bind_param("s", $request_no);
                            $stmt->execute();
                            $result = $stmt->get_result()->fetch_assoc();
                            
                            // Only include conflict if the conflicting request is not completed
                            if (!$result || $result['status'] !== 'completed') {
                                $active_conflicts[] = $conflict;
                            }
                        } else {
                            // If we can't parse the request number, include the conflict
                            $active_conflicts[] = $conflict;
                        }
                    }
                    
                    if (!empty($active_conflicts)): ?>
                        <div class="alert alert-warning conflict-alert">
                            <h5><i class="fas fa-exclamation-triangle"></i> Schedule Conflicts Detected</h5>
                            <ul>
                                <?php foreach ($active_conflicts as $conflict): ?>
                                    <li><?= htmlspecialchars($conflict['message']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p>Do you want to proceed anyway?</p>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-danger" id="proceedWithConflict">Proceed Anyway</button>
                            <button type="button" class="btn btn-secondary" id="cancelWithConflict">Cancel</button>
                        </div>
                    <?php else:
                        // If all conflicts are from completed requests, clear the session
                        unset($_SESSION['conflicts']);
                        unset($_SESSION['request_data']);
                    endif; ?>
                <?php endif; ?>
                
                <table id="requestsTable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Request No.</th>
                      <th>Date of Travel</th>
                      <th>Time</th>
                      <th>Requester</th>
                      <th>Destination</th>
                      <th>Vehicle</th>
                      <th>Driver</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                      <td><?= $request['request_no'] ?></td>
                      <td><?= date('m/d/Y', strtotime($request['date_of_travel'])) ?> - <?= date('m/d/Y', strtotime($request['date_of_travel_end'])) ?></td>
                      <td><?= date('h:i A', strtotime($request['time_departure'])) ?> - <?= date('h:i A', strtotime($request['time_return'])) ?></td>
                      <td><?= $request['requester_name'] ?></td>
                      <td><?= htmlspecialchars($request['destination']) ?></td>
                      <td><?= $request['model'] ?? 'N/A' ?></td>
                      <td><?= $request['driver_name'] ?? 'N/A' ?></td>
                        <td>
                            <?php
                            $badge_class = '';
                            $status_text = ucfirst($request['status']);
                            
                            switch ($request['status']) {
                                case 'pending':
                                    $badge_class = 'badge-warning';
                                    break;
                                case 'approved':
                                    $badge_class = 'badge-success';
                                    break;
                                case 'rejected':
                                    $badge_class = 'badge-danger';
                                    break;
                                case 'completed':
                                    $badge_class = 'badge-info';
                                    // Completed requests should not show conflicts
                                    $request['has_conflicts'] = false;
                                    break;
                                default:
                                    $badge_class = 'badge-secondary';
                            }
                            
                            // Add conflict status if applicable (but not for completed requests)
                            if ($request['has_conflicts'] && $request['status'] !== 'completed') {
                                $status_text .= " (Conflict)";
                                $badge_class = 'badge-danger';
                            }
                            ?>
                            <span class="badge status-badge <?= $badge_class ?>"><?= $status_text ?></span>
                        </td>
                      <td class="action-btns">
                          <div class="btn-group">
                              <button class="btn btn-info btn-sm view-request" data-id="<?= $request['request_id'] ?>" title="View">
                                  <i class="fas fa-eye"></i>
                              </button>
                              <?php if (($request['status'] == 'pending' && ($_SESSION['emp_id'] == $request['requesting_emp_id'] || $is_admin))): ?>
                                  <button class="btn btn-primary btn-sm edit-request" data-id="<?= $request['request_id'] ?>" title="Edit">
                                      <i class="fas fa-edit"></i>
                                  </button>
                                  <button class="btn btn-danger btn-sm delete-request" data-id="<?= $request['request_id'] ?>" data-request-no="<?= $request['request_no'] ?>" title="Delete">
                                      <i class="fas fa-trash"></i>
                                  </button>
                              <?php endif; ?>
                              <?php if ($request['status'] == 'pending' && ($_SESSION['emp_id'] == $request['supervisor_emp_id'] || $is_admin)): ?>
                                  <button class="btn btn-success btn-sm approve-request" data-id="<?= $request['request_id'] ?>" title="Approve">
                                      <i class="fas fa-check"></i>
                                  </button>
                                  <button class="btn btn-warning btn-sm reject-request" data-id="<?= $request['request_id'] ?>" title="Reject">
                                      <i class="fas fa-times"></i>
                                  </button>
                              <?php endif; ?>
                              <?php if (($request['status'] == 'approved' || $request['status'] == 'completed') && ($_SESSION['emp_id'] == $request['requesting_emp_id'] || $_SESSION['emp_id'] == $request['driver_emp_id'] || $is_admin)): ?>
                                  <button class="btn btn-secondary btn-sm print-request" data-id="<?= $request['request_id'] ?>" title="Print">
                                      <i class="fas fa-print"></i>
                                  </button>
                              <?php endif; ?>
                              <?php if ($request['status'] == 'approved' && ($_SESSION['emp_id'] == $request['requesting_emp_id'] || $_SESSION['emp_id'] == $request['driver_emp_id'] || $is_admin)): ?>
                                  <button class="btn btn-info btn-sm complete-request" data-id="<?= $request['request_id'] ?>" title="Mark as Completed">
                                      <i class="fas fa-flag-checkered"></i>
                                  </button>
                                  <button class="btn btn-danger btn-sm delete-request" data-id="<?= $request['request_id'] ?>" data-request-no="<?= $request['request_no'] ?>" title="Delete">
                                      <i class="fas fa-trash"></i>
                                  </button>
                              <?php endif; ?>
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
    </section>
  </div>
  
  <!-- Add Request Modal -->
  <div class="modal fade" id="addRequestModal" tabindex="-1" role="dialog" aria-labelledby="addRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addRequestModalLabel">New Transport Request</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="service_request.php" id="requestForm">
          <div class="modal-body">
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label>Requesting Employee</label>
                <select class="form-control select22" multiple="multiple" name="requesting_emp_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= $employee['emp_id'] ?>" <?= ($employee['emp_id'] == $_SESSION['emp_id']) ? 'selected' : '' ?>>
                            <?= $employee['full_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="requesting_emp_id" value="<?= $_SESSION['emp_id'] ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Supervisor</label>
                        <select class="form-control select22" multiple="multiple" name="supervisor_emp_id" required>
                            <option value="">Select Supervisor</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                                <option value="<?= $supervisor['emp_id'] ?>">
                                    <?= $supervisor['full_name'] ?> (<?= $supervisor['role'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Date of Travel (From - To)</label>
                    <input type="text" class="form-control" id="date_range" name="date_range" required readonly>
                    <input type="hidden" id="date_of_travel_start" name="date_of_travel_start">
                    <input type="hidden" id="date_of_travel_end" name="date_of_travel_end">
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Time Departure</label>
                  <input type="time" class="form-control timepicker" name="time_departure" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Time Return</label>
                  <input type="time" class="form-control timepicker" name="time_return" required>
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <label>Destination</label>
              <input type="text" class="form-control" name="destination" placeholder="Enter destination" required>
            </div>
            
            <div class="form-group">
              <label>Purpose</label>
              <textarea class="form-control" name="purpose" rows="3" placeholder="Enter purpose of travel" required></textarea>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Vehicle</label>
                  <select class="form-control select22" multiple="multiple" name="vehicle_id" required>
                    <option value="">Select Vehicle</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                      <option value="<?= $vehicle['vehicle_id'] ?>">
                        <?= $vehicle['model'] ?> (<?= $vehicle['plate_no'] ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Driver</label>
                  <select class="form-control select22" multiple="multiple" name="driver_emp_id" required>
                    <option value="">Select Driver</option>
                    <?php foreach ($drivers as $driver): ?>
                      <option value="<?= $driver['emp_id'] ?>"><?= $driver['full_name'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <label>Passengers</label>
              <select class="form-control select2" name="passengers[]" multiple="multiple" data-placeholder="Select passengers">
                <?php foreach ($employees as $employee): ?>
                  <?php if ($employee['emp_id'] != $_SESSION['emp_id']): ?>
                    <option value="<?= $employee['emp_id'] ?>"><?= $employee['full_name'] ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="create_request" class="btn btn-primary">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Edit Request Modal -->
  <?php if ($edit_request): ?>
    <div class="modal fade" id="editRequestModal" tabindex="-1" role="dialog" aria-labelledby="editRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title" id="editRequestModalLabel">Edit Transport Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>
                <form method="POST" action="service_request.php" id="editRequestForm">
                <input type="hidden" name="request_id" value="<?= $edit_request['request_id'] ?>">
                <div class="modal-body">
                    <?php if ($is_admin): ?>
                    <div class="form-group">
                        <label>Requesting Employee</label>
                        <select class="form-control select22" multiple="multiple" name="requesting_emp_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['emp_id'] ?>" <?= ($employee['emp_id'] == $edit_request['requesting_emp_id']) ? 'selected' : '' ?>>
                                    <?= $employee['full_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="requesting_emp_id" value="<?= $edit_request['requesting_emp_id'] ?>">
                    <?php endif; ?>
                    <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Supervisor</label>
                            <select class="form-control select22" multiple="multiple" name="supervisor_emp_id" required>
                                <option value="">Select Supervisor</option>
                                <?php foreach ($supervisors as $supervisor): ?>
                                    <option value="<?= $supervisor['emp_id'] ?>" <?= ($supervisor['emp_id'] == $edit_request['supervisor_emp_id']) ? 'selected' : '' ?>>
                                        <?= $supervisor['full_name'] ?> (<?= $supervisor['role'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                        <label>Date of Travel (From - To)</label>
                        <input type="text" class="form-control" id="edit_date_range" name="date_range" required readonly>
                        <input type="hidden" id="edit_date_of_travel_start" name="date_of_travel_start" value="<?= $edit_request['date_of_travel'] ?>">
                        <input type="hidden" id="edit_date_of_travel_end" name="date_of_travel_end" value="<?= $edit_request['date_of_travel_end'] ?? $edit_request['date_of_travel'] ?>">
                        </div>
                    </div>
                    </div>
                    
                    <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                        <label>Time Departure</label>
                        <input type="time" class="form-control" name="time_departure" value="<?= $edit_request['time_departure'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                        <label>Time Return</label>
                        <input type="time" class="form-control" name="time_return" value="<?= $edit_request['time_return'] ?>" required>
                        </div>
                    </div>
                    </div>
                    
                    <div class="form-group">
                    <label>Destination</label>
                    <input type="text" class="form-control" name="destination" value="<?= htmlspecialchars($edit_request['destination']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                    <label>Purpose</label>
                    <textarea class="form-control" name="purpose" rows="3" required><?= htmlspecialchars($edit_request['purpose']) ?></textarea>
                    </div>
                    
                    <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                        <label>Vehicle</label>
                        <select class="form-control select22" multiple="multiple" name="vehicle_id" required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['vehicle_id'] ?>" <?= ($vehicle['vehicle_id'] == $edit_request['vehicle_id']) ? 'selected' : '' ?>>
                                <?= $vehicle['model'] ?> - (<?= $vehicle['plate_no'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                        <label>Driver</label>
                        <select class="form-control select22" multiple="multiple" name="driver_emp_id" required>
                            <option value="">Select Driver</option>
                            <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['emp_id'] ?>" <?= ($driver['emp_id'] == $edit_request['driver_emp_id']) ? 'selected' : '' ?>>
                                <?= $driver['full_name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                    </div>
                    
                <div class="form-group">
                    <label>Passengers</label>
                    <select class="form-control select2" name="passengers[]" multiple="multiple" data-placeholder="Select passengers" id="editPassengersSelect">
                        <?php 
                        // Get approved passengers for this request
                        $approved_passengers_query = "SELECT emp_id FROM service_request_passengers 
                                                    WHERE request_id = ? AND approved = 1";
                        $approved_stmt = $db->prepare($approved_passengers_query);
                        $approved_stmt->bind_param("i", $edit_request['request_id']);
                        $approved_stmt->execute();
                        $approved_passengers = $approved_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $approved_passengers = array_column($approved_passengers, 'emp_id');
                        
                        foreach ($employees as $employee): 
                            $is_approved = in_array($employee['emp_id'], $approved_passengers);
                            $is_selected = in_array($employee['emp_id'], $edit_passengers);
                            $disabled = $is_approved ? 'disabled' : '';
                            $title = $is_approved ? 'title="This passenger has already been approved and cannot be removed"' : '';
                        ?>
                            <?php if ($employee['emp_id'] != $_SESSION['emp_id']): ?>
                                <option value="<?= $employee['emp_id'] ?>" 
                                    <?= $is_selected ? 'selected' : '' ?>
                                    <?= $disabled ?>
                                    <?= $title ?>
                                    data-approved="<?= $is_approved ? 'true' : 'false' ?>">
                                    <?= $employee['full_name'] ?>
                                    <?= $is_approved ? ' (Approved)' : '' ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_request" class="btn btn-primary">Update Request</button>
                </div>
                </form>
            </div>
        </div>
    </div>
  <?php endif; ?>

  
  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteRequestModal" tabindex="-1" role="dialog" aria-labelledby="deleteRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteRequestModalLabel">Confirm Deletion</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="service_request.php">
          <input type="hidden" name="request_id" id="delete_request_id">
          <div class="modal-body">
            <p>Are you sure you want to delete this transport request? This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="delete_request" class="btn btn-danger">Delete Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Reject Request Modal -->
  <div class="modal fade" id="rejectRequestModal" tabindex="-1" role="dialog" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rejectRequestModalLabel">Reject Transport Request</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="service_request.php">
          <input type="hidden" name="request_id" id="reject_request_id">
          <div class="modal-body">
            <div class="form-group">
              <label>Remarks</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Enter reason for rejection" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="reject_request" class="btn btn-danger">Reject Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <?php include '../includes/footer.php'; ?>
</div>

<!-- jQuery -->
<!-- <script src="../plugins/jquery/jquery.min.js"></script> -->
<!-- Bootstrap 4 -->
<!-- <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script> -->
<!-- DataTables -->
<!-- <script src="../plugins/datatables/jquery.dataTables.min.js"></script> -->
<!-- <script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script> -->
<!-- Select2 -->
<!-- <script src="../plugins/select2/js/select2.full.min.js"></script> -->
<!-- Time Picker -->
<!-- <script src="../plugins/timepicker/bootstrap-timepicker.min.js"></script> -->
<!-- Moment -->
<!-- <script src="../plugins/moment/moment.min.js"></script> -->
<!-- Date Range Picker -->
<script src="../plugins/daterangepicker/daterangepicker.js"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    
    $('#editPassengersSelect').on('select2:selecting', function(e) {
        var option = e.params.args.data;
        if (option.element && option.element.getAttribute('data-approved') === 'true') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Cannot Modify',
                text: 'This passenger has already been approved and cannot be modified.',
                confirmButtonColor: '#3085d6'
            });
        }
    });

    // Prevent removal of approved passengers
    $('#editPassengersSelect').on('select2:unselecting', function(e) {
        var option = e.params.args.data;
        if (option.element && option.element.getAttribute('data-approved') === 'true') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Cannot Remove',
                text: 'This passenger has already been approved and cannot be removed.',
                confirmButtonColor: '#3085d6'
            });
        }
    });

    $('#date_range').daterangepicker({  
        autoUpdateInput: false,
        minDate: new Date(),
        locale: {
            cancelLabel: 'Clear',
            format: 'MM/DD/YYYY'
        }
    });
    
    $('#date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
        $('#date_of_travel_start').val(picker.startDate.format('YYYY-MM-DD'));
        $('#date_of_travel_end').val(picker.endDate.format('YYYY-MM-DD'));
    });

    $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        $('#date_of_travel_start').val('');
        $('#date_of_travel_end').val('');
    });
    
    // Initialize date range picker for edit request
    <?php if ($edit_request): ?>
        var editStartDate = '<?= date('m/d/Y', strtotime($edit_request['date_of_travel'])) ?>';
        var editEndDate = '<?= date('m/d/Y', strtotime($edit_request['date_of_travel_end'] ?? $edit_request['date_of_travel'])) ?>';
        
        $('#edit_date_range').daterangepicker({
            startDate: editStartDate,
            endDate: editEndDate,
            minDate: new Date(),
            locale: {
                cancelLabel: 'Clear',
                format: 'MM/DD/YYYY'
            }
        });
        
        $('#edit_date_range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
            $('#edit_date_of_travel_start').val(picker.startDate.format('YYYY-MM-DD'));
            $('#edit_date_of_travel_end').val(picker.endDate.format('YYYY-MM-DD'));
        });

        $('#edit_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            $('#edit_date_of_travel_start').val('');
            $('#edit_date_of_travel_end').val('');
        });
    <?php endif; ?>
    
    // Update form submission to handle date range
    $('#requestForm').submit(function(e) {
        if (!$('#date_of_travel_start').val() || !$('#date_of_travel_end').val()) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select a valid date range for travel.',
                confirmButtonColor: '#d33'
            });
        }
    });
    
    $('#editRequestForm').submit(function(e) {
        if (!$('#edit_date_of_travel_start').val() || !$('#edit_date_of_travel_end').val()) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select a valid date range for travel.',
                confirmButtonColor: '#d33'
            });
        }
    });
    // Initialize DataTable
    $('#requestsTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[1, 'desc']]
    });
    
    // Initialize Select2
    $('.select2').select2({
        placeholder: "-- Please Select --",
        allowClear: true
    });
    $('.select22').select2({
        placeholder: "-- Please Select --",
        allowClear: true,
        maximumSelectionLength: 1
    });
    
    // Show edit modal if edit parameter is present
    <?php if (isset($_GET['edit'])): ?>
        $('#editRequestModal').modal('show');
    <?php endif; ?>
    
    // Handle view button click with SweetAlert
    $('.view-request').click(function() {
        var requestId = $(this).data('id');
        $.ajax({
            url: 'service_request.php',
            type: 'POST',
            data: { 
                request_id: requestId,
                view_request: 1 
            },
            success: function(response) {
                Swal.fire({
                    title: 'Transport Request Details',
                    html: response,
                    icon: 'info',
                    width: '700px',
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#3085d6'
                });
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load request details.',
                    confirmButtonColor: '#d33'
                });
            }
        });
    });
    
    // Handle edit button click
    $('.edit-request').click(function() {
        var requestId = $(this).data('id');
        window.location.href = 'service_request.php?edit=' + requestId;
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-request', function() {
        var requestId = $(this).data('id');
        var requestNo = $(this).data('request-no');
        
        Swal.fire({
            title: 'Are you sure?',
            html: `You are about to delete transport request <strong>${requestNo}</strong>. This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit form
                $('<form>').attr({
                    method: 'POST',
                    action: 'service_request.php'
                }).append(
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'request_id',
                        value: requestId
                    }),
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'delete_request',
                        value: '1'
                    })
                ).appendTo('body').submit();
            }
        });
    });
    

    // Handle delete all button click
    $('#deleteAllBtn').click(function() {
        $('#deleteAllModal').modal('show');
    });
    
    // Handle confirm delete all
    $('#confirmDeleteAll').click(function() {
        $('#deleteAllForm').submit();
    });
    
    // Handle approve request button click
    $('.approve-request').click(function() {
        var requestId = $(this).data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You are about to approve this transport request",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, approve it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Approving Request',
                    html: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        // Use AJAX to approve the request
                        $.ajax({
                            url: 'service_request.php',
                            type: 'POST',
                            data: {
                                request_id: requestId,
                                approve_request: 1
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success!',
                                        text: response.message,
                                        confirmButtonColor: '#3085d6'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    let errorHtml = response.message;
                                    if (response.conflicts && response.conflicts.length > 0) {
                                        errorHtml += '<div class="swal2-conflict-container">';
                                        errorHtml += '<h6>Schedule Conflicts Detected:</h6>';
                                        response.conflicts.forEach(function(conflict) {
                                            errorHtml += '<div class="swal2-conflict-item">';
                                            errorHtml += '<span class="swal2-conflict-type">' + conflict.type.toUpperCase() + ':</span> ';
                                            errorHtml += '<span class="swal2-conflict-message">' + conflict.message + '</span>';
                                            errorHtml += '</div>';
                                        });
                                        errorHtml += '</div>';
                                    }
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Cannot Approve Request',
                                        html: errorHtml,
                                        confirmButtonColor: '#d33',
                                        width: '700px'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred while processing your request.',
                                    confirmButtonColor: '#d33'
                                });
                            }
                        });
                    }
                });
            }
        });
    });
    
    // Handle reject button click
    $('.reject-request').click(function() {
        var requestId = $(this).data('id');
        $('#reject_request_id').val(requestId);
        $('#rejectRequestModal').modal('show');
    });
    
    // Handle complete button click with SweetAlert
    $('.complete-request').click(function() {
        var requestId = $(this).data('id');
        
        Swal.fire({
            title: 'Mark as Completed?',
            text: "Are you sure you want to mark this transport request as completed?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, mark as completed!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Marking as Completed',
                    html: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        $('<form>').attr({
                            method: 'POST',
                            action: 'service_request.php'
                        }).append(
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'request_id',
                                value: requestId
                            }),
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'complete_request',
                                value: '1'
                            })
                        ).appendTo('body').submit();
                    }
                });
            }
        });
    });
    
    // Handle print button click
    $('.print-request').click(function() {
        var requestId = $(this).data('id');
        Swal.fire({
            title: 'Generating Excel Document',
            html: 'Please wait while we generate the request document...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                
                $('<form>').attr({
                    method: 'POST',
                    action: 'service_request.php'
                }).append(
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'request_id',
                        value: requestId
                    }),
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'print_request',
                        value: '1'
                    })
                ).appendTo('body').submit();
            },timer: 1500
        });
    });
    
    // Handle proceed with conflict button
    $('#proceedWithConflict').click(function() {
        var form = $('<form>').attr({
            method: 'POST',
            action: 'service_request.php'
        });
        
        <?php if (isset($_SESSION['request_data'])): ?>
            <?php foreach ($_SESSION['request_data'] as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $val): ?>
                        form.append(
                            $('<input>').attr({
                                type: 'hidden',
                                name: '<?= $key ?>[]',
                                value: '<?= $val ?>'
                            })
                        );
                    <?php endforeach; ?>
                <?php else: ?>
                    form.append(
                        $('<input>').attr({
                            type: 'hidden',
                            name: '<?= $key ?>',
                            value: '<?= $value ?>'
                        })
                    );
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        
        // Add the correct action based on whether we're editing or creating
        form.append(
            $('<input>').attr({
                type: 'hidden',
                name: '<?= isset($_SESSION["request_data"]["request_id"]) ? "update_request" : "create_request" ?>',
                value: '1'
            })
        );
        
        form.appendTo('body').submit();
    });
    
    // Handle cancel with conflict button
    $('#cancelWithConflict').click(function() {
        window.location.href = 'service_request.php?clear_conflicts=1';
    });
});
</script>
<script>
  // Handle approve passenger button click
    $(document).on('click', '.approve-passenger', function() {
        var requestId = $(this).data('request-id');
        var empId = $(this).data('emp-id');
        var passengerName = $(this).data('passenger-name');
        
        Swal.fire({
            title: 'Approve Passenger',
            html: `Are you sure you want to approve <strong>${passengerName}</strong> for this transport request?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, approve passenger!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Approving Passenger',
                    html: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        
                        // Create and submit form
                        $('<form>').attr({
                            method: 'POST',
                            action: 'service_request.php'
                        }).append(
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'request_id',
                                value: requestId
                            }),
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'emp_id',
                                value: empId
                            }),
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'approve_passenger',
                                value: '1'
                            })
                        ).appendTo('body').submit();
                    }
                });
            }
        });
    });
    // Handle reject passenger button click
$(document).on('click', '.reject-passenger', function() {
    var requestId = $(this).data('request-id');
    var empId = $(this).data('emp-id');
    var passengerName = $(this).data('passenger-name');
    
    Swal.fire({
        title: 'Reject Passenger',
        html: `Are you sure you want to reject <strong>${passengerName}</strong> for this transport request?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, reject passenger!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Rejecting Passenger',
                html: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    // Create and submit form
                    $('<form>').attr({
                        method: 'POST',
                        action: 'service_request.php'
                    }).append(
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'request_id',
                            value: requestId
                        }),
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'emp_id',
                            value: empId
                        }),
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'reject_passenger',
                            value: '1'
                        })
                    ).appendTo('body').submit();
                }
            });
        }
    });
});
</script>
</body>
</html>