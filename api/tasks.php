<?php
require_once 'config.php';

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Route requests based on HTTP method
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

/**
 * Get all tasks with column information
 */
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
                t.updated_at,
                c.name as column_name 
            FROM tasks t 
            JOIN columns c ON t.column_id = c.id 
            ORDER BY t.column_id ASC, t.position ASC
        ");
        
        $tasks = $stmt->fetchAll();
        sendResponse($tasks);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to fetch tasks: ' . $e->getMessage()], 500);
    }
}

/**
 * Create a new task
 */
function createTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    // Validate required fields
    if (empty($data['title']) || empty($data['column_id'])) {
        sendResponse(['error' => 'Title and column_id are required'], 400);
    }
    
    try {
        // Get the next position in the column
        $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM tasks WHERE column_id = ?");
        $posStmt->execute([$data['column_id']]);
        $position = $posStmt->fetch()['next_pos'];
        
        // Insert new task
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
        
        $taskId = $pdo->lastInsertId();
        
        sendResponse([
            'success' => true, 
            'id' => $taskId,
            'message' => 'Task created successfully'
        ]);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to create task: ' . $e->getMessage()], 500);
    }
}

/**
 * Update an existing task
 */
function updateTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    // Validate required fields
    if (empty($data['id'])) {
        sendResponse(['error' => 'Task ID is required'], 400);
    }
    
    try {
        if (isset($data['new_column_id']) && isset($data['new_position'])) {
            // Handle drag and drop - update position and column
            updateTaskPosition($data);
        } else {
            // Regular update - update task details
            $stmt = $pdo->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, priority = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP
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
        
        sendResponse(['success' => true, 'message' => 'Task updated successfully']);
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to update task: ' . $e->getMessage()], 500);
    }
}

/**
 * Update task position (for drag and drop)
 */
function updateTaskPosition($data) {
    global $pdo;
    
    $pdo->beginTransaction();
    
    try {
        // Update the task's column and position
        $stmt = $pdo->prepare("UPDATE tasks SET column_id = ?, position = ? WHERE id = ?");
        $stmt->execute([$data['new_column_id'], $data['new_position'], $data['id']]);
        
        // Reorder other tasks in the target column
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET position = position + 1 
            WHERE column_id = ? AND position >= ? AND id != ?
        ");
        $stmt->execute([$data['new_column_id'], $data['new_position'], $data['id']]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Delete a task
 */
function deleteTask() {
    global $pdo;
    
    $data = getJsonInput();
    
    // Validate required fields
    if (empty($data['id'])) {
        sendResponse(['error' => 'Task ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $result = $stmt->execute([$data['id']]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Task deleted successfully']);
        } else {
            sendResponse(['error' => 'Task not found'], 404);
        }
        
    } catch(Exception $e) {
        sendResponse(['error' => 'Failed to delete task: ' . $e->getMessage()], 500);
    }
}
?> 