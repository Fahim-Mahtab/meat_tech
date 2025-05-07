<?php
require 'config.php';
requireAuth();

// Handle report generation
$reportData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? date('Y-m-01');
    $endDate = $_POST['end_date'] ?? date('Y-m-t');
    $meatTypeIds = $_POST['meat_type_ids'] ?? [];
    
    // Build query based on report type
    switch ($_POST['report_type']) {
        case 'inventory':
            $query = "
                SELECT i.*, mt.name as meat_type, sl.name as storage_location
                FROM inventory i
                JOIN meat_types mt ON i.meat_type_id = mt.id
                JOIN storage_locations sl ON i.storage_location_id = sl.id
                WHERE i.processing_date BETWEEN ? AND ?
            ";
            
            if (!empty($meatTypeIds)) {
                $placeholders = implode(',', array_fill(0, count($meatTypeIds), '?'));
                $query .= " AND i.meat_type_id IN ($placeholders)";
                $params = array_merge([$startDate, $endDate], $meatTypeIds);
            } else {
                $params = [$startDate, $endDate];
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'loss':
            $query = "
                SELECT lr.*, mt.name as meat_type, i.batch_number
                FROM loss_records lr
                JOIN meat_types mt ON lr.meat_type_id = mt.id
                LEFT JOIN inventory i ON lr.inventory_id = i.id
                WHERE lr.recorded_at BETWEEN ? AND ?
            ";
            
            if (!empty($meatTypeIds)) {
                $placeholders = implode(',', array_fill(0, count($meatTypeIds), '?'));
                $query .= " AND lr.meat_type_id IN ($placeholders)";
                $params = array_merge([$startDate, $endDate], $meatTypeIds);
            } else {
                $params = [$startDate, $endDate];
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'distribution':
            $query = "
                SELECT d.*, 
                       (SELECT SUM(quantity) FROM distribution_items WHERE distribution_id = d.id) as total_quantity
                FROM distribution d
                WHERE d.scheduled_datetime BETWEEN ? AND ?
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$startDate, $endDate]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
}

// Get meat types for filter
$meatTypes = $pdo->query("SELECT * FROM meat_types")->fetchAll(PDO::FETCH_ASSOC);

// Get recent reports (simulated)
$recentReports = [
    [
        'name' => 'Monthly Loss Report - ' . date('F Y'),
        'date' => date('Y-m-d'),
        'description' => 'Summary of all loss incidents with analysis',
        'size' => '1.2MB',
        'type' => 'PDF'
    ],
    [
        'name' => 'Inventory Status - ' . date('F j, Y'),
        'date' => date('Y-m-d', strtotime('-1 day')),
        'description' => 'Current inventory levels by meat type',
        'size' => '0.8MB',
        'type' => 'PDF'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Reports</title>
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
                    <h1 class="h2">Reports</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-export me-1"></i>Export All
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Custom Report Generator</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" name="report_type" required>
                                            <option value="">Select report type</option>
                                            <option value="inventory" <?= ($_POST['report_type'] ?? '') === 'inventory' ? 'selected' : '' ?>>Inventory Status</option>
                                            <option value="loss" <?= ($_POST['report_type'] ?? '') === 'loss' ? 'selected' : '' ?>>Loss Analysis</option>
                                            <option value="distribution" <?= ($_POST['report_type'] ?? '') === 'distribution' ? 'selected' : '' ?>>Distribution Summary</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Date Range</label>
                                        <div class="input-group">
                                            <input type="date" class="form-control" name="start_date" value="<?= $_POST['start_date'] ?? date('Y-m-01') ?>">
                                            <span class="input-group-text">to</span>
                                            <input type="date" class="form-control" name="end_date" value="<?= $_POST['end_date'] ?? date('Y-m-t') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Meat Type</label>
                                        <select class="form-select" name="meat_type_ids[]" multiple>
                                            <?php foreach ($meatTypes as $type): ?>
                                                <option value="<?= $type['id'] ?>" <?= isset($_POST['meat_type_ids']) && in_array($type['id'], $_POST['meat_type_ids']) ? 'selected' : '' ?>>
                                                    <?= $type['name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Generate Report</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Reports</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($recentReports as $report): ?>
                                        <a href="#" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= $report['name'] ?></h6>
                                                <small><?= $report['date'] ?></small>
                                            </div>
                                            <p class="mb-1"><?= $report['description'] ?></p>
                                            <small><?= $report['type'] ?>, <?= $report['size'] ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($reportData)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5>Report Results</h5>
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download me-1"></i>Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if ($_POST['report_type'] === 'inventory'): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Batch #</th>
                                                        <th>Meat Type</th>
                                                        <th>Cut Type</th>
                                                        <th>Quantity (kg)</th>
                                                        <th>Process Date</th>
                                                        <th>Expiry Date</th>
                                                        <th>Storage Location</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $item): ?>
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
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php elseif ($_POST['report_type'] === 'loss'): ?>
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $record): ?>
                                                        <tr>
                                                            <td><?= date('M j, Y', strtotime($record['recorded_at'])) ?></td>
                                                            <td><?= $record['batch_number'] ?? 'N/A' ?></td>
                                                            <td><?= $record['meat_type'] ?></td>
                                                            <td><?= ucfirst($record['stage']) ?></td>
                                                            <td><?= number_format($record['quantity'], 2) ?></td>
                                                            <td><?= $record['reason'] ?></td>
                                                            <td><?= $record['action_taken'] ?? 'N/A' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php elseif ($_POST['report_type'] === 'distribution'): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Delivery ID</th>
                                                        <th>Destination</th>
                                                        <th>Scheduled</th>
                                                        <th>Quantity (kg)</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $dist): ?>
                                                        <tr>
                                                            <td><?= $dist['delivery_id'] ?></td>
                                                            <td><?= $dist['destination'] ?></td>
                                                            <td><?= date('M j, Y H:i', strtotime($dist['scheduled_datetime'])) ?></td>
                                                            <td><?= number_format($dist['total_quantity'], 2) ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= 
                                                                    $dist['status'] === 'preparing' ? 'warning' : 
                                                                    ($dist['status'] === 'in_transit' ? 'info' : 
                                                                    ($dist['status'] === 'delivered' ? 'success' : 'secondary'))
                                                                ?>">
                                                                    <?= ucfirst(str_replace('_', ' ', $dist['status'])) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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