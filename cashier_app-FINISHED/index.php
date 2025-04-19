<?php
// Initialize session for login tracking
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role_id'] == 1 ? "admin_dashboard.php" : "cashier_dashboard.php"));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - Cashier App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS and Font Awesome -->
    <link href="/ukk/cashier_app/assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header i {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .login-header h2 {
            color: #343a40;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #6c757d;
            margin-bottom: 0;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-login {
            background-color: #28a745;
            border-color: #28a745;
            width: 100%;
            padding: 0.6rem;
            font-size: 1rem;
        }
        .btn-login:hover, .btn-login:focus {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-floating label {
            color: #6c757d;
        }
        .alert {
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        .alert i {
            margin-right: 0.5rem;
        }
        .invalid-feedback {
            font-size: 80%;
            color: #dc3545;
            margin-top: 0.25rem;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .shake {
            animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-cash-register"></i>
            <h2>Cashier App</h2>
            <p>Please login to continue</p>
        </div>

        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($_SESSION['login_error']); ?></div>
            </div>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <form method="POST" action="authenticate.php" id="loginForm" novalidate>
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="Username" required>
                <label for="username">Username</label>
                <div class="invalid-feedback">
                    Please enter your username
                </div>
            </div>
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Password" required>
                <label for="password">Password</label>
                <div class="invalid-feedback">
                    Please enter your password
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </form>
    </div>

    <!-- Include Bootstrap JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="/ukk/cashier_app/assets/bootstrap.min.js"></script>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            let form = this;
            let isValid = true;
            
            // Remove previous validation classes
            form.querySelectorAll('.is-invalid').forEach(input => {
                input.classList.remove('is-invalid');
            });

            // Validate username
            let username = form.querySelector('#username');
            if (!username.value) {
                username.classList.add('is-invalid');
                isValid = false;
            }

            // Validate password
            let password = form.querySelector('#password');
            if (!password.value) {
                password.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                // Add shake animation to form
                form.closest('.login-container').classList.add('shake');
                setTimeout(() => {
                    form.closest('.login-container').classList.remove('shake');
                }, 820);
            }
        });

        // Clear validation on input
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    </script>
</body>
</html>