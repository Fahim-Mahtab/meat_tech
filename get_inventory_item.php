<?php
require 'config.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT i.*, i.batch_number, i.meat_type_id, i.quantity, 
               i.processing_date, i.storage_location_id
        FROM inventory i
        WHERE i.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($item);
}
?>