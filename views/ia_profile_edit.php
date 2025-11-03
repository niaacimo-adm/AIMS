<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to edit IA Profiles
if (!hasPermission('edit_ia_profile')) {
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

$page_title = "Edit IA Profile - " . htmlspecialchars($profile['ia_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> - NIA ACIMO</title>
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
                        <h1>Edit IA Profile</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="ia_profiles.php">IA Profiles</a></li>
                            <li class="breadcrumb-item active">Edit IA Profile</li>
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
                                <h3 class="card-title">Edit IA Profile Information</h3>
                            </div>
                            <form id="editIaProfileForm" method="POST">
                                <input type="hidden" name="id" value="<?= $profile['id'] ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="ia_name">IA Name *</label>
                                                <input type="text" class="form-control" id="ia_name" name="ia_name" value="<?= htmlspecialchars($profile['ia_name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status">Status *</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="operational" <?= $profile['status'] == 'operational' ? 'selected' : '' ?>>Operational</option>
                                                    <option value="non-operational" <?= $profile['status'] == 'non-operational' ? 'selected' : '' ?>>Non-operational</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="ia_code">IA Code</label>
                                                <input type="text" class="form-control" id="ia_code" name="ia_code" value="<?= htmlspecialchars($profile['ia_code']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="imo">IMO</label>
                                                <input type="text" class="form-control" id="imo" name="imo" value="<?= htmlspecialchars($profile['imo']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="mailing_address">Mailing Address</label>
                                                <textarea class="form-control" id="mailing_address" name="mailing_address" rows="3"><?= htmlspecialchars($profile['mailing_address']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="president_name">President Name</label>
                                                <input type="text" class="form-control" id="president_name" name="president_name" value="<?= htmlspecialchars($profile['president_name']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="contact_number">Contact Number</label>
                                                <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= htmlspecialchars($profile['contact_number']) ?>">
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
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="date_organized">Date Organized</label>
                                                <input type="date" class="form-control" id="date_organized" name="date_organized" value="<?= $profile['date_organized'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sec_registration_date">SEC Registration Date</label>
                                                <input type="date" class="form-control" id="sec_registration_date" name="sec_registration_date" value="<?= $profile['sec_registration_date'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sec_registration_number">SEC Registration Number</label>
                                                <input type="text" class="form-control" id="sec_registration_number" name="sec_registration_number" value="<?= htmlspecialchars($profile['sec_registration_number']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="ia_tin">IA TIN</label>
                                                <input type="text" class="form-control" id="ia_tin" name="ia_tin" value="<?= htmlspecialchars($profile['ia_tin']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="existing_contract">Existing Contract</label>
                                                <input type="text" class="form-control" id="existing_contract" name="existing_contract" value="<?= htmlspecialchars($profile['existing_contract']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="contract_effectivity_date">Contract Effectivity Date</label>
                                                <input type="date" class="form-control" id="contract_effectivity_date" name="contract_effectivity_date" value="<?= $profile['contract_effectivity_date'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="service_area_ha">Service Area (ha)</label>
                                                <input type="number" step="0.01" class="form-control" id="service_area_ha" name="service_area_ha" value="<?= $profile['service_area_ha'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="fusa_ha">FUSA (ha)</label>
                                                <input type="number" step="0.01" class="form-control" id="fusa_ha" name="fusa_ha" value="<?= $profile['fusa_ha'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="canal_length_km">Canal Length (km)</label>
                                                <input type="number" step="0.001" class="form-control" id="canal_length_km" name="canal_length_km" value="<?= $profile['canal_length_km'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="farmer_beneficiaries">Farmer Beneficiaries</label>
                                                <input type="number" class="form-control" id="farmer_beneficiaries" name="farmer_beneficiaries" value="<?= $profile['farmer_beneficiaries'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="actual_ia_members">Actual IA Members</label>
                                                <input type="number" class="form-control" id="actual_ia_members" name="actual_ia_members" value="<?= $profile['actual_ia_members'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="tsags_count">TSAGs Count</label>
                                                <input type="number" class="form-control" id="tsags_count" name="tsags_count" value="<?= $profile['tsags_count'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="male_members">Male Members</label>
                                                <input type="number" class="form-control" id="male_members" name="male_members" value="<?= $profile['male_members'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="female_members">Female Members</label>
                                                <input type="number" class="form-control" id="female_members" name="female_members" value="<?= $profile['female_members'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-2"></i>Update IA Profile
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
    // Load regions and set current values
    loadRegions('<?= $profile['region'] ?>', '<?= $profile['province'] ?>', '<?= $profile['congressional_district'] ?>');
    
    // Edit IA Profile Form Submission
    $('#editIaProfileForm').submit(function(e) {
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
        
        Swal.fire({
            title: 'Updating IA Profile',
            text: 'Please wait...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: formData + '&action=update',
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'IA Profile updated successfully',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = 'ia_profiles.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to update IA Profile',
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                Swal.fire({
                    title: 'Error!',
                    text: 'Error updating IA Profile. Please check console for details.',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 1500
                });
                console.error('AJAX Error:', error);
            }
        });
    });
});

function loadRegions(currentRegion = '', currentProvince = '', currentDistrict = '') {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_regions'},
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const regionSelect = $('#region');
                regionSelect.html('<option value="">Select Region</option>');
                
                response.data.forEach(region => {
                    regionSelect.append(new Option(region.region_name, region.region_code));
                });
                
                // Set current region if provided
                if (currentRegion) {
                    // Try to find the region by name first, then by code
                    let foundRegion = false;
                    response.data.forEach(region => {
                        if (region.region_name === currentRegion || region.region_code === currentRegion) {
                            regionSelect.val(region.region_code);
                            foundRegion = true;
                        }
                    });
                    
                    if (foundRegion) {
                        // Load provinces with the actual region code
                        loadProvinces(regionSelect.val(), currentProvince, currentDistrict);
                    } else {
                        // Auto-select Region V as fallback
                        regionSelect.val('V');
                        regionSelect.trigger('change');
                    }
                } else {
                    // Auto-select Region V
                    regionSelect.val('V');
                    regionSelect.trigger('change');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading regions:', error);
        }
    });
}

