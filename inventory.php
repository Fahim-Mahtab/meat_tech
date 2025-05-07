<?php
require 'config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add new inventory
        $stmt = $pdo->prepare("
            INSERT INTO inventory (
                batch_number, meat_type_id, cut_type, quantity, 
                processing_date, expiry_date, storage_location_id, quality_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['batch_number'],
            $_POST['meat_type_id'],
            $_POST['cut_type'],
            $_POST['quantity'],
            $_POST['processing_date'],
            $_POST['expiry_date'],
            $_POST['storage_location_id'],
            $_POST['quality_notes']
        ]);
        
        header('Location: inventory.php');
        exit;
    } elseif ($action === 'edit') {
        // Update inventory
        $stmt = $pdo->prepare("
            UPDATE inventory SET
                meat_type_id = ?,
                cut_type = ?,
                quantity = ?,
                processing_date = ?,
                expiry_date = ?,
                storage_location_id = ?,
                quality_notes = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['meat_type_id'],
            $_POST['cut_type'],
            $_POST['quantity'],
            $_POST['processing_date'],
            $_POST['expiry_date'],
            $_POST['storage_location_id'],
            $_POST['quality_notes'],
            $_POST['status'],
            $_POST['id']
        ]);
        
        header('Location: inventory.php');
        exit;
    }
} elseif ($action === 'delete') {
    // Delete inventory
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    
    header('Location: inventory.php');
    exit;
}

// Get data for forms
$meatTypes = $pdo->query("SELECT * FROM meat_types")->fetchAll(PDO::FETCH_ASSOC);
$storageLocations = $pdo->query("SELECT * FROM storage_locations")->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'edit') {
    $inventoryItem = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $inventoryItem->execute([$_GET['id']]);
    $inventoryItem = $inventoryItem->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventoryItem) {
        header('Location: inventory.php');
        exit;
    }
}

// Get inventory list
$inventory = $pdo->query("
    SELECT i.*, mt.name as meat_type, sl.name as storage_location
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    JOIN storage_locations sl ON i.storage_location_id = sl.id
    ORDER BY i.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeatTrack - Inventory</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Inventory Management</h1>
                    <a href="inventory.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add New
                    </a>
                </div>
                
                <?php if ($action === 'list'): ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between">
                                    <h5>Current Inventory</h5>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-secondary">Export</button>
                                        <button class="btn btn-sm btn-outline-secondary">Print</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Batch #</th>
                                                    <th>Type</th>
                                                    <th>Cut</th>
                                                    <th>Qty (kg)</th>
                                                    <th>Process Date</th>
                                                    <th>Expiry Date</th>
                                                    <th>Location</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($inventory as $item): ?>
                                                    <tr>
                                                        <td><?= $item['batch_number'] ?></td>
                                                        <td><?= $item['meat_type'] ?></td>
                                                        <td><?= $item['cut_type'] ?></td>
                                                        <td><?= number_format($item['quantity'], 2) ?></td>
                                                        <td><?= date('M j, Y', strtotime($item['processing_date'])) ?></td>
                                                        <td><?= date('M j, Y', strtotime($item['expiry_date'])) ?></td>
                                                        <td><?= $item['storage_location'] ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $item['status'] === 'good' ? 'success' : 
                                                                ($item['status'] === 'near_expiry' ? 'warning' : 'danger')
                                                            ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="inventory.php?action=edit&id=<?= $item['id'] ?>" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="inventory.php?action=delete&id=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
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
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5><?= $action === 'add' ? 'Add New' : 'Edit' ?> Inventory Item</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <?php if ($action === 'edit'): ?>
                                            <input type="hidden" name="id" value="<?= $inventoryItem['id'] ?>">
                                        <?php endif; ?>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="meat_type_id" class="form-label">Meat Type</label>
                                                <select class="form-select" id="meat_type_id" name="meat_type_id" required>
                                                    <option value="">Select type</option>
                                                    <?php foreach ($meatTypes as $type): ?>
                                                        <option value="<?= $type['id'] ?>" <?= isset($inventoryItem) && $inventoryItem['meat_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                                            <?= $type['name'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="cut_type" class="form-label">Cut Type</label>
                                                <input type="text" class="form-control" id="cut_type" name="cut_type" 
                                                    value="<?= $inventoryItem['cut_type'] ?? '' ?>" placeholder="e.g., Breast, Thigh, Loin">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="quantity" class="form-label">Quantity (kg)</label>
                                                <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" 
                                                    value="<?= $inventoryItem['quantity'] ?? '' ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="processing_date" class="form-label">Processing Date</label>
                                                <input type="date" class="form-control" id="processing_date" name="processing_date" 
                                                    value="<?= $inventoryItem['processing_date'] ?? date('Y-m-d') ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                                    value="<?= $inventoryItem['expiry_date'] ?? '' ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="batch_number" class="form-label">Batch Number</label>
                                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                                    value="<?= $inventoryItem['batch_number'] ?? 'MT-' . date('Y-m-d') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="storage_location_id" class="form-label">Storage Location</label>
                                                <select class="form-select" id="storage_location_id" name="storage_location_id" required>
                                                    <option value="">Select location</option>
                                                    <?php foreach ($storageLocations as $location): ?>
                                                        <option value="<?= $location['id'] ?>" <?= isset($inventoryItem) && $inventoryItem['storage_location_id'] == $location['id'] ? 'selected' : '' ?>>
                                                            <?= $location['name'] ?> (<?= $location['temperature_range'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <label for="quality_notes" class="form-label">Quality Notes</label>
                                                <textarea class="form-control" id="quality_notes" name="quality_notes" rows="2"><?= $inventoryItem['quality_notes'] ?? '' ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <?php if ($action === 'edit'): ?>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status" required>
                                                        <option value="good" <?= $inventoryItem['status'] === 'good' ? 'selected' : '' ?>>Good</option>
                                                        <option value="near_expiry" <?= $inventoryItem['status'] === 'near_expiry' ? 'selected' : '' ?>>Near Expiry</option>
                                                        <option value="spoiled" <?= $inventoryItem['status'] === 'spoiled' ? 'selected' : '' ?>>Spoiled</option>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                            <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
