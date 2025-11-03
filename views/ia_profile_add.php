<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to add IA Profiles
if (!hasPermission('add_ia_profile')) {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = "Add IA Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add IA Profile - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
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
                        <h1>Add IA Profile</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="ia_profiles.php">IA Profiles</a></li>
                            <li class="breadcrumb-item active">Add IA Profile</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">IA Profile Information</h3>
                            </div>
                            <form id="addIaProfileForm" method="POST">
                                <div class="card-body">
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
                                    </div>
                                    
                                    <div class="row">
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
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-2"></i>Save IA Profile
                                    </button>
                                    <a href="ia_profiles.php" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
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
        formData += '&province_text=' + encodeURIComponent(provinceText);
        formData += '&district_text=' + encodeURIComponent(districtText);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...');
        
        $.ajax({
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: formData + '&action=add',
            dataType: 'json',
            success: function(response) {
                console.log('Add response:', response);
                if (response.success) {
                    // SweetAlert success message
                    Swal.fire({
                        title: 'Success!',
                        text: 'IA Profile added successfully',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        // Redirect to profiles page after success
                        window.location.href = 'ia_profiles.php';
                    });
                } else {
                    // SweetAlert error message
                    Swal.fire({
                        title: 'Error!',
                        text: 'Error adding IA Profile: ' + response.message,
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    // Re-enable button
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                
                // SweetAlert error message
                Swal.fire({
                    title: 'Error!',
                    text: 'Error adding IA Profile. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });
                
                // Re-enable button
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
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
        }
    });
}
</script>
</body>
</html>