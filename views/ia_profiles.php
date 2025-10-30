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

        .assign-btn {
            cursor: pointer;
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
                                <?php if (hasPermission('add_ia_profile')): ?>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addIaProfileModal">
                                        <i class="fas fa-plus"></i> Add IA Profile
                                    </button>
                                </div>
                                <?php endif; ?>
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
                                            <td><?= htmlspecialchars($row['ia_name']) ?></td>
                                            <td><?= htmlspecialchars($row['president_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['contact_number'] ?? '') ?></td>
                                            <td><?= number_format($row['service_area_ha'] ?? 0, 2) ?></td>
                                            <td><?= $row['actual_ia_members'] ?? 0 ?></td>
                                            <td><?= $row['tsags_count'] ?? 0 ?></td>
                                            <td>
                                                <span class="badge badge-<?= $row['status'] == 'active' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm view-profile" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (hasPermission('edit_ia_profile')): ?>
                                                <button class="btn btn-primary btn-sm edit-profile" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (hasPermission('delete_ia_profile')): ?>
                                                <button class="btn btn-danger btn-sm delete-profile" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No IA Profiles found</td>
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
<!-- Add IA Profile Modal -->
<div class="modal fade" id="addIaProfileModal" tabindex="-1" role="dialog" aria-labelledby="addIaProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIaProfileModalLabel">Add IA Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addIaProfileForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ia_name">IA Name *</label>
                                <input type="text" class="form-control" id="ia_name" name="ia_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="operational">Operational</option>
                                    <option value="non-operational">Non-operational</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ia_code">IA Code</label>
                                <input type="text" class="form-control" id="ia_code" name="ia_code">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="region">Region *</label>
                                <select class="form-control" id="region" name="region" required>
                                    <option value="">Select Region</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="province">Province *</label>
                                <select class="form-control" id="province" name="province" required>
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="district">District *</label>
                                <select class="form-control" id="district" name="district" required>
                                    <option value="">Select District</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="mailing_address">Mailing Address</label>
                        <textarea class="form-control" id="mailing_address" name="mailing_address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="president_name">President Name</label>
                                <input type="text" class="form-control" id="president_name" name="president_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="date_organized">Date Organized</label>
                                <input type="date" class="form-control" id="date_organized" name="date_organized">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sec_registration_date">SEC Registration Date</label>
                                <input type="date" class="form-control" id="sec_registration_date" name="sec_registration_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sec_registration_number">SEC Registration Number</label>
                                <input type="text" class="form-control" id="sec_registration_number" name="sec_registration_number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="service_area_ha">Service Area (ha)</label>
                                <input type="number" step="0.01" class="form-control" id="service_area_ha" name="service_area_ha">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="fusa_ha">FUSA (ha)</label>
                                <input type="number" step="0.01" class="form-control" id="fusa_ha" name="fusa_ha">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ia_tin">IA TIN</label>
                                <input type="text" class="form-control" id="ia_tin" name="ia_tin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="farmer_beneficiaries">Farmer Beneficiaries</label>
                                <input type="number" class="form-control" id="farmer_beneficiaries" name="farmer_beneficiaries">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="actual_ia_members">Actual IA Members</label>
                                <input type="number" class="form-control" id="actual_ia_members" name="actual_ia_members">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tsags_count">No. of TSAGs</label>
                                <input type="number" class="form-control" id="tsags_count" name="tsags_count">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="existing_contract">Existing Contract</label>
                                <input type="text" class="form-control" id="existing_contract" name="existing_contract">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contract_effectivity_date">Contract Effectivity Date</label>
                                <input type="date" class="form-control" id="contract_effectivity_date" name="contract_effectivity_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="canal_length_km">Canal Length (km)</label>
                                <input type="number" step="0.001" class="form-control" id="canal_length_km" name="canal_length_km">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="male_members">Male Members</label>
                                <input type="number" class="form-control" id="male_members" name="male_members">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="female_members">Female Members</label>
                                <input type="number" class="form-control" id="female_members" name="female_members">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save IA Profile</button>
                </div>
            </form>
        </div>
    </div>
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
        "serverSide": false, // Change to false since we're handling data manually
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
            { "data": "ia_name" },
            { "data": "president_name" },
            { "data": "contact_number" },
            { 
                "data": "service_area_ha",
                "render": function(data, type, row) {
                    return data ? parseFloat(data).toFixed(2) : '0.00';
                }
            },
            { "data": "actual_ia_members" },
            { "data": "tsags_count" },
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
                    // This will be populated via AJAX
                    return '<div id="assigned-employee-' + data + '">Loading...</div>';
                }
            },
            {
                "data": "id",
                "render": function(data, type, row) {
                    let buttons = '<button class="btn btn-info btn-sm view-profile" data-id="' + data + '"><i class="fas fa-eye"></i></button>';
                    <?php if (hasPermission('edit_ia_profile')): ?>
                    buttons += ' <button class="btn btn-primary btn-sm edit-profile" data-id="' + data + '"><i class="fas fa-edit"></i></button>';
                    <?php endif; ?>
                    // Add assign button
                    buttons += ' <button class="btn btn-warning btn-sm assign-employee" data-id="' + data + '" title="Assign Employee"><i class="fas fa-user-plus"></i></button>';
                    <?php if (hasPermission('delete_ia_profile')): ?>
                    buttons += ' <button class="btn btn-danger btn-sm delete-profile" data-id="' + data + '"><i class="fas fa-trash"></i></button>';
                    <?php endif; ?>
                    return buttons;
                },
                "orderable": false,
                "searchable": false
            }
        ],
        "language": {
            "emptyTable": "No IA Profiles found",
            "zeroRecords": "No matching records found"
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

    // Add IA Profile Form Submission
    $('#addIaProfileForm').submit(function(e) {
        e.preventDefault();
        
        // Get the actual values from dropdowns
        const regionSelect = document.getElementById('region');
        const provinceSelect = document.getElementById('province');
        const districtSelect = document.getElementById('district');
        
        const regionText = regionSelect.options[regionSelect.selectedIndex].text;
        const provinceText = provinceSelect.options[provinceSelect.selectedIndex].text;
        const districtText = districtSelect.options[districtSelect.selectedIndex].text;
        
        // Create hidden inputs or modify form data
        let formData = $(this).serialize();
        formData += '&region_text=' + encodeURIComponent(regionText);
        formData += '&province_text=' + encodeURIComponent(provinceText); // ADD THIS LINE
        formData += '&district_text=' + encodeURIComponent(districtText);
        
        $.ajax({
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: formData + '&action=add',
            dataType: 'json',
            success: function(response) {
                console.log('Add response:', response);
                if (response.success) {
                    toastr.success('IA Profile added successfully');
                    $('#addIaProfileModal').modal('hide');
                    // Reload the page to show new data
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error('Error adding IA Profile: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                toastr.error('Error adding IA Profile. Please check console for details.');
            }
        });
    });

    // View IA Profile - Redirect to new page
    $(document).on('click', '.view-profile', function() {
        var profileId = $(this).data('id');
        window.location.href = 'ia_profile_view.php?id=' + profileId;
    });

    // Delete IA Profile
    $(document).on('click', '.delete-profile', function() {
        var profileId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this IA Profile?')) {
            $.ajax({
                url: '../includes/ia_profiles_ajax.php',
                type: 'POST',
                data: {action: 'delete', id: profileId},
                dataType: 'json',
                success: function(response) {
                    console.log('Delete response:', response);
                    if (response.success) {
                        toastr.success('IA Profile deleted successfully');
                        // Reload the page to reflect changes
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        toastr.error('Error deleting IA Profile: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    toastr.error('Error deleting IA Profile. Please check console for details.');
                }
            });
        }
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
    $('#iaProfilesTable tbody tr').each(function() {
        const profileId = $(this).find('.assign-employee').data('id');
        if (profileId) {
            loadAssignedEmployee(profileId);
        }
    });
}

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