function loadProvinces(regionCode, currentProvince = '', currentDistrict = '') {
    if (!regionCode) return;
    
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_provinces', region_code: regionCode},
        dataType: 'json',
        success: function(response) {
            const provinceSelect = $('#province');
            provinceSelect.html('<option value="">Select Province</option>');
            
            if (response.success) {
                response.data.forEach(province => {
                    provinceSelect.append(new Option(province.province_name, province.province_code));
                });
                
                // Set current province if provided
                if (currentProvince) {
                    let foundProvince = false;
                    response.data.forEach(province => {
                        // Match by province name (since database stores the name)
                        if (province.province_name === currentProvince) {
                            provinceSelect.val(province.province_code);
                            foundProvince = true;
                        }
                    });
                    
                    if (foundProvince) {
                        // Load districts with the actual province code
                        loadDistricts(provinceSelect.val(), currentDistrict);
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading provinces:', error);
        }
    });
}

function loadDistricts(provinceCode, currentDistrict = '') {
    if (!provinceCode) return;
    
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_districts', province_code: provinceCode},
        dataType: 'json',
        success: function(response) {
            const districtSelect = $('#district');
            districtSelect.html('<option value="">Select District</option>');
            
            if (response.success) {
                response.data.forEach(district => {
                    districtSelect.append(new Option(district.district_name, district.district_code));
                });
                
                // Set current district if provided
                if (currentDistrict) {
                    let foundDistrict = false;
                    response.data.forEach(district => {
                        // Match by district name (since database stores the name)
                        if (district.district_name === currentDistrict) {
                            districtSelect.val(district.district_code);
                            foundDistrict = true;
                        }
                    });
                    
                    if (!foundDistrict) {
                        // If exact match not found, try partial match
                        response.data.forEach(district => {
                            if (currentDistrict.includes(district.district_name) || district.district_name.includes(currentDistrict)) {
                                districtSelect.val(district.district_code);
                                foundDistrict = true;
                            }
                        });
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading districts:', error);
        }
    });
}

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
</script>
</body>
</html>