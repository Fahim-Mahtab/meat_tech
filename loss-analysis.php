<?php
require 'config.php';
requireAuth();

// Handle form submission for new loss records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loss'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO loss_records 
            (inventory_id, meat_type_id, stage, quantity, reason, action_taken, recorded_at)
            VALUES (:inventory_id, :meat_type_id, :stage, :quantity, :reason, :action_taken, NOW())
        ");
        
        $inventory_id = !empty($_POST['inventory_id']) ? $_POST['inventory_id'] : null;
        
        $stmt->execute([
            ':inventory_id' => $inventory_id,
            ':meat_type_id' => $_POST['meat_type_id'],
            ':stage' => $_POST['stage'],
            ':quantity' => $_POST['quantity'],
            ':reason' => $_POST['reason'],
            ':action_taken' => $_POST['action_taken'] ?? null
        ]);
        
        $_SESSION['success_message'] = "Loss record added successfully";
        header("Location: loss-analysis.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding loss record: " . $e->getMessage();
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM loss_records WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        $_SESSION['success_message'] = "Loss record deleted successfully";
        header("Location: loss-analysis.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting loss record: " . $e->getMessage();
    }
}

// Get loss by stage
$lossByStage = $pdo->query("
    SELECT stage, SUM(quantity) as total
    FROM loss_records
    WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY stage
")->fetchAll(PDO::FETCH_ASSOC);

// Get loss by meat type
$lossByType = $pdo->query("
    SELECT mt.name, SUM(lr.quantity) as total
    FROM loss_records lr
    JOIN meat_types mt ON lr.meat_type_id = mt.id
    WHERE lr.recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY mt.name
")->fetchAll(PDO::FETCH_ASSOC);

// Get detailed loss records
$lossRecords = $pdo->query("
    SELECT lr.*, mt.name as meat_type, i.batch_number
    FROM loss_records lr
    JOIN meat_types mt ON lr.meat_type_id = mt.id
    LEFT JOIN inventory i ON lr.inventory_id = i.id
    ORDER BY lr.recorded_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get meat types for dropdown
$meatTypes = $pdo->query("SELECT id, name FROM meat_types")->fetchAll();

// Get batches for dropdown
$batches = $pdo->query("SELECT id, batch_number FROM inventory ORDER BY batch_number DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Loss Analysis</title>
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
                    <h1 class="h2">Loss Analysis</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-export me-1"></i>Export Report
                            </button>
                        </div>
                    </div>
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
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Loss by Stage (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="lossByStageChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Loss by Meat Type (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="lossByTypeChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Detailed Loss Records</h5>
                                <button class="btn btn-sm btn-primary" id="toggleAddForm">
                                    <i class="fas fa-plus me-1"></i>Add Record
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Add Record Form (Initially Hidden) -->
                                <div class="mb-4 p-3 border rounded bg-light" id="addRecordForm" style="display: none;">
                                    <h6 class="mb-3">Add New Loss Record</h6>
                                    <form method="POST" action="loss-analysis.php">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="batchNumber" class="form-label">Batch Number</label>
                                                <select class="form-select" id="batchNumber" name="inventory_id">
                                                    <option value="">Select Batch</option>
                                                    <?php foreach ($batches as $batch): ?>
                                                        <option value="<?= $batch['id'] ?>"><?= $batch['batch_number'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="meatType" class="form-label">Meat Type *</label>
                                                <select class="form-select" id="meatType" name="meat_type_id" required>
                                                    <option value="">Select Type</option>
                                                    <?php foreach ($meatTypes as $type): ?>
                                                        <option value="<?= $type['id'] ?>"><?= $type['name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="stage" class="form-label">Stage *</label>
                                                <select class="form-select" id="stage" name="stage" required>
                                                    <option value="">Select Stage</option>
                                                    <option value="slaughter">Slaughter</option>
                                                    <option value="processing">Processing</option>
                                                    <option value="storage">Storage</option>
                                                    <option value="handling">Handling</option>
                                                    <option value="transport">Transport</option>
                                                    <option value="retail">Retail</option>
                                                </select>
                                            </div>
                                            <div class="col-md-1">
                                                <label for="quantity" class="form-label">Qty (kg) *</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="quantity" name="quantity" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="reason" class="form-label">Reason *</label>
                                                <input type="text" class="form-control" id="reason" name="reason" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label for="actionTaken" class="form-label">Action Taken</label>
                                                <input type="text" class="form-control" id="actionTaken" name="action_taken">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" name="add_loss" class="btn btn-primary me-2">Save</button>
                                                <button type="button" id="cancelAdd" class="btn btn-outline-secondary">Cancel</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Batch #</th>
                                                <th>Meat Type</th>
                                                <th>Stage</th>
                                                <th>Quantity (kg)</th>
                                                <th>Reason</th>
                                                <th>Action Taken</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lossRecords as $record): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($record['recorded_at'])) ?></td>
                                                    <td><?= $record['batch_number'] ?? 'N/A' ?></td>
                                                    <td><?= $record['meat_type'] ?></td>
                                                    <td><?= ucfirst($record['stage']) ?></td>
                                                    <td><?= number_format($record['quantity'], 2) ?></td>
                                                    <td><?= $record['reason'] ?></td>
                                                    <td><?= $record['action_taken'] ?? 'N/A' ?></td>
                                                    <td>
                                                        <a href="loss-analysis.php?delete=<?= $record['id'] ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this record?')">
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Loss by Stage Chart
        new Chart(
            document.getElementById('lossByStageChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($lossByStage, 'stage')) ?>,
                    datasets: [{
                        label: 'Loss Quantity (kg)',
                        data: <?= json_encode(array_column($lossByStage, 'total')) ?>,
                        backgroundColor: '#e74a3b'
                    }]
                }
            }
        );
        
        // Loss by Type Chart
        new Chart(
            document.getElementById('lossByTypeChart').getContext('2d'),
            {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($lossByType, 'name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($lossByType, 'total')) ?>,
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
                        ]
                    }]
                }
            }
        );

        // Toggle add record form
        document.getElementById('toggleAddForm').addEventListener('click', function() {
            const form = document.getElementById('addRecordForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('cancelAdd').addEventListener('click', function() {
            document.getElementById('addRecordForm').style.display = 'none';
        });
    </script>
</body>
</html>