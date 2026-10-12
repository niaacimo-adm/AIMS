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

// Load PhpSpreadsheet - Use the same approach as service_request.php
try {
    // Try Composer autoloader first
    $composerAutoload = '../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } 
    // If not found, try manual include
    else {
        $phpspreadsheetPath = '../includes/phpspreadsheet/';
        if (file_exists($phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php')) {
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Spreadsheet.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Writer/Xlsx.php';
        } else {
            throw new Exception("PhpSpreadsheet library not found. Please install via Composer: composer require phpoffice/phpspreadsheet");
        }
    }
} catch (Exception $e) {
    die("Error loading PhpSpreadsheet: " . $e->getMessage());
}

$database = new Database();
$db = $database->getConnection();;

// Partial-delivery columns (added automatically the first time this page runs)
$chk = $db->query("SHOW COLUMNS FROM iar_items LIKE 'ordered_quantity'");
if ($chk && $chk->num_rows === 0) {
    $db->query("ALTER TABLE iar_items ADD COLUMN ordered_quantity INT NULL AFTER quantity");
}
$chk = $db->query("SHOW COLUMNS FROM iar_records LIKE 'delivery_status'");
if ($chk && $chk->num_rows === 0) {
    $db->query("ALTER TABLE iar_records ADD COLUMN delivery_status VARCHAR(10) NOT NULL DEFAULT 'Complete'");
}

// Initialize variables
$error = $success = "";

// Flash message set before a redirect (Post/Redirect/Get) - shown once, then cleared
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
$delivery_data = [
    'po_number' => '',
    'po_date' => date('Y-m-d'),
    'supplier' => '',
    'delivery_date' => date('Y-m-d'),
    'items' => []
];

// Get items for dropdown
$items = [];
$item_query = "SELECT id, name, description, unit_of_measure, current_stock FROM items ORDER BY name";
$item_result = $db->query($item_query);
if ($item_result) {
    while ($row = $item_result->fetch_assoc()) {
        $items[$row['id']] = $row;
    }
}

// Categories & units of measure (used by the "Add New Item" quick-add modal)
$categories = [];
$category_result = $db->query("SELECT id, name FROM categories ORDER BY name");
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
}
$units = [];
$unit_result = $db->query("SELECT id, name, abbreviation FROM unit_of_measure ORDER BY name");
if ($unit_result) {
    while ($row = $unit_result->fetch_assoc()) {
        $units[] = $row;
    }
}

// AJAX: quickly add a brand-new item from inside the Delivery Entry modal
// (same insert as view_inventory.php, but returns the new item so the form is NOT reloaded)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_item'])) {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);

    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Item name is required.']);
        exit();
    }
    if ($unit_of_measure === '') {
        echo json_encode(['success' => false, 'message' => 'Unit of measure is required.']);
        exit();
    }

    // Prevent duplicate item names
    $dup = $db->prepare("SELECT id FROM items WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $dup->bind_param("s", $name);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        echo json_encode(['success' => false, 'message' => 'An item with this name already exists. Please select it from the Item list.']);
        exit();
    }
    $dup->close();

    $stmt = $db->prepare("INSERT INTO items (category_id, name, description, unit_of_measure) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $category_id, $name, $description, $unit_of_measure);
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Item added successfully!',
            'item' => ['id' => $db->insert_id, 'name' => $name, 'description' => $description]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding item: ' . $db->error]);
    }
    $stmt->close();
    exit();
}

// AJAX: what is still owed on the PO of a partial IAR (used for the follow-up delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_po_balance'])) {
    header('Content-Type: application/json');
    $po_iar_id = intval($_POST['iar_id'] ?? 0);
    $st = $db->prepare("SELECT po_number, po_date, supplier, requisition_office FROM iar_records WHERE id = ?");
    $st->bind_param("i", $po_iar_id);
    $st->execute();
    $po = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$po) {
        echo json_encode(['success' => false, 'message' => 'IAR not found']);
        exit();
    }

    $q = $db->prepare("SELECT di.item_id, MAX(ii.ordered_quantity) AS ordered, SUM(ii.quantity) AS delivered, MAX(ii.unit_price) AS unit_price
                       FROM iar_items ii
                       JOIN delivery_items di ON di.id = ii.delivery_item_id
                       JOIN iar_records ir ON ir.id = ii.iar_id
                       WHERE ir.po_number = ?
                       GROUP BY di.item_id");
    $q->bind_param("s", $po['po_number']);
    $q->execute();
    $balance = [];
    foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $remaining = (int)$r['ordered'] - (int)$r['delivered'];
        if ($remaining > 0) {
            $balance[] = [
                'item_id'    => (int)$r['item_id'],
                'ordered'    => (int)$r['ordered'],
                'delivered'  => (int)$r['delivered'],
                'remaining'  => $remaining,
                'unit_price' => (float)$r['unit_price'],
            ];
        }
    }
    $q->close();

    // Next free IAR number for this PO: IAR-YYYY-MM-####-2, -3, ...
    $base_iar = str_replace('PO-', 'IAR-', $po['po_number']);
    $n = 2;
    do {
        $iar_no = $base_iar . '-' . $n++;
        $c = $db->prepare("SELECT id FROM iar_records WHERE iar_number = ?");
        $c->bind_param("s", $iar_no);
        $c->execute();
        $c->store_result();
        $exists = $c->num_rows > 0;
        $c->close();
    } while ($exists);

    echo json_encode(['success' => true, 'iar_number' => $iar_no, 'items' => $balance] + $po);
    exit();
}

