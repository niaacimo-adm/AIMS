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

    <link rel="stylesheet" href="index.css">

</head>
<body>

    <!-- Floating Calendar Button -->
    <button class="floating-calendar-btn" id="floatingCalendarBtn" title="Quick Calendar View">
        <i class="fas fa-calendar-alt"></i>
    </button>

    <!-- Floating Calendar Modal -->
    <div class="modal fade floating-calendar-modal" id="floatingCalendarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick Calendar</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="miniCalendar"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a href="#events" class="btn btn-primary" data-dismiss="modal">Full Calendar</a>
                </div>
            </div>
        </div>
    </div>
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
            <div class="text-center">
                <h1 class="display-4 mb-3">Welcome to</h1>
                <h1 class="display-4 mb-3">National Irrigation Administration</h1>
                <h1 class="display-4 mb-4">Albay-Catanduanes Irrigation Management Office</h1>
                <p class="lead mb-4">Providing efficient irrigation services for sustainable agriculture</p>
                <a href="login.php" class="login-btn btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i> Employee Sign In
                </a>
            </div>
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
                        Showing <span id="gallery-start">1</span> of <span id="gallery-total"><?= count($carousel_images) ?></span> images
                    </div>
                </div>

                <!-- 3D Carousel Container -->
                <div class="carousel-3d-container">
                    <div class="carousel-3d" id="carousel3d">
                        <?php foreach ($carousel_images as $index => $image): ?>
                            <?php 
                                // Fix image path - ensure it's relative to the project root
                                $imagePath = $image['image_path'];
                                
                                // Debug output
                                echo "<!-- Original path: " . htmlspecialchars($imagePath) . " -->";
                                
                                // Remove any problematic prefixes
                                $imagePath = str_replace(['../', './'], '', $imagePath);
                                
                                // Ensure the path starts from project root
                                if (strpos($imagePath, 'uploads/') === 0) {
                                    // Path is already correct
                                    $finalPath = $imagePath;
                                } else if (strpos($imagePath, 'carousel/') === 0) {
                                    $finalPath = 'uploads/' . $imagePath;
                                } else {
                                    // Extract just the filename and build correct path
                                    $filename = basename($imagePath);
                                    $finalPath = 'uploads/carousel/' . $filename;
                                }
                                
                                // Check if file exists
                                $fileExists = file_exists($finalPath);
                                if (!$fileExists) {
                                    // Try alternative paths
                                    $alternativePaths = [
                                        '../' . $finalPath,
                                        './' . $finalPath,
                                        'uploads/carousel/' . basename($imagePath),
                                        '../uploads/carousel/' . basename($imagePath)
                                    ];
                                    
                                    foreach ($alternativePaths as $altPath) {
                                        if (file_exists($altPath)) {
                                            $finalPath = $altPath;
                                            $fileExists = true;
                                            echo "<!-- Found at alternative path: $finalPath -->";
                                            break;
                                        }
                                    }
                                }
                                
                                echo "<!-- Final path: $finalPath -->";
                                echo "<!-- File exists: " . ($fileExists ? 'YES' : 'NO') . " -->";
                                
                                $caption = $image['caption'] ?: 'No caption';
                            ?>
                            <div class="carousel-item" data-index="<?= $index ?>">
                                <img src="<?= $finalPath ?>" alt="<?= htmlspecialchars($caption) ?>" 
                                    onerror="this.onerror=null; this.src='dist/img/default-image.jpg'; console.log('Image failed to load: <?= $finalPath ?>')">
                                <div class="carousel-caption">
                                    <p><?= htmlspecialchars($caption) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Navigation Controls -->
                    <button class="carousel-control prev" id="carouselPrev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-control next" id="carouselNext">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <!-- Indicators -->
                    <div class="carousel-indicators" id="carouselIndicators">
                        <?php foreach ($carousel_images as $index => $image): ?>
                            <span class="indicator <?= $index === 0 ? 'active' : '' ?>" 
                                data-index="<?= $index ?>"></span>
                        <?php endforeach; ?>
                    </div>
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
        // 3D Carousel functionality - FIXED VERSION
        class Carousel3D {
            constructor() {
                this.carousel = document.getElementById('carousel3d');
                this.items = document.querySelectorAll('.carousel-item');
                this.prevBtn = document.getElementById('carouselPrev');
                this.nextBtn = document.getElementById('carouselNext');
                this.indicators = document.querySelectorAll('.carousel-indicators .indicator');
                this.galleryStart = document.getElementById('gallery-start');
                this.galleryTotal = document.getElementById('gallery-total');
                
                this.currentIndex = 0;
                this.totalItems = this.items.length;
                this.isAnimating = false;
                this.autoRotateInterval = null;
                
                this.init();
            }
            
            init() {
                if (this.totalItems === 0) return;
                
                console.log(`Initializing 3D Carousel with ${this.totalItems} items`);
                
                this.setupEventListeners();
                this.updateCarousel();
                this.startAutoRotation();
                
                // Update gallery info
                this.updateGalleryInfo();
            }
            
            setupEventListeners() {
                // Navigation buttons
                if (this.prevBtn) {
                    this.prevBtn.addEventListener('click', () => this.previous());
                }
                if (this.nextBtn) {
                    this.nextBtn.addEventListener('click', () => this.next());
                }
                
                // Indicators
                this.indicators.forEach((indicator, index) => {
                    indicator.addEventListener('click', () => this.goToSlide(index));
                });
                
                // Keyboard navigation
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        this.previous();
                    } else if (e.key === 'ArrowRight') {
                        this.next();
                    }
                });
                
                // Touch events for mobile
                this.setupTouchEvents();
                
                // Pause auto-rotation on hover
                if (this.carousel) {
                    this.carousel.addEventListener('mouseenter', () => this.pauseAutoRotation());
                    this.carousel.addEventListener('mouseleave', () => this.startAutoRotation());
                }
                
                // Click on carousel items to open modal
                this.items.forEach(item => {
                    item.addEventListener('click', () => {
                        const img = item.querySelector('img');
                        const caption = item.querySelector('.carousel-caption p')?.textContent || 'No caption';
                        this.openImageModal(img.src, caption);
                    });
                });
            }
            
            setupTouchEvents() {
                let startX = 0;
                let endX = 0;
                
                if (this.carousel) {
                    this.carousel.addEventListener('touchstart', (e) => {
                        startX = e.touches[0].clientX;
                    });
                    
                    this.carousel.addEventListener('touchend', (e) => {
                        endX = e.changedTouches[0].clientX;
                        this.handleSwipe(startX, endX);
                    });
                }
            }
            
            handleSwipe(startX, endX) {
                const swipeThreshold = 50;
                const diff = startX - endX;
                
                if (Math.abs(diff) > swipeThreshold) {
                    if (diff > 0) {
                        this.next();
                    } else {
                        this.previous();
                    }
                }
            }
            
            previous() {
                if (this.isAnimating || this.totalItems <= 1) return;
                
                this.currentIndex = (this.currentIndex - 1 + this.totalItems) % this.totalItems;
                this.updateCarousel();
            }
            
            next() {
                if (this.isAnimating || this.totalItems <= 1) return;
                
                this.currentIndex = (this.currentIndex + 1) % this.totalItems;
                this.updateCarousel();
            }
            
            goToSlide(index) {
                if (this.isAnimating || index === this.currentIndex || this.totalItems <= 1) return;
                
                this.currentIndex = index;
                this.updateCarousel();
            }
            
            updateCarousel() {
                this.isAnimating = true;
                
                console.log(`Updating carousel to index: ${this.currentIndex}`);
                
                // Calculate positions for all items
                this.items.forEach((item, index) => {
                    const position = (index - this.currentIndex + this.totalItems) % this.totalItems;
                    this.positionItem(item, position);
                });
                
                // Update indicators
                this.updateIndicators();
                
                // Update gallery info
                this.updateGalleryInfo();
                
                // Reset animation flag after transition
                setTimeout(() => {
                    this.isAnimating = false;
                }, 800);
            }
            
            positionItem(item, position) {
                let transform = '';
                let zIndex = 0;
                let opacity = 1;
                let scale = 1;
                
                // For single item, center it
                if (this.totalItems === 1) {
                    transform = 'translateX(0) translateZ(0) rotateY(0)';
                    zIndex = 10;
                    scale = 1;
                    opacity = 1;
                } 
                // For multiple items
                else {
                    switch(position) {
                        case 0: // Center (active)
                            transform = 'translateX(0) translateZ(0) rotateY(0)';
                            zIndex = 10;
                            scale = 1;
                            opacity = 1;
                            break;
                        case 1: // Right side
                            transform = 'translateX(280px) translateZ(-150px) rotateY(-25deg)';
                            zIndex = 8;
                            scale = 0.85;
                            opacity = 0.8;
                            break;
                        case this.totalItems - 1: // Left side
                            transform = 'translateX(-280px) translateZ(-150px) rotateY(25deg)';
                            zIndex = 8;
                            scale = 0.85;
                            opacity = 0.8;
                            break;
                        case 2: // Far right
                            transform = 'translateX(400px) translateZ(-300px) rotateY(-40deg)';
                            zIndex = 6;
                            scale = 0.7;
                            opacity = 0.6;
                            break;
                        case this.totalItems - 2: // Far left
                            transform = 'translateX(-400px) translateZ(-300px) rotateY(40deg)';
                            zIndex = 6;
                            scale = 0.7;
                            opacity = 0.6;
                            break;
                        default: // Hidden (behind)
                            transform = 'translateX(0) translateZ(-500px) rotateY(0)';
                            zIndex = 1;
                            scale = 0.5;
                            opacity = 0.3;
                    }
                }
                
                // Apply all transformations
                item.style.transform = `${transform} scale(${scale})`;
                item.style.zIndex = zIndex;
                item.style.opacity = opacity;
                
                // Ensure the item is visible
                item.style.visibility = 'visible';
                item.style.display = 'block';
            }
            
            updateIndicators() {
                this.indicators.forEach((indicator, index) => {
                    if (index === this.currentIndex) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
            }
            
            updateGalleryInfo() {
                if (this.galleryStart && this.galleryTotal) {
                    this.galleryStart.textContent = this.currentIndex + 1;
                    this.galleryTotal.textContent = this.totalItems;
                }
            }
            
            startAutoRotation() {
                if (this.autoRotateInterval || this.totalItems <= 1) {
                    return;
                }
                
                this.autoRotateInterval = setInterval(() => {
                    this.next();
                }, 5000); // Change every 5 seconds
            }
            
            pauseAutoRotation() {
                if (this.autoRotateInterval) {
                    clearInterval(this.autoRotateInterval);
                    this.autoRotateInterval = null;
                }
            }
            
            openImageModal(imagePath, caption) {
                $('#modalImage').attr('src', imagePath);
                $('#modalCaption').text(caption);
                $('#imageModal').modal('show');
            }
            
            destroy() {
                this.pauseAutoRotation();
            }
        }

        // Initialize the 3D carousel when DOM is fully loaded
        function initializeCarousel() {
            console.log('Initializing 3D carousel...');
            
            // Hide old gallery elements if they exist
            const galleryContainer = document.getElementById('gallery-container');
            if (galleryContainer) {
                galleryContainer.style.display = 'none';
            }
            
            const paginationContainer = document.querySelector('.pagination-container');
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            }
            
            // Initialize 3D Carousel if elements exist
            const carousel3d = document.getElementById('carousel3d');
            if (carousel3d) {
                console.log('3D Carousel container found, initializing...');
                window.carousel3D = new Carousel3D();
                
                // Debug: Log carousel state
                setTimeout(() => {
                    console.log('Carousel initialized successfully');
                    console.log('Carousel items:', window.carousel3D.items.length);
                    console.log('Current index:', window.carousel3D.currentIndex);
                }, 100);
            } else {
                console.error('3D Carousel container not found!');
            }
        }

        // Make carousel methods available globally
        window.Carousel3D = Carousel3D;

        // Add this to your JavaScript section
        function checkAllImages() {
            console.log('Checking all carousel images...');
            document.querySelectorAll('.carousel-item img').forEach((img, index) => {
                img.onerror = function() {
                    console.error('Image failed to load:', this.src);
                    this.src = 'dist/img/default-image.jpg';
                };
                
                // Test if image loads
                const testImage = new Image();
                testImage.onload = function() {
                    console.log('✓ Image loaded successfully:', img.src);
                };
                testImage.onerror = function() {
                    console.error('✗ Image failed to load:', img.src);
                };
                testImage.src = img.src;
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCarousel);
        } else {
            initializeCarousel();
        }

        // Debug function to check carousel state
        function debugCarousel() {
            console.log('=== CAROUSEL DEBUG INFO ===');
            if (window.carousel3D) {
                console.log('Total items:', window.carousel3D.totalItems);
                console.log('Current index:', window.carousel3D.currentIndex);
                console.log('Is animating:', window.carousel3D.isAnimating);
            } else {
                console.log('Carousel3D not initialized yet');
            }
            
            const items = document.querySelectorAll('.carousel-item');
            console.log('DOM items found:', items.length);
            
            items.forEach((item, index) => {
                const style = window.getComputedStyle(item);
                console.log(`Item ${index}:`, {
                    transform: style.transform,
                    opacity: style.opacity,
                    zIndex: style.zIndex,
                    visibility: style.visibility,
                    display: style.display
                });
            });
            console.log('=== END DEBUG ===');
        }

        // Test carousel functionality
        function testCarousel() {
            console.log('Testing carousel functionality...');
            
            // Test if buttons exist and are clickable
            const prevBtn = document.getElementById('carouselPrev');
            const nextBtn = document.getElementById('carouselNext');
            
            console.log('Previous button:', prevBtn);
            console.log('Next button:', nextBtn);
            
            if (prevBtn && nextBtn) {
                console.log('Buttons found, testing click events...');
                
                // Add test click handlers
                prevBtn.addEventListener('click', function() {
                    console.log('Previous button clicked');
                });
                
                nextBtn.addEventListener('click', function() {
                    console.log('Next button clicked');
                });
            }
        }

        // Call debug functions after initialization
        setTimeout(() => {
            debugCarousel();
            testCarousel();
        }, 100);
    });

    // Floating Calendar functionality
