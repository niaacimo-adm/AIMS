<?php
session_start();
require_once 'config/database.php';

// Get carousel images
$database = new Database();
$db = $database->getConnection();

// Fetch active carousel images
$carousel_stmt = $db->prepare("SELECT * FROM carousel_images WHERE is_active = TRUE ORDER BY display_order, created_at DESC");
$carousel_stmt->execute();
$carousel_images = $carousel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch company information
$company_stmt = $db->prepare("SELECT * FROM company_info WHERE is_active = TRUE ORDER BY id");
$company_stmt->execute();
$company_info = $company_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch active forms
$forms_stmt = $db->prepare("SELECT * FROM company_forms WHERE is_active = TRUE ORDER BY created_at DESC");
$forms_stmt->execute();
$company_forms = $forms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ACIMO Intelligent Management Solution (AIMS)</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- IonIcons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="plugins/fullcalendar/main.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #28a745; /* Green */
            --primary-dark: #1e7e34; /* Darker green */
            --secondary-color: #6c757d; /* Grey */
            --light-color: #f8f9fa; /* Light grey/white */
            --dark-color: #343a40; /* Dark grey/black */
            --accent-color: #17a2b8; /* Light blue */
        }
        
        body {
            background: url("dist/img/OGSONG.JPG") no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
            position: relative;
            z-index: 1;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.45); /* adjust darkness */
            z-index: -1;
        }
                
        .hero-section h1 {
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .hero-section p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }
        
        .login-btn {
            background: #fff;
            color: #007b3e;
            font-weight: bold;
            border-radius: 30px;
            padding: 12px 25px;
            box-shadow: 0px 5px 15px rgba(0,0,0,0.4); /* Increased shadow visibility */
            transition: all 0.3s ease-in-out;
        }
        .login-btn:hover {
            background: #007b3e;
            color: #fff;
        }
        
        .section-padding {
            padding: 80px 0;
            text-align: center;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 50px;
            text-align: center;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: var(--primary-color);
            margin: 15px auto 0;
        }
        
        .about-section {
            background: transparent;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3); /* Increased shadow visibility */
            transition: transform 0.3s ease;
            height: 100%;
        }

        
        .about-section:hover {
            transform: translateY(-5px);
        }
        
        .about-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
                
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.3); /* Increased shadow visibility */
            transition: transform 0.3s ease;
            cursor: pointer;
            height: 200px;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover img {
            transform: scale(1.05);
        }
        
        .gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 10px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover .gallery-caption {
            transform: translateY(0);
        }
        
        .events-section {
            background: var(--light-color);
        }
        
        /* Update calendar box shadow */
        #calendar {
            position: relative;
            border-radius: 8px;
            box-shadow: 0 3px 5px rgba(0,0,0,0.2);
            padding: 20px;
            color: #fff !important;
        }

        #calendar::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            border-radius: 8px;
            z-index: 1;
        }

        #calendar > * {
            position: relative;
            z-index: 2;
        }

        /* Force all calendar text to be white */
        #calendar,
        #calendar * {
            color: #fff !important;
        }

        /* Calendar header and navigation buttons */
        .fc-toolbar-title,
        .fc-col-header-cell,
        .fc-daygrid-day-number,
        .fc-timegrid-slot-label,
        .fc-event-title {
            color: #fff !important;
        }

        /* Calendar buttons */
        .fc-button {
            color: #fff !important;
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .fc-button:hover {
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
        }
        
        
        .nav-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .nav-tabs .nav-link {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1); /* Very subtle white */
            transition: transform 0.3s ease;
            height: 100%;
            background: rgba(255, 255, 255, 0.98);
        }

        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.15);
        }
        
        .form-card .card-body {
            text-align: center;
            padding: 30px 20px;
        }
        
        .form-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .form-card .card-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .form-card .card-text {
            color: var(--dark-color);
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        footer {
            background: var(--dark-color);
            color: white;
            padding: 40px 0 20px;
        }
        
        .footer-logo {
            margin-bottom: 20px;
        }
        
        .footer-text {
            opacity: 0.8;
            margin-bottom: 10px;
        }
        
        /* Scroll to top button */
        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 6px 15px rgba(0,0,0,0.4); /* Increased shadow visibility */
            transition: all 0.3s ease;
        }
        
        .scroll-to-top:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Fixed navbar offset */
        html {
            scroll-padding-top: 80px;
        }
        
        /* Loading spinner */
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Pagination styling */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .page-link {
            color: var(--primary-color);
        }

        .page-link:hover {
            color: var(--primary-dark);
        }

        /* Search box styling */
        .search-box {
            max-width: 400px;
            margin: 0 auto 30px;
        }

        /* Modal styling */
        .modal-image {
            width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        /* Gallery controls */
        .gallery-controls, .forms-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .gallery-controls, .forms-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .hero-section {
                padding: 100px 0 60px;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
        }
        
        /* Navigation styling */
        .navbar {
            background: var(--dark-color) !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); /* Increased shadow visibility */
            padding: 10px 0;
        }
                
        .navbar-brand {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand img {
            margin-right: 10px;
        }
        
        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
            margin: 0 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: var(--light-color) !important;
        }
        
        .navbar-nav .login-btn {
            background: var(--primary-color);
            color: white !important;
            padding: 8px 20px;
            margin-left: 10px;
        }

        .navbar-nav .login-btn:hover {
            background: var(--primary-dark);
            color: white !important;
        }

        /* Calendar event colors */
        .fc-event {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .badge-primary {
            background-color: var(--primary-color);
        }

        .badge-info {
            background-color: var(--accent-color);
        }

        .badge-warning {
            background-color: #ffc107;
        }

        .badge-success {
            background-color: var(--primary-color);
        }
        /* Hero Section */
        #home.hero-section {
            background: url("dist/img/OGSONG.JPG") no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            text-align: center;
            color: #fff; 
            position: relative;
            z-index: 1;
        }

        /* Dark overlay for readability */
        #home.hero-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.45); /* darker overlay */
            z-index: -1;
        }

        .display-3 {
            font-weight: 700;
            text-shadow: 
                -2px -2px 4px rgba(0,0,0,0.9),
                2px -2px 4px rgba(0,0,0,0.9),
                -2px  2px 4px rgba(0,0,0,0.9),
                2px  2px 4px rgba(0,0,0,0.9);
        }

        p, .lead {
            color: #f0f0f0;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.8);
        }
        /* Lead paragraph under headline */
        .hero-section .lead {
            color: #e0e0e0; /* softer gray for contrast */
            font-size: 1.2rem;
            text-shadow: 0px 1px 3px rgba(0,0,0,0.8);
            margin-bottom: 25px;
        }
       section {
            background: transparent !important;
            color: #fff; /* text stays white */
        }
    </style>
