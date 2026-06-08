<?php
session_start();
header('Content-Type: application/json');

// Disable error display - return JSON even on error
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once '../config/db_config.php';

    $conn = getDBConnection();

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, password, is_banned FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is banned first
        if ($user['is_banned']) {
            echo json_encode(['success' => false, 'message' => 'Your account has been banned due to multiple reports.']);
            exit;
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Check if user has set preferences
            $pref_check = $conn->prepare("SELECT preferred_categories FROM users WHERE id = ?");
            $pref_check->bind_param("i", $user['id']);
            $pref_check->execute();
            $pref_result = $pref_check->get_result()->fetch_assoc();

            $has_preferences = !empty($pref_result['preferred_categories']) && $pref_result['preferred_categories'] !== '[]';

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'has_preferences' => $has_preferences
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