// Floating Calendar functionality
function initializeFloatingCalendar() {
    const calendarBtn = document.getElementById('floatingCalendarBtn');
    const calendarModal = $('#floatingCalendarModal');
    
    let miniCalendar = null;
    
    if (calendarBtn) {
        calendarBtn.addEventListener('click', function() {
            initializeMiniCalendar();
            calendarModal.modal('show');
        });
    }
    
    // Reinitialize when modal is shown
    calendarModal.on('shown.bs.modal', function() {
        initializeMiniCalendar();
    });
    
    function initializeMiniCalendar() {
        var miniCalendarEl = document.getElementById('miniCalendar');
        
        // Destroy existing calendar if it exists
        if (miniCalendar) {
            miniCalendar.destroy();
        }
        
        if (miniCalendarEl) {
            miniCalendar = new FullCalendar.Calendar(miniCalendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                height: 'auto',
                aspectRatio: 1.2,
                dayMaxEvents: 2,
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
                                        borderColor: getEventColor(event.type),
                                        textColor: '#ffffff'
                                    };
                                });
                                successCallback(events);
                            } else {
                                successCallback([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading mini calendar events:', error);
                            successCallback([]);
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
                },
                datesSet: function(dateInfo) {
                    // Force update of calendar styling after navigation
                    setTimeout(() => {
                        updateMiniCalendarStyles();
                    }, 50);
                }
            });
            miniCalendar.render();
            
            // Apply initial styles
            setTimeout(() => {
                updateMiniCalendarStyles();
            }, 100);
        }
    }
    
    function updateMiniCalendarStyles() {
        // Ensure all text in mini calendar remains dark
        $('#miniCalendar .fc-toolbar-title').css('color', '#343a40');
        $('#miniCalendar .fc-col-header-cell').css('color', '#343a40');
        $('#miniCalendar .fc-col-header-cell a').css('color', '#343a40');
        $('#miniCalendar .fc-daygrid-day-number').css('color', '#343a40');
        
        // Style the buttons
        $('#miniCalendar .fc-button').css({
            'background-color': '#28a745',
            'border-color': '#28a745',
            'color': 'white'
        });
    }
}

// Initialize floating calendar
initializeFloatingCalendar();

    // Update the existing getEventColor function to ensure it's available
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
    $(window).on('resize', function() {
    setTimeout(() => {
        updateMiniCalendarStyles();
    }, 100);
});

// Update when tab becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        setTimeout(() => {
            updateMiniCalendarStyles();
        }, 100);
    }
});
    </script>
</body>
</html>