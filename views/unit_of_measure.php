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

$success_message = '';
$error_message = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error_message = 'Unit name is required.';
    } else {
        $stmt = $db->prepare("INSERT INTO unit_of_measure (name, abbreviation, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $abbreviation, $description);
        if ($stmt->execute()) {
            $success_message = 'Unit of measure added successfully.';
        } else {
            $error_message = ($db->errno === 1062)
                ? 'A unit with that name already exists.'
                : 'Failed to add unit of measure.';
        }
    }
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $id <= 0) {
        $error_message = 'Unit name is required.';
    } else {
        $stmt = $db->prepare("UPDATE unit_of_measure SET name = ?, abbreviation = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $abbreviation, $description, $id);
        if ($stmt->execute()) {
            $success_message = 'Unit of measure updated successfully.';
        } else {
            $error_message = ($db->errno === 1062)
                ? 'A unit with that name already exists.'
                : 'Failed to update unit of measure.';
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        // Look up the unit name so we can warn if items still reference it
        $check_stmt = $db->prepare("SELECT name FROM unit_of_measure WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $unit_row = $check_stmt->get_result()->fetch_assoc();

        $in_use = 0;
        if ($unit_row) {
            $usage_stmt = $db->prepare("SELECT COUNT(*) as cnt FROM items WHERE unit_of_measure = ?");
            $usage_stmt->bind_param("s", $unit_row['name']);
            $usage_stmt->execute();
            $in_use = (int)$usage_stmt->get_result()->fetch_assoc()['cnt'];
        }

        if ($in_use > 0) {
            $error_message = "Cannot delete: {$in_use} item(s) still use this unit of measure.";
        } else {
            $stmt = $db->prepare("DELETE FROM unit_of_measure WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = 'Unit of measure deleted successfully.';
            } else {
                $error_message = 'Failed to delete unit of measure.';
            }
        }
    }
}

// Fetch all units
$units = [];
$result = $db->query("SELECT id, name, abbreviation, description, created_at FROM unit_of_measure ORDER BY name");
if ($result) {
    $units = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit of Measure - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
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
                        <h1>Unit of Measure</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addUnitModal">
                            <i class="fas fa-plus-circle"></i> Add Unit of Measure
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Units of Measure</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="unitTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Abbreviation</th>
                                        <th>Description</th>
                                        <th>Date Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($units)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No units of measure found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($units as $unit): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($unit['name']) ?></td>
                                                <td><?= htmlspecialchars($unit['abbreviation'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($unit['description'] ?? '') ?></td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($unit['created_at']))) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-unit-btn"
                                                        data-id="<?= $unit['id'] ?>"
                                                        data-name="<?= htmlspecialchars($unit['name']) ?>"
                                                        data-abbreviation="<?= htmlspecialchars($unit['abbreviation'] ?? '') ?>"
                                                        data-description="<?= htmlspecialchars($unit['description'] ?? '') ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-unit-btn"
                                                        data-id="<?= $unit['id'] ?>"
                                                        data-name="<?= htmlspecialchars($unit['name']) ?>">
                                                        <i class="fas fa-trash"></i> Delete
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
<?php include '../includes/footer.php'; ?>

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Unit of Measure</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="50" placeholder="e.g. Piece">
                    </div>
                    <div class="form-group">
                        <label>Abbreviation</label>
                        <input type="text" name="abbreviation" class="form-control" maxlength="10" placeholder="e.g. pc">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" maxlength="100" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Unit of Measure</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Abbreviation</label>
                        <input type="text" name="abbreviation" id="edit_abbreviation" class="form-control" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" maxlength="100" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteUnitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete <strong id="delete_name"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#unitTable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "order": [[0, "asc"]],
            "pageLength": 25
        });

        // Populate edit modal
        $('.edit-unit-btn').on('click', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_abbreviation').val($(this).data('abbreviation'));
            $('#edit_description').val($(this).data('description'));
            $('#editUnitModal').modal('show');
        });

        // Populate delete modal
        $('.delete-unit-btn').on('click', function() {
            $('#delete_id').val($(this).data('id'));
            $('#delete_name').text($(this).data('name'));
            $('#deleteUnitModal').modal('show');
        });
    });
</script>
</body>
</html>