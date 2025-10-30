<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to access IA Profiles
if (!hasPermission('manage_ia_profiles')) {
    header('Location: ../unauthorized.php');
    exit();
}

$page_title = "Advanced Search - IA Profiles";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced Search - IA Profiles - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .search-card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        .search-card:hover {
            transform: translateY(-5px);
        }
        .search-card .card-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            font-weight: 600;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #3498db;
        }
        .filter-section h6 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
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
        .result-count {
            background: #e8f4fd;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #3498db;
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .no-results i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        .toggle-filters {
            cursor: pointer;
            color: #3498db;
            font-weight: 600;
        }
        .toggle-filters:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        .range-inputs {
            display: flex;
            gap: 10px;
        }
        .range-inputs .form-group {
            flex: 1;
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
                        <h1>Advanced Search - IA Profiles</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="ia_profiles.php">IA Profiles</a></li>
                            <li class="breadcrumb-item active">Advanced Search</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card search-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-search mr-2"></i>
                                    Search Filters
                                </h3>
                                <span class="toggle-filters" id="toggleFilters">
                                    <i class="fas fa-chevron-down mr-1"></i> Show/Hide Filters
                                </span>
                            </div>
                            <div class="card-body" id="searchFilters">
                                <form id="searchForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="filter-section">
                                                <h6><i class="fas fa-info-circle mr-2"></i>Basic Information</h6>
                                                <div class="form-group">
                                                    <label for="ia_name">IA Name</label>
                                                    <input type="text" class="form-control" id="ia_name" name="ia_name" placeholder="Enter IA name">
                                                </div>
                                                <div class="form-group">
                                                    <label for="ia_code">IA Code</label>
                                                    <input type="text" class="form-control" id="ia_code" name="ia_code" placeholder="Enter IA code">
                                                </div>
                                                <div class="form-group">
                                                    <label for="president_name">President Name</label>
                                                    <input type="text" class="form-control" id="president_name" name="president_name" placeholder="Enter president name">
                                                </div>
                                                <div class="form-group">
                                                    <label for="status">Status</label>
                                                    <select class="form-control" id="status" name="status">
                                                        <option value="">All Statuses</option>
                                                        <option value="active">Active</option>
                                                        <option value="inactive">Inactive</option>
                                                        <option value="operational">Operational</option>
                                                        <option value="non-operational">Non-operational</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="filter-section">
                                                <h6><i class="fas fa-map-marker-alt mr-2"></i>Location</h6>
                                                <div class="form-group">
                                                    <label for="region">Region</label>
                                                    <select class="form-control" id="region" name="region">
                                                        <option value="">Select Region</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="province">Province</label>
                                                    <select class="form-control" id="province" name="province">
                                                        <option value="">Select Province</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="congressional_district">Congressional District</label>
                                                    <select class="form-control" id="congressional_district" name="congressional_district">
                                                        <option value="">Select Province</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="filter-section">
                                                <h6><i class="fas fa-calendar-alt mr-2"></i>Date Filters</h6>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="date_organized_from">Date Organized (From)</label>
                                                            <input type="date" class="form-control" id="date_organized_from" name="date_organized_from">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="date_organized_to">Date Organized (To)</label>
                                                            <input type="date" class="form-control" id="date_organized_to" name="date_organized_to">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="sec_registration_date">SEC Registration Date</label>
                                                            <input type="date" class="form-control" id="sec_registration_date" name="sec_registration_date">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-12 text-center">
                                            <button type="submit" class="btn btn-primary btn-modern mr-2">
                                                <i class="fas fa-search mr-2"></i>Search
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-modern" id="resetFilters">
                                                <i class="fas fa-redo mr-2"></i>Reset Filters
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="result-count" id="resultCount" style="display: none;">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span id="resultText">Found 0 results</span>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Search Results</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-success btn-sm" id="exportResults">
                                        <i class="fas fa-file-export mr-1"></i> Export Results
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="searchResults">
                                    <div class="no-results">
                                        <i class="fas fa-search"></i>
                                        <h4>No Search Performed Yet</h4>
                                        <p>Use the filters above to search for IA Profiles</p>
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
    // Load regions on page load
    loadRegions();
    
    // Toggle filters visibility
    $('#toggleFilters').click(function() {
        $('#searchFilters').slideToggle();
        const icon = $(this).find('i');
        if (icon.hasClass('fa-chevron-down')) {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });
    
    // Region change event
    $('#region').change(function() {
        const regionCode = $(this).val();
        if (regionCode) {
            loadProvinces(regionCode);
        } else {
            $('#province').html('<option value="">Select Province</option>');
        }
    });
    
    // Search form submission
    $('#searchForm').submit(function(e) {
        e.preventDefault();
        performSearch();
    });
    
    // Reset filters
    $('#resetFilters').click(function() {
        $('#searchForm')[0].reset();
        $('#province').html('<option value="">Select Province</option>');
        $('#searchResults').html(`
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h4>No Search Performed Yet</h4>
                <p>Use the filters above to search for IA Profiles</p>
            </div>
        `);
        $('#resultCount').hide();
    });
    
    // Export results
    $('#exportResults').click(function() {
        exportResults();
    });
});

function loadRegions() {
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
            } else {
                console.error('Regions API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading regions:', error);
        }
    });
}

