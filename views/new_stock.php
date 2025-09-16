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

// Initialize variables
$name = $description = $unit_of_measure = "";
$category_id = 0;
$error = $success = "";

// Get categories for dropdown
$database = new Database();
$db = $database->getConnection();
$categories = [];
$category_query = "SELECT * FROM categories ORDER BY name";
$category_result = $db->query($category_query);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
        $categories[$row['id']] = $row['description']; // Added description
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $unit_of_measure = trim($_POST['unit_of_measure']);
    $category_id = intval($_POST['category_id']);

    // Validate required fields
    if (empty($name)) {
        $error = "Item name is required.";
    } else {
        // Insert into database
        $query = "INSERT INTO items (category_id, name, description, unit_of_measure, current_stock, min_stock_level) 
                  VALUES (?, ?, ?, ?, 0, 0)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("isss", $category_id, $name, $description, $unit_of_measure);
        
        if ($stmt->execute()) {
            $success = "Item added successfully!";
            // Reset form
            $name = $description = $unit_of_measure = "";
            $category_id = 0;
        } else {
            $error = "Error adding item: " . $db->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Stock - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(120deg, #28a745, #20c997);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .btn-success {
            background: linear-gradient(120deg, #28a745, #20c997);
            border: none;
        }
        .btn-success:hover {
            background: linear-gradient(120deg, #218838, #1e7e34);
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
                        <h1>Add New Item</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                            <li class="breadcrumb-item active">Add New Item</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">New Item Information</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?= $error ?></div>
                                <?php endif; ?>
                                <?php if ($success): ?>
                                    <div class="alert alert-success"><?= $success ?></div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="name">Item Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($name) ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="category_id">Category</label>
                                        <select class="form-control" id="category_id" name="category_id">
                                            <option value="0">-- Select Category --</option>
                                            <?php foreach ($categories as $id => $category_name): ?>
                                                <option value="<?= $id ?>" <?= $category_id == $id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category_name) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3"><?= htmlspecialchars($description) ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="unit_of_measure">Unit of Measure</label>
                                        <input type="text" class="form-control" id="unit_of_measure" 
                                               name="unit_of_measure" value="<?= htmlspecialchars($unit_of_measure) ?>">
                                    </div>

                                    <div class="form-group text-center">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-plus-circle"></i> Add Item
                                        </button>
                                        <a href="inventory.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                                        </a>
                                        <a href="delivery_entry.php" class="btn btn-primary btn-lg">
                                            <i class="fas fa-truck"></i> Delivery Entry
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>