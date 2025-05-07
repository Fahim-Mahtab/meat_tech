<?php
require 'config.php';
requireAuth();

// Only admin can access settings
$user = getCurrentUser($pdo);
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update meat type expiry days
    foreach ($_POST['expiry_days'] as $meatTypeId => $days) {
        $stmt = $pdo->prepare("UPDATE meat_types SET default_expiry_days = ? WHERE id = ?");
        $stmt->execute([$days, $meatTypeId]);
    }
    
    // Update notification settings (in a real app, would save to database)
    $success = "Settings updated successfully!";
}

// Get meat types for expiry settings
$meatTypes = $pdo->query("SELECT * FROM meat_types")->fetchAll(PDO::FETCH_ASSOC);

// Get current settings (simulated)
$settings = [
    'expiry_alerts' => true,
    'expiry_days_before' => 3,
    'max_temp' => 4.0,
    'min_temp' => 0.0,
    'max_humidity' => 80,
    'min_humidity' => 65,
    'monitoring_alerts' => true,
    'email_alerts' => true,
    'email_recipients' => 'admin@MeaTrack.com,manager@MeaTrack.com',
    'sms_alerts' => false,
    'sms_recipients' => '+1234567890,+1987654321'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeaTrack - Settings</title>
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
                    <h1 class="h2">System Settings</h1>
                    <button type="submit" form="settingsForm" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Settings
                    </button>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <form id="settingsForm" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Inventory Settings</h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="mb-3">Default Expiry Days by Meat Type</h6>
                                    <?php foreach ($meatTypes as $type): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?= $type['name'] ?></label>
                                            <input type="number" class="form-control" name="expiry_days[<?= $type['id'] ?>]" 
                                                value="<?= $type['default_expiry_days'] ?>" min="1" required>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="expiryAlerts" name="expiry_alerts" 
                                            <?= $settings['expiry_alerts'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="expiryAlerts">Enable Expiry Alerts</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Days Before Expiry to Alert</label>
                                        <input type="number" class="form-control" name="expiry_days_before" 
                                            value="<?= $settings['expiry_days_before'] ?>" min="1" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Monitoring Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Max Temperature Threshold (°C)</label>
                                        <input type="number" step="0.1" class="form-control" name="max_temp" 
                                            value="<?= $settings['max_temp'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Min Temperature Threshold (°C)</label>
                                        <input type="number" step="0.1" class="form-control" name="min_temp" 
                                            value="<?= $settings['min_temp'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Max Humidity Threshold (%)</label>
                                        <input type="number" class="form-control" name="max_humidity" 
                                            value="<?= $settings['max_humidity'] ?>" min="0" max="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Min Humidity Threshold (%)</label>
                                        <input type="number" class="form-control" name="min_humidity" 
                                            value="<?= $settings['min_humidity'] ?>" min="0" max="100" required>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="monitoringAlerts" name="monitoring_alerts" 
                                            <?= $settings['monitoring_alerts'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="monitoringAlerts">Enable Monitoring Alerts</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Notification Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Notifications</label>
                                                <input type="text" class="form-control" name="email_recipients" 
                                                    value="<?= $settings['email_recipients'] ?>" required>
                                                <small class="text-muted">Separate multiple emails with commas</small>
                                            </div>
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="emailAlerts" name="email_alerts" 
                                                    <?= $settings['email_alerts'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="emailAlerts">Enable Email Alerts</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMS Notifications</label>
                                                <input type="text" class="form-control" name="sms_recipients" 
                                                    value="<?= $settings['sms_recipients'] ?>">
                                                <small class="text-muted">Separate multiple numbers with commas</small>
                                            </div>
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="smsAlerts" name="sms_alerts" 
                                                    <?= $settings['sms_alerts'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="smsAlerts">Enable SMS Alerts</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>