function loadProvinces(regionCode) {
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
            } else {
                console.error('Provinces API error:', response.message);
            }
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
            const districtSelect = $('#congressional_district');
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

function performSearch() {
    const formData = $('#searchForm').serialize();
    
    $.ajax({
        url: '../includes/ia_profiles_ajax.php',
        type: 'POST',
        data: formData + '&action=search_ia_profiles',
        dataType: 'json',
        beforeSend: function() {
            $('#searchResults').html(`
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Searching...</p>
                </div>
            `);
        },
        success: function(response) {
            if (response.success) {
                displayResults(response.data);
                $('#resultText').text(`Found ${response.data.length} result(s)`);
                $('#resultCount').show();
            } else {
                $('#searchResults').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Error: ${response.message}
                    </div>
                `);
                $('#resultCount').hide();
            }
        },
        error: function(xhr, status, error) {
            console.error('Search error:', error);
            $('#searchResults').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Error performing search. Please try again.
                </div>
            `);
            $('#resultCount').hide();
        }
    });
}

function displayResults(results) {
    if (results.length === 0) {
        $('#searchResults').html(`
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h4>No Results Found</h4>
                <p>Try adjusting your search criteria</p>
            </div>
        `);
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>IA Name</th>
                        <th>President</th>
                        <th>Contact</th>
                        <th>Service Area (ha)</th>
                        <th>Members</th>
                        <th>TSAGs</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    results.forEach(profile => {
        html += `
            <tr>
                <td>${escapeHtml(profile.ia_name)}</td>
                <td>${escapeHtml(profile.president_name || '')}</td>
                <td>${escapeHtml(profile.contact_number || '')}</td>
                <td>${parseFloat(profile.service_area_ha || 0).toFixed(2)}</td>
                <td>${profile.actual_ia_members || 0}</td>
                <td>${profile.tsags_count || 0}</td>
                <td>
                    <span class="badge badge-${(profile.status === 'active' || profile.status === 'operational') ? 'success' : 'danger'}">
                        ${profile.status ? profile.status.charAt(0).toUpperCase() + profile.status.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-info btn-sm view-profile" data-id="${profile.id}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (hasPermission('edit_ia_profile')): ?>
                    <button class="btn btn-primary btn-sm edit-profile" data-id="${profile.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $('#searchResults').html(html);
    
    // Add event handlers for view and edit buttons
    $('.view-profile').click(function() {
        const profileId = $(this).data('id');
        window.location.href = 'ia_profile_view.php?id=' + profileId;
    });
    
    $('.edit-profile').click(function() {
        const profileId = $(this).data('id');
        // Implement edit functionality as needed
        alert('Edit functionality for profile ID: ' + profileId);
    });
}

function exportResults() {
    const formData = $('#searchForm').serialize();
    window.open('../includes/ia_profiles_ajax.php?' + formData + '&action=export_ia_profiles', '_blank');
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
}
</script>
</body>
</html>