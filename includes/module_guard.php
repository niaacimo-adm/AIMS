<?php

$GLOBALS['MODULE_MAP'] = [
    // file (basename)                 => module_name (must match system_modules.module_name exactly)
    'dashboard.php'                    => 'Admin Dashboard',
    'attachments_monitoring.php'       => 'Attachment Monitoring',
    'calendar.php'                     => 'Calendar System',
    'emp.create.php'                   => 'Employee Creation',
    'emp.list.php'                     => 'Employee Directory',
    'maintenance_page.php'             => 'Module Maintenance',
    'content_management.php'          => 'Content Management',
    'appointment_status.php'          => 'Appointment Settings',
    'position.php'                     => 'Position Management',
    'sections.php'                     => 'Section Management',
    'offices.php'                      => 'Office Management',
    'employment_status.php'           => 'Employment Status',
    'users.php'                        => 'User Management',
    'roles.php'                        => 'Role Management',
    'permissions.php'                  => 'Permission Management',
    'service.php'                      => 'Service Dashboard',
    'service_calendar.php'            => 'Service Calendar',
    'service_vehicle.php'             => 'Service Information',
    'service_driver.php'              => 'Operator/Driver Management',
    'service_request.php'             => 'Transportation Request',
    'inventory.php'                    => 'Inventory Dashboard',
    'view_inventory.php'              => 'Inventory Management',
    'request_supplies.php'            => 'Supply Requests',
    'my_supply_requests.php'          => 'My Supply Requests',
    'file_management.php'             => 'File Management',
    'ict_dashboard.php'                => 'ICT Equipment Inventory',

    // ── HR Management extras ──────────────────────────────
    'leave_request.php'                => 'Leave Request',
    'types_leaves.php'                 => 'Leave Types',
    'personal_locator_slip.php'        => 'Personal Locator Slip',
    'personal_locator_monitoring.php'  => 'Slip Monitoring',
    'applicant.php'                    => 'Applicant Databank',
    'hr_leave_monitoring.php'          => 'HR Leave Monitoring',
    'intern.php'                       => 'Intern Databank',
    'room_reservation.php'             => 'Room Reservation',
    'my_ict_equipment.php'             => 'My ICT Equipment',

    // ── Document Management module ────────────────────────
    'document_dashboard.php'           => 'Document Dashboard',
    'document_list.php'                => 'Document Records',
    'document_types.php'               => 'Document Types',
    'document_archive.php'             => 'Document Archive',

    // ── IA Profiles / SAHUR module ────────────────────────
    'dashboard_ia.php'                 => 'IA Dashboard',
    'ia_profiles.php'                  => 'IA Profiles',
    'ia_reports.php'                   => 'IA Reports',
    'ia_analytics.php'                 => 'IA Analytics',

    // ── ICT Equipment module extras ───────────────────────
    'ict_equipment.php'                => 'Equipment Inventory',
    'ict_assignments.php'              => 'ICT Assign/Return',
    'ict_scanner.php'                  => 'ICT QR Scanner',
    'ict_maintenance.php'              => 'ICT Maintenance Logs',
    'ict_categories.php'               => 'ICT Categories',

    // ── Queue Management module ───────────────────────────
    'queue_display.php'                => 'Queue Display',
    'section_queue.php'                => 'Section/IMO Queue',
    'queue.php'                        => 'Visitor Registration',
    'queue_reports.php'                => 'Queue Reports',
    'visitor_history.php'              => 'Visitor History',
    'queue_settings.php'               => 'Queue Settings',
    'queue_counters.php'               => 'Section/Unit Counters',
    'purpose_categories.php'           => 'Purpose Categories',

    // ── Scrum Board module ─────────────────────────────────
    'scrum_dashboard.php'              => 'Scrum Dashboard',
    'scrum_project.php'                => 'Projects Monitoring',
    'scrum_team.php'                   => 'Teams',
    'my_scrum_project.php'             => 'My Projects',
    'my_scrum_task.php'                => 'My Tasks',
    'scrum_calendar.php'               => 'Scrum Calendar',

    // Add new pages here as you build them:
    // 'my_new_page.php' => 'My New Module',
];


function checkModuleMaintenance($db, $module_name = null) {
    if ($module_name === null) {
        $file = basename($_SERVER['PHP_SELF']);
        $module_name = $GLOBALS['MODULE_MAP'][$file] ?? null;

        // Page isn't registered in the map — nothing to check, let it load normally.
        if ($module_name === null) {
            return;
        }
    }

    $stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
    $stmt->bind_param("s", $module_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $module = $result->fetch_assoc();
        if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
            $_SESSION['error'] = "The $module_name module is currently under maintenance.";
            header("Location: ../unauthorized.php");
            exit();
        }
    }
}