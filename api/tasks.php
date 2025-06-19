<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        getAllTasks();
        break;
    case 'POST':
        createTask();
        break;
    case 'PUT':
        updateTask();
        break;
    case 'DELETE':
        deleteTask();
        break;
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getAllTasks() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                t.id, 
                t.title, 
                t.description, 
                t.column_id, 
                t.position, 
                t.priority, 
                t.due_date, 
                t.created_at, 
                t.updated_at
            FROM tasks t 
            ORDER BY t.column_id ASC, t.position ASC
        ");
        
        sendResponse($stmt->fetchAll());
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to fetch tasks'], 500);
    }
}

function createTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    if (empty($data['title']) || empty($data['column_id'])) {
        sendResponse(['error' => 'Title and column_id are required'], 400);
    }
    
    try {
        $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM tasks WHERE column_id = ?");
        $posStmt->execute([$data['column_id']]);
        $position = $posStmt->fetch()['next_pos'];
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, column_id, position, priority, due_date) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['column_id'],
            $position,
            $data['priority'] ?? 'medium',
            !empty($data['due_date']) ? $data['due_date'] : null
        ]);
        
        sendResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to create task'], 500);
    }
}

function updateTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    if (empty($data['id'])) {
        sendResponse(['error' => 'Task ID is required'], 400);
    }
    
    try {
        if (isset($data['new_column_id'])) {
            // Handle drag and drop
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE tasks SET column_id = ?, position = ? WHERE id = ?");
            $stmt->execute([$data['new_column_id'], $data['new_position'] ?? 1, $data['id']]);
            
            $pdo->commit();
        } else {
            // Regular update
            $stmt = $pdo->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, priority = ?, due_date = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['priority'] ?? 'medium',
                !empty($data['due_date']) ? $data['due_date'] : null,
                $data['id']
            ]);
        }
        
        sendResponse(['success' => true]);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to update task'], 500);
    }
}

function deleteTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    if (empty($data['id'])) {
        sendResponse(['error' => 'Task ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true]);
        } else {
            sendResponse(['error' => 'Task not found'], 404);
        }
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to delete task'], 500);
    }
}
?>