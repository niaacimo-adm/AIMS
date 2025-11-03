<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to access IA Profiles
if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = "IA Profiles";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IA Profiles - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-operational {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-nonoperational {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .assignment-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        /* Modern Action Buttons */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-action {
            border: none;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-view {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            color: white;
        }

        .btn-history {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-history:hover {
            background: linear-gradient(135deg, #5a6268, #545b62);
            color: white;
        }

        .btn-edit {
            background: linear-gradient(135deg, #007bff, #0069d9);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #0069d9, #0062cc);
            color: white;
        }

        .btn-assign {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }

        .btn-assign:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            color: #212529;
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            color: white;
        }

        .btn-icon {
            font-size: 12px;
            line-height: 1;
        }

        /* Table responsive improvements */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f8f9fa;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
        }

        /* Card header improvements */
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
        }

        /* Badge improvements */
        .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Hover effects for table rows */
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.04);
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ia.php'; ?>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>IA Profiles</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">IA Profiles</li>
                        </ol>
                    </div>
                </div>
                <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter mr-2"></i>Filter IA Profiles
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter_assigned_employee">Assigned Employee</label>
                            <select class="form-control" id="filter_assigned_employee">
                                <option value="">All Employees</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter_status">Status</label>
                            <select class="form-control" id="filter_status">
                                <option value="">All Status</option>
                                <option value="operational">Operational</option>
                                <option value="non-operational">Non-operational</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter_region">Region</label>
                            <select class="form-control" id="filter_region">
                                <option value="">All Regions</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="filter_province">Province</label>
                            <select class="form-control" id="filter_province">
                                <option value="">All Provinces</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="filter_ia_name">IA Name</label>
                            <input type="text" class="form-control" id="filter_ia_name" placeholder="Search by IA name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="filter_ia_code">IA Code</label>
                            <input type="text" class="form-control" id="filter_ia_code" placeholder="Search by IA code">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" id="applyFilters">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                        <button type="button" class="btn btn-secondary" id="resetFilters">
                            <i class="fas fa-redo mr-2"></i>Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Irrigators' Association Profiles</h3>
                                <div class="card-tools">
                                    <?php if (hasPermission('add_ia_profile')): ?>
                                    <a href="ia_profile_add.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add IA Profile
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="iaProfilesTable" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>IA Name</th>
                                            <th>President</th>
                                            <th>Contact</th>
                                            <th>Service Area (ha)</th>
                                            <th>Members</th>
                                            <th>TSAGs</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $database = new Database();
                                        $db = $database->getConnection();
                                        
                                        $query = "SELECT * FROM ia_profiles ORDER BY ia_name";
                                        $result = $db->query($query);
                                        
                                        if ($result && $result->num_rows > 0):
                                            while ($row = $result->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['ia_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['president_name'] ?? '') ?: '<span class="text-muted">N/A</span>' ?></td>
                                            <td><?= htmlspecialchars($row['contact_number'] ?? '') ?: '<span class="text-muted">N/A</span>' ?></td>
                                            <td><strong><?= number_format($row['service_area_ha'] ?? 0, 2) ?></strong></td>
                                            <td><?= $row['actual_ia_members'] ? '<span class="badge badge-info">' . $row['actual_ia_members'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                                            <td><?= $row['tsags_count'] ? '<span class="badge badge-secondary">' . $row['tsags_count'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                                            <td>
                                                <span class="badge badge-<?= $row['status'] == 'active' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div id="assigned-employee-<?= $row['id'] ?>">
                                                    <span class="text-muted">Loading...</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-view view-profile" data-id="<?= $row['id'] ?>" title="View Profile">
                                                        <i class="fas fa-eye btn-icon"></i>
                                                    </button>
                                                    <a href="ia_profile_history.php?id=<?= $row['id'] ?>" class="btn-action btn-history" title="View History">
                                                        <i class="fas fa-history btn-icon"></i>
                                                    </a>
                                                    <?php if (hasPermission('edit_ia_profile')): ?>
                                                    <button class="btn-action btn-edit edit-profile" data-id="<?= $row['id'] ?>" title="Edit Profile">
                                                        <i class="fas fa-edit btn-icon"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn-action btn-assign assign-employee" data-id="<?= $row['id'] ?>" title="Assign Employee">
                                                        <i class="fas fa-user-plus btn-icon"></i>
                                                    </button>
                                                    <?php if (hasPermission('delete_ia_profile')): ?>
                                                    <button class="btn-action btn-delete delete-profile" data-id="<?= $row['id'] ?>" title="Delete Profile">
                                                        <i class="fas fa-trash btn-icon"></i>
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
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No IA Profiles found</p>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
   <?php include '../includes/mainfooter.php'; ?>
</div>
<!-- Assign Employee Modal -->
<div class="modal fade" id="assignEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="assignEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignEmployeeModalLabel">Assign Employee to IA Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignEmployeeForm">
                <div class="modal-body">
                    <input type="hidden" id="assign_ia_profile_id" name="ia_profile_id">
                    <div class="form-group">
                        <label for="assigned_employee">Select Employee (IDU - Operation and Maintenance Section)</label>
                        <select class="form-control" id="assigned_employee" name="emp_id">
                            <option value="">-- Unassign / No one assigned --</option>
                            <!-- Employees will be loaded via AJAX -->
                        </select>
                    </div>
                    <div id="current-assignment" class="alert alert-info" style="display: none;">
                        <strong>Currently assigned to:</strong> <span id="current-assigned-name"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    
    $('#iaProfilesTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "processing": true,
        "serverSide": false,
        "ajax": {
            "url": "../includes/ia_profiles_ajax.php",
            "type": "POST",
            "data": { "action": "get_ia_profiles" },
            "dataSrc": function (response) {
                // Handle the response structure properly
                if (response.success) {
                    return response.data;
                } else {
                    console.error('Error loading data:', response.message);
                    return [];
                }
            }
        },
        "columns": [
            { 
                "data": "ia_name",
                "render": function(data, type, row) {
                    return '<strong>' + data + '</strong>';
                }
            },
            { 
                "data": "president_name",
                "render": function(data, type, row) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": "contact_number",
                "render": function(data, type, row) {
                    return data || '<span class="text-muted">N/A</span>';
                }
            },
            { 
                "data": "service_area_ha",
                "render": function(data, type, row) {
                    return data ? '<strong>' + parseFloat(data).toFixed(2) + '</strong>' : '<span class="text-muted">0.00</span>';
                }
            },
            { 
                "data": "actual_ia_members",
                "render": function(data, type, row) {
                    return data ? '<span class="badge badge-info">' + data + '</span>' : '<span class="text-muted">0</span>';
                }
            },
            { 
                "data": "tsags_count",
                "render": function(data, type, row) {
                    return data ? '<span class="badge badge-secondary">' + data + '</span>' : '<span class="text-muted">0</span>';
                }
            },
            { 
                "data": "status",
                "render": function(data, type, row) {
                    const isActive = data === 'active' || data === 'operational';
                    const badgeClass = isActive ? 'badge-success' : 'badge-danger';
                    const statusText = data ? data.charAt(0).toUpperCase() + data.slice(1) : 'Unknown';
                    return '<span class="badge ' + badgeClass + '">' + statusText + '</span>';
                }
            },
            { 
                "data": "id",
                "render": function(data, type, row) {
                    // Create a container for the assigned employee
                    return '<div id="assigned-employee-' + data + '"><span class="text-muted">Loading...</span></div>';
                }
            },
            {
                "data": "id",
                "render": function(data, type, row) {
                    let buttons = '<div class="action-buttons">';
                    
                    // View button
                    buttons += '<button class="btn-action btn-view view-profile" data-id="' + data + '" title="View Profile">';
                    buttons += '<i class="fas fa-eye btn-icon"></i>';
                    buttons += '</button>';
                    
                    // History button
                    buttons += '<a href="ia_profile_history.php?id=' + data + '" class="btn-action btn-history" title="View History">';
                    buttons += '<i class="fas fa-history btn-icon"></i>';
                    buttons += '</a>';
                    
                    <?php if (hasPermission('edit_ia_profile')): ?>
                    // Edit button
                    buttons += '<button class="btn-action btn-edit edit-profile" data-id="' + data + '" title="Edit Profile">';
                    buttons += '<i class="fas fa-edit btn-icon"></i>';
                    buttons += '</button>';
                    <?php endif; ?>
                    
                    // Assign button
                    buttons += '<button class="btn-action btn-assign assign-employee" data-id="' + data + '" title="Assign Employee">';
                    buttons += '<i class="fas fa-user-plus btn-icon"></i>';
                    buttons += '</button>';
                    
                    <?php if (hasPermission('delete_ia_profile')): ?>
                    // Delete button
                    buttons += '<button class="btn-action btn-delete delete-profile" data-id="' + data + '" title="Delete Profile">';
                    buttons += '<i class="fas fa-trash btn-icon"></i>';
                    buttons += '</button>';
                    <?php endif; ?>
                    
                    buttons += '</div>';
                    return buttons;
                },
                "orderable": false,
                "searchable": false,
                "width": "180px"
            }
        ],
        "language": {
            "emptyTable": "No IA Profiles found",
            "zeroRecords": "No matching records found"
        },
        "initComplete": function(settings, json) {
            // Load assigned employees after table is initialized
            loadAllAssignedEmployees();
        }
    });

    // Load regions on page load
    loadRegions();

    // Region change event
    $('#region').change(function() {
        const regionCode = $(this).val();
        if (regionCode) {
            loadProvinces(regionCode);
        } else {
            $('#province').html('<option value="">Select Province</option>');
            $('#district').html('<option value="">Select District</option>');
        }
    });

    // Province change event
    $('#province').change(function() {
        const provinceCode = $(this).val();
        if (provinceCode) {
            loadDistricts(provinceCode);
        } else {
            $('#district').html('<option value="">Select District</option>');
        }
    });

    // View IA Profile - Redirect to new page
    $(document).on('click', '.view-profile', function() {
        var profileId = $(this).data('id');
        window.location.href = 'ia_profile_view.php?id=' + profileId;
    });

});

function loadRegions() {
    console.log('Loading regions...');
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_regions'},
        dataType: 'json',
        success: function(response) {
            console.log('Regions response:', response);
            if (response.success) {
                const regionSelect = $('#region');
                regionSelect.html('<option value="">Select Region</option>');
                
                response.data.forEach(region => {
                    regionSelect.append(new Option(region.region_name, region.region_code));
                });
                
                // Auto-select Region V
                regionSelect.val('V');
                regionSelect.trigger('change');
            } else {
                console.error('Regions API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading regions:', error);
            console.error('XHR response:', xhr.responseText);
            // Fallback: manually set Region V
            const regionSelect = $('#region');
            regionSelect.html('<option value="">Select Region</option>');
            regionSelect.append(new Option('Region V - Bicol Region', 'V'));
            regionSelect.val('V');
            regionSelect.trigger('change');
        }
    });
}

function loadProvinces(regionCode) {
    console.log('Loading provinces for region:', regionCode);
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_provinces', region_code: regionCode},
        dataType: 'json',
        success: function(response) {
            console.log('Provinces response:', response);
            const provinceSelect = $('#province');
            provinceSelect.html('<option value="">Select Province</option>');
            
            if (response.success) {
                response.data.forEach(province => {
                    provinceSelect.append(new Option(province.province_name, province.province_code));
                });
            } else {
                console.error('Provinces API error:', response.message);
            }
            
            // Clear districts
            $('#district').html('<option value="">Select District</option>');
        },
        error: function(xhr, status, error) {
            console.error('Error loading provinces:', error);
            console.error('XHR response:', xhr.responseText);
        }
    });
}

