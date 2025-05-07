<?php
require 'config.php';
requireAuth();

// Get meat type distribution
$meatDistribution = $pdo->query("
    SELECT mt.name as label, SUM(i.quantity) as value
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    WHERE i.status != 'spoiled'
    GROUP BY mt.name
")->fetchAll(PDO::FETCH_ASSOC);

// Get loss stages data (this would come from your spoilage/loss tracking)
$lossStages = $pdo->query("
    SELECT stage as label, SUM(quantity) as value
    FROM loss_stages
    GROUP BY stage
")->fetchAll(PDO::FETCH_ASSOC);

// Get spoilage statistics
$spoilageStats = $pdo->query("
    SELECT 
        SUM(s.quantity) as totalSpoiled,
        (SELECT SUM(quantity) FROM inventory) as totalInventory,
        (SUM(s.quantity) / (SELECT SUM(quantity) FROM inventory) * 100 as percentage
    FROM spoilage s
")->fetch(PDO::FETCH_ASSOC);

// Get condition alerts (temperature/humidity outside safe ranges)
$conditionAlerts = $pdo->query("
    SELECT 
        sl.name as location,
        cm.temperature,
        cm.humidity,
        cm.recorded_at as recordedAt,
        CASE 
            WHEN cm.temperature > 4 OR cm.temperature < 0 THEN 'critical'
            WHEN cm.humidity > 80 OR cm.humidity < 65 THEN 'warning'
            ELSE 'normal'
        END as severity,
        CASE 
            WHEN cm.temperature > 4 THEN 'too high'
            WHEN cm.temperature < 0 THEN 'too low'
            ELSE 'normal'
        END as temperatureStatus,
        CASE 
            WHEN cm.humidity > 80 THEN 'too high'
            WHEN cm.humidity < 65 THEN 'too low'
            ELSE 'normal'
        END as humidityStatus
    FROM condition_monitoring cm
    JOIN storage_locations sl ON cm.storage_location_id = sl.id
    WHERE (cm.temperature > 4 OR cm.temperature < 0 OR cm.humidity > 80 OR cm.humidity < 65)
    ORDER BY cm.recorded_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get items expiring soon (within 7 days)
$expiringSoon = $pdo->query("
    SELECT 
        i.batch_number as batchNumber,
        mt.name as meatType,
        i.quantity,
        i.expiry_date as expiryDate
    FROM inventory i
    JOIN meat_types mt ON i.meat_type_id = mt.id
    WHERE i.status = 'good' 
    AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY i.expiry_date ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Update inventory status for expired items
$pdo->query("
    UPDATE inventory 
    SET status = 'spoiled' 
    WHERE expiry_date < CURDATE() 
    AND status != 'spoiled'
");

header('Content-Type: application/json');
echo json_encode([
    'meatDistribution' => [
        'labels' => array_column($meatDistribution, 'label'),
        'values' => array_column($meatDistribution, 'value')
    ],
    'lossStages' => [
        'labels' => array_column($lossStages, 'label'),
        'values' => array_column($lossStages, 'value')
    ],
    'spoilageStats' => [
        'percentage' => number_format($spoilageStats['percentage'] ?? 0, 2),
        'totalSpoiled' => number_format($spoilageStats['totalSpoiled'] ?? 0, 2),
        'totalInventory' => number_format($spoilageStats['totalInventory'] ?? 0, 2)
    ],
    'conditionAlerts' => $conditionAlerts,
    'expiringSoon' => $expiringSoon
]);