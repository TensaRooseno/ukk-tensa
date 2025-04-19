<?php
require_once '../../include/config.php';

// Check if already logged in
if (isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Process login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Simple validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check user credentials
        $query = "SELECT u.*, r.role_name FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 WHERE u.username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name'];
                
                header('Location: ../../index.php');
                exit;
            } else {
                $error = 'Wrong password';
            }
        } else {
            $error = 'User not found';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        form { max-width: 300px; margin: 0 auto; }
        label { display: block; margin: 10px 0 5px; }
        input { width: 100%; padding: 5px; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        button { padding: 8px; background: #007bff; color: white; border: none; width: 100%; }
    </style>
</head>
<body>
    <h1 style="text-align: center;">Cashier App Login</h1>
    
    <form method="POST" action="">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div>
            <button type="submit">Login</button>
        </div>
    </form>
</body>
</html>