function loadDistricts(provinceCode) {
    console.log('Loading districts for province:', provinceCode);
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_districts', province_code: provinceCode},
        dataType: 'json',
        success: function(response) {
            console.log('Districts response:', response);
            const districtSelect = $('#district');
            districtSelect.html('<option value="">Select District</option>');
            
            if (response.success) {
                response.data.forEach(district => {
                    districtSelect.append(new Option(district.district_name, district.district_code));
                });
            } else {
                console.error('Districts API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading districts:', error);
            console.error('XHR response:', xhr.responseText);
        }
    });
}

// Load assigned employees for all profiles
function loadAllAssignedEmployees() {
    const table = $('#iaProfilesTable').DataTable();
    const data = table.rows().data();
    
    data.each(function (value, index) {
        const profileId = value.id;
        if (profileId) {
            loadAssignedEmployee(profileId);
        }
    });
}
// Edit IA Profile - Redirect to edit page
$(document).on('click', '.edit-profile', function() {
    var profileId = $(this).data('id');
    
    Swal.fire({
        title: 'Edit IA Profile',
        text: 'You are about to edit this IA Profile. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Edit it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to edit page (you'll need to create ia_profile_edit.php)
            window.location.href = 'ia_profile_edit.php?id=' + profileId;
        }
    });
});

