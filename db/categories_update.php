<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

$categoryID = intval($_POST['categoryID'] ?? 0);
$categoryName = trim($_POST['categoryName'] ?? '');

if (!$categoryID || !$categoryName) {
    echo json_encode(['status' => 'error', 'message' => 'Category ID and name are required']);
    exit;
}

$stmt = $conn->prepare("UPDATE categories SET categoryName = ? WHERE categoryID = ?");
$stmt->bind_param("si", $categoryName, $categoryID);

if ($stmt->execute()) {
    // Log audit activity
    if (isset($_SESSION['userID'])) {
        logCategoryActivity($conn, $_SESSION['userID'], 'update', $categoryName, "Category ID: $categoryID");
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Category updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update category']);
}

$stmt->close();
$conn->close();
?>
