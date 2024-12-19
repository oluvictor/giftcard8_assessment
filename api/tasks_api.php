<?php
require_once 'connection.php';

// Set up the PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Helper function for sending JSON responses
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get the HTTP method and request URI
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$resource = array_shift($request);
$id = array_shift($request) ?? null;

if ($resource !== 'tasks') {
    sendResponse(['error' => 'Invalid endpoint'], 404);
}

switch ($method) {
    case 'GET':
        if ($id === null) {
            // Fetch all tasks
            $stmt = $pdo->query('SELECT * FROM tasks');
            $tasks = $stmt->fetchAll();
            sendResponse($tasks);
        } else {
            // Fetch a specific task
            $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $task = $stmt->fetch();
            if ($task) {
                sendResponse($task);
            } else {
                sendResponse(['error' => 'Task not found'], 404);
            }
        }
        break;

    case 'POST':
        // Add a new task
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['name'], $input['description'])) {
            sendResponse(['error' => 'Invalid input'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO tasks (name, description) VALUES (:name, :description)');
        $stmt->execute(['name' => $input['name'], 'description' => $input['description']]);
        sendResponse(['message' => 'Task created', 'id' => $pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        // Update a task
        if ($id === null) {
            sendResponse(['error' => 'Task ID is required'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['name'], $input['description'])) {
            sendResponse(['error' => 'Invalid input'], 400);
        }

        $stmt = $pdo->prepare('UPDATE tasks SET name = :name, description = :description WHERE id = :id');
        $stmt->execute(['name' => $input['name'], 'description' => $input['description'], 'id' => $id]);

        if ($stmt->rowCount()) {
            sendResponse(['message' => 'Task updated']);
        } else {
            sendResponse(['error' => 'Task not found or no changes made'], 404);
        }
        break;

    case 'DELETE':
        // Delete a task
        if ($id === null) {
            sendResponse(['error' => 'Task ID is required'], 400);
        }

        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount()) {
            sendResponse(['message' => 'Task deleted']);
        } else {
            sendResponse(['error' => 'Task not found'], 404);
        }
        break;

    default:
        sendResponse(['error' => 'Invalid HTTP method'], 405);
}
?>
