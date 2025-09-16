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

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_category'])) {
        // Add new category
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $name, $description);
            $stmt->execute();
            $stmt->close();
        }
    } elseif (isset($_POST['edit_category'])) {
        // Edit category
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $query = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssi", $name, $description, $id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['delete_category'])) {
        // Delete category (only if no items are using it)
        $id = intval($_POST['id']);
        
        // Check if category is in use
        $check_query = "SELECT COUNT(*) as count FROM items WHERE category_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            $query = "DELETE FROM categories WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Get all categories
$query = "SELECT * FROM categories ORDER BY name";
$result = $db->query($query);
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
        }
        .card-header {
            background: linear-gradient(120deg, #6f42c1, #6610f2);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .btn-purple {
            background: linear-gradient(120deg, #6f42c1, #6610f2);
            color: white;
            border: none;
        }
        .btn-purple:hover {
            background: linear-gradient(120deg, #5a359c, #4d2d84);
            color: white;
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
                        <h1>Manage Categories</h1>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Add New Category</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="name">Category Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    <button type="submit" name="add_category" class="btn btn-purple">
                                        <i class="fas fa-plus"></i> Add Category
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Existing Categories</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="activityTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($categories)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No categories found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($categories as $category): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($category['name']) ?></td>
                                                        <td><?= htmlspecialchars($category['description'] ?? '') ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary" 
                                                                    data-toggle="modal" data-target="#editCategoryModal"
                                                                    data-id="<?= $category['id'] ?>"
                                                                    data-name="<?= htmlspecialchars($category['name']) ?>"
                                                                    data-description="<?= htmlspecialchars($category['description'] ?? '') ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                                                <button type="submit" name="delete_category" 
                                                                        class="btn btn-sm btn-danger"
                                                                        onclick="return confirm('Are you sure you want to delete this category?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_name">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
    $('#editCategoryModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');
        var description = button.data('description');
        
        var modal = $(this);
        modal.find('#edit_id').val(id);
        modal.find('#edit_name').val(name);
        modal.find('#edit_description').val(description);
    });
</script>
<script>
    $(document).ready(function() {
        // Initialize DataTable for activity logs
        $('#activityTable').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [[0, "desc"]],
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