// Delete IA Profile with SweetAlert
$(document).on('click', '.delete-profile', function() {
    var profileId = $(this).data('id');
    var profileName = $(this).closest('tr').find('td:first').text();
    
    Swal.fire({
        title: 'Are you sure?',
        html: `<strong>${profileName}</strong><br>This action cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve) => {
                $.ajax({
                    url: '../includes/ia_profiles_ajax.php',
                    type: 'POST',
                    data: {action: 'delete', id: profileId},
                    dataType: 'json',
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        Swal.showValidationMessage(
                            `Request failed: ${error}`
                        );
                    }
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value.success) {
                Swal.fire({
                    title: 'Deleted!',
                    text: 'IA Profile has been deleted.',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    // Reload the page to reflect changes
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.value.message || 'Failed to delete IA Profile',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
    });
});
// Load assigned employee for a specific profile
function loadAssignedEmployee(profileId) {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_assigned_employee', ia_profile_id: profileId},
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const container = $('#assigned-employee-' + profileId);
                if (response.assigned && response.employee_name) {
                    container.html('<span class="assignment-badge"><i class="fas fa-user-check"></i> ' + response.employee_name + '</span>');
                } else {
                    container.html('<span class="text-muted"><i class="fas fa-user-times"></i> Not assigned</span>');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading assigned employee:', error);
            $('#assigned-employee-' + profileId).html('<span class="text-danger">Error loading</span>');
        }
    });
}

// Load IDU employees for dropdown
function loadIduEmployees() {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_idu_employees'},
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#assigned_employee');
                // Keep the "Unassign" option
                select.find('option:not(:first)').remove();
                
                response.data.forEach(employee => {
                    select.append(new Option(employee.full_name, employee.emp_id));
                });
            } else {
                toastr.error('Error loading employees: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading IDU employees:', error);
            toastr.error('Error loading employee list');
        }
    });
}

// Assign employee button click
$(document).on('click', '.assign-employee', function() {
    const profileId = $(this).data('id');
    $('#assign_ia_profile_id').val(profileId);
    
    // Load current assignment
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_assigned_employee', ia_profile_id: profileId},
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const currentAssignment = $('#current-assignment');
                if (response.assigned && response.employee_name) {
                    currentAssignment.show();
                    $('#current-assigned-name').text(response.employee_name);
                    $('#assigned_employee').val(response.emp_id);
                } else {
                    currentAssignment.hide();
                    $('#assigned_employee').val('');
                }
            }
        }
    });
    
    // Load employees and show modal
    loadIduEmployees();
    $('#assignEmployeeModal').modal('show');
});

// Assign employee form submission
$('#assignEmployeeForm').submit(function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: formData + '&action=assign_employee',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                toastr.success('Employee assigned successfully');
                $('#assignEmployeeModal').modal('hide');
                
                // Reload the assigned employee display
                const profileId = $('#assign_ia_profile_id').val();
                loadAssignedEmployee(profileId);
            } else {
                toastr.error('Error assigning employee: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            toastr.error('Error assigning employee. Please check console for details.');
        }
    });
});

// Load assigned employees when page loads
$(document).ready(function() {
    // ... existing code ...
    
    // Load assigned employees after table is initialized
    setTimeout(loadAllAssignedEmployees, 1000);
});
</script>
</body>
</html>