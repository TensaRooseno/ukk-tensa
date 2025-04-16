<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role_id = $_POST['role_id'];
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (:username, :password, :role_id)");
        $stmt->execute(['username' => $username, 'password' => $password, 'role_id' => $role_id]);
    } elseif (isset($_POST['edit_user'])) {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $role_id = $_POST['role_id'];
        $stmt = $conn->prepare("UPDATE users SET username = :username, role_id = :role_id WHERE user_id = :user_id");
        $stmt->execute(['username' => $username, 'role_id' => $role_id, 'user_id' => $user_id]);
    }
}

// Fetch Users with Pagination
$limit = 5; // Users per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalStmt = $conn->query("SELECT COUNT(*) as total FROM users");
$total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

$stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Management - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 20px;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_management.php">Product Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>User Management</h2>
        <p>Manage users with roles (Admin or Cashier).</p>

        <!-- Add User Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
            Add New User
        </button>

        <!-- User List -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['user_id']; ?></td>
                    <td><?php echo $user['username']; ?></td>
                    <td><?php echo $user['role_name']; ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['user_id']; ?>">Edit</button>
                        <a href="delete_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                    </td>
                </tr>

                <!-- Edit User Modal -->
                <div class="modal fade" id="editUserModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <div class="mb-3">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control" value="<?php echo $user['username']; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Role</label>
                                        <select name="role_id" class="form-control">
                                            <option value="1" <?php echo $user['role_id'] == 1 ? 'selected' : ''; ?>>Admin</option>
                                            <option value="2" <?php echo $user['role_id'] == 2 ? 'selected' : ''; ?>>Cashier</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Role</label>
                            <select name="role_id" class="form-control">
                                <option value="1">Admin</option>
                                <option value="2">Cashier</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>