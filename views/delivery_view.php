<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get IAR ID from query parameter
$iar_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch IAR details
$iar = [];
$items = [];

if ($iar_id > 0) {
    // Fetch IAR record
    $query = "SELECT i.*, u.user as created_by_name 
              FROM iar_records i 
              LEFT JOIN users u ON i.created_by = u.id 
              WHERE i.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $iar_id);
    $stmt->execute();
    $iar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($iar) {
        // Fetch IAR items
        $items_query = "SELECT ii.*, i.name as item_name, i.description as item_description 
                       FROM iar_items ii
                       LEFT JOIN delivery_items di ON ii.delivery_item_id = di.id
                       LEFT JOIN items i ON di.item_id = i.id
                       WHERE ii.iar_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $iar_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $items_stmt->close();
    }
}

// If no IAR found, redirect back
if (!$iar) {
    header("Location: delivery_entry.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View IAR Details - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modal-xl {
            max-width: 90%;
        }
        .iar-header {
            background: linear-gradient(120deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .item-table th {
            background: linear-gradient(120deg, #e3f2fd, #bbdefb);
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .card-header {
            background: linear-gradient(120deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px 10px 0 0;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>IAR Details</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item"><a href="delivery_entry.php">Delivery Management</a></li>
                            <li class='breadcrumb-item active'>IAR Details</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">IAR: <?= htmlspecialchars($iar['iar_number']) ?></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- IAR Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">PO Number</span>
                                        <span class="info-box-number"><?= htmlspecialchars($iar['po_number']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Supplier</span>
                                        <span class="info-box-number"><?= htmlspecialchars($iar['supplier']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Requisition Office</span>
                                        <span class="info-box-number"><?= htmlspecialchars($iar['requisition_office']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Delivery Date</span>
                                        <span class="info-box-number"><?= date('M j, Y', strtotime($iar['delivery_date'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Amount</span>
                                        <span class="info-box-number">₱<?= number_format($iar['total_amount'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped item-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item Description</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    $grand_total = 0;
                                    foreach ($items as $item): 
                                        $grand_total += $item['total_price'];
                                    ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($item['description'] ?? $item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['unit']) ?></td>
                                        <td><?= number_format($item['quantity']) ?></td>
                                        <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                        <td>₱<?= number_format($item['total_price'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
                                        <td><strong>₱<?= number_format($grand_total, 2) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Document References</h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Invoice No:</strong> <?= htmlspecialchars($iar['invoice_number'] ?? 'N/A') ?></p>
                                        <p><strong>Invoice Date:</strong> <?= $iar['invoice_date'] ? date('M j, Y', strtotime($iar['invoice_date'])) : 'N/A' ?></p>
                                        <p><strong>DR No:</strong> <?= htmlspecialchars($iar['dr_number'] ?? 'N/A') ?></p>
                                        <p><strong>DR Date:</strong> <?= $iar['dr_date'] ? date('M j, Y', strtotime($iar['dr_date'])) : ' ' ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">System Information</h3>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Date Created:</strong> <?= date('M j, Y g:i A', strtotime($iar['created_at'])) ?></p>
                                        <p><strong>IAR Number:</strong> <?= htmlspecialchars($iar['iar_number']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="text-right">
                            <button class="btn btn-secondary print-iar" data-id="<?= $iar['id'] ?>">
                                <i class="fas fa-print"></i> Print IAR
                            </button>
                            <a href="delivery_entry.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Handle IAR print button click
        $('.print-iar').click(function() {
            var iarId = $(this).data('id');
            Swal.fire({
                title: 'Generating IAR Document',
                html: 'Please wait while we generate the Inspection and Acceptance Report...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    // Create a hidden form to submit the print request
                    $('<form>').attr({
                        method: 'POST',
                        action: 'delivery_entry.php'
                    }).append(
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'iar_id',
                            value: iarId
                        }),
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'print_iar',
                            value: '1'
                        })
                    ).appendTo('body').submit();
                },
                timer: 1500
            });
        });
    });
</script>
</body>
</html>