</head>
<body>
    <!-- Scroll to top button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="40" class="d-inline-block align-middle">
                <span class="align-middle">NIA Albay-Catanduanes IMO</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="#forms">Forms</a></li>
                    <li class="nav-item"><a class="nav-link login-btn" href="login.php">Sign In</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <h1 class="display-3 mb-8">Welcome to NIA Albay-Catanduanes Irrigation Management Office</h1>
            <p class="lead mb-4">National Irrigation Administration - Providing efficient irrigation services for sustainable agriculture</p>
            <a href="login.php" class="login-btn btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i> Employee Sign In
            </a>
        </div>
    </section>


    <!-- About Section -->
    <section id="about" class="section-padding">
        <div class="container">
            <h1 class="lead-4 mb-4"><b>ABOUT OUR OFFICE</b></h1>
            <div class="row">
                <?php if (!empty($company_info)): ?>
                    <?php foreach ($company_info as $info): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="about-section">
                                <h3 class="about-title"><?= htmlspecialchars($info['section_name']) ?></h3>
                                <p class="card-text"><?= nl2br(htmlspecialchars($info['content'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="about-section">
                            <h3 class="about-title">About NIA Albay-Catanduanes IMO</h3>
                            <p class="text-muted">Company information will be updated soon.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="section-padding">
        <div class="container">
            <h2 class="section-title">Gallery</h2>
            
            <?php if (!empty($carousel_images)): ?>
                <div class="gallery-controls">
                    <div class="gallery-info">
                        Showing <span id="gallery-start">1</span>-<span id="gallery-end">6</span> of <span id="gallery-total"><?= count($carousel_images) ?></span> images
                    </div>
                </div>

                <div id="gallery-container">
                    <!-- Gallery pages will be loaded here by JavaScript -->
                </div>

                <div class="pagination-container">
                    <nav>
                        <ul class="pagination" id="gallery-pagination">
                            <!-- Pagination will be generated by JavaScript -->
                        </ul>
                    </nav>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-images fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No images available</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Image Preview</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="" class="modal-image" id="modalImage">
                    <p class="mt-3" id="modalCaption"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Events Section -->
    <section id="events" class="section-padding events-section">
        <div class="container">
            <h2 class="section-title">Upcoming Events</h2>
            
            <ul class="nav nav-tabs mb-4" id="eventsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="calendar-tab" data-toggle="tab" href="#calendar-view" role="tab">
                        <i class="fas fa-calendar-alt mr-2"></i>Calendar View
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="table-tab" data-toggle="tab" href="#table-view" role="tab">
                        <i class="fas fa-table mr-2"></i>Table View
                    </a>
                </li>
            </ul>
            
            <div class="tab-content" id="eventsTabsContent">
                <!-- Calendar View -->
                <div class="tab-pane fade show active" id="calendar-view" role="tabpanel">
                    <div id="calendar"></div>
                </div>
                
                <!-- Table View -->
                <div class="tab-pane fade" id="table-view" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="events-table" class="table table-striped table-hover" style="width:100%">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Event</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="events-table-body">
                                        <!-- Events will be loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="events-loading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading events...</span>
                                </div>
                                <p class="mt-2">Loading events...</p>
                            </div>
                            <div id="events-empty" class="text-center py-4" style="display: none;">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No upcoming events scheduled.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Forms Section -->
    <section id="forms" class="section-padding">
        <div class="container">
            <h2 class="section-title">Forms & Documents</h2>
            
            <?php if (!empty($company_forms)): ?>
                <div class="forms-control">
                    <div class="search-box">
                        <div class="input-group">
                            <input type="text" id="forms-search" class="form-control" placeholder="Search forms...">
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="forms-info">
                        Showing <span id="forms-start">1</span>-<span id="forms-end">6</span> of <span id="forms-total"><?= count($company_forms) ?></span> forms
                    </div>
                </div>

                <div id="forms-container">
                    <!-- Forms pages will be loaded here by JavaScript -->
                </div>

                <div class="pagination-container">
                    <nav>
                        <ul class="pagination" id="forms-pagination">
                            <!-- Pagination will be generated by JavaScript -->
                        </ul>
                    </nav>
                </div>
            <?php else: ?>
                <div class="col-12 text-center">
                    <div class="card form-card">
                        <div class="card-body">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No forms available at the moment.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <div class="footer-logo">
                <img src="dist/img/nialogo.png" alt="NIA Logo" height="50">
            </div>
            <p class="footer-text">&copy; <?= date('Y') ?> National Irrigation Administration - Albay Catanduanes IMO. All rights reserved.</p>
            <p class="footer-text">Providing efficient irrigation services for sustainable agricultural development</p>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="dist/js/adminlte.js"></script>
    <!-- DataTables -->
    <script src="plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="plugins/sweetalert2/sweetalert2.min.js"></script>
    <!-- Toastr -->
    <script src="plugins/toastr/toastr.min.js"></script>
    <!-- Select2 -->
    <script src="plugins/select2/js/select2.full.min.js"></script>
    <!-- FullCalendar -->
    <script src="plugins/fullcalendar/main.js"></script>
    <script src="plugins/moment/moment.min.js"></script>

    <script>
    $(document).ready(function() {
        // Gallery functionality
        const galleryItemsPerPage = 6;
        let currentGalleryPage = 1;
        let filteredGalleryItems = <?= json_encode($carousel_images) ?>;

        // Forms functionality
        const formsItemsPerPage = 6;
        let currentFormsPage = 1;
        let filteredFormsItems = <?= json_encode($company_forms) ?>;

        // Initialize Gallery
        function initializeGallery() {
            renderGalleryPage(currentGalleryPage);
            renderGalleryPagination();
        }

        // Render Gallery Page
        function renderGalleryPage(page) {
            const startIndex = (page - 1) * galleryItemsPerPage;
            const endIndex = startIndex + galleryItemsPerPage;
            const pageItems = filteredGalleryItems.slice(startIndex, endIndex);

            let galleryHtml = '<div class="gallery-grid">';
            
            pageItems.forEach((image, index) => {
                const imagePath = image.image_path.replace('../', '');
                const caption = image.caption || 'No caption';
                
                galleryHtml += `
                    <div class="gallery-item" onclick="openImageModal('${imagePath}', '${caption.replace(/'/g, "\\'")}')">
                        <img src="${imagePath}" alt="${caption}" onerror="this.src='dist/img/default-image.jpg'">
                        <div class="gallery-caption">
                            <p class="mb-0">${caption}</p>
                        </div>
                    </div>
                `;
            });
            
            galleryHtml += '</div>';
            
            $('#gallery-container').html(galleryHtml);
            updateGalleryInfo(startIndex + 1, Math.min(endIndex, filteredGalleryItems.length), filteredGalleryItems.length);
        }

        // Render Gallery Pagination
        function renderGalleryPagination() {
            const totalPages = Math.ceil(filteredGalleryItems.length / galleryItemsPerPage);
            let paginationHtml = '';

            if (totalPages > 1) {
                // Previous button
                paginationHtml += `
                    <li class="page-item ${currentGalleryPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changeGalleryPage(${currentGalleryPage - 1})">Previous</a>
                    </li>
                `;

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `
                        <li class="page-item ${i === currentGalleryPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changeGalleryPage(${i})">${i}</a>
                        </li>
                    `;
                }

                // Next button
                paginationHtml += `
                    <li class="page-item ${currentGalleryPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changeGalleryPage(${currentGalleryPage + 1})">Next</a>
                    </li>
                `;
            }

            $('#gallery-pagination').html(paginationHtml);
        }

        // Change Gallery Page
        window.changeGalleryPage = function(page) {
            const totalPages = Math.ceil(filteredGalleryItems.length / galleryItemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentGalleryPage = page;
                renderGalleryPage(page);
                renderGalleryPagination();
            }
        }

        // Update Gallery Info
        function updateGalleryInfo(start, end, total) {
            $('#gallery-start').text(start);
            $('#gallery-end').text(end);
            $('#gallery-total').text(total);
        }

        // Open Image Modal
        window.openImageModal = function(imagePath, caption) {
            $('#modalImage').attr('src', imagePath);
            $('#modalCaption').text(caption);
            $('#imageModal').modal('show');
        }

        // Initialize Forms
        function initializeForms() {
            renderFormsPage(currentFormsPage);
            renderFormsPagination();
        }

        // Render Forms Page
        function renderFormsPage(page) {
            const startIndex = (page - 1) * formsItemsPerPage;
            const endIndex = startIndex + formsItemsPerPage;
            const pageItems = filteredFormsItems.slice(startIndex, endIndex);

            let formsHtml = '<div class="forms-grid">';
            
            pageItems.forEach((form, index) => {
                const filePath = form.file_path.replace('../', '');
                const fileName = form.form_name;
                const description = form.description || 'No description';
                const fileExtension = filePath.split('.').pop().toLowerCase();
                
                let fileIcon = 'fa-file text-secondary';
                let iconColor = 'text-secondary';
                
                if (fileExtension === 'pdf') {
                    fileIcon = 'fa-file-pdf';
                    iconColor = 'text-danger';
                } else if (['doc', 'docx'].includes(fileExtension)) {
                    fileIcon = 'fa-file-word';
                    iconColor = 'text-primary';
                }
                
                formsHtml += `
                    <div class="card form-card">
                        <div class="card-body">
                            <div class="form-icon">
                                <i class="fas ${fileIcon} ${iconColor}"></i>
                            </div>
                            <h5 class="card-title">${fileName}</h5>
                            <p class="card-text">${description}</p>
                            <a href="${filePath}" class="btn btn-primary" target="_blank" download="${fileName}">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                `;
            });
            
            formsHtml += '</div>';
            
            $('#forms-container').html(formsHtml);
            updateFormsInfo(startIndex + 1, Math.min(endIndex, filteredFormsItems.length), filteredFormsItems.length);
        }

        // Render Forms Pagination
        function renderFormsPagination() {
            const totalPages = Math.ceil(filteredFormsItems.length / formsItemsPerPage);
            let paginationHtml = '';

            if (totalPages > 1) {
                // Previous button
                paginationHtml += `
                    <li class="page-item ${currentFormsPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changeFormsPage(${currentFormsPage - 1})">Previous</a>
                    </li>
                `;

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `
                        <li class="page-item ${i === currentFormsPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="changeFormsPage(${i})">${i}</a>
                        </li>
                    `;
                }

                // Next button
                paginationHtml += `
                    <li class="page-item ${currentFormsPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="changeFormsPage(${currentFormsPage + 1})">Next</a>
                    </li>
                `;
            }

            $('#forms-pagination').html(paginationHtml);
        }

        // Change Forms Page
        window.changeFormsPage = function(page) {
            const totalPages = Math.ceil(filteredFormsItems.length / formsItemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentFormsPage = page;
                renderFormsPage(page);
                renderFormsPagination();
            }
        }

        // Update Forms Info
        function updateFormsInfo(start, end, total) {
            $('#forms-start').text(start);
            $('#forms-end').text(end);
            $('#forms-total').text(total);
        }

        // Forms Search
        $('#forms-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            filteredFormsItems = <?= json_encode($company_forms) ?>.filter(form => 
                form.form_name.toLowerCase().includes(searchTerm) ||
                (form.description && form.description.toLowerCase().includes(searchTerm))
            );
            currentFormsPage = 1;
            renderFormsPage(currentFormsPage);
            renderFormsPagination();
        });

        // Initialize both galleries
        if (filteredGalleryItems.length > 0) {
            initializeGallery();
        }
        
        if (filteredFormsItems.length > 0) {
            initializeForms();
        }

        // Initialize DataTable
        var eventsTable = $('#events-table').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "emptyTable": "No events available"
            }
        });

        // Initialize FullCalendar
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: function(fetchInfo, successCallback, failureCallback) {
                $.ajax({
                    url: 'views/get_events.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            var events = response.data.map(function(event) {
                                return {
                                    id: event.id,
                                    title: event.title,
                                    start: event.start,
                                    end: event.end,
                                    description: event.description,
                                    type: event.type,
                                    backgroundColor: getEventColor(event.type),
                                    borderColor: getEventColor(event.type)
                                };
                            });
                            successCallback(events);
                        } else {
                            successCallback([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading calendar events:', error);
                        failureCallback(error);
                    }
                });
            },
            eventClick: function(info) {
                // Show event details when clicked
                Swal.fire({
                    title: info.event.title,
                    html: `<p><strong>Type:</strong> ${info.event.extendedProps.type}</p>
                           <p><strong>Description:</strong> ${info.event.extendedProps.description || 'No description'}</p>
                           <p><strong>Start:</strong> ${moment(info.event.start).format('MMMM D, YYYY h:mm A')}</p>
                           <p><strong>End:</strong> ${info.event.end ? moment(info.event.end).format('MMMM D, YYYY h:mm A') : 'N/A'}</p>`,
                    icon: 'info',
                    confirmButtonText: 'Close'
                });
            }
        });
        calendar.render();

        // Get event color based on type
        function getEventColor(type) {
            switch(type) {
                case 'holiday':
                    return '#ffc107'; // Yellow
                case 'meeting':
                    return '#17a2b8'; // Teal
                case 'birthday':
                    return '#e83e8c'; // Pink
                default:
                    return '#007bff'; // Blue
            }
        }

        // Load events for DataTable
        function loadLandingPageEvents() {
            $.ajax({
                url: 'views/get_events.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#events-loading').hide();
                    
                    if (response.success && response.data && response.data.length > 0) {
                        eventsTable.clear();
                        
                        response.data.forEach(function(event) {
                            var startDate = new Date(event.start);
                            var endDate = event.end ? new Date(event.end) : null;
                            
                            var startFormatted = formatDateTime(startDate);
                            var endFormatted = endDate ? formatDateTime(endDate) : 'N/A';
                            
                            // Get badge color based on event type
                            var badgeClass = '';
                            var typeText = '';
                            switch(event.type) {
                                case 'holiday':
                                    badgeClass = 'badge-warning';
                                    typeText = 'Holiday';
                                    break;
                                case 'meeting':
                                    badgeClass = 'badge-info';
                                    typeText = 'Meeting';
                                    break;
                                case 'birthday':
                                    badgeClass = 'badge-success';
                                    typeText = 'Birthday';
                                    break;
                                default:
                                    badgeClass = 'badge-primary';
                                    typeText = 'Event';
                            }
                            
                            eventsTable.row.add([
                                `<strong>${event.title}</strong>`,
                                `<span class="badge ${badgeClass}">${typeText}</span>`,
                                event.description || 'No description',
                                startFormatted,
                                endFormatted
                            ]);
                        });
                        
                        eventsTable.draw();
                        $('#events-empty').hide();
                    } else {
                        $('#events-empty').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#events-loading').hide();
                    $('#events-empty').show();
                    console.error('Error loading events:', error);
                }
            });
        }

        // Format date and time
        function formatDateTime(date) {
            if (!date || isNaN(date.getTime())) {
                return 'N/A';
            }
            
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            
            return date.toLocaleDateString('en-US', options);
        }

        // Load events when DataTable tab is shown
        $('#table-tab').on('shown.bs.tab', function() {
            if (eventsTable.data().count() === 0) {
                loadLandingPageEvents();
            }
        });

        // Scroll to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('#scrollToTop').fadeIn();
            } else {
                $('#scrollToTop').fadeOut();
            }
        });

        $('#scrollToTop').click(function() {
            $('html, body').animate({scrollTop: 0}, 300);
            return false;
        });

        // Smooth scrolling for navigation links
        $('a[href*="#"]').not('[href="#"]').not('[href="#0"]').click(function(event) {
            if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                if (target.length) {
                    event.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 70
                    }, 1000, function() {
                        var $target = $(target);
                        $target.focus();
                        if ($target.is(":focus")) {
                            return false;
                        } else {
                            $target.attr('tabindex','-1');
                            $target.focus();
                        }
                    });
                }
            }
        });

        // Add active class to nav items on scroll
        $(window).scroll(function() {
            var scrollDistance = $(window).scrollTop();
            
            $('section').each(function(i) {
                if ($(this).position().top <= scrollDistance + 100) {
                    $('.navbar-nav a.active').removeClass('active');
                    $('.navbar-nav a').eq(i).addClass('active');
                }
            });
        }).scroll();
    });
    </script>
</body>
</html>