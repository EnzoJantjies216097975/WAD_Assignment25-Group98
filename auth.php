<?php
// php/api/auth.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

// Simple router based on 'action'
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        register($data);
        break;
    case 'login':
        login($data);
        break;
    case 'logout':
        logout();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// ----- FUNCTIONS -----

function register($data) {
    global $conn;

    if (!isset($data['username'], $data['email'], $data['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
        return;
    }

    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'User already exists']);
        return;
    }

    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $passwordHash);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
}

function login($data) {
    global $conn;

    if (!isset($data['email'], $data['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
        return;
    }

    $email = trim($data['email']);
    $password = $data['password'];

    // Fetch user
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $username, $passwordHash);
    $stmt->fetch();

    if ($id && password_verify($password, $passwordHash)) {
        // Login successful
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        echo json_encode(['status' => 'success', 'message' => 'Login successful']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
}

function logout() {
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
}