// Get sections for dropdown
$sections = [];
$section_query = "SELECT section_id, section_name FROM section ORDER BY section_name";
$section_result = $db->query($section_query);
if ($section_result) {
    while ($row = $section_result->fetch_assoc()) {
        $sections[$row['section_id']] = $row['section_name'];
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle delete action
    if (isset($_POST['delete_iar'])) {
        $iar_id = intval($_POST['iar_id']);

        $db->begin_transaction();
        try {
            // 1. Refuse to delete a delivery that already has RIS issued from it
            $ris_chk = $db->prepare("SELECT ris_number FROM ris_records WHERE iar_id = ?");
            $ris_chk->bind_param("i", $iar_id);
            $ris_chk->execute();
            $ris_rows = $ris_chk->get_result()->fetch_all(MYSQLI_ASSOC);
            $ris_chk->close();
            if (!empty($ris_rows)) {
                $ris_list = implode(', ', array_column($ris_rows, 'ris_number'));
                throw new Exception("this delivery already has RIS issued from it ($ris_list). Delete those RIS first (RIS Records page), then delete the delivery.");
            }

            // 2. Collect everything that belongs to this IAR
            $get_stmt = $db->prepare("SELECT ii.id AS iar_item_id, di.id AS delivery_item_id, di.delivery_id AS movement_id,
                                             di.item_id, di.quantity
                                      FROM iar_items ii
                                      JOIN delivery_items di ON ii.delivery_item_id = di.id
                                      WHERE ii.iar_id = ?");
            $get_stmt->bind_param("i", $iar_id);
            $get_stmt->execute();
            $rows = $get_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $get_stmt->close();

            // 3. Make sure removing these quantities will not push any item's stock below zero
            $per_item = [];
            foreach ($rows as $r) {
                $per_item[$r['item_id']] = ($per_item[$r['item_id']] ?? 0) + $r['quantity'];
            }
            foreach ($per_item as $chk_item_id => $chk_qty) {
                $st = $db->prepare("SELECT name, current_stock FROM items WHERE id = ?");
                $st->bind_param("i", $chk_item_id);
                $st->execute();
                $it = $st->get_result()->fetch_assoc();
                $st->close();
                if ($it && $it['current_stock'] < $chk_qty) {
                    throw new Exception("not enough stock to reverse this delivery for " . $it['name'] . " (in stock: " . $it['current_stock'] . ", delivered: $chk_qty). Part of it was already issued.");
                }
            }

            // 4. Delete child rows first, then parents
            $d1 = $db->prepare("DELETE FROM iar_items WHERE iar_id = ?");
            $d1->bind_param("i", $iar_id);
            if (!$d1->execute()) { throw new Exception($d1->error); }
            $d1->close();

            foreach ($rows as $r) {
                // delivery_items row
                $d2 = $db->prepare("DELETE FROM delivery_items WHERE id = ?");
                $d2->bind_param("i", $r['delivery_item_id']);
                if (!$d2->execute()) { throw new Exception($d2->error); }
                $d2->close();

                // take the delivered quantity back out of stock
                $d3 = $db->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
                $d3->bind_param("ii", $r['quantity'], $r['item_id']);
                if (!$d3->execute()) { throw new Exception($d3->error); }
                $d3->close();

                // the 'in' stock movement
                $d4 = $db->prepare("DELETE FROM stock_movements WHERE id = ?");
                $d4->bind_param("i", $r['movement_id']);
                if (!$d4->execute()) { throw new Exception($d4->error); }
                $d4->close();
            }

            // 5. The IAR record itself
            $d5 = $db->prepare("DELETE FROM iar_records WHERE id = ?");
            $d5->bind_param("i", $iar_id);
            if (!$d5->execute()) { throw new Exception($d5->error); }
            $d5->close();

            $db->commit();
            $success = "IAR record deleted successfully!";

        } catch (Exception $e) {
            $db->rollback();
            $error = "Cannot delete IAR record: " . $e->getMessage();
        }
    }
    // Handle save delivery action
    elseif (isset($_POST['save_delivery'])) {
        $iar_number = trim($_POST['iar_number']); 
        $po_number = trim($_POST['po_number']); 
        $po_date = trim($_POST['po_date']); 
        $supplier = trim($_POST['supplier']);
        $delivery_date = trim($_POST['delivery_date']);
        $requisition_office = trim($_POST['requisition_office']);
        $invoice_number = trim($_POST['invoice_number']);
        $invoice_date = trim($_POST['invoice_date']);
        $dr_number = trim($_POST['dr_number']);
        $dr_date = trim($_POST['dr_date']);
        $item_ids = $_POST['item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_costs = $_POST['unit_cost'] ?? [];
        $ordered_quantities = $_POST['ordered_quantity'] ?? [];

        // Validate required fields
        if (empty($po_number) || empty($supplier) || empty($delivery_date) || empty($requisition_office)) {
            $error = "PO Number, Supplier, Delivery Date, and Requisition Office are required.";
        } elseif (!preg_match('/^PO-\d{4}-\d{2}-\d{4}$/', $po_number)) {
            $error = "PO Number must be in the format PO-YYYY-MM-#### (e.g., PO-2025-03-0045).";
        } elseif (empty($item_ids) || empty($quantities)) {
            $error = "At least one item is required.";
        } else {
            // Begin transaction
            $db->begin_transaction();
            
            try {
                // Create IAR record
                $iar_query = "INSERT INTO iar_records (iar_number, po_number, po_date, supplier, requisition_office, invoice_number, invoice_date, dr_number, dr_date, delivery_date, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $iar_stmt = $db->prepare($iar_query);
                $iar_stmt->bind_param("ssssssssssi", $iar_number, $po_number, $po_date, $supplier, $requisition_office, $invoice_number, $invoice_date, $dr_number, $dr_date, $delivery_date, $_SESSION['user_id']);
                $iar_stmt->execute();
                $iar_id = $db->insert_id;
                $iar_stmt->close();
                
                $total_amount = 0;
                $is_partial = false;      // becomes true when any item is still short of the PO quantity
                $partial_items = 0;

                // Process each item
                foreach ($item_ids as $index => $item_id) {
                    $quantity = intval($quantities[$index]);
                    $unit_cost = floatval($unit_costs[$index]);
                    $total_cost = $quantity * $unit_cost;
                    $total_amount += $total_cost;
                    
                    if ($quantity > 0 && $item_id > 0) {
                        // --- Complete / Partial check against the PO quantity ---
                        // Earlier deliveries of the same item on the same PO count toward the ordered quantity.
                        $ordered_qty = intval($ordered_quantities[$index] ?? 0);
                        if ($ordered_qty <= 0) {
                            $ordered_qty = $quantity;   // no PO quantity given: treat as fully delivered
                        }
                        $prior_stmt = $db->prepare("SELECT COALESCE(SUM(ii.quantity), 0) AS delivered
                                                    FROM iar_items ii
                                                    JOIN delivery_items di ON di.id = ii.delivery_item_id
                                                    JOIN iar_records ir ON ir.id = ii.iar_id
                                                    WHERE ir.po_number = ? AND di.item_id = ?");
                        $prior_stmt->bind_param("si", $po_number, $item_id);
                        $prior_stmt->execute();
                        $already_delivered = intval($prior_stmt->get_result()->fetch_assoc()['delivered']);
                        $prior_stmt->close();

                        $cumulative = $already_delivered + $quantity;
                        if ($cumulative > $ordered_qty) {
                            throw new Exception("Delivered quantity for " . $items[$item_id]['name'] . " exceeds the ordered quantity (Ordered: $ordered_qty, Already delivered: $already_delivered, This delivery: $quantity).");
                        }
                        if ($cumulative < $ordered_qty) {
                            $is_partial = true;
                            $partial_items++;
                        }

                        // Record stock movement
                        $movement_query = "INSERT INTO stock_movements (item_id, movement_type, quantity, reference, unit_cost) 
                                           VALUES (?, 'in', ?, ?, ?)";
                        $movement_stmt = $db->prepare($movement_query);
                        $movement_stmt->bind_param("iisd", $item_id, $quantity, $po_number, $unit_cost);
                        $movement_stmt->execute();
                        $movement_id = $db->insert_id;
                        $movement_stmt->close();
                        
                        // Add to delivery_items
                        $delivery_item_query = "INSERT INTO delivery_items (delivery_id, item_id, quantity, unit_cost, total_cost) 
                                               VALUES (?, ?, ?, ?, ?)";
                        $delivery_item_stmt = $db->prepare($delivery_item_query);
                        $delivery_item_stmt->bind_param("iiidd", $movement_id, $item_id, $quantity, $unit_cost, $total_cost);
                        $delivery_item_stmt->execute();
                        $delivery_item_id = $db->insert_id;
                        $delivery_item_stmt->close();
                        
                        // Add to iar_items
                        $item_info = $items[$item_id];
                        $iar_item_query = "INSERT INTO iar_items (iar_id, delivery_item_id, description, unit, quantity, ordered_quantity, unit_price, total_price) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $iar_item_stmt = $db->prepare($iar_item_query);
                        $iar_item_stmt->bind_param("iissiidd", $iar_id, $delivery_item_id, $item_info['name'], $item_info['unit_of_measure'], $quantity, $ordered_qty, $unit_cost, $total_cost);
                        $iar_item_stmt->execute();
                        $iar_item_stmt->close();
                        
                        // Update current stock
                        $stock_query = "UPDATE items SET current_stock = current_stock + ? WHERE id = ?";
                        $stock_stmt = $db->prepare($stock_query);
                        $stock_stmt->bind_param("ii", $quantity, $item_id);
                        $stock_stmt->execute();
                        $stock_stmt->close();
                    }
                }
                
                // Update total amount in IAR record
                // Status is PO-wide: Partial while ANY item on this PO (across all its IARs) is still short
                $short = $db->prepare("SELECT COUNT(*) AS n FROM (
                                           SELECT di.item_id, MAX(ii.ordered_quantity) AS ordered, SUM(ii.quantity) AS delivered
                                           FROM iar_items ii
                                           JOIN delivery_items di ON di.id = ii.delivery_item_id
                                           JOIN iar_records ir ON ir.id = ii.iar_id
                                           WHERE ir.po_number = ?
                                           GROUP BY di.item_id
                                           HAVING delivered < ordered) t");
                $short->bind_param("s", $po_number);
                $short->execute();
                $partial_items = (int)$short->get_result()->fetch_assoc()['n'];
                $short->close();
                $is_partial = $partial_items > 0;
                $delivery_status = $is_partial ? 'Partial' : 'Complete';
                $update_iar_query = "UPDATE iar_records SET total_amount = ?, delivery_status = ? WHERE id = ?";
                $update_iar_stmt = $db->prepare($update_iar_query);
                $update_iar_stmt->bind_param("dsi", $total_amount, $delivery_status, $iar_id);
                $update_iar_stmt->execute();
                $update_iar_stmt->close();
                
                $db->commit();
                $success = "Delivery entry recorded successfully! IAR Number: $iar_number" . ($is_partial ? " - PARTIAL delivery ($partial_items item(s) still short of the PO quantity)." : " - Complete delivery.");
                
                // Reset form
                $delivery_data = [
                    'po_number' => '',
                    'po_date' => date('Y-m-d'),
                    'supplier' => '',
                    'delivery_date' => date('Y-m-d'),
                    'items' => []
                ];
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error recording delivery: " . $e->getMessage();
            }
        }
    }

    elseif (isset($_POST['print_iar'])) {
        $iar_id = $_POST['iar_id'];
        generateIARExcel($db, $iar_id);
        exit();
    }

    // Load the items of a RIS for the PAR modal (JSON)
    elseif (isset($_POST['get_ris_items'])) {
        header('Content-Type: application/json');
        $data = fetchRisParItems($db, intval($_POST['ris_id'] ?? 0));
        echo json_encode($data ? ['success' => true] + $data : ['success' => false, 'message' => 'RIS not found']);
        exit();
    }

    // Download a Property Acknowledgment Receipt (PAR) built from a RIS
    elseif (isset($_POST['print_par'])) {
        $par_ris_id = intval($_POST['par_ris_id'] ?? 0);
        $par_selected = array_map('intval', $_POST['par_items'] ?? []);
        if ($par_ris_id <= 0 || empty($par_selected)) {
            $error = "Select a RIS and at least one item for the PAR.";
        } else {
            generateParExcel(
                $db,
                $par_ris_id,
                $par_selected,
                $_POST['par_property_no'] ?? [],
                trim($_POST['par_no'] ?? ''),
                trim($_POST['par_fund_cluster'] ?? ''),
                trim($_POST['par_issued_by'] ?? ''),
                trim($_POST['par_issued_position'] ?? '')
            );
            exit();
        }
    }

    // Download a Stock Card (SC) for one item
    elseif (isset($_POST['print_stock_card'])) {
        $stock_item_id = intval($_POST['stock_item_id'] ?? 0);
        if ($stock_item_id <= 0) {
            $error = "Please select an item for the Stock Card.";
        } else {
            generateStockCardExcel(
                $db,
                $stock_item_id,
                trim($_POST['stock_fund_cluster'] ?? ''),
                trim($_POST['stock_no'] ?? ''),
                trim($_POST['stock_reorder_point'] ?? ''),
                trim($_POST['stock_date_from'] ?? ''),
                trim($_POST['stock_date_to'] ?? '')
            );
            exit();
        }
    }
}

// Process RIS form submission
if (isset($_POST['save_ris'])) {
    $requisition_office = trim($_POST['ris_requisition_office']);
    $purpose = trim($_POST['ris_purpose']);
    $requested_by_id = intval($_POST['ris_requested_by']);
    $designation = trim($_POST['ris_designation']);
    $item_ids = $_POST['ris_item_id'] ?? [];
    $quantities = $_POST['ris_quantity'] ?? [];
    $iar_id = intval($_POST['ris_iar_id']);

    // Get employee details
    $employee_query = "SELECT CONCAT(first_name, ' ', last_name) as full_name 
                      FROM employee WHERE emp_id = ?";
    $employee_stmt = $db->prepare($employee_query);
    $employee_stmt->bind_param("i", $requested_by_id);
    $employee_stmt->execute();
    $employee_result = $employee_stmt->get_result();
    $employee = $employee_result->fetch_assoc();
    $employee_stmt->close();
    
    $requested_by = $employee['full_name'] ?? '';

    // Validate required fields
    if (empty($requisition_office) || empty($purpose) || empty($requested_by)) {
        $error = "Requisition Office, Purpose, and Requested By are required.";
    } elseif (empty($item_ids) || empty($quantities)) {
        $error = "At least one item is required.";
    } else {
        // Begin transaction
        $db->begin_transaction();
        
        try {
            // Generate RIS number (YYYY-#### format)
            $current_year = date('Y');
            $ris_query = "SELECT ris_number FROM ris_records WHERE ris_number LIKE '$current_year-%' ORDER BY id DESC LIMIT 1";
            $ris_result = $db->query($ris_query);
            $next_ris_number = "$current_year-0001";

            if ($ris_result && $ris_result->num_rows > 0) {
                $last_ris = $ris_result->fetch_assoc();
                $last_number = intval(substr($last_ris['ris_number'], -4));
                $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
                $next_ris_number = "$current_year-$next_number";
            }

            // Create RIS record
            $ris_query = "INSERT INTO ris_records (ris_number, iar_id, requisition_office, purpose, requested_by, requested_by_id, designation, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $ris_stmt = $db->prepare($ris_query);
            $ris_stmt->bind_param("sisssisi", $next_ris_number, $iar_id, $requisition_office, $purpose, $requested_by, $requested_by_id, $designation, $_SESSION['user_id']);
            $ris_stmt->execute();
            $ris_id = $db->insert_id;
            $ris_stmt->close();
            
            // Process each item
            foreach ($item_ids as $index => $item_id) {
                $quantity = intval($quantities[$index]);
                
                if ($quantity > 0 && $item_id > 0) {
                    // Check if sufficient stock is available
                    $stock_query = "SELECT current_stock, unit_of_measure FROM items WHERE id = ?";
                    $stock_stmt = $db->prepare($stock_query);
                    $stock_stmt->bind_param("i", $item_id);
                    $stock_stmt->execute();
                    $stock_result = $stock_stmt->get_result();
                    $item_data = $stock_result->fetch_assoc();
                    $current_stock = $item_data['current_stock'];
                    $unit_of_measure = $item_data['unit_of_measure'];
                    $stock_stmt->close();
                    
                    if ($current_stock >= $quantity) {
                        // Record stock movement with reference to RIS
                        $movement_query = "INSERT INTO stock_movements (item_id, movement_type, quantity, reference, notes) 
                                          VALUES (?, 'out', ?, ?, ?)";
                        $movement_stmt = $db->prepare($movement_query);
                        $notes = "RIS: $next_ris_number - $purpose";
                        $movement_stmt->bind_param("iiss", $item_id, $quantity, $next_ris_number, $notes);
                        $movement_stmt->execute();
                        $movement_id = $db->insert_id;
                        $movement_stmt->close();
                        
                        // Add to ris_items
                        $item_info = $items[$item_id];
                        $ris_item_query = "INSERT INTO ris_items (ris_id, item_id, description, unit, quantity, movement_id) 
                                        VALUES (?, ?, ?, ?, ?, ?)";
                        $ris_item_stmt = $db->prepare($ris_item_query);
                        $ris_item_stmt->bind_param("iissii", $ris_id, $item_id, $item_info['name'], $unit_of_measure, $quantity, $movement_id);
                        $ris_item_stmt->execute();
                        $ris_item_stmt->close();
                        
                        // Update current stock
                        $update_stock = "UPDATE items SET current_stock = current_stock - ? WHERE id = ?";
                        $update_stmt = $db->prepare($update_stock);
                        $update_stmt->bind_param("ii", $quantity, $item_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        throw new Exception("Insufficient stock for item: " . $items[$item_id]['name'] . ". Available: $current_stock, Requested: $quantity");
                    }
                }
            }
            
            $db->commit();
            $success = "RIS created successfully! RIS Number: $next_ris_number";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error creating RIS: " . $e->getMessage();
        }
    }
}

// Post/Redirect/Get: after a successful save / delete / RIS creation, redirect so that
// refreshing the page does not trigger the "Confirm Form Resubmission" prompt.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $success !== '' && $error === '') {
    $_SESSION['flash_success'] = $success;
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit();
}

function generateIARExcel($db, $iar_id) {
    try {
        // Fetch IAR details with related information
        $query = "SELECT i.*, u.user as created_by_name
                 FROM iar_records i 
                 LEFT JOIN users u ON i.created_by = u.id 
                 WHERE i.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $iar_id);
        $stmt->execute();
        $iar = $stmt->get_result()->fetch_assoc();
        
        if (!$iar) {
            throw new Exception("IAR record not found");
        }
        
        // Fetch IAR items
        $items_query = "SELECT ii.*, di.item_id, i.name as item_name, i.description as item_description, i.unit_of_measure as unit 
               FROM iar_items ii
               LEFT JOIN delivery_items di ON ii.delivery_item_id = di.id
               LEFT JOIN items i ON di.item_id = i.id
               WHERE ii.iar_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $iar_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Load the IAR template
        $template_path = "../public/templates/IAR-2025.xlsx";
        if (file_exists($template_path)) {
            $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        } else {
            // Create a basic IAR format if template doesn't exist
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set basic headers
            $sheet->setCellValue('A1', 'INSPECTION AND ACCEPTANCE REPORT');
            $sheet->setCellValue('A2', 'Entity Name:');
            $sheet->setCellValue('A3', 'Supplier:');
            $sheet->setCellValue('A4', 'Address:');
            $sheet->setCellValue('A5', 'TIN:');
            $sheet->setCellValue('E2', 'PO Number:');
            $sheet->setCellValue('E3', 'PO Date:');
            $sheet->setCellValue('E4', 'Invoice No:');
            $sheet->setCellValue('E5', 'Invoice Date:');
            $sheet->setCellValue('E6', 'DR No:');
            $sheet->setCellValue('E7', 'DR Date:');
            
            // Item table headers
            $sheet->setCellValue('A9', 'Stock/Property No.');
            $sheet->setCellValue('B9', 'Description');
            $sheet->setCellValue('C9', 'Unit');
            $sheet->setCellValue('D9', 'Quantity');
            $sheet->setCellValue('E9', 'Unit Price');
            $sheet->setCellValue('F9', 'Total Price');
        }
        
        $sheet = $spreadsheet->getActiveSheet();
        
        // Fill in the header information with null checks
        $sheet->setCellValue('C5', $iar['supplier'] ?? '');
        $sheet->setCellValue('F7', $iar['requisition_office'] ?? '');
        $sheet->setCellValue('C6', $iar['po_number'] ?? '');
        $sheet->setCellValue('F6', !empty($iar['po_date']) ? date('m/d/Y', strtotime($iar['po_date'])) : '');
        $sheet->setCellValue('M5', $iar['iar_number'] ?? '');
        $sheet->setCellValue('M6', !empty($iar['delivery_date']) ? date('m/d/Y', strtotime($iar['delivery_date'])) : '');
        $sheet->setCellValue('M8', !empty($iar['invoice_date']) ? date('m/d/Y', strtotime($iar['invoice_date'])) : '');
        $sheet->setCellValue('M7', $iar['invoice_number'] ?? '');
        $sheet->setCellValue('M9', $iar['dr_number'] ?? '');

        // DR Date handling
        $dr_date = '';
        if (!empty($iar['dr_date']) && $iar['dr_date'] != '0000-00-00') {
            $dr_date = date('m/d/Y', strtotime($iar['dr_date']));
        }
        $sheet->setCellValue('M10', $dr_date);
        
        $start_row = 13;
        
        $item_count = count($items);
        if ($item_count > 1) {
            $rows_to_insert = $item_count - 1;
            
            $sheet->insertNewRowBefore($start_row + 1, $rows_to_insert);
        }
        
        // Fill in item details
        $row = $start_row;
        foreach ($items as $item) {
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->mergeCells('C' . $row . ':E' . $row);
            $sheet->setCellValue('A' . $row, $item['stock_property_no'] ?? '');
            $sheet->setCellValue('F' . $row, $item['description'] ?? $item['item_name']);
            $sheet->setCellValue('G' . $row, $item['unit'] ?? '');
            $sheet->mergeCells('G' . $row . ':H' . $row);
            $sheet->setCellValue('I' . $row, $item['quantity'] ?? '');
            $sheet->mergeCells('I' . $row . ':K' . $row);
            $sheet->setCellValue('L' . $row, number_format($item['unit_price'], 2));
            $sheet->setCellValue('M' . $row, number_format($item['total_price'], 2));
            $row++;
        }
        
        // Calculate the row where totals should be (after all items)
        $total_row = $row + 1;
        
        // Fill in totals
        $total = $iar['total_amount'] ?? array_sum(array_column($items, 'total_price'));
        $sheet->setCellValue('M' . $total_row, number_format($total, 2));
        
        // ---- Complete / Partial marks on the IAR ----
        // Template boxes (row 18 + any rows inserted for extra items):
        //   Received/Inspected: B18 = Complete, B19 = Partial     Acceptance: H18 = Complete, J18 = Partial
        $shift = max(0, $item_count - 1);
        $is_partial_iar = (($iar['delivery_status'] ?? 'Complete') === 'Partial');
        $mark_cells = $is_partial_iar
            ? ['B' . (19 + $shift), 'J' . (18 + $shift)]
            : ['B' . (18 + $shift), 'H' . (18 + $shift)];
        foreach ($mark_cells as $cell) {
            $sheet->setCellValue($cell, "\u{2713}");
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle($cell)->getAlignment()
                  ->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                  ->setVertical(PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }

        // For a partial delivery, list the short items on the "(please specify quantity)" lines (L19:M21)
        if ($is_partial_iar) {
            $spec_lines = [];
            foreach ($items as $item) {
                $ordered = intval($item['ordered_quantity'] ?? 0);
                if ($ordered <= 0) continue;
                // Quantity delivered so far on this PO for this item, up to and including this IAR
                $cum_stmt = $db->prepare("SELECT COALESCE(SUM(ii2.quantity), 0) AS delivered
                                          FROM iar_items ii2
                                          JOIN delivery_items di2 ON di2.id = ii2.delivery_item_id
                                          JOIN iar_records ir2 ON ir2.id = ii2.iar_id
                                          WHERE ir2.po_number = ? AND di2.item_id = ? AND ir2.id <= ?");
                $cum_stmt->bind_param("sii", $iar['po_number'], $item['item_id'], $iar_id);
                $cum_stmt->execute();
                $delivered_so_far = intval($cum_stmt->get_result()->fetch_assoc()['delivered']);
                $cum_stmt->close();
                if ($delivered_so_far < $ordered) {
                    $spec_lines[] = ($item['item_name'] ?? $item['description']) . ': ' . $delivered_so_far . ' of ' . $ordered;
                }
            }
            $max_lines = 3;
            if (count($spec_lines) > $max_lines) {
                $extra = count($spec_lines) - ($max_lines - 1);
                $spec_lines = array_slice($spec_lines, 0, $max_lines - 1);
                $spec_lines[] = '+' . $extra . ' more item(s)';
            }
            foreach ($spec_lines as $i => $line) {
                $cell = 'L' . (19 + $shift + $i);
                $sheet->setCellValue($cell, $line);
                $sheet->getStyle($cell)->getAlignment()->setShrinkToFit(true);
                $sheet->getStyle($cell)->getFont()->setSize(8);
            }
        }
        
        // Set filename
        $filename = "IAR_" . ($iar['iar_number'] ?? $iar_id) . ".xlsx";
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        // Output the file
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Error generating IAR Excel: " . $e->getMessage();
    }
}

function buildStockCardLines($db, $item_id, $current_stock, $date_from = '', $date_to = '') {
    $query = "SELECT sm.id, sm.movement_type, sm.quantity, sm.reference,
                     COALESCE(ir.delivery_date, DATE(sm.created_at)) AS movement_date,
                     ir.iar_number, rr.ris_number, rr.requisition_office
              FROM stock_movements sm
              LEFT JOIN delivery_items di ON sm.movement_type = 'in'  AND di.delivery_id = sm.id
              LEFT JOIN iar_items ii      ON ii.delivery_item_id = di.id
              LEFT JOIN iar_records ir    ON ir.id = ii.iar_id
              LEFT JOIN ris_items ri      ON sm.movement_type = 'out' AND ri.movement_id = sm.id
              LEFT JOIN ris_records rr    ON rr.id = ri.ris_id
              WHERE sm.item_id = ? AND sm.movement_type IN ('in', 'out')
              ORDER BY movement_date ASC, sm.id ASC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $movements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_in = 0;
    $total_out = 0;
    foreach ($movements as $m) {
        if ($m['movement_type'] === 'in') { $total_in += (int)$m['quantity']; }
        else { $total_out += (int)$m['quantity']; }
    }
    // Stock that existed before any recorded movement
    $opening = (int)$current_stock - $total_in + $total_out;

    $entries = [];
    $running = $opening;
    foreach ($movements as $m) {
        $qty = (int)$m['quantity'];
        $is_in = ($m['movement_type'] === 'in');
        $running += $is_in ? $qty : -$qty;
        $reference = $is_in
            ? ($m['iar_number'] ?: $m['reference'])
            : ($m['ris_number'] ?: $m['reference']);
        // Show IAR-YYYY-MM-#### as YYYY-#### (same style as the RIS numbers)
        if (preg_match('/^IAR-(\d{4})-\d{2}-(\d{4})$/', (string)$reference, $ref_parts)) {
            $reference = $ref_parts[1] . '-' . $ref_parts[2];
        }
        $entries[] = [
            'ts'      => strtotime($m['movement_date']),
            'date'    => date('m/d/Y', strtotime($m['movement_date'])),
            'ref'     => (string)$reference,
            'in'      => $is_in ? $qty : null,
            'out'     => $is_in ? null : $qty,
            'office'  => $is_in ? '' : (string)($m['requisition_office'] ?? ''),
            'balance' => $running,
        ];
    }

    $from_ts = $date_from !== '' ? strtotime($date_from) : false;
    $to_ts   = $date_to   !== '' ? strtotime($date_to)   : false;

    $forward = $opening;
    $lines = [];
    foreach ($entries as $e) {
        if ($from_ts !== false && $e['ts'] < $from_ts) { $forward = $e['balance']; continue; }
        if ($to_ts !== false && $e['ts'] > $to_ts) { break; }
        $lines[] = $e;
    }
    if ($forward != 0) {
        array_unshift($lines, [
            'ts' => 0, 'date' => '',
            'ref' => ($from_ts !== false ? 'Balance forwarded' : 'Beginning balance'),
            'in' => null, 'out' => null, 'office' => '', 'balance' => $forward,
        ]);
    }
    return $lines;
}

function generateStockCardExcel($db, $item_id, $fund_cluster = '', $stock_no = '', $reorder_point = '', $date_from = '', $date_to = '') {
    try {
        $stmt = $db->prepare("SELECT id, name, description, unit_of_measure, current_stock FROM items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            throw new Exception("Item not found");
        }

        $lines = buildStockCardLines($db, $item_id, $item['current_stock'], $date_from, $date_to);

        $template_path = "../public/templates/STOCK_CARD.xlsx";
        if (!file_exists($template_path)) {
            throw new Exception("Stock Card template not found (public/templates/STOCK_CARD.xlsx)");
        }
        $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        $base = $spreadsheet->getSheetByName('Form') ?: $spreadsheet->getSheet(0);

        // The template form has 36 data rows (16-51). Longer histories continue on copies of the form.
        $first_row = 16;
        $rows_per_page = 36;
        $pages = max(1, (int)ceil(count($lines) / $rows_per_page));
        $sheets = [$base];
        for ($p = 1; $p < $pages; $p++) {
            $copy = clone $base;
            $copy->setTitle('Form (' . ($p + 1) . ')');
            $spreadsheet->addSheet($copy, $spreadsheet->getIndex($base) + $p);
            $sheets[] = $copy;
        }

        $text = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $description = trim((string)($item['description'] ?? ''));
        $is_numeric_reorder = ($reorder_point !== '' && is_numeric($reorder_point));

        foreach ($sheets as $page => $sheet) {
            // Header block
            foreach (['G9:H9', 'G11:H11', 'G12:H12', 'B11:E11', 'B12:E12', 'B13:E13'] as $range) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValueExplicit('G9', $fund_cluster, $text);
            $sheet->setCellValueExplicit('B11', (string)$item['name'], $text);
            $sheet->setCellValueExplicit('B12', $description, $text);
            $sheet->setCellValueExplicit('B13', (string)($item['unit_of_measure'] ?? ''), $text);
            $sheet->setCellValueExplicit('G11', $stock_no, $text);
            if ($is_numeric_reorder) {
                $sheet->setCellValue('G12', $reorder_point + 0);
            } else {
                $sheet->setCellValueExplicit('G12', $reorder_point, $text);
            }
            foreach (['G9', 'G11', 'G12', 'B11', 'B12', 'B13'] as $cell) {
                $sheet->getStyle($cell)->getAlignment()
                    ->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                    ->setVertical(PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            $sheet->getStyle('B12')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension(12)->setRowHeight(min(90, max(15, 15 * (int)ceil(strlen($description) / 60))));

            // Data rows
            $last_row = $first_row + $rows_per_page - 1;
            $range = $sheet->getStyle("A{$first_row}:F{$last_row}")->getAlignment();
            $range->setVertical(PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            foreach (['A', 'B', 'C', 'D', 'F'] as $col) {
                $sheet->getStyle("{$col}{$first_row}:{$col}{$last_row}")->getAlignment()
                    ->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getStyle("A{$first_row}:B{$last_row}")->getAlignment()->setShrinkToFit(true);
            $sheet->getStyle("E{$first_row}:E{$last_row}")->getAlignment()
                ->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                ->setShrinkToFit(true);
            $sheet->getStyle("C{$first_row}:D{$last_row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$first_row}:F{$last_row}")->getNumberFormat()->setFormatCode('#,##0');

            $row = $first_row;
            foreach (array_slice($lines, $page * $rows_per_page, $rows_per_page) as $line) {
                $sheet->setCellValueExplicit('A' . $row, $line['date'], $text);
                $sheet->setCellValueExplicit('B' . $row, $line['ref'], $text);
                if ($line['in'] !== null)  { $sheet->setCellValue('C' . $row, $line['in']); }
                if ($line['out'] !== null) { $sheet->setCellValue('D' . $row, $line['out']); }
                $sheet->setCellValueExplicit('E' . $row, $line['office'], $text);
                $sheet->setCellValue('F' . $row, $line['balance']);
                // "No. of Days to Consume" (G:H) is an estimate by the Supply Unit - left blank
                $row++;
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        $safe_name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$item['name']);
        $filename = "STOCK_CARD_" . trim(substr($safe_name, 0, 40), '_') . ".xlsx";

        if (ob_get_length()) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Error generating Stock Card: " . $e->getMessage();
    }
}

/**
 * Header + line items of a RIS, with unit cost and date acquired taken from the
 * IAR the RIS was created from (falls back to the item's latest receipt).
 */
function fetchRisParItems($db, $ris_id) {
    $stmt = $db->prepare("SELECT id, ris_number, iar_id, requisition_office, requested_by, designation, created_at
                          FROM ris_records WHERE id = ?");
    $stmt->bind_param("i", $ris_id);
    $stmt->execute();
    $ris = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ris) {
        return null;
    }

    $stmt = $db->prepare("SELECT ri.id AS ris_item_id, ri.item_id, ri.description AS name, ri.unit, ri.quantity,
                                 i.description AS item_description
                          FROM ris_items ri
                          LEFT JOIN items i ON i.id = ri.item_id
                          WHERE ri.ris_id = ? ORDER BY ri.id");
    $stmt->bind_param("i", $ris_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $items = [];
    foreach ($rows as $row) {
        $unit_price = null;
        $acquired = null;

        if (!empty($ris['iar_id'])) {
            $q = $db->prepare("SELECT ii.unit_price, ir.delivery_date
                               FROM iar_items ii
                               JOIN delivery_items di ON di.id = ii.delivery_item_id
                               JOIN iar_records ir    ON ir.id = ii.iar_id
                               WHERE ii.iar_id = ? AND di.item_id = ? LIMIT 1");
            $q->bind_param("ii", $ris['iar_id'], $row['item_id']);
            $q->execute();
            $hit = $q->get_result()->fetch_assoc();
            $q->close();
            if ($hit) {
                $unit_price = $hit['unit_price'];
                $acquired = $hit['delivery_date'];
            }
        }
        if ($unit_price === null) {
            $q = $db->prepare("SELECT sm.unit_cost AS unit_price, COALESCE(ir.delivery_date, DATE(sm.created_at)) AS delivery_date
                               FROM stock_movements sm
                               LEFT JOIN delivery_items di ON di.delivery_id = sm.id
                               LEFT JOIN iar_items ii      ON ii.delivery_item_id = di.id
                               LEFT JOIN iar_records ir    ON ir.id = ii.iar_id
                               WHERE sm.item_id = ? AND sm.movement_type = 'in'
                               ORDER BY sm.id DESC LIMIT 1");
            $q->bind_param("i", $row['item_id']);
            $q->execute();
            $hit = $q->get_result()->fetch_assoc();
            $q->close();
            if ($hit) {
                $unit_price = $hit['unit_price'];
                $acquired = $hit['delivery_date'];
            }
        }

        $qty = (int)$row['quantity'];
        $items[] = [
            'ris_item_id'   => (int)$row['ris_item_id'],
            'item_id'       => (int)$row['item_id'],
            'name'          => (string)$row['name'],
            'item_description' => (string)($row['item_description'] ?? ''),
            'unit'          => (string)$row['unit'],
            'quantity'      => $qty,
            'unit_price'    => $unit_price !== null ? (float)$unit_price : null,
            'amount'        => $unit_price !== null ? round($qty * (float)$unit_price, 2) : null,
            'date_acquired' => $acquired ? date('m/d/Y', strtotime($acquired)) : '',
        ];
    }
    return ['ris' => $ris, 'items' => $items];
}

function generateParExcel($db, $ris_id, array $selected_ids, $property_nos, $par_no = '', $fund_cluster = '', $issued_by = '', $issued_position = '') {
    try {
        $data = fetchRisParItems($db, $ris_id);
        if (!$data) {
            throw new Exception("RIS record not found");
        }
        $ris = $data['ris'];
        $lines = array_values(array_filter($data['items'], function ($it) use ($selected_ids) {
            return in_array($it['ris_item_id'], $selected_ids, true);
        }));
        if (empty($lines)) {
            throw new Exception("No items selected");
        }

        $template_path = "../public/templates/PAR.xlsx";
        if (!file_exists($template_path)) {
            throw new Exception("PAR template not found (public/templates/PAR.xlsx)");
        }
        $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        $base = $spreadsheet->getSheetByName('PAR (2)') ?: $spreadsheet->getSheet(0);

        // Keep only the PAR form; the other sheets are lookup tables for property numbers.
        foreach (array_reverse($spreadsheet->getSheetNames()) as $name) {
            if ($name !== $base->getTitle()) {
                $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($spreadsheet->getSheetByName($name)));
            }
        }
        $base->setTitle('PAR');

        // The form has 29 item rows (13-41); longer lists continue on copies of the form.
        $first_row = 13;
        $rows_per_page = 29;
        $pages = max(1, (int)ceil(count($lines) / $rows_per_page));
        $sheets = [$base];
        for ($p = 1; $p < $pages; $p++) {
            $copy = clone $base;
            $copy->setTitle('PAR ' . ($p + 1));
            $spreadsheet->addSheet($copy);
            $sheets[] = $copy;
        }

        $text = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $align = PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $received_position = trim($ris['designation'] . ($ris['requisition_office'] !== '' ? ' / ' . $ris['requisition_office'] : ''), ' /');

        foreach ($sheets as $page => $sheet) {
            if ($fund_cluster !== '') {
                $sheet->setCellValueExplicit('A9', 'Fund Cluster:  ' . $fund_cluster, $text);
            }
            if ($par_no !== '') {
                $sheet->setCellValueExplicit('H9', 'PAR No.: ' . $par_no, $text);
            }

            $row = $first_row;
            foreach (array_slice($lines, $page * $rows_per_page, $rows_per_page) as $line) {
                $sheet->mergeCells("C{$row}:E{$row}");

                $description = $line['name'];
                if ($line['item_description'] !== '' && stripos($line['item_description'], $line['name']) !== 0) {
                    $description .= ', ' . $line['item_description'];
                }
                if (mb_strlen($description) > 150) {
                    $description = mb_substr($description, 0, 147) . '...';
                }
                $property_no = trim((string)($property_nos[$line['ris_item_id']] ?? ''));

                $sheet->setCellValue("A{$row}", $line['quantity']);
                $sheet->setCellValueExplicit("B{$row}", $line['unit'], $text);
                $sheet->setCellValueExplicit("C{$row}", $description, $text);
                $sheet->setCellValueExplicit("F{$row}", $property_no, $text);
                $sheet->setCellValueExplicit("H{$row}", $line['date_acquired'], $text);
                if ($line['amount'] !== null) {
                    $sheet->setCellValue("I{$row}", $line['amount']);
                }

                $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical($align::VERTICAL_CENTER);
                foreach (['A', 'B', 'F', 'H'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER);
                }
                $sheet->getStyle("F{$row}")->getAlignment()->setShrinkToFit(true);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_LEFT)->setWrapText(true);
                $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_RIGHT);
                $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getRowDimension($row)->setRowHeight(min(63, max(15.75, 15.75 * (int)ceil(mb_strlen($description) / 55))));
                $row++;
            }

            // Signatories (the date lines are left for hand signing)
            $sheet->setCellValueExplicit('A43', (string)$ris['requested_by'], $text);
            $sheet->setCellValueExplicit('A45', $received_position, $text);
            $sheet->setCellValueExplicit('F43', $issued_by, $text);
            $sheet->setCellValueExplicit('F45', $issued_position, $text);
            foreach (['A43', 'A45', 'F43', 'F45'] as $cell) {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER)
                    ->setVertical(substr($cell, 1) === '43' ? $align::VERTICAL_BOTTOM : $align::VERTICAL_CENTER)
                    ->setShrinkToFit(true);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        $filename = "PAR_" . preg_replace('/[^A-Za-z0-9_-]+/', '_', $par_no !== '' ? $par_no : $ris['ris_number']) . ".xlsx";

        if (ob_get_length()) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Error generating PAR: " . $e->getMessage();
    }
}

// Logged-in employee (default "Issued by" on the PAR) and RIS list for the PAR modal
$current_emp = ['name' => '', 'position' => ''];
$cur_emp_id = intval($_SESSION['emp_id'] ?? 0);
if ($cur_emp_id) {
    $cur_stmt = $db->prepare("SELECT CONCAT(e.first_name, ' ', e.last_name) AS full_name, p.position_name, e.section_id
                              FROM employee e LEFT JOIN position p ON e.position_id = p.position_id
                              WHERE e.emp_id = ?");
    $cur_stmt->bind_param("i", $cur_emp_id);
    $cur_stmt->execute();
    $cur_row = $cur_stmt->get_result()->fetch_assoc();
    $cur_stmt->close();
    if ($cur_row) {
        $current_emp['name'] = $cur_row['full_name'];
        $current_emp['position'] = trim(($cur_row['position_name'] ?? '') . ' / ' . ($sections[$cur_row['section_id']] ?? ''), ' /');
    }
}
// PPE threshold by salary grade / job grade (from the PAR template's "Threshold" sheet).
// Items costing up to the threshold are semi-expendable (ICS); only items above it go on a PAR.
$par_thresholds = [
    ['label' => 'SG 1-7 / JG 2-5 — up to ₱70,000',      'max' => 70000],
    ['label' => 'SG 8-10 / JG 6-7 — up to ₱500,000',    'max' => 500000],
    ['label' => 'SG 11-17 / JG 8-10 — up to ₱1M',       'max' => 1000000],
    ['label' => 'SG 18-21 / JG 11 — up to ₱10M',        'max' => 10000000],
    ['label' => 'SG 22-30 / JG 12-18 — ₱10M above',     'max' => 10000000],
];
$ris_list = [];
$ris_list_result = $db->query("SELECT id, ris_number, requested_by, requisition_office, created_at FROM ris_records ORDER BY id DESC");
if ($ris_list_result) {
    while ($row = $ris_list_result->fetch_assoc()) {
        $ris_list[] = $row;
    }
}

// Get all IAR records for datatable
$iar_records = [];
$records_query = "SELECT i.*, u.user as created_by_name, COUNT(ii.id) as item_count 
                  FROM iar_records i 
                  LEFT JOIN users u ON i.created_by = u.id 
                  LEFT JOIN iar_items ii ON i.id = ii.iar_id 
                  GROUP BY i.id 
                  ORDER BY i.created_at DESC";
$records_result = $db->query($records_query);
if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $iar_records[] = $row;
    }
}

// Get employees records for dropdown
$employees = [];
$employee_query = "SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as full_name, 
                  p.position_name as designation, e.section_id 
                  FROM employee e
                  LEFT JOIN position p ON e.position_id = p.position_id
                  WHERE e.employment_status_id = 1 
                  ORDER BY e.first_name, e.last_name";
$employee_result = $db->query($employee_query);
if ($employee_result) {
    while ($employee = $employee_result->fetch_assoc()) {
        $section_name = isset($sections[$employee['section_id']]) ? $sections[$employee['section_id']] : 'N/A';
        $employees[] = [
            'emp_id' => $employee['emp_id'],
            'full_name' => $employee['full_name'],
            'designation' => $employee['designation'] ?? '',
            'section_name' => $section_name
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" type="text/css" href="../css/delivery_entry.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-truck-loading"></i>Delivery Management</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Record deliveries, generate IAR documents, and issue RIS slips</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php /* Success / error messages are shown as SweetAlert toasts (see script at bottom) */ ?>

                <div class="card">
                    <div class="card-header" style="display:flex !important; justify-content:space-between !important; align-items:center !important;">
                        <h3 class="card-title" style="margin-right:auto !important;">Delivery Records</h3>
                        <div class="card-tools" style="display:flex !important; justify-content:flex-end !important; margin-left:auto !important;">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#deliveryModal">
                                <i class="fas fa-plus"></i> New Delivery Entry
                            </button>
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#stockCardModal">
                                <i class="fas fa-clipboard-list"></i> Stock Card
                            </button>
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#parModal">
                                <i class="fas fa-file-signature"></i> PAR
                            </button>
                            <a href="ris_records.php" class="btn btn-success">
                                <i class="fas fa-external-link-alt"></i> View RIS Records
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="deliveryTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>IAR Number</th>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Requisition Office</th>
                                        <th>Delivery Date</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($iar_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['iar_number']) ?></td>
                                        <td><?= htmlspecialchars($record['po_number']) ?></td>
                                        <td><?= htmlspecialchars($record['supplier']) ?></td>
                                        <td><?= htmlspecialchars($record['requisition_office']) ?></td>
                                        <td><?= date('M j, Y', strtotime($record['delivery_date'])) ?></td>
                                        <td class="text-center"><?= $record['item_count'] ?></td>
                                        <td class="text-center">
                                            <?php if (($record['delivery_status'] ?? 'Complete') === 'Partial'): ?>
                                                <span class="badge badge-warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Complete</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>₱<?= number_format($record['total_amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($record['created_by_name']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($record['created_at'])) ?></td>
                                        <td class="action-buttons">
                                            <a href="delivery_view.php?id=<?= $record['id'] ?>" class="btn btn-info btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-secondary btn-sm print-iar" data-id="<?= $record['id'] ?>" title="Generate IAR">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if (($record['delivery_status'] ?? 'Complete') === 'Partial'): ?>
                                                <button class="btn btn-warning btn-sm complete-delivery" data-id="<?= $record['id'] ?>" title="Record the remaining delivery">
                                                    <i class="fas fa-truck-loading"></i>
                                                </button>
                                            <?php endif; ?>
                                            <!-- Add this Create RIS button -->
                                            <button class="btn btn-success btn-sm create-ris" data-id="<?= $record['id'] ?>" title="Create RIS">
                                                <i class="fas fa-file-export"></i> RIS
                                            </button>
                                            <form method="POST" action="" style="display: inline;" class="delete-iar-form">
                                                <input type="hidden" name="iar_id" value="<?= $record['id'] ?>">
                                                <input type="hidden" name="delete_iar" value="1">
                                                <button type="submit" name="delete_iar" class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Entry Modal -->
    <div class="modal fade scrum-style-modal" id="deliveryModal" tabindex="-1" role="dialog" aria-labelledby="deliveryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deliveryModalLabel"><i class="fas fa-truck"></i>New Delivery Entry</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" id="deliveryForm">
                    <input type="hidden" name="iar_number" id="hidden_iar_number" value="">
                    <div class="modal-body">
                        <!-- IAR Information Section -->
                        <div class="iar-info mb-4">
                            <h4 class="mb-3">IAR Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="iar_number">IAR Number</label>
                                        <input type="text" class="form-control" id="iar_number" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requisition_office">Requisition Office/Dept *</label>
                                        <select class="form-control" id="requisition_office" name="requisition_office" required>
                                            <option value="">-- Select Section --</option>
                                            <?php foreach ($sections as $id => $name): ?>
                                                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_number">Invoice No.</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="invoice_date">Invoice Date</label>
                                        <input type="date" class="form-control" id="invoice_date" name="invoice_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dr_number">D.R. No.</label>
                                        <input type="text" class="form-control" id="dr_number" name="dr_number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dr_date">D.R. Date</label>
                                        <input type="date" class="form-control" id="dr_date" name="dr_date">
                                    </div>
                                </div>
                            </div>
                        
                            <!-- In the deliveryModal form -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="po_number">PO Number *</label>
                                        <input type="text" class="form-control" id="po_number" name="po_number" 
                                            value="<?= htmlspecialchars($delivery_data['po_number']) ?>" 
                                            pattern="PO-\d{4}-\d{2}-\d{4}"
                                            placeholder="PO-YYYY-MM-####"
                                            title="Format: PO-YYYY-MM-#### (e.g., PO-2025-03-0045)"
                                            maxlength="15"
                                            autocomplete="off"
                                            required>
                                        <small class="form-text text-muted">Format: PO-YYYY-MM-#### (e.g., PO-2025-03-0045)</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="po_date">PO Date *</label>
                                        <input type="date" class="form-control" id="po_date" name="po_date" value="<?= htmlspecialchars($delivery_data['po_date']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="supplier">Supplier *</label>
                                        <input type="text" class="form-control" id="supplier" name="supplier" 
                                            value="<?= htmlspecialchars($delivery_data['supplier']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="delivery_date">Delivery Date *</label>
                                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                            value="<?= htmlspecialchars($delivery_data['delivery_date']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-4 mb-3">Items Delivered</h4>
                        <div id="deliveryStatusPreview" class="mb-3" style="display:none;"></div>
                        <div id="deliveryItems">
                            <div class="delivery-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Item *</label>
                                            <select class="form-control select22 item-select" name="item_id[]" required>
                                                <option value="">-- Select Item --</option>
                                                <?php foreach ($items as $id => $item): ?>
                                                    <option value="<?= $id ?>" data-description="<?= htmlspecialchars($item['description']) ?>"><?= htmlspecialchars($item['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Ordered Qty (PO) *</label>
                                            <input type="number" class="form-control" name="ordered_quantity[]" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Delivered Qty *</label>
                                            <input type="number" class="form-control" name="quantity[]" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Unit Cost</label>
                                            <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Total Cost</label>
                                            <input type="text" class="form-control total-cost" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-group">
                                            <label>Actions</label>
                                            <button type="button" class="form-control btn btn-sm btn-remove-item" onclick="removeItem(this)">
                                                <i class="fas fa-trash" title="Remove"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" id="addItem" class="btn btn-add-item">
                                <i class="fas fa-plus"></i> Add Another Item
                            </button>
                            <button type="button" id="openQuickAddItem" class="btn btn-outline-success ml-2" title="Item not in the list? Add it to the inventory without leaving this form">
                                <i class="fas fa-box-open"></i> Item not in list? Add New Item
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_delivery" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Delivery & Generate IAR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Quick Add Item Modal (opens on top of the Delivery Entry modal, so the delivery form keeps its data) -->
    <div class="modal fade scrum-style-modal" id="quickAddItemModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="quickAddItemForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i>Add New Item</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control">
                                <option value="0">-- Select Category --</option>
                                <?php foreach ($categories as $cid => $cname): ?>
                                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Unit of Measure <span class="text-danger">*</span></label>
                            <select name="unit_of_measure" class="form-control" required>
                                <option value="">-- Select Unit of Measure --</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= htmlspecialchars($unit['name']) ?>">
                                        <?= htmlspecialchars($unit['name']) ?><?= $unit['abbreviation'] ? ' (' . htmlspecialchars($unit['abbreviation']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="quickAddItemSubmit"><i class="fas fa-plus-circle"></i> Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <!-- RIS Entry Modal -->
    <div class="modal fade scrum-style-modal" id="risModal" tabindex="-1" role="dialog" aria-labelledby="risModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="risModalLabel"><i class="fas fa-file-export"></i>Create Requisition and Issue Slip (RIS)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" id="risForm">
                    <input type="hidden" name="ris_iar_id" id="ris_iar_id" value="">
                    <div class="modal-body">
                        <div class="ris-info mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ris_requisition_office">Requisition Office/Dept *</label>
                                        <input type="text" class="form-control" id="ris_requisition_office" name="ris_requisition_office" required readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ris_purpose">Purpose *</label>
                                        <input type="text" class="form-control" id="ris_purpose" name="ris_purpose" required>
                                    </div>
                                </div>
                            </div>
                        
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="ris_requested_by">Requested By *</label>
                                        <select class="form-control select22" id="ris_requested_by" name="ris_requested_by" required>
                                            <option value="">-- Select Employee --</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <option value="<?= $employee['emp_id'] ?>" 
                                                        data-designation="<?= htmlspecialchars($employee['designation']) ?>" 
                                                        data-section="<?= htmlspecialchars($employee['section_name']) ?>">
                                                    <?= htmlspecialchars($employee['full_name'] . ' (' . $employee['section_name'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="ris_designation">Designation</label>
                                        <input type="text" class="form-control" id="ris_designation" name="ris_designation" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="ris_date">Date</label>
                                        <input type="date" class="form-control" id="ris_date" name="ris_date" value="<?= date('Y-m-d') ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>                            
                        <h4 class="mt-4 mb-3">Items to Issue</h4>
                        <div id="risItems">
                            <div class="ris-item">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Item *</label>
                                            <select class="form-control ris-item-select select22" name="ris_item_id[]" required>
                                                <option value="">-- Select Item --</option>
                                                <!-- Items will be populated dynamically via JavaScript -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Available Stock</label>
                                            <input type="text" class="form-control ris-available-stock" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Quantity *</label>
                                            <input type="number" class="form-control" name="ris_quantity[]" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Unit</label>
                                            <input type="text" class="form-control ris-unit" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Actions</label>
                                            <button type="button" class="form-control btn btn-sm btn-danger ris-remove-item">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" id="addRisItem" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Another Item
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_ris" class="btn btn-success">
                            <i class="fas fa-save"></i> Create RIS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- PAR Modal -->
    <div class="modal fade scrum-style-modal" id="parModal" tabindex="-1" role="dialog" aria-labelledby="parModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="parModalLabel"><i class="fas fa-file-signature"></i>Generate Property Acknowledgment Receipt (PAR)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" id="parForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="par_ris_id">Requisition and Issue Slip (RIS) *</label>
                                    <select class="form-control select22" id="par_ris_id" name="par_ris_id" required>
                                        <option value="">-- Select RIS --</option>
                                        <?php foreach ($ris_list as $r): ?>
                                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['ris_number'] . ' — ' . $r['requested_by'] . ' (' . $r['requisition_office'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="par_no">PAR No.</label>
                                    <input type="text" class="form-control" id="par_no" name="par_no" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="par_fund_cluster">Fund Cluster</label>
                                    <input type="text" class="form-control" id="par_fund_cluster" name="par_fund_cluster" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="par_issued_by">Issued By</label>
                                    <input type="text" class="form-control" id="par_issued_by" name="par_issued_by" value="<?= htmlspecialchars($current_emp['name']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="par_issued_position">Issuer Position/Office</label>
                                    <input type="text" class="form-control" id="par_issued_position" name="par_issued_position" value="<?= htmlspecialchars($current_emp['position']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="par_threshold">PPE Threshold (SG / JG)</label>
                                    <select class="form-control" id="par_threshold">
                                        <option value="">-- No threshold check --</option>
                                        <?php foreach ($par_thresholds as $t): ?>
                                            <option value="<?= $t['max'] ?>"><?= htmlspecialchars($t['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-3 mb-3">Items to include</h4>
                        <div id="parItems"><p class="text-muted mb-0">Select a RIS to load its items.</p></div>
                        <small class="form-text text-muted">Items whose unit cost is within the selected threshold are semi-expendable (ICS) and are unticked. The end user (Received by) is taken from the RIS. Date lines are left blank for signing. Amount = quantity × unit cost from the delivery.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="print_par" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i> Download PAR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Stock Card Modal -->
    <div class="modal fade scrum-style-modal" id="stockCardModal" tabindex="-1" role="dialog" aria-labelledby="stockCardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockCardModalLabel"><i class="fas fa-clipboard-list"></i>Generate Stock Card</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="" id="stockCardForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="stock_item_id">Item *</label>
                                    <select class="form-control select22" id="stock_item_id" name="stock_item_id" required>
                                        <option value="">-- Select Item --</option>
                                        <?php foreach ($items as $id => $item): ?>
                                            <option value="<?= $id ?>" data-description="<?= htmlspecialchars($item['description']) ?>"><?= htmlspecialchars($item['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="stock_fund_cluster">Fund Cluster</label>
                                    <input type="text" class="form-control" id="stock_fund_cluster" name="stock_fund_cluster" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="stock_no">Stock No.</label>
                                    <input type="text" class="form-control" id="stock_no" name="stock_no" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="stock_reorder_point">Re-order Point</label>
                                    <input type="number" class="form-control" id="stock_reorder_point" name="stock_reorder_point" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stock_date_from">Date From</label>
                                    <input type="date" class="form-control" id="stock_date_from" name="stock_date_from">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stock_date_to">Date To</label>
                                    <input type="date" class="form-control" id="stock_date_to" name="stock_date_to">
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted">Leave the dates blank to include every receipt and issue of the item. Earlier transactions are rolled into a "Balance forwarded" line.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="print_stock_card" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i> Download Stock Card
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    // <option> list for every Item dropdown in the delivery form
    var deliveryItemOptions = <?php
        $__opts = '<option value="">-- Select Item --</option>';
        foreach ($items as $id => $item) {
            $__opts .= '<option value="' . $id . '" data-description="' . htmlspecialchars($item['description'] ?? '') . '">' . htmlspecialchars($item['name']) . '</option>';
        }
        echo json_encode($__opts);
    ?>;

    function showAlert(icon, message) {
        Swal.fire({
            icon: icon,
            title: icon === 'success' ? 'Success!' : 'Error!',
            text: message,
            confirmButtonText: 'OK',
            confirmButtonColor: icon === 'success' ? '#2a9863' : '#c92a2a',
            allowOutsideClick: false
        });
    }

    <?php if ($success): ?>
    showAlert('success', <?= json_encode(strip_tags($success)) ?>);
    <?php endif; ?>
    <?php if ($error): ?>
    showAlert('error', <?= json_encode(strip_tags($error)) ?>);
    <?php endif; ?>
    // Renders each dropdown row as the item name plus a short, truncated preview of its (often very long) description, instead of dumping the hole spec sheet into the row/selected chip.
    function formatItemOption(option) {
        if (!option.id) {
            return option.text;
        }
        var description = $(option.element).data('description') || '';
        var $result = $('<div class="item-option"><div class="item-option-name"></div></div>');
        $result.find('.item-option-name').text(option.text);
        if (description) {
            var preview = description.length > 90 ? description.substring(0, 90) + '…' : description;
            $result.append($('<div class="item-option-desc"></div>').text(preview));
        }
        return $result;
    }

    function initSelect2($els) {
        $els.each(function() {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) {
                return; 
            }
            var $modal = $el.closest('.modal');
            $el.select2({
                theme: 'bootstrap',
                width: '100%',
                placeholder: '-- Please Select --',
                allowClear: true,
                dropdownParent: $modal.length ? $modal : $(document.body),
                templateResult: formatItemOption
            });
        });
    }

    $(document).ready(function() {
        $('.delete-iar-form').on('submit', function(e) {
            let form = this;
            if ($(form).data('confirmed')) {
                return true;
            }
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the IAR record and cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#c92a2a',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(form).data('confirmed', true);
                    form.submit();
                }
            });
        });

        // Auto-format PO Number like a landline-style masked input: fixed "PO-" prefix, digits auto-grouped as YYYY-MM-####
        function formatPoNumber(raw) {
            // Strip everything except digits (ignore any existing "PO" / dashes the user typed)
            var digits = raw.replace(/\D/g, '').substring(0, 10); // 4 (year) + 2 (month) + 4 (seq)
            var formatted = 'PO-';
            if (digits.length > 0) {
                formatted += digits.substring(0, 4);
            }
            if (digits.length >= 5) {
                formatted += '-' + digits.substring(4, 6);
            }
            if (digits.length >= 7) {
                formatted += '-' + digits.substring(6, 10);
            }
            return formatted;
        }

        $('#po_number').on('focus', function() {
            if ($(this).val().trim() === '') {
                $(this).val('PO-');
            }
        });

        $('#po_number').on('input', function() {
            var cursorAtEnd = this.selectionStart === this.value.length;
            var formatted = formatPoNumber($(this).val());
            $(this).val(formatted);
            if (cursorAtEnd) {
                this.setSelectionRange(formatted.length, formatted.length);
            }

            // Update IAR number to mirror a complete PO number
            var poPattern = /^PO-\d{4}-\d{2}-\d{4}$/;
            if (poPattern.test(formatted)) {
                var iarNumber = formatted.replace('PO-', 'IAR-');
                $('#iar_number').val(iarNumber);
                $('#hidden_iar_number').val(iarNumber);
            } else {
                $('#iar_number').val('');
                $('#hidden_iar_number').val('');
            }
        });

        // Prevent the user from deleting into/through the fixed "PO-" prefix
        $('#po_number').on('keydown', function(e) {
            var val = $(this).val();
            var start = this.selectionStart;
            if ((e.key === 'Backspace' || e.key === 'Delete') && start <= 3 && val.substring(0, 3) === 'PO-') {
                e.preventDefault();
            }
        });

        // Validate PO number format on form submission
        $('#deliveryForm').on('submit', function() {
            var poNumber = $('#po_number').val();
            var poPattern = /^PO-\d{4}-\d{2}-\d{4}$/;
            
            if (!poPattern.test(poNumber)) {
                alert('PO Number must be in the format PO-YYYY-MM-#### (e.g., PO-2025-03-0045)');
                return false;
            }
            return true;
        });
        initSelect2($('.select22'));
        // Initialize DataTable with export buttons
        $('#deliveryTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[9, 'desc']]
        });

        $('#risTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[6, 'desc']]
        });

        // ---- Live Complete / Partial preview for the delivery being entered ----
        // (The saved status also counts earlier deliveries of the same items on the same PO.)
        function updateDeliveryStatusPreview() {
            let hasData = false, shortItems = 0, over = 0;
            $('#deliveryItems .delivery-item').each(function() {
                const ordered = parseInt($(this).find('input[name="ordered_quantity[]"]').val(), 10);
                const $qty = $(this).find('input[name="quantity[]"]');
                const prior = parseInt($qty.data('prior'), 10) || 0;   // delivered on earlier IARs of this PO
                const delivered = prior + parseInt($qty.val(), 10);
                if (!isNaN(ordered) && !isNaN(delivered)) {
                    hasData = true;
                    if (delivered < ordered) shortItems++;
                    if (delivered > ordered) over++;
                }
            });
            const $box = $('#deliveryStatusPreview');
            if (!hasData) { $box.hide().empty(); return; }
            if (over > 0) {
                $box.html('<span class="badge badge-danger">Check quantities</span> Delivered quantity is more than the ordered quantity on ' + over + ' item(s).').show();
            } else if (shortItems > 0) {
                $box.html('<span class="badge badge-warning">Partial delivery</span> ' + shortItems + ' item(s) are short of the PO quantity.').show();
            } else {
                $box.html('<span class="badge badge-success">Complete delivery</span> All items match the PO quantity.').show();
            }
        }
        $('#deliveryItems').on('input', 'input[name="ordered_quantity[]"], input[name="quantity[]"]', updateDeliveryStatusPreview);
        $(document).on('click', '.btn-remove-item', function() { setTimeout(updateDeliveryStatusPreview, 0); });
        $('#deliveryModal').on('hidden.bs.modal', function() { setTimeout(updateDeliveryStatusPreview, 0); });

        // ---- Complete a PARTIAL delivery: open the delivery form pre-filled with what is still owed ----
        $(document).on('click', '.complete-delivery', function() {
            $.post('delivery_entry.php', {get_po_balance: 1, iar_id: $(this).data('id')}, function(res) {
                if (!res.success) { showAlert('error', res.message); return; }

                $('#deliveryModal').one('shown.bs.modal', function() {
                    $('#po_number').val(res.po_number).prop('readonly', true);
                    $('#po_date').val(res.po_date);
                    $('#supplier').val(res.supplier).prop('readonly', true);
                    $('#requisition_office').val(res.requisition_office);
                    $('#iar_number').val(res.iar_number);
                    $('#hidden_iar_number').val(res.iar_number);

                    $('#deliveryItems').empty();
                    $.each(res.items, function(i, it) {
                        $('#addItem').trigger('click');
                        var $row = $('#deliveryItems .delivery-item').last();
                        $row.find('.item-select').val(String(it.item_id)).trigger('change');
                        $row.find('input[name="ordered_quantity[]"]').val(it.ordered).prop('readonly', true);
                        $row.find('input[name="quantity[]"]').val(it.remaining).attr('max', it.remaining).data('prior', it.delivered);
                        $row.find('input[name="unit_cost[]"]').val(it.unit_price);
                        $row.find('input[name="quantity[]"]').trigger('input');
                    });
                    updateDeliveryStatusPreview();
                });
                $('#deliveryModal').modal('show');
            }, 'json').fail(function() {
                showAlert('error', 'Could not load the remaining items for this PO.');
            });
        });

        // ---- Quick "Add New Item" (does not reload the page, delivery form keeps its data) ----
        $('#openQuickAddItem').on('click', function() {
            $('#quickAddItemForm')[0].reset();
            $('#quickAddItemModal').modal('show');
        });

        // Stack the quick-add modal above the delivery modal
        $('#quickAddItemModal').on('shown.bs.modal', function() {
            $(this).css('z-index', 1070);
            $('.modal-backdrop').last().css('z-index', 1065);
            $(this).find('input[name="name"]').trigger('focus');
        });
        // Closing the 2nd modal makes Bootstrap drop "modal-open" from <body>, which kills
        // scrolling in the delivery modal. Put it back while the delivery modal is still open.
        $('#quickAddItemModal').on('hidden.bs.modal', function() {
            if ($('#deliveryModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        $('#quickAddItemForm').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#quickAddItemSubmit').prop('disabled', true);
            $.ajax({
                url: 'delivery_entry.php',
                type: 'POST',
                data: $(this).serialize() + '&quick_add_item=1',
                dataType: 'json'
            }).done(function(res) {
                if (!res.success) {
                    Swal.fire({icon: 'error', title: 'Error!', text: res.message, customClass: {container: 'swal-above-modal'}});
                    return;
                }
                var item = res.item;
                var $opt = $('<option></option>').val(item.id).attr('data-description', item.description || '').text(item.name);

                // Make the new item available in every Item dropdown + in future rows
                $('.item-select').each(function() { $(this).append($opt.clone()); });
                deliveryItemOptions += $('<div>').append($opt.clone()).html();

                // Select it in the first empty row (or add a new row if all are filled)
                var $target = $('#deliveryItems .item-select').filter(function() { return !$(this).val(); }).first();
                if (!$target.length) {
                    $('#addItem').trigger('click');
                    $target = $('#deliveryItems .item-select').last();
                }
                $target.val(String(item.id)).trigger('change');

                $('#quickAddItemModal').modal('hide');
                Swal.fire({icon: 'success', title: 'Item added', text: item.name + ' was added to the inventory and selected.', timer: 1800, showConfirmButton: false});
            }).fail(function() {
                Swal.fire({icon: 'error', title: 'Error!', text: 'An error occurred while adding the item.'});
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        let itemCount = 1;
        
        // Add new item row
        $('#addItem').click(function() {
            itemCount++;
            const newItem = `
                <div class="delivery-item">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control select22 item-select" name="item_id[]" required>
                                    ${deliveryItemOptions}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Ordered Qty (PO) *</label>
                                <input type="number" class="form-control" name="ordered_quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Delivered Qty *</label>
                                <input type="number" class="form-control" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit Cost</label>
                                <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Cost</label>
                                <input type="text" class="form-control total-cost" readonly>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-remove-item">
                                    <i class="fas fa-trash" title="Remove"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#deliveryItems').append(newItem);
            attachCalculationEvents();
            initSelect2($('.select22'));
        });
        
        // Attach calculation events to existing elements
        attachCalculationEvents();
        
        function attachCalculationEvents() {
            // Calculate total cost when quantity or unit cost changes
            $('input[name="quantity[]"], input[name="unit_cost[]"]').on('input', function() {
                const row = $(this).closest('.row');
                const quantity = parseFloat(row.find('input[name="quantity[]"]').val()) || 0;
                const unitCost = parseFloat(row.find('input[name="unit_cost[]"]').val()) || 0;
                const totalCost = quantity * unitCost;
                row.find('.total-cost').val(totalCost.toFixed(2));
            });
        }
        
        // Use event delegation for remove buttons
        $(document).on('click', '.btn-remove-item', function() {
            removeItem(this);
        });
        
        // Reset form when modal is closed
        $('#deliveryModal').on('hidden.bs.modal', function () {
            document.getElementById("deliveryForm").reset();
            $('#po_number, #supplier').prop('readonly', false);   // unlocked again after a "complete delivery" entry
            $('#deliveryItems').html(`
                <div class="delivery-item">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control select22 item-select" name="item_id[]" required>
                                    ${deliveryItemOptions}
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Ordered Qty (PO) *</label>
                                <input type="number" class="form-control" name="ordered_quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Delivered Qty *</label>
                                <input type="number" class="form-control" name="quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit Cost</label>
                                <input type="number" class="form-control" name="unit_cost[]" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Cost</label>
                                <input type="text" class="form-control total-cost" readonly>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="btn btn-sm btn-remove-item mt-1">
                                    <i class="fas fa-trash" title="Remove"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            attachCalculationEvents();
            initSelect2($('.select22'));
        });

       // Handle IAR print button click
        $('.print-iar').click(function() {
            var iarId = $(this).data('id'); // Changed from deliveryId to iarId
            Swal.fire({
                title: 'Generating IAR Document',
                html: 'Please wait while we generate the Inspection and Acceptance Report...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    $('<form>').attr({
                        method: 'POST',
                        action: 'delivery_entry.php'
                    }).append(
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'iar_id', // Changed from delivery_id to iar_id
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
            
    let iarItems = [];
    // Add this function to fetch IAR items
    function fetchIarItems(iarId) {
        $.ajax({
            url: 'fetch_iar_items.php',
            method: 'POST',
            data: { iar_id: iarId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    iarItems = response.items; // Store items globally
                    populateAllRisItemSelects(); // Populate all existing selects
                } else {
                    alert('Error fetching IAR items: ' + response.message);
                }
            },
            error: function() {
                alert('Error fetching IAR items');
            }
        });
    }

    function populateAllRisItemSelects() {
        $('.ris-item-select').each(function() {
            populateRisItemSelect($(this));
        });
    }

    // Populate a single RIS item select
    function populateRisItemSelect($select) {
        // Clear existing options except the first one
        $select.find('option:not(:first)').remove();
        
        // Add items from the global array
        $.each(iarItems, function(index, item) {
            $select.append(
                $('<option>', {
                    value: item.item_id,
                    text: item.name + ' (Delivered: ' + item.quantity + ')',
                    'data-stock': item.quantity,
                    'data-unit': item.unit_of_measure,
                    'data-description': item.description || ''
                })
            );
        });
        
        // Reinitialize Select2
        initSelect2($select);
    }

    // Move removeItem function outside the document ready scope
    function removeItem(button) {
        if ($('.delivery-item').length > 1) {
            $(button).closest('.delivery-item').remove();
        } else {
            alert('You must have at least one item.');
        }
    }
        // RIS Item functionality
        let risItemCount = 1;
        
        // Add new RIS item row
        $('#addRisItem').click(function() {
            risItemCount++;
            const newItem = `
                <div class="ris-item">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control ris-item-select select21" name="ris_item_id[]" required>
                                    <option value="">-- Select Item --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Available Stock</label>
                                <input type="text" class="form-control ris-available-stock" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control" name="ris_quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" class="form-control ris-unit" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-danger ris-remove-item">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#risItems').append(newItem);
            
            // Populate the new select with existing items
            const $newSelect = $('#risItems .ris-item-select').last();
            populateRisItemSelect($newSelect);
            
            attachRisItemEvents();

            initSelect2($('.select21'));
        });

        
        function attachRisItemEvents() {
            $('.ris-item-select').off('change.risItem').on('change.risItem', function() {
                const selectedOption = $(this).find('option:selected');
                const stock = selectedOption.data('stock') || 0;
                const unit = selectedOption.data('unit') || '';

                $(this).closest('.row').find('.ris-available-stock').val(stock);
                $(this).closest('.row').find('.ris-unit').val(unit);
            });

            // Remove item event
            $('.ris-remove-item').off('click.risItem').on('click.risItem', function() {
                if ($('.ris-item').length > 1) {
                    $(this).closest('.ris-item').remove();
                } else {
                    alert('You must have at least one item.');
                }
            });
        }

        // Initialize RIS item events
        attachRisItemEvents();

       // Update the RIS modal opening event
        $(document).on('click', '.create-ris', function() {
            const iarId = $(this).data('id');
            $('#ris_iar_id').val(iarId);
            
            // Fetch and populate IAR items
            fetchIarItems(iarId);
            
            $('#risModal').modal('show');
        });

        // Handle employee selection change
        $('#ris_requested_by').change(function() {
            var selectedOption = $(this).find('option:selected');
            var designation = selectedOption.data('designation') || '';
            var section = selectedOption.data('section') || '';
            
            $('#ris_designation').val(designation);
            $('#ris_requisition_office').val(section);
        });

        // PAR: load the items of the selected RIS
        // Untick items that fall within the selected PPE threshold (semi-expendable, not for PAR)
        function applyParThreshold() {
            var max = parseFloat($('#par_threshold').val());
            $('#parItems .par-item').each(function() {
                var $row = $(this);
                var price = $row.data('unitPrice');
                var $flag = $row.find('.par-flag').empty();
                if (!isNaN(max) && price !== null && price !== undefined && price <= max) {
                    $row.find('.par-use').prop('checked', false);
                    $flag.text('Unit cost ₱' + parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 2}) +
                        ' is within the threshold: semi-expendable (ICS), not PAR');
                } else {
                    $row.find('.par-use').prop('checked', true);
                }
            });
        }
        $('#par_threshold').on('change', applyParThreshold);

        function renderParItems(items) {
            var $wrap = $('#parItems').empty();
            if (!items.length) {
                $wrap.append('<p class="text-muted mb-0">This RIS has no items.</p>');
                return;
            }
            $.each(items, function(i, it) {
                var desc = it.name + (it.item_description ? ', ' + it.item_description : '');
                if (desc.length > 110) { desc = desc.substring(0, 110) + '…'; }
                var $row = $(
                    '<div class="par-item"><div class="row">' +
                    '<div class="col-md-1"><div class="form-group"><label>Use</label>' +
                    '<input type="checkbox" class="form-control par-use" name="par_items[]" checked style="height:20px;"></div></div>' +
                    '<div class="col-md-4"><div class="form-group"><label>Item</label><input type="text" class="form-control par-desc" readonly><small class="par-flag" style="color:var(--h-warning);display:block;margin-top:3px;"></small></div></div>' +
                    '<div class="col-md-3"><div class="form-group"><label>Property Number</label><input type="text" class="form-control par-prop" autocomplete="off"></div></div>' +
                    '<div class="col-md-2"><div class="form-group"><label>Date Acquired</label><input type="text" class="form-control par-date" readonly></div></div>' +
                    '<div class="col-md-2"><div class="form-group"><label>Amount</label><input type="text" class="form-control par-amount" readonly></div></div>' +
                    '</div></div>'
                );
                $row.data('unitPrice', it.unit_price);
                $row.find('.par-use').val(it.ris_item_id);
                $row.find('.par-desc').val(it.quantity + ' ' + it.unit + ' — ' + desc).attr('title', desc);
                $row.find('.par-prop').attr('name', 'par_property_no[' + it.ris_item_id + ']');
                $row.find('.par-date').val(it.date_acquired);
                $row.find('.par-amount').val(it.amount !== null ? parseFloat(it.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : '');
                $wrap.append($row);
            });
            applyParThreshold();
        }

        $('#par_ris_id').on('change', function() {
            var risId = $(this).val();
            if (!risId) {
                $('#parItems').html('<p class="text-muted mb-0">Select a RIS to load its items.</p>');
                return;
            }
            $('#parItems').html('<p class="text-muted mb-0"><i class="fas fa-spinner fa-spin"></i> Loading items…</p>');
            $.post('delivery_entry.php', {get_ris_items: 1, ris_id: risId}, function(res) {
                if (res.success) {
                    renderParItems(res.items);
                } else {
                    $('#parItems').html('<p class="text-danger mb-0"></p>').find('p').text(res.message || 'Could not load items.');
                }
            }, 'json').fail(function() {
                $('#parItems').html('<p class="text-danger mb-0">Could not load items.</p>');
            });
        });

        $('#parModal').on('hidden.bs.modal', function() {
            $('#parForm')[0].reset();
            $('#par_ris_id').val(null).trigger('change');
        });

        // Reset Stock Card form when modal is closed
        $('#stockCardModal').on('hidden.bs.modal', function() {
            $('#stockCardForm')[0].reset();
            $('#stock_item_id').val(null).trigger('change');
        });

        // Reset RIS form when modal is closed
        $('#risModal').on('hidden.bs.modal', function() {
            $('#risForm')[0].reset();
            iarItems = []; // Clear the global items array
            $('#ris_requested_by').val(null).trigger('change');

            // Reset to single item row
            $('#risItems').html(`
                <div class="ris-item">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Item *</label>
                                <select class="form-control ris-item-select select21" name="ris_item_id[]" required>
                                    <option value="">-- Select Item --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Available Stock</label>
                                <input type="text" class="form-control ris-available-stock" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity *</label>
                                <input type="number" class="form-control" name="ris_quantity[]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" class="form-control ris-unit" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actions</label>
                                <button type="button" class="form-control btn btn-sm btn-danger ris-remove-item">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            initSelect2($('#risItems .ris-item-select'));
            attachRisItemEvents();
        });

        // Searchable employee select inside the RIS modal
        initSelect2($('#ris_requested_by'));
        // RIS item selects are initialised when populated (populateRisItemSelect)
        initSelect2($('.ris-item-select'));

</script>
</body>
</html>