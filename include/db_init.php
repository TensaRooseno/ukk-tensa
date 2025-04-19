<?php
// Include database configuration
require_once 'config.php';

// SQL to create roles table
$roles_table = "CREATE TABLE IF NOT EXISTS roles (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
)";

// SQL to create users table
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role_id INT(11) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
)";

// SQL to create products table
$products_table = "CREATE TABLE IF NOT EXISTS products (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// SQL to create transactions table
$transactions_table = "CREATE TABLE IF NOT EXISTS transactions (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT(11) UNSIGNED NOT NULL,
    customer_name VARCHAR(100),
    total DECIMAL(10,2) NOT NULL,
    paid DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

// SQL to create transaction_details table
$transaction_details_table = "CREATE TABLE IF NOT EXISTS transaction_details (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT(11) UNSIGNED NOT NULL,
    product_id INT(11) UNSIGNED NOT NULL,
    quantity INT(11) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)";

// Create tables
mysqli_query($conn, $roles_table);
mysqli_query($conn, $users_table);
mysqli_query($conn, $products_table);
mysqli_query($conn, $transactions_table);
mysqli_query($conn, $transaction_details_table);

// Insert roles
$roles_exist = mysqli_query($conn, "SELECT * FROM roles LIMIT 1");
if (mysqli_num_rows($roles_exist) == 0) {
    mysqli_query($conn, "INSERT INTO roles (id, name) VALUES (1, 'admin')");
    mysqli_query($conn, "INSERT INTO roles (id, name) VALUES (2, 'cashier')");
}

// Insert default admin and cashier users
$admin_exists = mysqli_query($conn, "SELECT * FROM users WHERE username = 'admin'");
if (mysqli_num_rows($admin_exists) == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password, name, role_id) 
                         VALUES ('admin', '$admin_password', 'Administrator', 1)");
}

$cashier_exists = mysqli_query($conn, "SELECT * FROM users WHERE username = 'cashier'");
if (mysqli_num_rows($cashier_exists) == 0) {
    $cashier_password = password_hash('cashier123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password, name, role_id) 
                         VALUES ('cashier', '$cashier_password', 'Cashier User', 2)");
}

// Insert sample products if none exist
$products_exist = mysqli_query($conn, "SELECT * FROM products LIMIT 1");
if (mysqli_num_rows($products_exist) == 0) {
    $sample_products = [
        ['Coca Cola', 1.50, 20],
        ['Sprite', 1.50, 20],
        ['Water Bottle', 1.00, 30],
        ['Sandwich', 3.50, 10],
        ['Chips', 0.99, 25],
        ['Chocolate Bar', 1.25, 15]
    ];
    
    foreach ($sample_products as $product) {
        mysqli_query($conn, "INSERT INTO products (name, price, stock) 
                             VALUES ('$product[0]', $product[1], $product[2])");
    }
}

echo "Database initialized successfully!";
?>

<p>
    <a href="../index.php" class="btn btn-primary">Go to Application</a>
</p>