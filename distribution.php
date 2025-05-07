<?php
require 'config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        // Add new distribution
        $pdo->beginTransaction();
        
        try {
            // Create distribution record
            $stmt = $pdo->prepare("
                INSERT INTO distribution (
                    delivery_id, destination, scheduled_datetime, vehicle, driver
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['delivery_id'],
                $_POST['destination'],
                $_POST['scheduled_datetime'],
                $_POST['vehicle'],
                $_POST['driver']
            ]);
            $distributionId = $pdo->lastInsertId();
            
            // Add distribution items
            foreach ($_POST['items'] as $item) {
                if ($item['inventory_id'] && $item['quantity'] > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO distribution_items (distribution_id, inventory_id, quantity)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$distributionId, $item['inventory_id'], $item['quantity']]);
                    
                    // Update inventory quantity
                    $pdo->prepare("
                        UPDATE inventory SET quantity = quantity - ? 
                        WHERE id = ? AND quantity >= ?
                    ")->execute([$item['quantity'], $item['inventory_id'], $item['quantity']]);
                }
            }
            
            $pdo->commit();
            header('Location: distribution.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create distribution: " . $e->getMessage();
        }
    } elseif ($action === 'update_status') {
        // Update distribution status
        $stmt = $pdo->prepare("
            UPDATE distribution SET 
                status = ?,
                completed_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE NULL END
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['status'],
            $_POST['status'],
            $_POST['id']
        ]);
        
        header('Location: distribution.php');
        exit;
    } elseif ($action === 'delete') {
        // Delete distribution
        $pdo->beginTransaction();
        try {
            $distributionId = $_POST['id'];
            
            // First return items to inventory
            $items = $pdo->prepare("
                SELECT inventory_id, quantity 
                FROM distribution_items 
                WHERE distribution_id = ?
            ");
            $items->execute([$distributionId]);
            $items = $items->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $pdo->prepare("
                    UPDATE inventory SET quantity = quantity + ? 
                    WHERE id = ?
                ")->execute([$item['quantity'], $item['inventory_id']]);
            }
            
            // Then delete the distribution items
            $stmt = $pdo->prepare("DELETE FROM distribution_items WHERE distribution_id = ?");
            $stmt->execute([$distributionId]);
            
            // Finally delete the distribution record
            $stmt = $pdo->prepare("DELETE FROM distribution WHERE id = ?");
            $stmt->execute([$distributionId]);
            
            $pdo->commit();
            header('Location: distribution.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to delete distribution: " . $e->getMessage();
        }
    }
}

// Get inventory for distribution items
$inventory = $pdo->query("
    SELECT i.id, i.batch_number, mt.name as meat_type, i.quantity
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    WHERE i.status = 'good' AND i.quantity > 0
    ORDER BY i.expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get distributions
$distributions = $pdo->query("
    SELECT d.*, 
           (SELECT SUM(quantity) FROM distribution_items WHERE distribution_id = d.id) as total_quantity
    FROM distribution d
    ORDER BY d.scheduled_datetime DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get distribution details if needed
if ($action === 'view' || $action === 'update_status') {
    $distributionId = $_GET['id'];
    $distribution = $pdo->prepare("
        SELECT * FROM distribution WHERE id = ?
    ");
    $distribution->execute([$distributionId]);
    $distribution = $distribution->fetch(PDO::FETCH_ASSOC);
    
    if (!$distribution) {
        header('Location: distribution.php');
        exit;
    }
    
    $distributionItems = $pdo->prepare("
        SELECT di.*, i.batch_number, mt.name as meat_type
        FROM distribution_items di
        JOIN inventory i ON di.inventory_id = i.id
        JOIN meat_types mt ON i.meat_type_id = mt.id
        WHERE di.distribution_id = ?
    ");
    $distributionItems->execute([$distributionId]);
    $distributionItems = $distributionItems->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Distribution</title>
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
                    <h1 class="h2">Distribution Management</h1>
                    <a href="distribution.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>New Distribution
                    </a>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($action === 'list'): ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Recent Distributions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Delivery ID</th>
                                                    <th>Destination</th>
                                                    <th>Scheduled</th>
                                                    <th>Quantity</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($distributions as $dist): ?>
                                                    <tr>
                                                        <td><?= $dist['delivery_id'] ?></td>
                                                        <td><?= $dist['destination'] ?></td>
                                                        <td><?= date('M j, Y H:i', strtotime($dist['scheduled_datetime'])) ?></td>
                                                        <td><?= number_format($dist['total_quantity'], 2) ?> kg</td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $dist['status'] === 'preparing' ? 'warning' : 
                                                                ($dist['status'] === 'in_transit' ? 'info' : 
                                                                ($dist['status'] === 'delivered' ? 'success' : 'secondary'))
                                                            ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $dist['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="distribution.php?action=view&id=<?= $dist['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if ($dist['status'] !== 'delivered' && $dist['status'] !== 'cancelled'): ?>
                                                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $dist['id'] ?>)">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Pending Deliveries</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Delivery ID</th>
                                                    <th>Destination</th>
                                                    <th>Scheduled</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($distributions as $dist): 
                                                    if ($dist['status'] === 'delivered' || $dist['status'] === 'cancelled') continue;
                                                ?>
                                                    <tr>
                                                        <td><?= $dist['delivery_id'] ?></td>
                                                        <td><?= $dist['destination'] ?></td>
                                                        <td><?= date('M j, Y H:i', strtotime($dist['scheduled_datetime'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $dist['status'] === 'preparing' ? 'warning' : 'info' ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $dist['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="distribution.php?action=update_status&id=<?= $dist['id'] ?>" class="btn btn-sm btn-primary">
                                                                Update Status
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
                <?php elseif ($action === 'add'): ?>
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Create New Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="delivery_id" class="form-label">Delivery ID</label>
                                                <input type="text" class="form-control" id="delivery_id" name="delivery_id" 
                                                    value="DL-<?= date('Y-m-d') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="scheduled_datetime" class="form-label">Scheduled Date/Time</label>
                                                <input type="datetime-local" class="form-control" id="scheduled_datetime" name="scheduled_datetime" 
                                                    value="<?= date('Y-m-d\TH:i') ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="destination" class="form-label">Destination</label>
                                            <input type="text" class="form-control" id="destination" name="destination" required>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="vehicle" class="form-label">Vehicle</label>
                                                <input type="text" class="form-control" id="vehicle" name="vehicle">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="driver" class="form-label">Driver</label>
                                                <input type="text" class="form-control" id="driver" name="driver">
                                            </div>
                                        </div>
                                        
                                        <h5 class="mt-4 mb-3">Distribution Items</h5>
                                        
                                        <div id="items-container">
                                            <div class="item-row row mb-2">
                                                <div class="col-md-6">
                                                    <select class="form-select item-select" name="items[0][inventory_id]" required>
                                                        <option value="">Select Inventory Item</option>
                                                        <?php foreach ($inventory as $item): ?>
                                                            <option value="<?= $item['id'] ?>" data-quantity="<?= $item['quantity'] ?>">
                                                                <?= $item['batch_number'] ?> - <?= $item['meat_type'] ?> (<?= number_format($item['quantity'], 2) ?> kg)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="number" step="0.01" min="0.01" class="form-control item-quantity" name="items[0][quantity]" placeholder="Quantity" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-danger btn-sm remove-item">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="button" id="add-item" class="btn btn-secondary btn-sm mt-2">
                                            <i class="fas fa-plus me-1"></i>Add Item
                                        </button>
                                        
                                        <div class="d-grid gap-2 mt-4">
                                            <button type="submit" class="btn btn-primary">Create Distribution</button>
                                            <a href="distribution.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            let itemCount = 1;
                            
                            // Add new item row
                            document.getElementById('add-item').addEventListener('click', function() {
                                const container = document.getElementById('items-container');
                                const newRow = document.createElement('div');
                                newRow.className = 'item-row row mb-2';
                                newRow.innerHTML = `
                                    <div class="col-md-6">
                                        <select class="form-select item-select" name="items[${itemCount}][inventory_id]" required>
                                            <option value="">Select Inventory Item</option>
                                            <?php foreach ($inventory as $item): ?>
                                                <option value="<?= $item['id'] ?>" data-quantity="<?= $item['quantity'] ?>">
                                                    <?= $item['batch_number'] ?> - <?= $item['meat_type'] ?> (<?= number_format($item['quantity'], 2) ?> kg)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" step="0.01" min="0.01" class="form-control item-quantity" name="items[${itemCount}][quantity]" placeholder="Quantity" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm remove-item">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                `;
                                container.appendChild(newRow);
                                itemCount++;
                            });
                            
                            // Remove item row
                            document.addEventListener('click', function(e) {
                                if (e.target.classList.contains('remove-item')) {
                                    e.target.closest('.item-row').remove();
                                }
                            });
                            
                            // Set max quantity when item is selected
                            document.addEventListener('change', function(e) {
                                if (e.target.classList.contains('item-select')) {
                                    const selectedOption = e.target.options[e.target.selectedIndex];
                                    const maxQuantity = parseFloat(selectedOption.getAttribute('data-quantity'));
                                    const quantityInput = e.target.closest('.item-row').querySelector('.item-quantity');
                                    quantityInput.max = maxQuantity;
                                    quantityInput.placeholder = `Max ${maxQuantity} kg`;
                                }
                            });
                        });
                    </script>
                <?php elseif ($action === 'view'): ?>
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5>Distribution Details</h5>
                                    <div>
                                        <span class="badge bg-<?= 
                                            $distribution['status'] === 'preparing' ? 'warning' : 
                                            ($distribution['status'] === 'in_transit' ? 'info' : 
                                            ($distribution['status'] === 'delivered' ? 'success' : 'secondary'))
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $distribution['status'])) ?>
                                        </span>
                                        <?php if ($distribution['status'] !== 'delivered' && $distribution['status'] !== 'cancelled'): ?>
                                            <button type="button" class="btn btn-danger btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Delivery ID</h6>
                                            <p><?= $distribution['delivery_id'] ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Scheduled Date/Time</h6>
                                            <p><?= date('M j, Y H:i', strtotime($distribution['scheduled_datetime'])) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Destination</h6>
                                            <p><?= $distribution['destination'] ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Vehicle/Driver</h6>
                                            <p><?= $distribution['vehicle'] ?> / <?= $distribution['driver'] ?></p>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mt-4 mb-3">Items</h5>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Batch #</th>
                                                    <th>Meat Type</th>
                                                    <th>Quantity (kg)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($distributionItems as $item): ?>
                                                    <tr>
                                                        <td><?= $item['batch_number'] ?></td>
                                                        <td><?= $item['meat_type'] ?></td>
                                                        <td><?= number_format($item['quantity'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-secondary">
                                                    <td colspan="2"><strong>Total</strong></td>
                                                    <td><strong><?= number_format(array_sum(array_column($distributionItems, 'quantity')), 2) ?> kg</strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <a href="distribution.php" class="btn btn-secondary">Back to List</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete this distribution? This action cannot be undone.
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $distribution['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($action === 'update_status'): ?>
                    <div class="row">
                        <div class="col-md-6 mx-auto">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Update Distribution Status</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $distribution['id'] ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Current Status</label>
                                            <p class="form-control-static">
                                                <span class="badge bg-<?= 
                                                    $distribution['status'] === 'preparing' ? 'warning' : 
                                                    ($distribution['status'] === 'in_transit' ? 'info' : 
                                                    ($distribution['status'] === 'delivered' ? 'success' : 'secondary'))
                                                ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $distribution['status'])) ?>
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="status" class="form-label">New Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="preparing" <?= $distribution['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                                <option value="in_transit" <?= $distribution['status'] === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                                                <option value="delivered" <?= $distribution['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                <option value="cancelled" <?= $distribution['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                            <a href="distribution.php" class="btn btn-secondary">Cancel</a>
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
    <script>
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this distribution? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'distribution.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                form.appendChild(idInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>