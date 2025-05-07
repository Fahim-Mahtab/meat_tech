<?php
require 'config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO spoilage (
                    inventory_id, batch_number, meat_type_id, quantity, 
                    processing_date, storage_location_id, reason, 
                    disposal_method, recorded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['inventory_id'] ?: null,
                $_POST['batch_number'],
                $_POST['meat_type_id'],
                $_POST['quantity'],
                $_POST['processing_date'],
                $_POST['storage_location_id'],
                $_POST['reason'],
                $_POST['disposal_method'],
                $_SESSION['user_id']
            ]);
            
            // Update inventory status if an inventory item was selected
            if (!empty($_POST['inventory_id'])) {
                $updateStmt = $pdo->prepare("
                    UPDATE inventory SET status = 'spoiled' 
                    WHERE id = ?
                ");
                $updateStmt->execute([$_POST['inventory_id']]);
            }
            
            $_SESSION['success_message'] = "Spoilage record added successfully";
            header('Location: spoilage.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error adding spoilage record: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM spoilage WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            $_SESSION['success_message'] = "Spoilage record deleted successfully";
            header('Location: spoilage.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting spoilage record: " . $e->getMessage();
        }
    }
}

// Get data for forms
$meatTypes = $pdo->query("SELECT * FROM meat_types")->fetchAll(PDO::FETCH_ASSOC);
$storageLocations = $pdo->query("SELECT * FROM storage_locations")->fetchAll(PDO::FETCH_ASSOC);
$inventoryItems = $pdo->query("
    SELECT id, batch_number FROM inventory 
    WHERE status != 'spoiled' 
    ORDER BY expiry_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get spoilage records
$spoilageRecords = $pdo->query("
    SELECT s.*, mt.name as meat_type, sl.name as storage_location, u.name as recorded_by_name
    FROM spoilage s
    JOIN meat_types mt ON s.meat_type_id = mt.id
    JOIN storage_locations sl ON s.storage_location_id = sl.id
    JOIN users u ON s.recorded_by = u.id
    ORDER BY s.recorded_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate spoilage statistics
$spoilageStats = $pdo->query("
    SELECT 
        SUM(s.quantity) as total_spoiled,
        (SELECT SUM(quantity) FROM inventory) as total_inventory,
        (SUM(s.quantity) / (SELECT SUM(quantity) FROM inventory) * 100) as spoilage_percentage
    FROM spoilage s
")->fetch(PDO::FETCH_ASSOC);

// Format spoilage percentage
$spoilagePercentage = number_format($spoilageStats['spoilage_percentage'] ?? 0, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeatTrack - Spoilage Tracking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .spoilage-card {
            border-left: 5px solid #e74a3b;
        }
        .spoilage-badge {
            background-color: #e74a3b;
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .progress {
            height: 20px;
        }
        .progress-bar {
            background-color: #e74a3b;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Spoilage Tracking</h1>
                    <a href="spoilage.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Record Spoilage
                    </a>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if ($action === 'list'): ?>
                    <!-- Spoilage Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card mb-3">
                                <div class="card-body text-center">
                                    <div class="stat-value text-danger"><?= $spoilagePercentage ?>%</div>
                                    <div class="stat-label">Spoilage Rate</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card mb-3">
                                <div class="card-body text-center">
                                    <div class="stat-value"><?= number_format($spoilageStats['total_spoiled'] ?? 0, 2) ?> kg</div>
                                    <div class="stat-label">Total Spoiled</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card mb-3">
                                <div class="card-body text-center">
                                    <div class="stat-value"><?= number_format($spoilageStats['total_inventory'] ?? 0, 2) ?> kg</div>
                                    <div class="stat-label">Total Inventory</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Spoilage Progress -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Spoilage Rate</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $spoilagePercentage ?>%" 
                                     aria-valuenow="<?= $spoilagePercentage ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?= $spoilagePercentage ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Spoilage Records Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h5>Spoilage Records</h5>
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
                                            <th>Qty (kg)</th>
                                            <th>Process Date</th>
                                            <th>Location</th>
                                            <th>Reason</th>
                                            <th>Disposal Method</th>
                                            <th>Recorded By</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($spoilageRecords as $record): ?>
                                            <tr>
                                                <td><?= $record['batch_number'] ?></td>
                                                <td><?= $record['meat_type'] ?></td>
                                                <td><?= number_format($record['quantity'], 2) ?></td>
                                                <td><?= date('M j, Y', strtotime($record['processing_date'])) ?></td>
                                                <td><?= $record['storage_location'] ?></td>
                                                <td><?= $record['reason'] ?></td>
                                                <td><?= ucfirst($record['disposal_method']) ?></td>
                                                <td><?= $record['recorded_by_name'] ?></td>
                                                <td><?= date('M j, H:i', strtotime($record['recorded_at'])) ?></td>
                                                <td>
                                                    <form method="POST" action="spoilage.php?action=delete" style="display: inline;">
                                                        <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this record?')">
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
                <?php elseif ($action === 'add'): ?>
                    <!-- Add Spoilage Form -->
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <div class="card spoilage-card">
                                <div class="card-header">
                                    <h5>Record New Spoilage</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="inventory_id" class="form-label">Inventory Item (Optional)</label>
                                                <select class="form-select" id="inventory_id" name="inventory_id">
                                                    <option value="">Select inventory item</option>
                                                    <?php foreach ($inventoryItems as $item): ?>
                                                        <option value="<?= $item['id'] ?>"><?= $item['batch_number'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Select if spoilage is from existing inventory</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="batch_number" class="form-label">Batch Number *</label>
                                                <input type="text" class="form-control" id="batch_number" name="batch_number" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="meat_type_id" class="form-label">Meat Type *</label>
                                                <select class="form-select" id="meat_type_id" name="meat_type_id" required>
                                                    <option value="">Select type</option>
                                                    <?php foreach ($meatTypes as $type): ?>
                                                        <option value="<?= $type['id'] ?>"><?= $type['name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="quantity" class="form-label">Quantity (kg) *</label>
                                                <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="processing_date" class="form-label">Processing Date *</label>
                                                <input type="date" class="form-control" id="processing_date" name="processing_date" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="storage_location_id" class="form-label">Storage Location *</label>
                                                <select class="form-select" id="storage_location_id" name="storage_location_id" required>
                                                    <option value="">Select location</option>
                                                    <?php foreach ($storageLocations as $location): ?>
                                                        <option value="<?= $location['id'] ?>">
                                                            <?= $location['name'] ?> (<?= $location['temperature_range'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="reason" class="form-label">Reason for Spoilage *</label>
                                                <select class="form-select" id="reason" name="reason" required>
                                                    <option value="">Select reason</option>
                                                    <option value="temperature fluctuation">Temperature Fluctuation</option>
                                                    <option value="expired">Expired</option>
                                                    <option value="contamination">Contamination</option>
                                                    <option value="improper handling">Improper Handling</option>
                                                    <option value="packaging failure">Packaging Failure</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="disposal_method" class="form-label">Disposal Method *</label>
                                                <select class="form-select" id="disposal_method" name="disposal_method" required>
                                                    <option value="">Select method</option>
                                                    <option value="incineration">Incineration</option>
                                                    <option value="landfill">Landfill</option>
                                                    <option value="rendering">Rendering</option>
                                                    <option value="composting">Composting</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <label for="notes" class="form-label">Additional Notes</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Record Spoilage
                                            </button>
                                            <a href="spoilage.php" class="btn btn-secondary">Cancel</a>
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
        // Auto-fill form when inventory item is selected
        document.getElementById('inventory_id').addEventListener('change', function() {
            if (this.value) {
                fetch('get_inventory_item.php?id=' + this.value)
                    .then(response => response.json())
                    .then(data => {
                        if (data) {
                            document.getElementById('batch_number').value = data.batch_number;
                            document.getElementById('meat_type_id').value = data.meat_type_id;
                            document.getElementById('quantity').value = data.quantity;
                            document.getElementById('processing_date').value = data.processing_date;
                            document.getElementById('storage_location_id').value = data.storage_location_id;
                        }
                    });
            }
        });
    </script>
</body>
</html>