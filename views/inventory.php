<?php
    require_once '../config/database.php';
    
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Dashboard</title>
    
    <?php include '../includes/header.php'; ?>
    <!-- Theme style -->
     <style>
        .custom-file-input:lang(en)~.custom-file-label::after {
            content: "Browse";
        }
        #preview {
            max-width: 100%;
            height: auto;
        }
        
        /* Custom styles for the dashboard */
        .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .dashboard-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .dashboard-card .btn {
            border-radius: 5px;
            font-weight: 500;
        }
        
        .inventory-summary {
            background: linear-gradient(120deg, #f6f9fc, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
        }
        
        .summary-item h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            color: #6c757d;
            font-weight: 500;
        }
        
        .bg-new-stock {
            background-color: #28a745;
            color: white;
        }
        
        .bg-inventory {
            background-color: #007bff;
            color: white;
        }
        
        .bg-stock-types {
            background-color: #6f42c1;
            color: white;
        }
        
        .bg-rsmi {
            background-color: #dc3545;
            color: white;
        }
        
        .bg-ris {
            background-color: #fd7e14;
            color: white;
        }
        
        .bg-ics {
            background-color: #20c997;
            color: white;
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #343a40;
        }
        
        .content-header {
            padding: 15px 0;
        }
        
        .small-box {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .small-box>.inner {
            padding: 20px;
        }
        
        .small-box h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            white-space: nowrap;
            padding: 0;
        }
        
        .small-box p {
            font-size: 1rem;
            color: #f8f9fa;
        }
        
        .small-box .icon {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 0;
            font-size: 70px;
            color: rgba(255,255,255,0.2);
        }
        
        .small-box:hover .icon {
            font-size: 75px;
            transition: all 0.3s ease;
        }
        
        .bg-gradient-new-stock {
            background: linear-gradient(120deg, #28a745, #20c997) !important;
        }
        
        .bg-gradient-inventory {
            background: linear-gradient(120deg, #007bff, #0056b3) !important;
        }
        
        .bg-gradient-stock-types {
            background: linear-gradient(120deg, #6f42c1, #6610f2) !important;
        }
        
        .bg-gradient-rsmi {
            background: linear-gradient(120deg, #dc3545, #c82333) !important;
        }
        
        .bg-gradient-ris {
            background: linear-gradient(120deg, #fd7e14, #e36209) !important;
        }
        
        .bg-gradient-ics {
            background: linear-gradient(120deg, #17a2b8, #138496) !important;
        }
        
        .activity-logs {
            margin-top: 30px;
        }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            background-color: rgba(0,0,0,0.03);
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
    </style>
    
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Inventory Dashboard</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Small boxes (Stat box) -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-new-stock">
                            <div class="inner">
                                <h3>150</h3>
                                <p>New Stock Items</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-inventory">
                            <div class="inner">
                                <h3>53<sup style="font-size: 20px">%</sup></h3>
                                <p>Inventory Growth</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-ris">
                            <div class="inner">
                                <h3>44</h3>
                                <p>RIS Requests</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-rsmi">
                            <div class="inner">
                                <h3>65</h3>
                                <p>RSMI Reports</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- /.row -->

                <!-- Main row -->
                <div class="row">
                    <!-- Left col -->
                    <section class="col-lg-12">
                        <!-- Custom tabs (Charts with tabs)-->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Inventory Management</h3>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-success">
                                                    <i class="fas fa-plus-circle fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">NEW STOCK</h5>
                                                <p class="card-text">Add new items to your inventory</p>
                                                <a href="#" class="btn btn-success btn-block">Add New Stock</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-primary">
                                                    <i class="fas fa-boxes fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">INVENTORY</h5>
                                                <p class="card-text">View and manage your inventory</p>
                                                <a href="#" class="btn btn-primary btn-block">View Inventory</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-purple">
                                                    <i class="fas fa-tags fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">STOCK TYPES</h5>
                                                <p class="card-text">Manage categories and types</p>
                                                <a href="#" class="btn btn-purple btn-block">Manage Types</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-danger">
                                                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">RSMI</h5>
                                                <p class="card-text">RSMI management section</p>
                                                <a href="#" class="btn btn-danger btn-block">RSMI Tools</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-warning">
                                                    <i class="fas fa-file-alt fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">RIS</h5>
                                                <p class="card-text">RIS management section</p>
                                                <a href="#" class="btn btn-warning btn-block">RIS Tools</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card dashboard-card">
                                            <div class="card-body text-center">
                                                <div class="card-icon text-info">
                                                    <i class="fas fa-chart-line fa-3x"></i>
                                                </div>
                                                <h5 class="card-title">ICS</h5>
                                                <p class="card-text">ICS management section</p>
                                                <a href="#" class="btn btn-info btn-block">ICS Tools</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                        <!-- Activity Logs Section -->
                        <div class="card activity-logs">
                            <div class="card-header">
                                <h3 class="card-title">ACTIVITY LOGS</h3>
                            </div>
                            <div class="card-body">
                                <table id="activityTable" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Item</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>2023-07-15 14:32</td>
                                            <td>John Doe</td>
                                            <td>Added new item</td>
                                            <td>Network Switches</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-15 13:15</td>
                                            <td>Jane Smith</td>
                                            <td>Updated inventory</td>
                                            <td>Laptop HP EliteBook</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-15 11:45</td>
                                            <td>Mike Johnson</td>
                                            <td>Processed request</td>
                                            <td>Monitors</td>
                                            <td><span class="badge badge-warning">Pending</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-15 10:20</td>
                                            <td>Sarah Wilson</td>
                                            <td>Deleted item</td>
                                            <td>Obsolete Equipment</td>
                                            <td><span class="badge badge-danger">Warning</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-15 09:05</td>
                                            <td>Robert Brown</td>
                                            <td>Generated report</td>
                                            <td>Monthly Inventory</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-14 16:50</td>
                                            <td>Emily Davis</td>
                                            <td>Restocked item</td>
                                            <td>Network Cables</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-14 15:30</td>
                                            <td>David Miller</td>
                                            <td>Adjusted quantity</td>
                                            <td>Power Adapters</td>
                                            <td><span class="badge badge-success">Completed</span></td>
                                        </tr>
                                        <tr>
                                            <td>2023-07-14 14:15</td>
                                            <td>Lisa Anderson</td>
                                            <td>Created RSMI</td>
                                            <td>Server Racks</td>
                                            <td><span class="badge badge-warning">Pending</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                    <!-- /.Left col -->
                </div>
                <!-- /.row (main row) -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- Include your footer content -->
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Initialize DataTable for activity logs
        $('#activityTable').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#activityTable_wrapper .col-md-6:eq(0)');
        
        // Add purple button color
        $('.btn-purple').css('background-color', '#6f42c1').css('border-color', '#6f42c1');
        $('.btn-purple:hover').css('background-color', '#5a359c').css('border-color', '#5a359c');
        
        // Text color for card icons
        $('.text-purple').css('color', '#6f42c1');
    });
</script>
</body>
</html>