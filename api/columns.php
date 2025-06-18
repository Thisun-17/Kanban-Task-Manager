<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        getAllColumns();
        break;
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

/**
 * Get all columns
 */
function getAllColumns() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM columns ORDER BY position ASC");
        $columns = $stmt->fetchAll();
        sendResponse($columns);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to fetch columns: ' . $e->getMessage()], 500);
    }
}
?>