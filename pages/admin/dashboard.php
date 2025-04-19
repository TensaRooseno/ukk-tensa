<?php
require_once '../../include/config.php';

// Check if user is logged in and has admin role
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Get current month data for daily transactions chart
$current_month = date('Y-m');
$daily_transactions_query = "SELECT 
                              DATE(date_time) as date,
                              COUNT(*) as transaction_count, 
                              SUM(total_amount - discount_amount) as daily_total
                            FROM transactions
                            WHERE DATE_FORMAT(date_time, '%Y-%m') = '$current_month'
                            GROUP BY DATE(date_time)
                            ORDER BY date";
$daily_transactions = mysqli_query($conn, $daily_transactions_query);

$dates = [];
$transaction_counts = [];
$daily_totals = [];

// Format data for chart
if ($daily_transactions && mysqli_num_rows($daily_transactions) > 0) {
    while ($row = mysqli_fetch_assoc($daily_transactions)) {
        $dates[] = date('d', strtotime($row['date'])); // Day of month
        $transaction_counts[] = $row['transaction_count'];
        $daily_totals[] = $row['daily_total'];
    }
}

// Get product sales data for product chart
$product_sales_query = "SELECT 
                          p.name,
                          SUM(td.quantity) as total_quantity
                        FROM transaction_details td
                        JOIN products p ON td.product_id = p.product_id
                        GROUP BY p.product_id
                        ORDER BY total_quantity DESC
                        LIMIT 10";
$product_sales = mysqli_query($conn, $product_sales_query);

$product_names = [];
$product_quantities = [];

// Format data for chart
if ($product_sales && mysqli_num_rows($product_sales) > 0) {
    while ($row = mysqli_fetch_assoc($product_sales)) {
        $product_names[] = $row['name'];
        $product_quantities[] = $row['total_quantity'];
    }
}

// Get summary statistics
$total_transactions_query = "SELECT COUNT(*) as count FROM transactions";
$total_transactions_result = mysqli_query($conn, $total_transactions_query);
$total_transactions = mysqli_fetch_assoc($total_transactions_result)['count'];

$total_revenue_query = "SELECT SUM(total_amount - discount_amount) as total FROM transactions";
$total_revenue_result = mysqli_query($conn, $total_revenue_query);
$total_revenue = mysqli_fetch_assoc($total_revenue_result)['total'] ?: 0;

$total_products_query = "SELECT COUNT(*) as count FROM products";
$total_products_result = mysqli_query($conn, $total_products_query);
$total_products = mysqli_fetch_assoc($total_products_result)['count'];

$total_members_query = "SELECT COUNT(*) as count FROM members";
$total_members_result = mysqli_query($conn, $total_members_query);
$total_members = mysqli_fetch_assoc($total_members_result)['count'];

require_once '../../include/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h1>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex flex-row w-100">
            <div class="flex-fill">
                <div class="card bg-primary text-white h-100 rounded-0 m-0 border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Transactions</h6>
                                <h2 class="mb-0"><?php echo number_format($total_transactions); ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-exchange-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex-fill">
                <div class="card bg-success text-white h-100 rounded-0 m-0 border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Revenue</h6>
                                <h2 class="mb-0">$<?php echo number_format($total_revenue, 2); ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex-fill">
                <div class="card bg-info text-white h-100 rounded-0 m-0 border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Products</h6>
                                <h2 class="mb-0"><?php echo number_format($total_products); ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-box fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex-fill">
                <div class="card bg-warning text-dark h-100 rounded-0 m-0 border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Members</h6>
                                <h2 class="mb-0"><?php echo number_format($total_members); ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-users fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Daily Transactions Chart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Transactions (<?php echo date('F Y'); ?>)</h3>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="dailyTransactionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Products Chart -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Top Products</h3>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="productSalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Transactions Chart
const ctx1 = document.getElementById('dailyTransactionsChart').getContext('2d');
const dailyTransactionsChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [
            {
                label: 'Number of Transactions',
                data: <?php echo json_encode($transaction_counts); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                yAxisID: 'y',
            },
            {
                label: 'Total Sales ($)',
                data: <?php echo json_encode($daily_totals); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Number of Transactions'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Total Sales ($)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Day of Month'
                }
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Daily Sales Performance'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.datasetIndex === 1) {
                            label += '$' + context.raw.toFixed(2);
                        } else {
                            label += context.raw;
                        }
                        return label;
                    }
                }
            }
        }
    }
});

// Product Sales Chart - Changed to Pie Chart
const ctx2 = document.getElementById('productSalesChart').getContext('2d');
const productSalesChart = new Chart(ctx2, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($product_names); ?>,
        datasets: [{
            label: 'Units Sold',
            data: <?php echo json_encode($product_quantities); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)',
                'rgba(83, 102, 255, 0.7)',
                'rgba(40, 159, 64, 0.7)',
                'rgba(210, 199, 199, 0.7)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(199, 199, 199, 1)',
                'rgba(83, 102, 255, 1)',
                'rgba(40, 159, 64, 1)',
                'rgba(210, 199, 199, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    boxWidth: 15,
                    font: {
                        size: 10
                    }
                }
            },
            title: {
                display: true,
                text: 'Top Products by Units Sold'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.formattedValue;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((context.raw / total) * 100);
                        return `${label}: ${value} units (${percentage}%)`;
                    }
                }
            }
        }
    }
});
</script>

<?php require_once '../../include/footer.php'; ?>