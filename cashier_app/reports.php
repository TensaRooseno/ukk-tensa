<?php
// Start session and include database connection
session_start();
require_once 'includes/db.php';

// Authentication check - Only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Get date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today

// Fetch overall statistics
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT t.transaction_id) as total_transactions,
        COUNT(DISTINCT t.member_id) as member_transactions,
        SUM(t.total_amount) as total_revenue,
        SUM(t.points_earned) as total_points_earned,
        SUM(t.points_used) as total_points_redeemed
    FROM transactions t
    WHERE DATE(t.date_time) BETWEEN :start_date AND :end_date
");
$statsQuery->execute([
    'start_date' => $start_date,
    'end_date' => $end_date
]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Get member statistics
$memberStatsQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN first_purchase_date BETWEEN :start_date AND :end_date THEN 1 END) as new_members,
        AVG(points) as avg_points
    FROM members
");
$memberStatsQuery->execute([
    'start_date' => $start_date,
    'end_date' => $end_date
]);
$memberStats = $memberStatsQuery->fetch(PDO::FETCH_ASSOC);

// Get top members by points
$topMembersQuery = $conn->prepare("
    SELECT 
        m.phone_number,
        m.points,
        COUNT(t.transaction_id) as total_purchases,
        SUM(t.total_amount) as total_spent
    FROM members m
    LEFT JOIN transactions t ON m.id = t.member_id
    WHERE t.date_time BETWEEN :start_date AND :end_date
        OR t.date_time IS NULL
    GROUP BY m.id, m.phone_number, m.points
    ORDER BY m.points DESC
    LIMIT 5
");
$topMembersQuery->execute([
    'start_date' => $start_date,
    'end_date' => $end_date
]);
$topMembers = $topMembersQuery->fetchAll(PDO::FETCH_ASSOC);

// Calculate points statistics
$pointsStatsQuery = $conn->prepare("
    SELECT 
        SUM(points_earned) as total_points_earned,
        SUM(points_used) as total_points_used,
        COUNT(DISTINCT CASE WHEN points_used > 0 THEN member_id END) as members_using_points
    FROM transactions
    WHERE date_time BETWEEN :start_date AND :end_date
");
$pointsStatsQuery->execute([
    'start_date' => $start_date,
    'end_date' => $end_date
]);
$pointsStats = $pointsStatsQuery->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reports - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and JS -->
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar Navigation -->
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Dashboard</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php">User Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="product_management.php">Product Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaction_history.php">Transaction History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="reports.php">Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <h2>Reports and Analytics</h2>

        <!-- Date Range Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <p class="card-text display-6">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Transactions</h5>
                        <p class="card-text display-6"><?php echo $stats['total_transactions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Member Transactions</h5>
                        <p class="card-text display-6"><?php echo $stats['member_transactions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Points Earned</h5>
                        <p class="card-text display-6">$<?php echo number_format($stats['total_points_earned'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Membership Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Membership Overview</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Members
                                <span class="badge bg-primary rounded-pill"><?php echo $memberStats['total_members']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                New Members
                                <span class="badge bg-success rounded-pill"><?php echo $memberStats['new_members']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Average Points Balance
                                <span class="badge bg-info rounded-pill">$<?php echo number_format($memberStats['avg_points'], 2); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Members Using Points
                                <span class="badge bg-warning rounded-pill"><?php echo $pointsStats['members_using_points']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Points Activity</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Points Earned
                                <span class="badge bg-success rounded-pill">$<?php echo number_format($pointsStats['total_points_earned'], 2); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Points Redeemed
                                <span class="badge bg-danger rounded-pill">$<?php echo number_format($pointsStats['total_points_used'], 2); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Net Points Balance
                                <span class="badge bg-info rounded-pill">$<?php echo number_format($pointsStats['total_points_earned'] - $pointsStats['total_points_used'], 2); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Members Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Top Members by Points</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Phone Number</th>
                                <th>Points Balance</th>
                                <th>Total Purchases</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topMembers as $member): ?>
                            <tr>
                                <td><?php echo $member['phone_number']; ?></td>
                                <td>$<?php echo number_format($member['points'], 2); ?></td>
                                <td><?php echo $member['total_purchases']; ?></td>
                                <td>$<?php echo number_format($member['total_spent'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>