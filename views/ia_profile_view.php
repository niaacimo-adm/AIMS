<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to view IA Profiles
if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$id = $_GET['id'] ?? 0;

if (empty($id)) {
    header('Location: ia_profiles.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get IA Profile details
$query = "SELECT * FROM ia_profiles WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

if (!$profile) {
    header('Location: ia_profiles.php');
    exit();
}

$page_title = "IA Profile - " . htmlspecialchars($profile['ia_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .profile-header {
            background: purple;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            margin-top: 0.5rem;
            margin-left: 1rem;
            margin-right: 1rem;
            border-radius: 15px 15px 15px 15px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .info-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-5px);
        }
        .info-card .card-header {
            background: purple;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            font-weight: 600;
        }
        .stat-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        .info-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border: none;
        }
        .info-table td {
            border: none;
            padding: 1rem;
        }
        .info-table tr {
            border-bottom: 1px solid #e9ecef;
        }
        .info-table tr:last-child {
            border-bottom: none;
        }
        .section-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #3498db;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            display: block;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-modern {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ia.php'; ?>
    <div class="content-wrapper">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="mb-1"><?= htmlspecialchars($profile['ia_name']) ?></h1>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-id-card mr-1"></i>
                            Code: <?= htmlspecialchars($profile['ia_code']) ?> 
                            | 
                            <i class="fas fa-map-marker-alt mr-1 ml-2"></i>
                            <?= htmlspecialchars($profile['province']) ?: 'N/A' ?>
                        </p>
                    </div>
                    <div class="col-auto">
                        <span class="stat-badge badge-<?= $profile['status'] == 'active' ? 'success' : 'danger' ?>">
                            <i class="fas fa-circle mr-1"></i>
                            <?= ucfirst($profile['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Basic Information Card -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Basic Information
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless info-table">
                                    <tr>
                                        <th width="40%"><i class="fas fa-tag mr-2"></i>IA Name</th>
                                        <td><?= htmlspecialchars($profile['ia_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-code mr-2"></i>IA Code</th>
                                        <td><?= htmlspecialchars($profile['ia_code']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-map-marked-alt mr-2"></i>Mailing Address</th>
                                        <td><?= htmlspecialchars($profile['mailing_address']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-user-tie mr-2"></i>President Name</th>
                                        <td><?= htmlspecialchars($profile['president_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-phone mr-2"></i>Contact Number</th>
                                        <td><?= htmlspecialchars($profile['contact_number']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Registration Details Card -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-file-contract mr-2"></i>
                                    Registration Details
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless info-table">
                                    <tr>
                                        <th width="40%"><i class="fas fa-calendar-day mr-2"></i>Date Organized</th>
                                        <td><?= !empty($profile['date_organized']) ? date('F j, Y', strtotime($profile['date_organized'])) : 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-file-alt mr-2"></i>SEC Registration No</th>
                                        <td><?= htmlspecialchars($profile['sec_registration_number']) ?: 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-calendar-check mr-2"></i>SEC Registration Date</th>
                                        <td><?= !empty($profile['sec_registration_date']) ? date('F j, Y', strtotime($profile['sec_registration_date'])) : 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-receipt mr-2"></i>IA TIN</th>
                                        <td><?= htmlspecialchars($profile['ia_tin']) ?: 'N/A' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        
                        <!-- Area & Membership Card -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-chart-area mr-2"></i>
                                    Area & Membership
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless info-table">
                                    <tr>
                                        <th width="40%"><i class="fas fa-ruler-combined mr-2"></i>Service Area</th>
                                        <td><?= number_format($profile['service_area_ha'], 2) ?> ha</td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-tint mr-2"></i>FUSA</th>
                                        <td><?= number_format($profile['fusa_ha'], 2) ?> ha</td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-road mr-2"></i>Canal Length</th>
                                        <td><?= number_format($profile['canal_length_km'], 3) ?> km</td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-users mr-2"></i>Farmer Beneficiaries</th>
                                        <td><?= $profile['farmer_beneficiaries'] ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-user-friends mr-2"></i>Actual IA Members</th>
                                        <td><?= $profile['actual_ia_members'] ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-people-group mr-2"></i>TSAGs Count</th>
                                        <td><?= $profile['tsags_count'] ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-mars mr-2"></i>Male Members</th>
                                        <td><?= $profile['male_members'] ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-venus mr-2"></i>Female Members</th>
                                        <td><?= $profile['female_members'] ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Location & Contract Card -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    Location & Contract
                                </h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless info-table">
                                    <tr>
                                        <th width="40%"><i class="fas fa-globe-asia mr-2"></i>Region</th>
                                        <td><?= htmlspecialchars($profile['region']) ?: 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-map mr-2"></i>Province</th>
                                        <td><?= htmlspecialchars($profile['province']) ?: 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-landmark mr-2"></i>Congressional District</th>
                                        <td><?= htmlspecialchars($profile['congressional_district']) ?: 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-handshake mr-2"></i>Existing Contract</th>
                                        <td><?= htmlspecialchars($profile['existing_contract']) ?: 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-calendar-alt mr-2"></i>Contract Effectivity Date</th>
                                        <td><?= !empty($profile['contract_effectivity_date']) ? date('F j, Y', strtotime($profile['contract_effectivity_date'])) : 'N/A' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card info-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-users-cog mr-2"></i>
                                    IA Officers
                                </h3>
                                <?php if (hasPermission('add_ia_officer')): ?>
                                <button type="button" class="btn btn-primary btn-modern" data-toggle="modal" data-target="#addOfficerModal">
                                    <i class="fas fa-plus mr-2"></i> Add New Officer
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <!-- <div class="table-responsive"> -->
                                    <table id="officersTable" class="table table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th><i class="fas fa-user mr-2"></i>Officer Name</th>
                                                <th><i class="fas fa-briefcase mr-2"></i>Position</th>
                                                <th><i class="fas fa-phone mr-2"></i>Contact Number</th>
                                                <!-- <th><i class="fas fa-envelope mr-2"></i>Email</th> -->
                                                <th><i class="fas fa-circle mr-2"></i>Status</th>
                                                <th><i class="fas fa-cog mr-2"></i>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $officers_query = "SELECT * FROM ia_officers WHERE ia_profile_id = ? ORDER BY position";
                                            $officers_stmt = $db->prepare($officers_query);
                                            $officers_stmt->bind_param('i', $id);
                                            $officers_stmt->execute();
                                            $officers_result = $officers_stmt->get_result();
                                            
                                            if ($officers_result && $officers_result->num_rows > 0):
                                                while ($officer = $officers_result->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td class="font-weight-bold"><?= htmlspecialchars($officer['officer_name']) ?></td>
                                                <td>
                                                    <span class="badge badge-info px-3 py-2">
                                                        <?= htmlspecialchars($officer['position']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($officer['contact_number']) ?></td>
                                                <!-- <td><?= htmlspecialchars($officer['email']) ?></td> -->
                                                <td>
                                                    <span class="badge badge-<?= $officer['is_active'] ? 'success' : 'danger' ?> px-3 py-2">
                                                        <i class="fas fa-circle mr-1"></i>
                                                        <?= $officer['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if (hasPermission('edit_ia_officer')): ?>
                                                        <button class="btn btn-outline-primary btn-sm edit-officer" data-id="<?= $officer['id'] ?>" title="Edit Officer">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if (hasPermission('delete_ia_officer')): ?>
                                                        <button class="btn btn-outline-danger btn-sm delete-officer" data-id="<?= $officer['id'] ?>" title="Delete Officer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                                endwhile;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-users fa-2x mb-3 d-block"></i>
                                                    No officers found. Click "Add New Officer" to add the first officer.
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                <!-- </div> -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add Officer Modal -->
<div class="modal fade" id="addOfficerModal" tabindex="-1" role="dialog" aria-labelledby="addOfficerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="addOfficerModalLabel">
                    <i class="fas fa-user-plus mr-2"></i>
                    Add New IA Officer
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addOfficerForm">
                <input type="hidden" name="ia_profile_id" value="<?= $id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="officer_name" class="font-weight-bold">Officer Name *</label>
                        <input type="text" class="form-control form-control-lg" id="officer_name" name="officer_name" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="position" class="font-weight-bold">Position *</label>
                        <input type="text" class="form-control form-control-lg" id="position" name="position" placeholder="Enter position" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_number" class="font-weight-bold">Contact Number</label>
                        <input type="text" class="form-control form-control-lg" id="contact_number" name="contact_number" placeholder="Enter contact number">
                    </div>
                    <div class="form-group">
                        <label for="email" class="font-weight-bold">Email Address</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Enter email address">
                    </div>
                    <div class="form-group">
                        <label for="is_active" class="font-weight-bold">Status</label>
                        <select class="form-control form-control-lg" id="is_active" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-modern" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-modern">
                        <i class="fas fa-save mr-2"></i>Save Officer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // $('#officersTable').DataTable({
    //     responsive: true,
    //     autoWidth: false
    // });
    $('#officersTable').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "order": [[0, "desc"]],
        "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    });
    // Add Officer Form Submission
    $('#addOfficerForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../includes/ia_officers_ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success('Officer added successfully');
                    $('#addOfficerModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error('Error adding officer: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                toastr.error('Error adding officer. Please check console for details.');
            }
        });
    });

    // Delete Officer
    $(document).on('click', '.delete-officer', function() {
        var officerId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this officer? This action cannot be undone.')) {
            $.ajax({
                url: '../includes/ia_officers_ajax.php',
                type: 'POST',
                data: {action: 'delete', id: officerId},
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success('Officer deleted successfully');
                        location.reload();
                    } else {
                        toastr.error('Error deleting officer: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    toastr.error('Error deleting officer. Please check console for details.');
                }
            });
        }
    });
});
</script>
</body>
</html>