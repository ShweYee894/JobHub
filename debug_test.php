<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

$email = 'admin@gmail.com';
$password = 'admin123';

// 1. Force convert the database password field to the correct VARCHAR length
$conn->query('ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NOT NULL');

// 2. Generate a fresh, active runtime hash dynamically
$fresh_hash = password_hash($password, PASSWORD_DEFAULT);

// 3. Bind parameters via a safe prepared statement to guarantee no data truncation
$stmt = $conn->prepare("UPDATE `users` SET `password` = ?, `status` = 'active', `role` = 'admin' WHERE LOWER(`email`) = LOWER(?)");
$stmt->bind_param('ss', $fresh_hash, $email);
$stmt->execute();
$stmt->close();

// 4. Verify directly against the updated state
$check = $conn->prepare('SELECT `password`, `role` FROM `users` WHERE LOWER(`email`) = LOWER(?) LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$user = $check->get_result()->fetch_assoc();
$check->close();

echo '<h2>System Alignment Status</h2>';
if (!$user) {
    echo "<p style='color:red;'><strong>Failed:</strong> No user with the email 'admin@gmail.com' was found in this database instance.</p>";
} else {
    echo '<p>Found Account Role: <strong>' . htmlspecialchars($user['role']) . '</strong></p>';
    if (password_verify($password, $user['password'])) {
        echo "<h3 style='color:green;'>✔ Fixed! Your credentials match perfectly.</h3>";
        echo '<p>Go to your login form and sign in using:</p>';
        echo '<ul><li><strong>Email:</strong> admin@gmail.com</li><li><strong>Password:</strong> admin123</li></ul>';
    } else {
        echo "<h3 style='color:red;'>❌ Error: The script updated, but the hash is still breaking. Check if your table has active database triggers.</h3>";
    }
}
