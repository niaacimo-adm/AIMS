<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
// require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Handle CRUD operations - MUST BE AT THE TOP BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set content type to JSON for AJAX responses
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_category':
            echo addCategory($db);
            exit();
        case 'edit_category':
            echo editCategory($db);
            exit();
        case 'delete_category':
            echo deleteCategory($db);
            exit();
    }
}

function addCategory($db) {
    $category_name = $_POST['category_name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate required fields
    if (empty($category_name)) {
        return json_encode(['success' => false, 'message' => 'Category name is required']);
    }
    
    // Check if category name already exists
    $check_query = "SELECT category_id FROM ict_equipment_categories WHERE category_name = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("s", $category_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return json_encode(['success' => false, 'message' => 'Category name already exists']);
    }
    
    $query = "INSERT INTO ict_equipment_categories (category_name, description) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ss", $category_name, $description);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to add category: ' . $db->error]);
    }
}

function editCategory($db) {
    $category_id = $_POST['category_id'] ?? '';
    $category_name = $_POST['category_name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate required fields
    if (empty($category_name)) {
        return json_encode(['success' => false, 'message' => 'Category name is required']);
    }
    
    // Check if category name already exists (excluding current category)
    $check_query = "SELECT category_id FROM ict_equipment_categories WHERE category_name = ? AND category_id != ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("si", $category_name, $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return json_encode(['success' => false, 'message' => 'Category name already exists']);
    }
    
    $query = "UPDATE ict_equipment_categories SET category_name = ?, description = ? WHERE category_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ssi", $category_name, $description, $category_id);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to update category: ' . $db->error]);
    }
}

function deleteCategory($db) {
    $category_id = $_POST['category_id'] ?? '';
    
    if (empty($category_id)) {
        return json_encode(['success' => false, 'message' => 'Category ID is required']);
    }
    
    // Check if category is being used by any equipment
    $check_query = "SELECT COUNT(*) as count FROM ict_equipment WHERE category_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc()['count'];
    
    if ($count > 0) {
        return json_encode(['success' => false, 'message' => 'Cannot delete category. It is being used by ' . $count . ' equipment item(s).']);
    }
    
    $query = "DELETE FROM ict_equipment_categories WHERE category_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        return json_encode(['success' => false, 'message' => 'Failed to delete category: ' . $db->error]);
    }
}

// Get all categories
$query = "SELECT * FROM ict_equipment_categories ORDER BY category_name";
$result = $db->query($query);
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Equipment Categories - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_ict.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>ICT Equipment Categories</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ict_inventory.php">ICT Inventory</a></li>
                            <li class="breadcrumb-item active">Categories</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Equipment Categories</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCategoryModal">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Description</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No categories found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($category['category_name']) ?></td>
                                                <td><?= htmlspecialchars($category['description']) ?: '<span class="text-muted">No description</span>' ?></td>
                                                <td><?= date('M j, Y', strtotime($category['created_at'])) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm edit-category" 
                                                            data-id="<?= $category['category_id'] ?>"
                                                            data-name="<?= htmlspecialchars($category['category_name']) ?>"
                                                            data-description="<?= htmlspecialchars($category['description']) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm delete-category" 
                                                            data-id="<?= $category['category_id'] ?>"
                                                            data-name="<?= htmlspecialchars($category['category_name']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter category description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
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
            <form id="editCategoryForm">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Enter category description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete category: <strong id="delete_category_name"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCategoryDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Add Category Form
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_category');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#addCategoryModal').modal('hide');
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', result.message);
                }
            }
        });
    });

    // Edit Category - Populate modal
    $('.edit-category').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const description = $(this).data('description');
        
        $('#edit_category_id').val(id);
        $('#edit_category_name').val(name);
        $('#edit_description').val(description);
        
        $('#editCategoryModal').modal('show');
    });

    // Edit Category Form
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'edit_category');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#editCategoryModal').modal('hide');
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', result.message);
                }
            }
        });
    });

    // Delete Category
    let categoryToDelete = null;
    
    $('.delete-category').on('click', function() {
        categoryToDelete = $(this).data('id');
        const categoryName = $(this).data('name');
        $('#delete_category_name').text(categoryName);
        $('#deleteCategoryModal').modal('show');
    });

    $('#confirmCategoryDelete').on('click', function() {
        if (!categoryToDelete) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_category');
        formData.append('category_id', categoryToDelete);
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#deleteCategoryModal').modal('hide');
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('error', result.message);
                }
            }
        });
    });

    // Alert function
    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `<div class="alert ${alertClass} alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            ${message}
        </div>`;
        $('.content-wrapper').prepend(alertHtml);
        setTimeout(() => $('.alert').alert('close'), 5000);
    }
});
</script>
</body>
</html>