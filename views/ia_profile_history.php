<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has permission to view history
if (!hasPermission('view_ia_profile')) {
    header('Location: ../unauthorized.php');
    exit();
}

$ia_profile_id = $_GET['id'] ?? 0;

if (empty($ia_profile_id)) {
    header('Location: ia_profiles.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get IA Profile details
$query = "SELECT * FROM ia_profiles WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('i', $ia_profile_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

if (!$profile) {
    header('Location: ia_profiles.php');
    exit();
}

// Get history
require_once '../includes/ia_history_logger.php';
$logger = new IAHistoryLogger();
$history = $logger->getHistory($ia_profile_id, 100);

$page_title = "History - " . htmlspecialchars($profile['ia_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .history-timeline {
            position: relative;
            padding-left: 30px;
        }
        .history-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .history-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .history-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 2px solid white;
        }
        .history-item.created { border-left-color: #28a745; }
        .history-item.created::before { background: #28a745; }
        .history-item.updated { border-left-color: #ffc107; }
        .history-item.updated::before { background: #ffc107; }
        .history-item.deleted { border-left-color: #dc3545; }
        .history-item.deleted::before { background: #dc3545; }
        .history-item.assigned { border-left-color: #6f42c1; }
        .history-item.assigned::before { background: #6f42c1; }
        .history-item.officer_added { border-left-color: #20c997; }
        .history-item.officer_added::before { background: #20c997; }
        .history-item.officer_deleted { border-left-color: #fd7e14; }
        .history-item.officer_deleted::before { background: #fd7e14; }
        
        .history-description {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .history-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .history-changes {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .change-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .change-field {
            font-weight: 500;
            min-width: 120px;
        }
        .change-values {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }
        .change-old {
            text-decoration: line-through;
            color: #dc3545;
            margin-right: 0.5rem;
        }
        .change-new {
            color: #28a745;
            font-weight: 500;
        }
        .change-arrow {
            margin: 0 0.5rem;
            color: #6c757d;
        }
        .no-history {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .history-filter {
            margin-bottom: 1rem;
        }
        .action-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                        <h1>Activity History</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="ia_profiles.php">IA Profiles</a></li>
                            <li class="breadcrumb-item"><a href="ia_profile_view.php?id=<?= $ia_profile_id ?>"><?= htmlspecialchars($profile['ia_name']) ?></a></li>
                            <li class="breadcrumb-item active">History</li>
                        </ol>
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
                                <h3 class="card-title">
                                    <i class="fas fa-history mr-2"></i>
                                    Activity History for <?= htmlspecialchars($profile['ia_name']) ?>
                                </h3>
                                <div class="card-tools">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($history)): ?>
                                <div class="history-filter mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select class="form-control" id="actionFilter">
                                                <option value="">All Actions</option>
                                                <option value="created">Created</option>
                                                <option value="updated">Updated</option>
                                                <option value="deleted">Deleted</option>
                                                <option value="assigned">Assignments</option>
                                                <option value="officer_added">Officer Added</option>
                                                <option value="officer_updated">Officer Updated</option>
                                                <option value="officer_deleted">Officer Deleted</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="searchHistory" placeholder="Search in history...">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="history-timeline" id="historyTimeline">
                                    <?php foreach ($history as $item): ?>
                                    <div class="history-item <?= $item['action'] ?>" data-action="<?= $item['action'] ?>" data-description="<?= htmlspecialchars($item['description']) ?>">
                                        <div class="history-description">
                                            <?= htmlspecialchars($item['description']) ?>
                                            <span class="badge action-badge badge-<?= $item['action'] ?> float-right">
                                                <?= ucfirst(str_replace('_', ' ', $item['action'])) ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($item['field_name']) && !empty($item['old_value']) && !empty($item['new_value'])): ?>
                                        <div class="history-changes">
                                            <div class="change-item">
                                                <span class="change-field"><?= ucfirst(str_replace('_', ' ', $item['field_name'])) ?>:</span>
                                                <div class="change-values">
                                                    <span class="change-old"><?= htmlspecialchars($item['old_value']) ?></span>
                                                    <span class="change-arrow">→</span>
                                                    <span class="change-new"><?= htmlspecialchars($item['new_value']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="history-meta">
                                            <i class="fas fa-user mr-1"></i>
                                            <?= htmlspecialchars($item['performer_name'] ?? 'System') ?>
                                            • 
                                            <i class="fas fa-clock mr-1"></i>
                                            <?= date('M j, Y g:i A', strtotime($item['performed_at'])) ?>
                                            <?php if (!empty($item['ip_address'])): ?>
                                            • 
                                            <i class="fas fa-globe mr-1"></i>
                                            <?= htmlspecialchars($item['ip_address']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="no-history">
                                    <i class="fas fa-history fa-3x mb-3 text-muted"></i>
                                    <h4>No History Found</h4>
                                    <p>No activity has been recorded for this IA Profile yet.</p>
                                </div>
                                <?php endif; ?>
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
    // Filter by action type
    $('#actionFilter').change(function() {
        const filterValue = $(this).val();
        const $items = $('.history-item');
        
        if (filterValue === '') {
            $items.show();
        } else {
            $items.hide();
            $items.filter('[data-action="' + filterValue + '"]').show();
        }
    });
    
    // Search in history
    $('#searchHistory').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const $items = $('.history-item');
        
        if (searchTerm === '') {
            $items.show();
        } else {
            $items.hide();
            $items.filter(function() {
                return $(this).data('description').toLowerCase().includes(searchTerm) ||
                       $(this).text().toLowerCase().includes(searchTerm);
            }).show();
        }
    });
    
    // Auto-refresh history every 30 seconds
    setInterval(function() {
        $.ajax({
            url: '../includes/ia_profiles_ajax.php',
            type: 'POST',
            data: {
                action: 'get_history',
                ia_profile_id: <?= $ia_profile_id ?>
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // You could implement dynamic updating here
                    console.log('History updated:', response.data.length + ' items');
                }
            }
        });
    }, 30000);
});
</script>
</body>
</html>