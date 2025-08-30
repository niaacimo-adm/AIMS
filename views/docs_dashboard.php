<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Get document counts by status
$query = "SELECT 
            SUM(CASE WHEN d.is_archived = 0 THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN d.is_archived = 1 THEN 1 ELSE 0 END) as total_archived,
            COUNT(*) as total_documents
          FROM documents d";
$result = $conn->query($query);
$counts = $result->fetch_assoc();

// Get recent documents
$recentQuery = "SELECT d.doc_id, d.doc_number, d.title, dt.type_name, d.created_at 
                FROM documents d
                JOIN document_types dt ON d.type_id = dt.type_id
                ORDER BY d.created_at DESC LIMIT 5";
$recentResult = $conn->query($recentQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Document Tracking Dashboard</title>
  <?php include '../includes/header.php'; ?>
  <style>
      .stat-card {
          border-radius: 10px;
          padding: 20px;
          margin-bottom: 20px;
          color: white;
          text-align: center;
          box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }
      .stat-card i {
          font-size: 2.5rem;
          margin-bottom: 10px;
      }
      .stat-card .count {
          font-size: 2rem;
          font-weight: bold;
      }
      .stat-card .label {
          font-size: 1rem;
          text-transform: uppercase;
          letter-spacing: 1px;
      }
      .total-docs {
          background: linear-gradient(135deg, #6777ef, #3518d9);
      }
      .active-docs {
          background: linear-gradient(135deg, #47c363, #28a745);
      }
      .archived-docs {
          background: linear-gradient(135deg, #ffa426, #fd7e14);
      }
      .pending-docs {
          background: linear-gradient(135deg, #fc544b, #f00);
      }
      .recent-doc {
          border-left: 3px solid #6777ef;
          margin-bottom: 15px;
          padding: 10px;
          transition: all 0.3s;
      }
      .recent-doc:hover {
          background-color: #f8f9fa;
          transform: translateX(5px);
      }
      .recent-doc h5 {
          margin-bottom: 5px;
      }
      .recent-doc .doc-meta {
          color: #6c757d;
          font-size: 0.9rem;
      }
      .quick-action-btn {
          margin-bottom: 10px;
          text-align: left;
          padding: 12px 15px;
          font-weight: 500;
      }
      .quick-action-btn i {
          margin-right: 10px;
      }
      .chart-container {
          position: relative;
          height: 250px;
      }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Document Tracking Dashboard</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <div class="row">
            <!-- Stat Cards -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card total-docs">
                    <i class="fas fa-file-alt"></i>
                    <div class="count"><?php echo $counts['total_documents']; ?></div>
                    <div class="label">Total Documents</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card active-docs">
                    <i class="fas fa-check-circle"></i>
                    <div class="count"><?php echo $counts['total_active']; ?></div>
                    <div class="label">Active Documents</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card archived-docs">
                    <i class="fas fa-archive"></i>
                    <div class="count"><?php echo $counts['total_archived']; ?></div>
                    <div class="label">Archived Documents</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card pending-docs">
                    <i class="fas fa-clock"></i>
                    <div class="count">0</div>
                    <div class="label">Pending Approval</div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Recent Documents -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary">
                        <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i>Recently Added Documents</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($recentResult->num_rows > 0): ?>
                            <?php while($doc = $recentResult->fetch_assoc()): ?>
                                <div class="recent-doc">
                                    <h5>
                                        <a href="document_details.php?id=<?php echo $doc['doc_id']; ?>">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </a>
                                    </h5>
                                    <div class="doc-meta">
                                        <span class="badge bg-info"><?php echo htmlspecialchars($doc['type_name']); ?></span>
                                        <span class="ml-2"><?php echo htmlspecialchars($doc['doc_number']); ?></span>
                                        <span class="float-right"><?php echo date('M d, Y h:i A', strtotime($doc['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No recent documents found.</div>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="doctrack.php" class="btn btn-primary">View All Documents</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-info">
                        <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="doctrack.php" class="btn btn-block btn-primary quick-action-btn">
                            <i class="fas fa-plus-circle"></i> Add New Document
                        </a>
                        <a href="document_types.php" class="btn btn-block btn-secondary quick-action-btn">
                            <i class="fas fa-file-alt"></i> Manage Document Types
                        </a>
                        <a href="section_transfers.php" class="btn btn-block btn-info quick-action-btn">
                            <i class="fas fa-building"></i> Manage Sections/Office
                        </a>
                        <a href="reports.php" class="btn btn-block btn-warning quick-action-btn">
                            <i class="fas fa-chart-bar"></i> Generate Reports
                        </a>
                    </div>
                </div>
                
                <!-- Document Status Chart -->
                <div class="card mt-4">
                    <div class="card-header bg-success">
                        <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Document Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="docStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Chart.js -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
<script>
    // Document Status Chart
    const ctx = document.getElementById('docStatusChart').getContext('2d');
    const docStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Archived', 'Pending'],
            datasets: [{
                data: [<?php echo $counts['total_active']; ?>, <?php echo $counts['total_archived']; ?>, 0],
                backgroundColor: [
                    '#47c363',
                    '#ffa426',
                    '#fc544b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
</script>
</body>
</html>