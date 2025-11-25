<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$leaveFunctions = new LeaveFunctions();
$emp_id = $_SESSION['emp_id'];

// Get leave_id from URL
$leave_id = $_GET['leave_id'] ?? 0;

// Get leave request details
$query = "SELECT lr.*, lt.leave_name, lt.leave_code, 
                 e.first_name, e.last_name, e.position_id, e.section_id,
                 p.position_name, s.section_name,
                 sh.first_name as sh_first_name, sh.last_name as sh_last_name, sh.middle_name as sh_middle_name,
                 sh_pos.position_name as sh_position_name,
                 a.first_name as approver_first_name, a.last_name as approver_last_name,
                 a_pos.position_name as approver_position_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
          JOIN employee e ON lr.emp_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN employee sh ON lr.section_head_id = sh.emp_id
          LEFT JOIN employee a ON lr.approved_by = a.emp_id
          LEFT JOIN position sh_pos ON sh.position_id = sh_pos.position_id
          LEFT JOIN position a_pos ON a.position_id = a_pos.position_id
          WHERE lr.leave_id = ? AND lr.emp_id = ? AND lr.status = 'approved'";

$stmt = $db->prepare($query);
$stmt->bind_param("ii", $leave_id, $emp_id);
$stmt->execute();
$leave_request = $stmt->get_result()->fetch_assoc();

if (!$leave_request) {
    die("Leave request not found or not approved.");
}

// Function to convert leave code to form checkbox value
function getLeaveTypeCheckboxValue($leave_code) {
    $mapping = [
        'VL' => 'VACATION_LEAVE',
        'MFL' => 'MANDATORY_LEAVE', 
        'SL' => 'SICK_LEAVE',
        'MATL' => 'MATERNITY_LEAVE',
        'PATL' => 'PATERNITY_LEAVE',
        'SPL' => 'SPECIAL_PRIVILEGE_LEAVE',
        'SOLO' => 'SOLO_PARENT_LEAVE',
        'STUL' => 'STUDY_LEAVE',
        'VAWC' => 'VAWC_LEAVE',
        'REHAB' => 'REHABILITATION_PRIVILEGE',
        'WOMEN' => 'SPECIAL_LEAVE_BENEFITS_FOR_WOMEN',
        'CALAMITY' => 'SPECIAL_EMERGENCY_CALAMITY_LEAVE',
        'TERMINAL' => 'TERMINAL_LEAVE',
        'ADOPT' => 'ADOPTION_LEAVE'
    ];
    
    return $mapping[$leave_code] ?? '';
}

