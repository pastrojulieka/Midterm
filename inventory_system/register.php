<?php
session_start();

// Check if the user is already logged in, and redirect to the appropriate dashboard
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

// Database connection function
function getConnection()
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "inventory_db";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $roleID = $_POST['role'];
    $registrationCode = isset($_POST['registration_code']) ? $_POST['registration_code'] : '';

    // Initialize errors array
    $errors = [];

    // Validate username (at least 4 characters)
    if (strlen($username) < 4) {
        $errors[] = "Username must be at least 4 characters long";
    }

    // Check if username already exists
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $errors[] = "Username already exists";
    }
    $stmt->close();

    // Validate password (at least 8 characters, with at least one uppercase, one lowercase, and one number)
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    // Check if passwords match
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    // Validate registration codes based on role
    // Always check code when role requires it, regardless of whether field is visible in UI
    if ($roleID == 1) { // Admin role
        $adminCode = "ADMIN123"; // Admin registration code
        if ($registrationCode !== $adminCode) {
            $errors[] = "Invalid admin registration code";
        }
    } elseif ($roleID == 2) { // Staff role
        $staffCode = "STAFF456"; // Staff registration code
        if ($registrationCode !== $staffCode) {
            $errors[] = "Invalid staff registration code";
        }
    }
    // No registration code needed for customer/user role (3)

    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into database
        $stmt = $conn->prepare("INSERT INTO users (Username, Password, RoleID) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $username, $hashedPassword, $roleID);

        if ($stmt->execute()) {
            // Registration successful, redirect to login page with success message
            header("Location: login.php?success=Registration successful! You can now log in.");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- CSS styles remain the same - removed for brevity -->

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
            --warning-200: #fed7aa;
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

        .register-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 520px;
            padding: var(--space-12);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
        }

        .register-container:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .register-header {
            text-align: center;
            margin-bottom: var(--space-8);
            position: relative;
        }

        .register-logo {
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

        .register-header h1 {
            color: var(--gray-900);
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: var(--space-3);
            letter-spacing: -0.025em;
            position: relative;
        }

        .register-header h1::after {
            content: '';
            position: absolute;
            height: 2px;
            width: 3rem;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
            left: 50%;
            transform: translateX(-50%);
            bottom: -var(--space-3);
            border-radius: var(--radius-sm);
        }

        .register-header p {
            color: var(--gray-500);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
            margin-top: var(--space-4);
        }

        .error-message,
        .success-message {
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            font-size: 0.875rem;
            font-weight: 600;
            border-left: 4px solid;
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .error-message {
            background: var(--danger-50);
            color: var(--danger-500);
            border-color: var(--danger-500);
            border: 1px solid var(--danger-200);
        }

        .success-message {
            background: var(--success-50);
            color: var(--success-500);
            border-color: var(--success-500);
            border: 1px solid var(--success-200);
        }

        .error-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .error-list li {
            margin-bottom: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 0.875rem;
        }

        .error-list li:before {
            content: "⚠";
            color: var(--danger-500);
            font-weight: 600;
            flex-shrink: 0;
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

        input,
        select {
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

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236366f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right var(--space-4) center;
            background-size: 1rem;
            padding-right: 3rem;
        }

        select option {
            background-color: white;
            color: var(--gray-900);
            padding: var(--space-2);
        }

        input:focus,
        select:focus {
            border-color: var(--primary-500);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        input:focus+.input-icon,
        select:focus+.input-icon {
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

        .password-strength {
            margin-top: var(--space-3);
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .password-strength-meter {
            height: 4px;
            width: 100%;
            background: var(--gray-200);
            border-radius: var(--radius-sm);
            margin-top: var(--space-2);
            position: relative;
            overflow: hidden;
        }

        .password-strength-meter::before {
            content: '';
            position: absolute;
            left: 0;
            height: 100%;
            width: 0%;
            border-radius: var(--radius-sm);
            background: linear-gradient(90deg, var(--danger-500) 0%, var(--warning-500) 50%, var(--success-500) 100%);
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .password-strength-meter.weak::before {
            width: 33.33%;
            background: var(--danger-500);
        }

        .password-strength-meter.medium::before {
            width: 66.66%;
            background: var(--warning-500);
        }

        .password-strength-meter.strong::before {
            width: 100%;
            background: var(--success-500);
        }

        .code-container {
            display: none;
            margin-top: var(--space-4);
            padding: var(--space-5);
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            position: relative;
        }

        .code-container::before {
            content: 'SECURITY VERIFICATION';
            position: absolute;
            top: -var(--space-2);
            left: var(--space-4);
            padding: var(--space-1) var(--space-3);
            background: white;
            color: var(--primary-500);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
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

        .login-link {
            text-align: center;
            margin-top: var(--space-8);
        }

        .login-link a {
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

        .login-link a::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 1px;
            bottom: 0;
            left: 0;
            background: var(--primary-500);
            transform: scaleX(0);
            transform-origin: center;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-link a:hover {
            color: var(--primary-600);
            background: var(--primary-50);
        }

        .login-link a:hover::after {
            transform: scaleX(1);
        }

        .login-link i {
            margin-right: var(--space-2);
            font-size: 0.875rem;
        }

        .register-footer {
            text-align: center;
            margin-top: var(--space-8);
            font-size: 0.875rem;
            color: var(--gray-500);
            padding-top: var(--space-6);
            border-top: 1px solid var(--gray-200);
            font-weight: 500;
        }

        /* Enhanced focus states for better accessibility */
        input:focus-visible,
        select:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--primary-500);
            outline-offset: 2px;
        }

        /* Form validation states */
        .form-group.has-error input,
        .form-group.has-error select {
            border-color: var(--danger-500);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-group.has-success input,
        .form-group.has-success select {
            border-color: var(--success-500);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Loading state for button */
        button:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }

        button:disabled:hover {
            background: var(--gray-300);
            box-shadow: var(--shadow-sm);
            transform: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: var(--space-4);
            }

            .register-container {
                padding: var(--space-8);
                max-width: 100%;
            }

            .register-header h1 {
                font-size: 1.5rem;
            }

            .register-logo {
                width: 3rem;
                height: 3rem;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .register-container {
                padding: var(--space-6);
                margin: var(--space-4);
            }

            .register-header {
                margin-bottom: var(--space-6);
            }

            .register-header h1 {
                font-size: 1.375rem;
            }

            .form-group {
                margin-bottom: var(--space-5);
            }

            input,
            select {
                font-size: 0.875rem;
            }
        }
    </style>

</head>

<body>
    <div class="register-container">
        <!-- Glass morphism decorative elements -->
        <div class="glass-circle glass-circle-1"></div>
        <div class="glass-circle glass-circle-2"></div>

        <div class="register-header">
            <div class="register-logo">
                <i class="fas fa-box-open"></i>
            </div>
            <h1>Create an Account</h1>
            <p>Join the Inventory Management System</p>
        </div>

        <!-- Display error messages if there are any -->
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-group">
                    <i class="input-icon fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="input-icon fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <i class="password-toggle fas fa-eye" id="togglePassword"></i>
                </div>
                <div class="password-strength">
                    <span id="passwordStrengthText">Password strength: Not entered</span>
                    <div class="password-strength-meter" id="passwordStrengthMeter"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-group">
                    <i class="input-icon fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    <i class="password-toggle fas fa-eye" id="toggleConfirmPassword"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="role">Account Type</label>
                <div class="input-group">
                    <i class="input-icon fas fa-user-shield"></i>
                    <select id="role" name="role" required>
                        <option value="" disabled selected>Select account type</option>
                        <option value="3" <?php echo (isset($_POST['role']) && $_POST['role'] == '3') ? 'selected' : ''; ?>>Customer</option>
                        <option value="2" <?php echo (isset($_POST['role']) && $_POST['role'] == '2') ? 'selected' : ''; ?>>Staff</option>
                        <option value="1" <?php echo (isset($_POST['role']) && $_POST['role'] == '1') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>

            <!-- Use a single registration code input with single ID but change its container -->
            <div id="codeContainer" class="code-container" style="display: none;">
                <div class="form-group">
                    <label for="registration_code" id="codeLabel">Registration Code</label>
                    <div class="input-group">
                        <i class="input-icon fas fa-key"></i>
                        <input type="password" id="registration_code" name="registration_code" placeholder="Enter registration code">
                        <i class="password-toggle fas fa-eye" id="toggleRegistrationCode"></i>
                    </div>
                    <small id="codeHelperText" style="color: rgba(255, 255, 255, 0.7); margin-top: 0.5rem; display: block;">
                        <i class="fas fa-info-circle"></i> <span id="codeHelperSpan">Registration requires a special code.</span>
                    </small>
                </div>
            </div>

            <button type="submit" id="registerButton"><i class="fas fa-user-plus"></i> Create Account</button>
        </form>

        <div class="login-link">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Already have an account? Login</a>
        </div>

        <div class="register-footer">
            <p>Inventory Management System &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            togglePasswordVisibility('password', this);
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            togglePasswordVisibility('confirm_password', this);
        });

        document.getElementById('toggleRegistrationCode').addEventListener('click', function() {
            togglePasswordVisibility('registration_code', this);
        });

        function togglePasswordVisibility(inputId, icon) {
            const passwordInput = document.getElementById(inputId);

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Show/hide registration code field based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const codeContainer = document.getElementById('codeContainer');
            const codeInput = document.getElementById('registration_code');
            const codeLabel = document.getElementById('codeLabel');
            const codeHelperSpan = document.getElementById('codeHelperSpan');

            // Clear any previous input value when changing roles
            codeInput.value = '';

            // Hide code container by default
            codeContainer.style.display = 'none';

            // Remove required attribute 
            codeInput.removeAttribute('required');

            if (this.value === '1') { // Admin role
                codeLabel.textContent = 'Admin Registration Code';
                codeHelperSpan.textContent = 'Admin registration requires a special code. Please contact your system administrator if you don\'t have this code.';
                codeInput.placeholder = 'Enter admin registration code';
                codeContainer.style.display = 'block';
                codeInput.setAttribute('required', 'required');
            } else if (this.value === '2') { // Staff role
                codeLabel.textContent = 'Staff Registration Code';
                codeHelperSpan.textContent = 'Staff registration requires a special code. Please contact your manager if you don\'t have this code.';
                codeInput.placeholder = 'Enter staff registration code';
                codeContainer.style.display = 'block';
                codeInput.setAttribute('required', 'required');
            }
            // Customer/User role (3) doesn't need any code
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('passwordStrengthMeter');
            const strengthText = document.getElementById('passwordStrengthText');

            // Remove previous classes
            meter.className = 'password-strength-meter';

            if (password.length === 0) {
                strengthText.textContent = 'Password strength: Not entered';
                return;
            }

            let strength = 0;

            // Criteria for strength
            if (password.length >= 8) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^A-Za-z0-9]/)) strength += 1;

            // Update meter and text based on strength
            if (strength <= 2) {
                meter.classList.add('weak');
                strengthText.textContent = 'Password strength: Weak';
            } else if (strength <= 4) {
                meter.classList.add('medium');
                strengthText.textContent = 'Password strength: Medium';
            } else {
                meter.classList.add('strong');
                strengthText.textContent = 'Password strength: Strong';
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword === '') {
                this.setCustomValidity('');
            } else if (confirmPassword !== password) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Set initial state based on selected role (in case of form resubmission)
        window.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            if (roleSelect.value) {
                // Trigger the change event to set up the form correctly
                const event = new Event('change');
                roleSelect.dispatchEvent(event);
            }
        });
    </script>
</body>

</html>