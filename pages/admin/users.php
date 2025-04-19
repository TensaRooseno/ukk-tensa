<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// User editing mode
$edit_mode = false;
$edit_user = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $role_id = intval($_POST['role_id']);
        
        // Check if username already exists
        $check_query = "SELECT * FROM users WHERE username = '$username'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Username already exists", 'error');
        } else {
            $query = "INSERT INTO users (username, email, password, role_id) VALUES ('$username', '$email', '$password', $role_id)";
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "User $username added successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error adding user: " . mysqli_error($conn), 'error');
            }
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $role_id = intval($_POST['role_id']);
        
        // Check if username already exists for other users
        $check_query = "SELECT * FROM users WHERE username = '$username' AND user_id != $user_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            redirectWithMessage($_SERVER['PHP_SELF'], "Username already exists for another user", 'error');
        } else {
            // Check if password should be updated
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET username = '$username', email = '$email', password = '$password', role_id = $role_id WHERE user_id = $user_id";
            } else {
                $query = "UPDATE users SET username = '$username', email = '$email', role_id = $role_id WHERE user_id = $user_id";
            }
            
            if (mysqli_query($conn, $query)) {
                redirectWithMessage($_SERVER['PHP_SELF'], "User updated successfully", 'success');
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error updating user: " . mysqli_error($conn), 'error');
            }
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Don't allow deleting your own account
        if ($user_id == $_SESSION['user_id']) {
            redirectWithMessage($_SERVER['PHP_SELF'], "You cannot delete your own account", 'error');
        } else {
            // Check if user is referenced in transactions
            $query = "SELECT COUNT(*) as count FROM transactions WHERE user_id = $user_id";
            $result = mysqli_query($conn, $query);
            
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                if ($row['count'] > 0) {
                    // User has transactions, cannot delete
                    redirectWithMessage($_SERVER['PHP_SELF'], "Cannot delete user because they have associated transactions", 'error');
                } else {
                    // Safe to delete
                    $query = "DELETE FROM users WHERE user_id = $user_id";
                    if (mysqli_query($conn, $query)) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "User deleted successfully", 'success');
                    } else {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Error deleting user: " . mysqli_error($conn), 'error');
                    }
                }
            } else {
                redirectWithMessage($_SERVER['PHP_SELF'], "Error checking transactions: " . mysqli_error($conn), 'error');
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $user_id = intval($_GET['edit']);
    $query = "SELECT * FROM users WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_mode = true;
        $edit_user = mysqli_fetch_assoc($result);
    }
}

// Get all users with their roles
$query = "SELECT u.*, r.role_name FROM users u 
          JOIN roles r ON u.role_id = r.role_id 
          ORDER BY u.user_id";
$users = mysqli_query($conn, $query);

// Get all roles for dropdown
$roles_query = "SELECT * FROM roles";
$roles = mysqli_query($conn, $roles_query);

require_once '../../include/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="page-title"><i class="fas fa-users me-2"></i>User Management</h1>
    </div>
</div>

<div class="row">
    <!-- Add/Edit User Form -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0"><?php echo $edit_mode ? 'Edit User' : 'Add New User'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" id="username" name="username" class="form-control" required 
                               value="<?php echo $edit_mode ? $edit_user['username'] : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email" class="form-control" required
                               value="<?php echo $edit_mode ? $edit_user['email'] : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" id="password" name="password" class="form-control" <?php echo !$edit_mode ? 'required' : ''; ?>>
                        <?php if ($edit_mode): ?>
                            <div class="form-text text-muted">Leave blank to keep current password</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role:</label>
                        <select id="role" name="role_id" class="form-select" required>
                            <?php 
                            mysqli_data_seek($roles, 0);
                            while ($role = mysqli_fetch_assoc($roles)): 
                            ?>
                                <option value="<?php echo $role['role_id']; ?>" 
                                    <?php echo ($edit_mode && $edit_user['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                                    <?php echo $role['role_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex mt-4">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_user" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update User
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_user" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Add User
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- User List -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0">Users</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($users) > 0): ?>
                                <?php while ($user = mysqli_fetch_assoc($users)): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo $user['username']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['role_name'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                                <?php echo $user['role_name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?edit=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this user?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>