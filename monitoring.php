<?php
require 'config.php';
requireAuth();

// Handle form submission for new condition records
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new record
    if (isset($_POST['add_condition'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO condition_monitoring 
                (storage_location_id, temperature, humidity, recorded_at)
                VALUES (:storage_location_id, :temperature, :humidity, NOW())
            ");
            
            $stmt->execute([
                ':storage_location_id' => $_POST['storage_location_id'],
                ':temperature' => $_POST['temperature'],
                ':humidity' => $_POST['humidity']
            ]);
            
            $_SESSION['success_message'] = "Condition record added successfully";
            header("Location: monitoring.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error adding condition record: " . $e->getMessage();
        }
    }
    // Update existing record
    elseif (isset($_POST['update_condition'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE condition_monitoring 
                SET temperature = :temperature, 
                    humidity = :humidity,
                    recorded_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':temperature' => $_POST['temperature'],
                ':humidity' => $_POST['humidity'],
                ':id' => $_POST['record_id']
            ]);
            
            $_SESSION['success_message'] = "Condition record updated successfully";
            header("Location: monitoring.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating condition record: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM condition_monitoring WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success_message'] = "Condition record deleted successfully";
        header("Location: monitoring.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting condition record: " . $e->getMessage();
    }
}

// Get all condition records with storage location info
$conditionRecords = $pdo->query("
    SELECT cm.id, cm.storage_location_id, sl.name as location_name, 
           cm.temperature, cm.humidity, cm.recorded_at
    FROM condition_monitoring cm
    JOIN storage_locations sl ON cm.storage_location_id = sl.id
    ORDER BY cm.recorded_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get storage locations for dropdown
$storageLocations = $pdo->query("SELECT id, name FROM storage_locations ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Condition Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .badge-critical {
            background-color: #e74a3b;
        }
        .badge-warning {
            background-color: #f6c23e;
        }
        .badge-normal {
            background-color: #1cc88a;
        }
        .form-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .form-inline .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 120px;
        }
        .editable {
            cursor: pointer;
        }
        .edit-form {
            display: none;
        }
        .actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Condition Monitoring</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
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
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Add New Condition Reading</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="monitoring.php" class="form-inline">
                            <div class="form-group">
                                <label for="storageLocation" class="form-label">Storage Unit *</label>
                                <select class="form-select" id="storageLocation" name="storage_location_id" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach ($storageLocations as $location): ?>
                                        <option value="<?= $location['id'] ?>"><?= $location['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="temperature" class="form-label">Temperature (°C) *</label>
                                <input type="number" step="0.1" class="form-control" id="temperature" name="temperature" required>
                            </div>
                            <div class="form-group">
                                <label for="humidity" class="form-label">Humidity (%) *</label>
                                <input type="number" step="0.1" min="0" max="100" class="form-control" id="humidity" name="humidity" required>
                            </div>
                            <div class="form-group d-flex align-items-end">
                                <button type="submit" name="add_condition" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5>All Condition Records</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Storage Unit</th>
                                        <th>Temperature (°C)</th>
                                        <th>Humidity (%)</th>
                                        <th>Status</th>
                                        <th>Recorded At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conditionRecords as $record): 
                                        $tempStatus = ($record['temperature'] > 4 || $record['temperature'] < 0) ? 'danger' : 'success';
                                        $humidityStatus = ($record['humidity'] > 80 || $record['humidity'] < 65) ? 'warning' : 'success';
                                        $overallStatus = ($tempStatus === 'danger') ? 'critical' : (($humidityStatus === 'warning') ? 'warning' : 'normal');
                                    ?>
                                        <tr>
                                            <td><?= $record['location_name'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= $tempStatus ?>">
                                                    <?= number_format($record['temperature'], 1) ?>°C
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $humidityStatus ?>">
                                                    <?= number_format($record['humidity'], 1) ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $overallStatus ?>">
                                                    <?= ucfirst($overallStatus) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, H:i', strtotime($record['recorded_at'])) ?></td>
                                            <td class="actions">
                                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                                        data-id="<?= $record['id'] ?>"
                                                        data-location="<?= $record['storage_location_id'] ?>"
                                                        data-temp="<?= $record['temperature'] ?>"
                                                        data-humidity="<?= $record['humidity'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="monitoring.php?delete=<?= $record['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
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
            </main>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="monitoring.php">
                    <input type="hidden" name="record_id" id="editRecordId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Condition Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editStorageLocation" class="form-label">Storage Unit</label>
                            <select class="form-select" id="editStorageLocation" name="storage_location_id" disabled>
                                <?php foreach ($storageLocations as $location): ?>
                                    <option value="<?= $location['id'] ?>"><?= $location['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editTemperature" class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" class="form-control" id="editTemperature" name="temperature" required>
                        </div>
                        <div class="mb-3">
                            <label for="editHumidity" class="form-label">Humidity (%)</label>
                            <input type="number" step="0.1" min="0" max="100" class="form-control" id="editHumidity" name="humidity" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_condition" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit button clicks
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const recordId = this.getAttribute('data-id');
                const locationId = this.getAttribute('data-location');
                const temp = this.getAttribute('data-temp');
                const humidity = this.getAttribute('data-humidity');
                
                document.getElementById('editRecordId').value = recordId;
                document.getElementById('editStorageLocation').value = locationId;
                document.getElementById('editTemperature').value = temp;
                document.getElementById('editHumidity').value = humidity;
                
                const modal = new bootstrap.Modal(document.getElementById('editModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>