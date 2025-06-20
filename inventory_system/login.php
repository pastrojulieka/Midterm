<?php
session_start();

// Check if the user is already logged in, and redirect to the appropriate dashboard
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['RoleID'] == 1 || $_SESSION['user']['RoleID'] == 2) {
        // Redirect to the Admin/Staff dashboard
        header("Location: dashboard.php");
    } elseif ($_SESSION['user']['RoleID'] == 3) {
        // Redirect to the Customer dashboard
        header("Location: customer_dashboard.php");
    }
    exit();
}

// Handle direct form submission (without AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Database connection
    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname = "inventory_db";

    // Create connection
    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        // Prepare SQL statement to retrieve user data
        $stmt = $conn->prepare("SELECT u.UserID, u.Username, u.Password, u.RoleID, r.RoleName 
                               FROM users u 
                               JOIN roles r ON u.RoleID = r.RoleID 
                               WHERE u.Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['Password'])) {
                // Password is correct, create session
                $_SESSION['user'] = [
                    'UserID' => $user['UserID'],
                    'Username' => $user['Username'],
                    'RoleID' => $user['RoleID'],
                    'RoleName' => $user['RoleName']
                ];

                // ADD THE LOGIN HISTORY CODE RIGHT HERE
                $login_time = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO user_login_history (user_id, login_time) VALUES (?, ?)");
                $stmt->bind_param("is", $user['UserID'], $login_time);
                $stmt->execute();

                // Store the login history ID in the session
                $_SESSION['login_history_id'] = $conn->insert_id;

                // Redirect based on role
                if ($user['RoleID'] == 1 || $user['RoleID'] == 2) {
                    // Admin or Staff
                    header("Location: dashboard.php");
                } elseif ($user['RoleID'] == 3) {
                    // Customer
                    header("Location: customer_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Username not found";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - My Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            /* Modern Color Palette */
            --primary-500: #6366f1;
            --primary-600: #4f46e5;
            --primary-700: #4338ca;
            --primary-50: #eef2ff;
            --primary-100: #e0e7ff;
            --primary-200: #c7d2fe;

            /* Neutral Colors */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;

            /* Status Colors */
            --success-500: #10b981;
            --success-50: #ecfdf5;
            --success-200: #a7f3d0;
            --warning-500: #f59e0b;
            --warning-50: #fffbeb;
            --danger-500: #ef4444;
            --danger-50: #fef2f2;
            --danger-200: #fecaca;

            /* Spacing */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
            --space-16: 4rem;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;

            /* Typography */
            --font-sans: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--gray-50);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: var(--space-4);
            color: var(--gray-900);
            position: relative;
            line-height: 1.6;
        }

        .login-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            padding: var(--space-12);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
        }

        .login-container:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--space-8);
        }

        .login-logo {
            width: 4rem;
            height: 4rem;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: var(--shadow-md);
            margin: 0 auto var(--space-6);
        }

        .login-header h1 {
            color: var(--gray-900);
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: var(--space-3);
            letter-spacing: -0.025em;
        }

        .login-header p {
            color: var(--gray-500);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .error-message {
            background: var(--danger-50);
            color: var(--danger-500);
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            font-size: 0.875rem;
            font-weight: 600;
            text-align: center;
            border-left: 4px solid var(--danger-500);
            border: 1px solid var(--danger-200);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            justify-content: center;
        }

        .success-message {
            background: var(--success-50);
            color: var(--success-500);
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            font-size: 0.875rem;
            font-weight: 600;
            text-align: center;
            border-left: 4px solid var(--success-500);
            border: 1px solid var(--success-200);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            justify-content: center;
        }

        .form-group {
            margin-bottom: var(--space-6);
            position: relative;
        }

        label {
            display: block;
            margin-bottom: var(--space-3);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: var(--space-4);
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            z-index: 10;
            font-size: 1rem;
            transition: color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input {
            width: 100%;
            padding: var(--space-4) var(--space-4) var(--space-4) 3rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            color: var(--gray-900);
            font-weight: 500;
            line-height: 1.5;
        }

        input:focus {
            border-color: var(--primary-500);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        input:focus+.input-icon {
            color: var(--primary-500);
        }

        input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }

        .password-toggle {
            position: absolute;
            right: var(--space-4);
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            font-size: 1rem;
            padding: var(--space-2);
        }

        .password-toggle:hover {
            color: var(--gray-600);
        }

        button {
            background: var(--primary-500);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: var(--space-4) var(--space-6);
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            justify-content: center;
            align-items: center;
            letter-spacing: 0.025em;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        button:hover {
            background: var(--primary-600);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
        }

        button i {
            margin-right: var(--space-2);
            font-size: 1rem;
        }

        .login-footer {
            text-align: center;
            margin-top: var(--space-8);
            font-size: 0.875rem;
            color: var(--gray-500);
            padding-top: var(--space-6);
            border-top: 1px solid var(--gray-200);
            font-weight: 500;
        }

        /* Registration link styles */
        .register-link {
            text-align: center;
            margin-top: var(--space-6);
        }

        .register-link a {
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            position: relative;
            font-size: 0.875rem;
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius);
        }

        .register-link a::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 1px;
            bottom: 0;
            left: 0;
            background: var(--primary-500);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .register-link a:hover {
            color: var(--primary-600);
            background: var(--primary-50);
        }

        .register-link a:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .register-link i {
            margin-right: var(--space-2);
            font-size: 0.875rem;
        }

        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 3px solid var(--primary-100);
            border-radius: 50%;
            border-top-color: var(--primary-500);
            animation: spin 1s linear infinite;
            margin-bottom: var(--space-4);
        }

        .loading-text {
            color: var(--gray-700);
            font-size: 1rem;
            font-weight: 600;
            margin-top: var(--space-4);
            letter-spacing: 0.025em;
        }

        .loading-progress {
            width: 200px;
            height: 4px;
            background: var(--primary-100);
            border-radius: var(--radius-sm);
            margin-top: var(--space-4);
            overflow: hidden;
            position: relative;
        }

        .loading-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
            border-radius: var(--radius-sm);
            transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: var(--space-4);
            }

            .login-container {
                padding: var(--space-8);
                max-width: 100%;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-logo {
                width: 3rem;
                height: 3rem;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: var(--space-6);
                margin: var(--space-4);
            }

            .login-header {
                margin-bottom: var(--space-6);
            }

            .login-header h1 {
                font-size: 1.375rem;
            }

            .form-group {
                margin-bottom: var(--space-5);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Glass morphism decorative elements -->
        <div class="glass-circle glass-circle-1"></div>
        <div class="glass-circle glass-circle-2"></div>

        <div class="login-header">
            <div class="login-logo">
                <div class="logo-icon"><i class="fas fa-box-open"></i></div>
            </div>
            <h1>Inventory System</h1>
            <p>Sign in to your account</p>
        </div>

        <!-- Display error message if there is any -->
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Display success message if redirected from registration -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $_GET['success']; ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-group">
                    <i class="input-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="input-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="password-toggle fas fa-eye" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" id="loginButton"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>

        <!-- Registration link -->
        <div class="register-link">
            <a href="register.php"><i class="fas fa-user-plus"></i> Don't have an account? Create one</a>
        </div>

        <div class="login-footer">
            <p>Inventory Management System &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Logging in...</div>
        <div class="loading-progress">
            <div class="loading-progress-bar" id="progressBar"></div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this;

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Handle form submission with loading animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Prevent the form from submitting immediately
            e.preventDefault();

            // Store reference to the form
            const form = this;

            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');

            // Start progress bar animation
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = '0%';

            // Animate progress bar to 100% over 2 seconds
            setTimeout(() => {
                progressBar.style.width = '100%';
            }, 100);

            // Wait for 2 seconds before actually submitting the form
            setTimeout(() => {
                form.submit(); // Submit the form after 2 seconds
            }, 2000);
        });
    </script>
</body>

</html>