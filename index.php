<?php
require 'config.php';
requireAuth();

$user = getCurrentUser($pdo);

// Handle alert dismissal
if (isset($_POST['dismiss_alert']) && isset($_POST['alert_id'])) {
    $stmt = $pdo->prepare("INSERT INTO dismissed_alerts (user_id, alert_identifier) VALUES (?, ?)");
    $stmt->execute([$user['id'], $_POST['alert_id']]);
}

// Get inventory stats
$inventoryStats = $pdo->query("
    SELECT 
        SUM(quantity) as total,
        SUM(CASE WHEN status = 'good' THEN quantity ELSE 0 END) as good,
        SUM(CASE WHEN status = 'near_expiry' THEN quantity ELSE 0 END) as near_expiry,
        SUM(CASE WHEN status = 'spoiled' THEN quantity ELSE 0 END) as spoiled
    FROM inventory
")->fetch(PDO::FETCH_ASSOC);

// Get inventory by type
$inventoryByType = $pdo->query("
    SELECT mt.name, SUM(i.quantity) as total
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    GROUP BY mt.name
")->fetchAll(PDO::FETCH_ASSOC);

// Get loss by stage
$lossByStage = $pdo->query("
    SELECT stage, SUM(quantity) as total
    FROM loss_records
    GROUP BY stage
")->fetchAll(PDO::FETCH_ASSOC);

// Enhanced Alerts System
$alerts = [];

// 1. Get inventory alerts (expiring soon and spoiled)
$inventoryAlerts = $pdo->query("
    SELECT 
        i.id,
        i.batch_number, 
        i.expiry_date, 
        i.status,
        'inventory' as alert_type,
        CONCAT(
            'Batch ', i.batch_number, ' ',
            CASE 
                WHEN i.status = 'spoiled' THEN 'has spoiled'
                WHEN i.status = 'near_expiry' THEN 'is near expiry'
                ELSE 'expires soon'
            END,
            CASE 
                WHEN i.status != 'spoiled' THEN CONCAT(' on ', DATE_FORMAT(i.expiry_date, '%M %e, %Y'))
                ELSE ''
            END
        ) as message,
        CASE
            WHEN i.status = 'spoiled' THEN 'danger'
            WHEN i.status = 'near_expiry' THEN 'warning'
            ELSE 'info'
        END as alert_class,
        CONCAT('inv_', i.id) as alert_identifier
    FROM inventory i
    WHERE (i.status IN ('near_expiry', 'spoiled') OR i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))
    AND NOT EXISTS (
        SELECT 1 FROM dismissed_alerts 
        WHERE user_id = {$user['id']} 
        AND alert_identifier = CONCAT('inv_', i.id)
    )
    ORDER BY 
        CASE 
            WHEN i.status = 'spoiled' THEN 1
            WHEN i.status = 'near_expiry' THEN 2
            ELSE 3
        END,
        i.expiry_date ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Get condition monitoring alerts
$conditionAlerts = $pdo->query("
    SELECT 
        cm.id,
        sl.name as location,
        cm.temperature,
        cm.humidity,
        cm.recorded_at,
        'condition' as alert_type,
        CONCAT(
            'Storage condition alert: ',
            CASE 
                WHEN cm.temperature > 4 THEN 'Temperature too high ('
                WHEN cm.temperature < 0 THEN 'Temperature too low ('
                ELSE ''
            END,
            CASE 
                WHEN cm.temperature > 4 OR cm.temperature < 0 THEN CONCAT(cm.temperature, '°C)')
                ELSE ''
            END,
            CASE 
                WHEN (cm.temperature > 4 OR cm.temperature < 0) AND (cm.humidity > 80 OR cm.humidity < 65) THEN ' and '
                ELSE ''
            END,
            CASE 
                WHEN cm.humidity > 80 THEN 'Humidity too high ('
                WHEN cm.humidity < 65 THEN 'Humidity too low ('
                ELSE ''
            END,
            CASE 
                WHEN cm.humidity > 80 OR cm.humidity < 65 THEN CONCAT(cm.humidity, '%)')
                ELSE ''
            END,
            ' at ', sl.name
        ) as message,
        CASE 
            WHEN cm.temperature > 4 OR cm.temperature < 0 THEN 'danger'
            WHEN cm.humidity > 80 OR cm.humidity < 65 THEN 'warning'
            ELSE 'info'
        END as alert_class,
        CONCAT('cond_', cm.id) as alert_identifier
    FROM condition_monitoring cm
    JOIN storage_locations sl ON cm.storage_location_id = sl.id
    WHERE (cm.temperature > 4 OR cm.temperature < 0 OR cm.humidity > 80 OR cm.humidity < 65)
    AND NOT EXISTS (
        SELECT 1 FROM dismissed_alerts 
        WHERE user_id = {$user['id']} 
        AND alert_identifier = CONCAT('cond_', cm.id)
    )
    ORDER BY cm.recorded_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Combine all alerts
$alerts = array_merge($inventoryAlerts, $conditionAlerts);

// Sort alerts by severity (danger first, then warning, then info)
usort($alerts, function($a, $b) {
    $severity = ['danger' => 1, 'warning' => 2, 'info' => 3];
    return $severity[$a['alert_class']] <=> $severity[$b['alert_class']];
});

// Recent inventory
$recentInventory = $pdo->query("
    SELECT i.batch_number, mt.name as type, i.quantity, i.expiry_date
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    ORDER BY i.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Update inventory status for expired items
$pdo->query("
    UPDATE inventory 
    SET status = 'spoiled' 
    WHERE expiry_date < CURDATE() 
    AND status != 'spoiled'
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .alert-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
        }
        .alert-content {
            flex-grow: 1;
        }
        .alert-time {
            font-size: 0.8rem;
            color: #6c757d;
            margin-right: 10px;
        }
        .alert-actions {
            white-space: nowrap;
        }
        .badge-expiry {
            background-color: #fd7e14;
        }
        .badge-spoiled {
            background-color: #dc3545;
        }
        .dismiss-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0 5px;
        }
        .dismiss-btn:hover {
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar me-1"></i>This week
                        </button>
                    </div>
                </div>

                <!-- Dashboard Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Inventory</h5>
                                <h2 class="card-text"><?= number_format($inventoryStats['total'] ?? 0, 2) ?></h2>
                                <p class="card-text">kg of meat products</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Good Condition</h5>
                                <h2 class="card-text"><?= number_format($inventoryStats['good'] ?? 0, 2) ?></h2>
                                <p class="card-text">kg (<?= $inventoryStats['total'] > 0 ? round(($inventoryStats['good']/$inventoryStats['total'])*100, 1) : 0 ?>%)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Near Expiry</h5>
                                <h2 class="card-text"><?= number_format($inventoryStats['near_expiry'] ?? 0, 2) ?></h2>
                                <p class="card-text">kg (<?= $inventoryStats['total'] > 0 ? round(($inventoryStats['near_expiry']/$inventoryStats['total'])*100, 1) : 0 ?>%)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Spoiled</h5>
                                <h2 class="card-text"><?= number_format($inventoryStats['spoiled'] ?? 0, 2) ?></h2>
                                <p class="card-text">kg (<?= $inventoryStats['total'] > 0 ? round(($inventoryStats['spoiled']/$inventoryStats['total'])*100, 1) : 0 ?>%)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Inventory by Meat Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="meatTypeChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Loss Analysis by Stage</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="lossStageChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Alerts Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Alerts & Notifications</h5>
                                <span class="badge bg-<?= count($alerts) > 0 ? 'danger' : 'success' ?>">
                                    <?= count($alerts) ?> alert<?= count($alerts) != 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($alerts)): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i> No active alerts
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($alerts as $alert): ?>
                                            <div class="list-group-item list-group-item-action list-group-item-<?= $alert['alert_class'] ?>">
                                                <div class="alert-item">
                                                    <div class="alert-content">
                                                        <?php if ($alert['alert_type'] === 'inventory'): ?>
                                                            <i class="fas fa-box-open me-2"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-thermometer-half me-2"></i>
                                                        <?php endif; ?>
                                                        <?= $alert['message'] ?>
                                                    </div>
                                                    <div class="alert-time">
                                                        <?php if ($alert['alert_type'] === 'inventory'): ?>
                                                            <?= date('M j, Y', strtotime($alert['expiry_date'])) ?>
                                                        <?php else: ?>
                                                            <?= date('M j, H:i', strtotime($alert['recorded_at'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="alert-actions">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="alert_id" value="<?= $alert['alert_identifier'] ?>">
                                                            <button type="submit" name="dismiss_alert" class="dismiss-btn" title="Dismiss alert">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Recent Inventory Additions</h5>
                                <a href="inventory.php?action=add" class="btn btn-sm btn-primary">Add New</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Batch #</th>
                                                <th>Type</th>
                                                <th>Qty (kg)</th>
                                                <th>Expiry</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentInventory as $item): 
                                                $daysLeft = floor((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24));
                                                $badgeClass = $daysLeft <= 3 ? 'bg-danger' : ($daysLeft <= 7 ? 'bg-warning' : 'bg-success');
                                            ?>
                                                <tr>
                                                    <td><?= $item['batch_number'] ?></td>
                                                    <td><?= $item['type'] ?></td>
                                                    <td><?= number_format($item['quantity'], 2) ?></td>
                                                    <td>
                                                        <span class="badge <?= $badgeClass ?>">
                                                            <?= date('M j, Y', strtotime($item['expiry_date'])) ?>
                                                            (<?= $daysLeft ?> days)
                                                        </span>
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
        // Meat Type Chart
        const meatTypeCtx = document.getElementById('meatTypeChart').getContext('2d');
        new Chart(meatTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($inventoryByType, 'name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($inventoryByType, 'total')) ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                    ],
                    hoverBorderColor: "#fff"
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value}kg (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Loss Stage Chart
        const lossStageCtx = document.getElementById('lossStageChart').getContext('2d');
        new Chart(lossStageCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($lossByStage, 'stage')) ?>,
                datasets: [{
                    label: 'Loss Quantity (kg)',
                    data: <?= json_encode(array_column($lossByStage, 'total')) ?>,
                    backgroundColor: '#e74a3b',
                    hoverBackgroundColor: '#c82333'
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity Lost (kg)'
                        }
                    }
                }
            }
        });

        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>