// Function to format section head name
function formatSectionHeadName($first_name, $last_name, $middle_name = '') {
    $name = '';
    if (!empty($first_name) && !empty($last_name)) {
        $name = $last_name . ' ' . $first_name;
        if (!empty($middle_name)) {
            // Get first letter of middle name
            $middle_initial = substr($middle_name, 0, 1);
            $name .= ' ' . $middle_initial . '.';
        }
    }
    return $name;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Leave Form - NIA ACIMO</title>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-form, .print-form * {
                visibility: visible;
            }
            .print-form {
                position: relative; /* Changed from absolute to relative */
                left: 0;
                top: 0;
                width: 100%;
            }
            .instructions-page {
                position: relative; /* Ensure this is also relative */
                page-break-before: always; /* Force page break before instructions */
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        body {
            font-family: "Arial", serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }
        
        .print-form {
            font-family: "Arial", serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            max-width: 8.2in;
            margin: 0 auto;
            padding: 0.5in;
            position: relative; /* Added for normal flow */
        }
        
        .instructions-page {
            margin-top: 30px;
            position: relative; /* Added for normal flow */
        }
        
        /* Rest of your existing CSS remains the same */
        .form-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #000;
        }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .logo {
            width: 0.8in;
            height: 0.8in;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            text-align: center;
        }
        
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .logo-placeholder {
            font-size: 8px;
            color: #666;
            border: 1px solid #000;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .agency-name {
            flex-grow: 1;
            text-align: left;
            padding: 0 10px;
        }
        
        .form-header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 1px 0;
            text-transform: uppercase;
        }
        
        .form-header h2 {
            font-size: 14px;
            font-weight: bold;
            margin: 1px 0;
            text-transform: uppercase;
        }
        
        .form-section {
            margin-bottom: 15px;
        }
        
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #000;
        }
        
        .form-table-no-border {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: none;
        }
        
        .form-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: top;
        }
        
        .form-table-no-border td {
            border: none;
            padding: 3px 5px;
            vertical-align: top;
        }
        
        .form-table .section-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .checkbox-group {
            margin: 1px 0;
        }
        
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 5px;
            margin-bottom: 1px;
        }
        
        .checkbox-box {
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            display: inline-block;
            text-align: center;
            line-height: 12px;
            font-size: 10px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .checked {
            background-color: #000;
            color: white;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            text-align: center;
        }
        
        .form-field {
            min-height: 18px;
            border-bottom: 1px solid #000;
            margin: 2px 0;
            padding: 2px 5px;
        }
        
        .form-field-no-border {
            min-height: 18px;
            margin: 2px 0;
            padding: 2px 5px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-3 {
            margin-bottom: 15px;
        }
        
        .instructions {
            font-size: 10px;
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        
        .instructions-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #000;
        }
        
        .inline-field {
            display: inline-block;
            min-width: 100px;
            border-bottom: 1px solid #000;
            margin: 0 5px;
            text-align: center;
        }
        
        .form-number {
            top: 0.5in;
            right: 0.5in;
            font-size: 10px;
            text-align: right;
            margin: 5px;
        }
        
        .no-print {
            padding: 20px;
            background: #f8f9fa;
        }
        
        .btn {
            padding: 8px 15px;
            margin: 5px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        .container-fluid {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-right: 15px;
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h1>Print Leave Form</h1>
                </div>
                <div class="col-md-6 text-right">
                    <button onclick="window.print()" class="btn btn-primary">
                        Print Form
                    </button>
                    <a href="leave_request.php" class="btn btn-secondary">
                        Back to Leave Requests
                    </a>
                </div>
            </div>
            <div class="alert alert-info">
                Review the form below and click "Print Form" when ready. Ensure your printer is set up properly.
            </div>
        </div>
    </div>

    <div class="print-form">
        <!-- Form Number -->
        <div class="form-number">
            <strong>ANNEX A</strong><br>
            <em>Civil Service Form No. 6</em><br>
            <em>Revised 2020</em>
        </div>
        
        <!-- Header Section -->
        <div class="form-header">
            <div class="header-logos">
                <div class="logo">
                    <img src="../dist/img/OP.png" alt="Office of the President">
                </div>
                <div class="logo">
                    <img src="../dist/img/nialogo.png" alt="National Irrigation Administration">
                </div>
                <div class="agency-name">
                    <h1>Republic of the Philippines</h1>
                    <h2>OFFICE OF THE PRESIDENT</h2>
                    <h2>NATIONAL IRRIGATION ADMINISTRATION</h2>
                    <h2>Albay-Catanduanes Irrigation Management Office</h2>
                    <h2>Tuburan, Ligao City, Albay</h2>
                </div>
                <div class="logo">
                    <img src="../dist/img/bagong-pilipinas.png" alt="Bagong Pilipinas">
                </div>
            </div>
            
            <h1 style="margin-top: 20px;">APPLICATION FOR LEAVE</h1>
        </div>

        <!-- Section 1 & 2: Office/Department and Name -->
        <table class="form-table-no-border">
            <tr>
                <td width="50%">
                    <strong>1. OFFICE/DEPARTMENT</strong><br>
                    <div class="form-field-no-border"><?php echo htmlspecialchars($leave_request['section_name'] ?? 'ADMINISTRATIVE SECTION'); ?></div>
                </td>
                <td width="50%">
                    <strong>2. NAME (Last) (First) (Middle)</strong><br>
                    <div class="form-field-no-border"><?php echo htmlspecialchars($leave_request['last_name'] . ', ' . $leave_request['first_name']); ?></div>
                </td>
            </tr>
        </table>

        <!-- Section 3, 4, 5: Date of Filing, Position, Salary -->
        <table class="form-table-no-border">
            <tr>
                <td width="33%">
                    <strong>3. DATE OF FILING</strong><br>
                    <div class="form-field-no-border"><?php echo date('F j, Y', strtotime($leave_request['applied_date'])); ?></div>
                </td>
                <td width="34%">
                    <strong>4. POSITION</strong><br>
                    <div class="form-field-no-border"><?php echo htmlspecialchars($leave_request['position_name'] ?? ''); ?></div>
                </td>
                <td width="33%">
                    <strong>5. SALARY</strong><br>
                    <div class="form-field-no-border">₱ ___________</div>
                </td>
            </tr>
        </table>

        <!-- Section 6: Details of Application -->
        <table class="form-table">
            <tr>
                <td colspan="2" class="section-header">
                    <strong>6. DETAILS OF APPLICATION</strong>
                </td>
            </tr>
            <tr>
                <td width="60%">
                    <strong>6.A TYPE OF LEAVE TO BE AVAILED OF</strong><br>
                    <div class="checkbox-group">
                        <?php
                        $leave_types_all = [
                            'VACATION_LEAVE' => 'VACATION LEAVE (Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'MANDATORY_LEAVE' => 'MANDATORY LEAVE (Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'SICK_LEAVE' => 'SICK LEAVE (Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'MATERNITY_LEAVE' => 'MATERNITY LEAVE (R.A.11210/IRR issued by CSC, DOLE and SSS)',
                            'PATERNITY_LEAVE' => 'PATERNITY LEAVE (R.A. 8187/CSC MC No. 71, s. 1998, as amended)',
                            'SPECIAL_PRIVILEGE_LEAVE' => 'SPECIAL PRIVILEGE LEAVE (Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'SOLO_PARENT_LEAVE' => 'SOLO PARENT LEAVE (R.A. 8972/CSC MC No. 8, s. 2004)',
                            'STUDY_LEAVE' => 'STUDY LEAVE (Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'VAWC_LEAVE' => '10-DAY VAWC LEAVE (R.A. No. 9262/CSC MC No. 15, s. 2005)',
                            'REHABILITATION_PRIVILEGE' => 'REHABILITATION PRIVILEGE (Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
                            'SPECIAL_LEAVE_BENEFITS_FOR_WOMEN' => 'SPECIAL LEAVE BENEFITS FOR WOMEN (R.A. 9710/CSC MC No. 25, s. 2010)',
                            'SPECIAL_EMERGENCY_CALAMITY_LEAVE' => 'SPECIAL EMERGENCY (CALAMITY) LEAVE (CSC MC No. 2, s. 2012, as amended)',
                            'ADOPTION_LEAVE' => 'ADOPTION LEAVE (R.A. No. 8552)'
                        ];
                        
                        $current_leave_type = getLeaveTypeCheckboxValue($leave_request['leave_code']);
                        
                        foreach ($leave_types_all as $key => $label): 
                            $is_checked = ($key === $current_leave_type);
                        ?>
                            <div class="checkbox-item">
                                <span class="checkbox-box <?php echo $is_checked ? 'checked' : ''; ?>">
                                    <?php if ($is_checked): ?>✓<?php endif; ?>
                                </span>
                                <span style="font-size: 10px;"><?php echo $label; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="checkbox-item">
                            <span class="checkbox-box"> </span>
                            <span style="font-size: 10px;">OTHERS: _________________________</span>
                        </div>
                    </div>
                    <br>
                    <div style="margin-top: 20px;">
                        <strong>6.C NUMBER OF WORKING DAYS APPLIED FOR</strong><br>
                        <strong>INCLUSIVE DATES</strong><br>
                        <div class="form-field text-center">
                            <?php echo $leave_request['total_days'] . ' day(s) - ' . 
                                   date('M j, Y', strtotime($leave_request['start_date'])) . ' to ' . 
                                   date('M j, Y', strtotime($leave_request['end_date'])); ?>
                        </div>
                    </div>
                </td>
                <td width="40%">
                    <strong>6.B DETAILS OF LEAVE</strong><br>
                    
                    <!-- Show all leave details options -->
                    <em>In case of Vacation/Special Privilege Leave:</em><br>
                    <div class="checkbox-item">
                        <span class="checkbox-box <?php echo in_array($leave_request['leave_code'], ['VL', 'SPL']) ? 'checked' : ''; ?>">
                            <?php if (in_array($leave_request['leave_code'], ['VL', 'SPL'])): ?>✓<?php endif; ?>
                        </span>
                        <span>Within Philippines _________________</span>
                    </div>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>Abroad _____________________________</span>
                    </div>
                    
                    <em>In case of Sick Leave:</em><br>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>In Hospital (Specify Illness): _________________</span>
                    </div>
                    <div class="checkbox-item">
                        <span class="checkbox-box <?php echo $leave_request['leave_code'] === 'SL' ? 'checked' : ''; ?>">
                            <?php if ($leave_request['leave_code'] === 'SL'): ?>✓<?php endif; ?>
                        </span>
                        <span>Out Patient (Specify Illness): ________________</span>
                    </div>
                    
                    <em>In case of Special Leave Benefits for Women: (Specify Illness)</em><br>
                    <div class="form-field" style="min-height: 20px; margin: 5px 0;"></div>
                    
                    <em>In case of Study Leave:</em><br>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>Completion of Master's Degree</span>
                    </div>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>Bar/Board Examination Review</span>
                    </div>
                    <em>Other Purpose:</em><br>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>Monetization of Leave Credits</span>
                    </div>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>Terminal Leave</span>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <strong>6.D COMMUTATION</strong><br>
                        <div class="checkbox-item">
                            <span class="checkbox-box checked">✓</span>
                            <span>Not Requested</span>
                        </div>
                        <div class="checkbox-item">
                            <span class="checkbox-box"> </span>
                            <span>Requested</span>
                        </div>
                    </div>
                    
                    <div class="signature-line" style="margin-top: 20px;">
                        <em>(Signature of Applicant)</em>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="section-header">
                    <strong>7. DETAILS OF ACTION ON APPLICATION</strong>
                </td>
            </tr>
            <tr>
                <td width="50%">
                    <strong>7.A CERTIFICATION OF LEAVE CREDITS</strong><br>
                    As of <u><?php echo date('F j, Y'); ?></u>
                    
                    <table style="width: 100%; margin-top: 5px; font-size: 10px;">
                        <tr>
                            <td width="40%"></td>
                            <td width="30%" class="text-center"><strong>VACATION LEAVE</strong></td>
                            <td width="30%" class="text-center"><strong>SICK LEAVE</strong></td>
                        </tr>
                        <tr>
                            <td><strong>TOTAL EARNED</strong></td>
                            <td class="text-center">__________</td>
                            <td class="text-center">__________</td>
                        </tr>
                        <tr>
                            <td><strong>LESS THIS APPLICATION</strong></td>
                            <td class="text-center">__________</td>
                            <td class="text-center">__________</td>
                        </tr>
                        <tr>
                            <td><strong>BALANCE</strong></td>
                            <td class="text-center">__________</td>
                            <td class="text-center">__________</td>
                        </tr>
                    </table>
                    
                    <div class="signature-line" style="margin-top: 15px;">
                        <strong>MYRA M. ETCOBANEZ</strong><br>
                        <em>Administrative Services Officer B</em>
                    </div>
                </td>
                <td width="50%">
                    <strong>7.B RECOMMENDATION</strong><br><br>
                    
                    <div class="checkbox-item">
                        <span class="checkbox-box checked">✓</span>
                        <span>For approval</span>
                    </div>
                    <div class="checkbox-item">
                        <span class="checkbox-box"> </span>
                        <span>For disapproval due to _________________________</span>
                    </div>
                    
                    <div class="signature-line" style="margin-top: 40px;">
                        <strong>
                            <?php 
                            $section_head_name = formatSectionHeadName(
                                $leave_request['sh_first_name'] ?? '', 
                                $leave_request['sh_last_name'] ?? '', 
                                $leave_request['sh_middle_name'] ?? ''
                            );
                            echo !empty($section_head_name) ? htmlspecialchars($section_head_name) : 'IAN FELICIANO P. BERDIN III';
                            ?>
                        </strong><br>
                        <em>
                            Section Head, 
                            <?php echo htmlspecialchars($leave_request['section_name'] ?? 'Administrative Section'); ?>
                        </em>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table style="width: 100%;">
                        <tr>
                            <td width="50%">
                                <strong>7.C APPROVED FOR:</strong><br>
                                <div style="margin-left: 20px;">
                                    <div class="checkbox-item">
                                        <span class="checkbox-box checked">✓</span>
                                        <span>_____ days with pay</span>
                                    </div>
                                    <div class="checkbox-item">
                                        <span class="checkbox-box"> </span>
                                        <span>_____ days without pay</span>
                                    </div>
                                    <div class="checkbox-item">
                                        <span class="checkbox-box"> </span>
                                        <span>_____ others (specify) ___________</span>
                                    </div>
                                </div>
                                
                                <div class="signature-line" style="margin-top: 20px;">
                                    <strong>ENGR. MARK CLOYD G. SO</strong><br>
                                    <em>Acting Division Manager</em>
                                </div>
                            </td>
                            <td width="50%">
                                <strong>7.D DISAPPROVED DUE TO:</strong><br>
                                <div class="form-field" style="min-height: 80px; margin-top: 5px;"></div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- Second Page - Instructions -->
    <div class="print-form instructions-page">
        <!-- Header Section for Second Page -->
        <div class="instructions-header">
            <h1>INSTRUCTIONS AND REQUIREMENTS</h1>
        </div>

        <div class="instructions">
            Application for any type of leave shall be made on this Form and <u>to be accomplished at least in duplicate</u> with documentary requirements, as follows:<br><br>

            <strong>Vacation leave*</strong><br>
            It shall be filed five (5) days in advance, whenever possible, of the effective date of such leave. Vacation leave within in the Philippines or abroad shall be indicated in the form for purposes of securing travel authority and completing clearance from money and work accountabilities.<br><br>

            <strong>Mandatory/Forced leave</strong><br>
            Annual five-day vacation leave shall be forfeited if not taken during the year. In case the scheduled leave has been cancelled in the exigency of the service by the head of agency, it shall no longer be deducted from the accumulated vacation leave. Availment of one (1) day or more Vacation Leave (VL) shall be considered for complying the mandatory/forced leave subject to the conditions under Section 25, Rule XVI of the Omnibus Rules Implementing E.O. No. 292.<br><br>

            <strong>Sick leave*</strong><br>
            • It shall be filed immediately upon employee's return from such leave.<br>
            • If filed in advance or exceeding five (5) days, application shall be accompanied by a medical certificate. In case medical consultation was not availed of, an affidavit should be executed by an applicant.<br><br>

            <strong>Maternity leave* -- 105 days</strong><br>
            • Proof of pregnancy e.g. ultrasound, doctor's certificate on the expected date of delivery<br>
            • Accomplished Notice of Allocation of Maternity Leave Credits (CS Form No. 6a), if needed<br>
            • Seconded female employees shall enjoy maternity leave with full pay in the recipient agency.<br><br>

            <strong>Paternity leave -- 7 days</strong><br>
            Proof of child's delivery e.g. birth certificate, medical certificate and marriage contract<br><br>

            <strong>Special Privilege leave -- 3 days</strong><br>
            It shall be filed/approved for at least one (1) week prior to availment, except on emergency cases. Special privilege leave within the Philippines or abroad shall be indicated in the form for purposes of securing travel authority and completing clearance from money and work accountabilities.<br><br>

            <strong>Solo Parent leave -- 7 days</strong><br>
            It shall be filed in advance or whenever possible five (5) days before going on such leave with updated Solo Parent Identification Card.<br><br>

            <strong>Study leave* -- up to 6 months</strong><br>
            • Shall meet the agency's internal requirements, if any;<br>
            • Contract between the agency head or authorized representative and the employee concerned.<br><br>

            <strong>VAWC leave -- 10 days</strong><br>
            • It shall be filed in advance or immediately upon the woman employee's return from such leave.<br>
            • It shall be accompanied by any of the following supporting documents:<br>
            &nbsp;&nbsp;a. Barangay Protection Order (BPO) obtained from the barangay;<br>
            &nbsp;&nbsp;b. Temporary/Permanent Protection Order (TPO/PPO) obtained from the court;<br>
            &nbsp;&nbsp;c. If the protection order is not yet issued by the barangay or the court, a certification issued by the Punong Barangay/Kagawad or Prosecutor or the Clerk of Court that the application for the BPO, TPO or PPO has been filed with the said office shall be sufficient to support the application for the ten-day leave; or<br>
            &nbsp;&nbsp;d. In the absence of the BPO/TPO/PPO or the certification, a police report specifying the details of the occurrence of violence on the victim and a medical certificate may be considered, at the discretion of the immediate supervisor of the woman employee concerned.<br><br>

            <strong>Rehabilitation leave* -- up to 6 months</strong><br>
            • Application shall be made within one (1) week from the time of the accident except when a longer period is warranted.<br>
            • Letter request supported by relevant reports such as the police report, if any,<br>
            • Medical certificate on the nature of the injuries, the course of treatment involved, and the need to undergo rest, recuperation, and rehabilitation, as the case may be.<br>
            • Written concurrence of a government physician should be obtained relative to the recommendation for rehabilitation if the attending physician is a private practitioner, particularly on the duration of the period of rehabilitation.<br><br>

            <strong>Special leave benefits for women* -- up to 2 months</strong><br>
            • The application may be filed in advance, that is, at least five (5) days prior to the scheduled date of the gynecological surgery that will be undergone by the employee. In case of emergency, the application for special leave shall be filed immediately upon employee's return but during confinement the agency shall be notified of said surgery.<br>
            • The application shall be accompanied by a medical certificate filled out by the proper medical authorities, e.g. the attending surgeon accompanied by a clinical summary reflecting the gynecological disorder which shall be addressed or was addressed by the said surgery; the histopathological report; the operative technique used for the surgery; the duration of the surgery including the peri-operative period (period of confinement around surgery); as well as the employees estimated period of recuperation for the same.<br><br>

            <strong>Special Emergency (Calamity) leave -- up to 5 days</strong><br>
            • The special emergency leave can be applied for a maximum of five (5) straight working days or staggered basis within thirty (30) days from the actual occurrence of the natural calamity/disaster. Said privilege shall be enjoyed once a year, not in every instance of calamity or disaster.<br>
            • The head of office shall take full responsibility for the grant of special emergency leave and verification of the employee's eligibility to be granted thereof. Said verification shall include: validation of place of residence based on latest available records of the affected employee; verification that the place of residence is covered in the declaration of calamity area by the proper government agency; and such other proofs as may be necessary.<br><br>

            <strong>Monetization of leave credits</strong><br>
            Application for monetization of fifty percent (50%) or more of the accumulated leave credits shall be accompanied by letter request to the head of the agency stating the valid and justifiable reasons.<br><br>

            <strong>Terminal leave*</strong><br>
            Proof of employee's resignation or retirement or separation from the service.<br><br>

            <strong>Adoption Leave</strong><br>
            • Application for adoption leave shall be filed with an authenticated copy of the Pre-Adoptive Placement Authority issued by the Department of Social Welfare and Development (DSWD).<br><br>

            *For leave of absence for thirty (30) calendar days or more and terminal leave, application shall be accompanied by a clearance from money, property and work-related accountabilities (pursuant to CSC Memorandum Circular No. 2, s. 1985).
        </div>
    </div>

    <div class="no-print" style="padding: 20px; text-align: center;">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            Print Form
        </button>
        <a href="leave_request.php" class="btn btn-secondary btn-lg">
            Back to Leave Requests
        </a>
    </div>

    <script>
        // Auto-print option (optional)
        window.onload = function() {
            // Uncomment the line below to auto-print when page loads
            // window.print();
        };
    </script>
</body>
</html>