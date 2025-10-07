<?php
require_once '../config.php';
requireAuth();

if (!isset($_GET['id'])) {
    respond(false, 'Sale ID required');
}

$sale_id = (int)$_GET['id'];

// Get sale details
$stmt = $conn->prepare("SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    respond(false, 'Sale not found');
}

$sale = $result->fetch_assoc();
$stmt->close();

// Check permission (sellers can only see their own sales)
if ($_SESSION['role'] === 'seller' && $sale['user_id'] != $_SESSION['user_id']) {
    respond(false, 'Access denied');
}

// Get sale items
$stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

respond(true, '', [
    'sale' => $sale,
    'items' => $items
]);
?>