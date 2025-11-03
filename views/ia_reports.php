<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to generate reports
if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = "IA Reports";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IA Reports - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .report-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #218838, #1e9e8a);
            color: white;
        }
        
        .import-section {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-top: 30px;
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
                        <h1>IA Reports</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">IA Reports</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <!-- Generate Report Section -->
                        <div class="card report-section">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-file-excel mr-2"></i>Generate IA Profile Report
                                </h3>
                            </div>
                            <div class="card-body">
                                <form id="generateReportForm">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="report_region">Region</label>
                                                <select class="form-control" id="report_region" name="region">
                                                    <option value="">All Regions</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="report_province">Province</label>
                                                <select class="form-control" id="report_province" name="province">
                                                    <option value="">All Provinces</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="report_district">Congressional District</label>
                                                <select class="form-control" id="report_district" name="congressional_district">
                                                    <option value="">All Districts</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="report_status">Status</label>
                                                <select class="form-control" id="report_status" name="status">
                                                    <option value="">All Status</option>
                                                    <option value="operational">Operational</option>
                                                    <option value="non-operational">Non-operational</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="report_month_year">Report Period</label>
                                                <input type="month" class="form-control" id="report_month_year" name="report_period">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="date_organized_from">Date Organized From</label>
                                                <input type="date" class="form-control" id="date_organized_from" name="date_organized_from">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="date_organized_to">Date Organized To</label>
                                                <input type="date" class="form-control" id="date_organized_to" name="date_organized_to">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-download">
                                                <i class="fas fa-download mr-2"></i>Generate Excel Report
                                            </button>
                                            <button type="button" id="resetFilters" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-2"></i>Reset Filters
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Import Section -->
                        <div class="card import-section">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-file-import mr-2"></i>Import IA Profiles from Excel
                                </h3>
                            </div>
                            <div class="card-body">
                                <form id="importForm" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="import_file">Select Excel File</label>
                                                <input type="file" class="form-control-file" id="import_file" name="import_file" accept=".xlsx,.xls" required>
                                                <small class="form-text text-muted">
                                                    Please use the standard IA Profile template format (.xlsx or .xls)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <div>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-upload mr-2"></i>Import Profiles
                                                    </button>
                                                    <a href="../public/templates/R5_IA-PROFILE_Template.xlsx" class="btn btn-outline-secondary" download>
                                                        <i class="fas fa-download mr-2"></i>Download Template
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                                <div id="importResults" style="display: none;">
                                    <div class="alert alert-success mt-3">
                                        <h5><i class="fas fa-check-circle"></i> Import Successful</h5>
                                        <p id="importSummary"></p>
                                        <div id="importErrors" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
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
    // Load regions and provinces
    loadReportRegions();
    
    // Region change event
    $('#report_region').change(function() {
        const regionCode = $(this).val();
        if (regionCode) {
            loadReportProvinces(regionCode);
        } else {
            $('#report_province').html('<option value="">All Provinces</option>');
            $('#report_district').html('<option value="">All Districts</option>');
        }
    });

    // Province change event
    $('#report_province').change(function() {
        const provinceCode = $(this).val();
        if (provinceCode) {
            loadReportDistricts(provinceCode);
        } else {
            $('#report_district').html('<option value="">All Districts</option>');
        }
    });

    // Generate report form submission - DEBUG VERSION
    $('#generateReportForm').submit(function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Generating Report...');
        submitBtn.prop('disabled', true);
        
        // Get form data
        const formData = $(this).serialize();
        console.log('Form data:', formData);
        
        // Create a new XMLHttpRequest
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '../includes/ia_profiles_ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.responseType = 'blob';
        
        xhr.onload = function() {
            console.log('XHR Status:', xhr.status);
            console.log('XHR Response Type:', xhr.responseType);
            console.log('XHR Response:', xhr.response);
            
            if (xhr.status === 200) {
                const contentType = xhr.getResponseHeader('Content-Type');
                console.log('Content-Type:', contentType);
                
                if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                    // It's an Excel file
                    const blob = new Blob([xhr.response], { 
                        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    
                    let filename = 'IA_Profile_Report_' + new Date().toISOString().split('T')[0] + '.xlsx';
                    const contentDisposition = xhr.getResponseHeader('Content-Disposition');
                    if (contentDisposition) {
                        const filenameMatch = contentDisposition.match(/filename="?(.+)"?/);
                        if (filenameMatch && filenameMatch.length === 2) {
                            filename = filenameMatch[1].replace(/"/g, '');
                        }
                    }
                    
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    toastr.success('Report generated successfully!');
                } else {
                    // Try to read as JSON error
                    const reader = new FileReader();
                    reader.onload = function() {
                        try {
                            const response = JSON.parse(reader.result);
                            console.error('Server error:', response);
                            toastr.error('Error generating report: ' + (response.message || 'Unknown error'));
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            console.error('Raw response:', reader.result);
                            toastr.error('Error generating report. Please check console for details.');
                        }
                    };
                    reader.readAsText(xhr.response);
                }
            } else {
                toastr.error('Error generating report. Server returned status: ' + xhr.status);
            }
            
            // Reset button
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        };
        
        xhr.onerror = function() {
            console.error('XHR Error occurred');
            toastr.error('Error generating report. Please check your connection and try again.');
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        };
        
        // Send the request
        console.log('Sending request...');
        xhr.send(formData + '&action=generate_report');
    });

    // Import form submission
    $('#importForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'import_profiles');
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Importing...');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#importSummary').html(
                        `Successfully imported <strong>${response.imported_count}</strong> IA profiles.`
                    );
                    
                    if (response.errors && response.errors.length > 0) {
                        $('#importErrors').html(
                            `<h6>Errors encountered:</h6><ul>` +
                            response.errors.map(error => `<li>${error}</li>`).join('') +
                            `</ul>`
                        ).show();
                    } else {
                        $('#importErrors').hide();
                    }
                    
                    $('#importResults').show();
                    toastr.success('Import completed successfully!');
                } else {
                    toastr.error('Import failed: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error importing file:', error);
                toastr.error('Error importing file. Please check the format and try again.');
            },
            complete: function() {
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        });
    });

    // Reset filters
    $('#resetFilters').click(function() {
        $('#generateReportForm')[0].reset();
        $('#report_province').html('<option value="">All Provinces</option>');
        $('#report_district').html('<option value="">All Districts</option>');
    });
});

function loadReportRegions() {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_regions'},
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const regionSelect = $('#report_region');
                regionSelect.html('<option value="">All Regions</option>');
                
                response.data.forEach(region => {
                    regionSelect.append(new Option(region.region_name, region.region_code));
                });
            }
        }
    });
}

function loadReportProvinces(regionCode) {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_provinces', region_code: regionCode},
        dataType: 'json',
        success: function(response) {
            const provinceSelect = $('#report_province');
            provinceSelect.html('<option value="">All Provinces</option>');
            
            if (response.success) {
                response.data.forEach(province => {
                    provinceSelect.append(new Option(province.province_name, province.province_code));
                });
            }
        }
    });
}

function loadReportDistricts(provinceCode) {
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: {action: 'get_districts', province_code: provinceCode},
        dataType: 'json',
        success: function(response) {
            const districtSelect = $('#report_district');
            districtSelect.html('<option value="">All Districts</option>');
            
            if (response.success) {
                response.data.forEach(district => {
                    districtSelect.append(new Option(district.district_name, district.district_code));
                });
            }
        }
    });
}
</script>
</body>
</html>