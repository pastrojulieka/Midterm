    <?php
    require_once "includes/auth.php";
    require_once "includes/db.php"; // Added database connection
    $user = $_SESSION['user'];
    $productsStock = $pdo->query("
    SELECT 
        p.Name, 
        p.ProductID, 
        p.Category, 
        p.Price,
        COALESCE(SUM(s.QuantityAdded), 0) - COALESCE((SELECT SUM(QuantitySold) FROM sales WHERE ProductID = p.ProductID), 0) AS CurrentStock
    FROM 
        products p
    LEFT JOIN 
        stock s ON s.ProductID = p.ProductID
    GROUP BY 
        p.ProductID, p.Name, p.Category, p.Price
    ORDER BY 
        CurrentStock ASC
    LIMIT 6
    ")->fetchAll();
    // Fetch system settings
    $settingsStmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    // Use the settings values or fallback to defaults if not found
    $criticalStockThreshold = isset($settings['critical_stock_threshold']) ? intval($settings['critical_stock_threshold']) : 3;
    $lowStockThreshold = isset($settings['low_stock_threshold']) ? intval($settings['low_stock_threshold']) : 5;

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard - Inventory System</title>
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
                --warning-500: #f59e0b;
                --warning-50: #fffbeb;
                --danger-500: #ef4444;
                --danger-50: #fef2f2;

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
                color: var(--gray-900);
                line-height: 1.6;
                display: flex;
                min-height: 100vh;
            }

            /* Sidebar Styles */
            .sidebar {
                width: 280px;
                background: white;
                border-right: 1px solid var(--gray-200);
                display: flex;
                flex-direction: column;
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 40;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: var(--shadow-sm);
            }

            .sidebar-logo {
                padding: var(--space-6);
                display: flex;
                align-items: center;
                border-bottom: 1px solid var(--gray-200);
                background: linear-gradient(135deg, var(--primary-50) 0%, white 100%);
            }

            .logo-icon {
                width: 2.5rem;
                height: 2.5rem;
                background: var(--primary-500);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: var(--radius-md);
                font-size: 1.25rem;
                font-weight: 700;
                margin-right: var(--space-3);
                box-shadow: var(--shadow);
            }

            .logo-text {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--gray-900);
            }

            .nav-items {
                padding: var(--space-6);
                flex-grow: 1;
                overflow-y: auto;
            }

            .nav-section {
                margin-bottom: var(--space-8);
            }

            .nav-section-title {
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                color: var(--gray-500);
                margin-bottom: var(--space-3);
                letter-spacing: 0.05em;
            }

            .nav-item {
                display: flex;
                align-items: center;
                padding: var(--space-3) var(--space-4);
                margin-bottom: var(--space-1);
                text-decoration: none;
                color: var(--gray-700);
                border-radius: var(--radius);
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                font-weight: 500;
            }

            .nav-item:hover {
                background: var(--gray-100);
                color: var(--gray-900);
                transform: translateX(2px);
            }

            .nav-item.active {
                background: var(--primary-50);
                color: var(--primary-700);
                border-left: 3px solid var(--primary-500);
                margin-left: -3px;
                padding-left: calc(var(--space-4) + 3px);
            }

            .nav-icon {
                margin-right: var(--space-3);
                font-size: 1.125rem;
                width: 1.25rem;
                text-align: center;
            }

            .sidebar-footer {
                padding: var(--space-6);
                border-top: 1px solid var(--gray-200);
            }

            .logout-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: var(--space-3) var(--space-4);
                background: var(--danger-50);
                color: var(--danger-500);
                border: 1px solid var(--danger-200);
                border-radius: var(--radius);
                cursor: pointer;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                width: 100%;
            }

            .logout-btn:hover {
                background: var(--danger-500);
                color: white;
                box-shadow: var(--shadow);
            }

            .logout-icon {
                margin-right: var(--space-2);
            }

            /* Main Content Styles */
            .main-content {
                flex-grow: 1;
                margin-left: 280px;
                display: flex;
                flex-direction: column;
            }

            .topbar {
                padding: var(--space-4) var(--space-8);
                background: white;
                border-bottom: 1px solid var(--gray-200);
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 30;
                backdrop-filter: blur(8px);
                background: rgba(255, 255, 255, 0.95);
            }

            .topbar-left {
                display: flex;
                align-items: center;
            }

            .menu-toggle {
                display: none;
                background: none;
                border: none;
                color: var(--gray-600);
                font-size: 1.25rem;
                cursor: pointer;
                padding: var(--space-2);
                border-radius: var(--radius);
                margin-right: var(--space-4);
            }

            .menu-toggle:hover {
                background: var(--gray-100);
                color: var(--gray-900);
            }

            .page-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--gray-900);
            }

            .topbar-right {
                display: flex;
                align-items: center;
                gap: var(--space-4);
            }

            .notification-bell {
                position: relative;
                color: var(--gray-500);
                font-size: 1.25rem;
                cursor: pointer;
                padding: var(--space-2);
                border-radius: var(--radius);
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .notification-bell:hover {
                color: var(--primary-600);
                background: var(--primary-50);
            }

            .notification-indicator {
                position: absolute;
                top: 0;
                right: 0;
                background: var(--danger-500);
                width: 1rem;
                height: 1rem;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.6rem;
                font-weight: 700;
                border: 2px solid white;
            }

            .user-profile {
                display: flex;
                align-items: center;
                cursor: pointer;
                padding: var(--space-2);
                border-radius: var(--radius-md);
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid transparent;
            }

            .user-profile:hover {
                background: var(--gray-50);
                border-color: var(--gray-200);
            }

            .avatar {
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 1rem;
                margin-right: var(--space-3);
                box-shadow: var(--shadow-sm);
            }

            .user-info {
                display: flex;
                flex-direction: column;
            }

            .user-name {
                font-weight: 600;
                color: var(--gray-900);
                font-size: 0.875rem;
            }

            .user-role {
                font-size: 0.75rem;
                color: var(--gray-500);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 500;
            }

            .dropdown-icon {
                margin-left: var(--space-3);
                color: var(--gray-400);
                transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                font-size: 0.75rem;
            }

            .user-profile:hover .dropdown-icon {
                transform: rotate(180deg);
                color: var(--primary-500);
            }

            /* Dashboard Content Styles */
            .content {
                padding: var(--space-8);
                flex-grow: 1;
                background: var(--gray-50);
            }

            .welcome-section {
                margin-bottom: var(--space-8);
                padding: var(--space-8);
                background: white;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-sm);
                border: 1px solid var(--gray-200);
                position: relative;
                overflow: hidden;
            }

            .welcome-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
            }

            .welcome-title {
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: var(--space-2);
                color: var(--gray-900);
                background: linear-gradient(135deg, var(--gray-900), var(--gray-700));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .welcome-subtitle {
                color: var(--gray-600);
                font-size: 1.125rem;
                margin-bottom: var(--space-6);
            }

            /* Stock Overview Section */
            .stock-overview {
                margin-top: var(--space-8);
                padding: var(--space-8);
                background: white;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow);
                border: 1px solid var(--gray-200);
            }

            .stock-overview-title {
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: var(--space-6);
                color: var(--gray-900);
                display: flex;
                align-items: center;
            }

            .stock-overview-title i {
                margin-right: var(--space-3);
                color: var(--primary-500);
                font-size: 1.5rem;
            }

            .stock-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: var(--space-6);
            }

            .stock-item {
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
                display: flex;
                overflow: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid var(--gray-200);
                position: relative;
            }

            .stock-item:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-lg);
            }

            .stock-item.critical {
                border-left: 4px solid var(--danger-500);
                background: linear-gradient(135deg, var(--danger-50) 0%, white 100%);
            }

            .stock-item.warning {
                border-left: 4px solid var(--warning-500);
                background: linear-gradient(135deg, var(--warning-50) 0%, white 100%);
            }

            .stock-item.normal {
                border-left: 4px solid var(--success-500);
                background: linear-gradient(135deg, var(--success-50) 0%, white 100%);
            }

            .stock-item.featured {
                grid-column: span 2;
                transform: scale(1.02);
                z-index: 10;
                box-shadow: var(--shadow-lg);
            }

            .stock-item.featured:hover {
                transform: translateY(-4px) scale(1.02);
            }

            .stock-level-indicator {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: var(--space-6);
                color: white;
                font-weight: 600;
                min-width: 80px;
                position: relative;
            }

            .stock-level-indicator i {
                font-size: 1.5rem;
                margin-bottom: var(--space-2);
            }

            .stock-level-indicator span {
                font-size: 1.25rem;
                font-weight: 700;
            }

            .stock-details {
                padding: var(--space-6);
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .stock-name {
                font-weight: 600;
                color: var(--gray-900);
                font-size: 1.125rem;
                margin: 0;
            }

            .stock-info {
                display: flex;
                justify-content: space-between;
                margin: var(--space-4) 0;
                font-size: 0.875rem;
                color: var(--gray-600);
            }

            .stock-actions {
                display: flex;
                gap: var(--space-3);
            }

            .btn-add-stock,
            .btn-view-product {
                padding: var(--space-2) var(--space-4);
                border-radius: var(--radius);
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid transparent;
            }

            .btn-add-stock {
                background: var(--primary-500);
                color: white;
                flex-grow: 1;
                text-align: center;
            }

            .btn-add-stock:hover {
                background: var(--primary-600);
                box-shadow: var(--shadow);
            }

            .btn-view-product {
                background: var(--gray-100);
                color: var(--gray-700);
                border-color: var(--gray-200);
            }

            .btn-view-product:hover {
                background: var(--gray-200);
                color: var(--gray-900);
            }

            .stock-overview-footer {
                margin-top: var(--space-8);
                text-align: center;
            }

            .view-all-btn {
                display: inline-flex;
                align-items: center;
                padding: var(--space-3) var(--space-6);
                background: var(--primary-50);
                color: var(--primary-600);
                border: 2px solid var(--primary-200);
                border-radius: var(--radius-lg);
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                gap: var(--space-2);
            }

            .view-all-btn:hover {
                background: var(--primary-500);
                color: white;
                border-color: var(--primary-500);
                box-shadow: var(--shadow);
            }

            .no-products-alert {
                margin-top: var(--space-6);
                padding: var(--space-6);
                background: var(--gray-50);
                border-radius: var(--radius-lg);
                display: flex;
                align-items: center;
                color: var(--gray-600);
                border: 1px solid var(--gray-200);
            }

            .no-products-alert i {
                font-size: 1.5rem;
                margin-right: var(--space-4);
                color: var(--primary-500);
            }

            .no-products-alert a {
                color: var(--primary-600);
                font-weight: 600;
                text-decoration: none;
            }

            .no-products-alert a:hover {
                text-decoration: underline;
            }

            /* Dashboard Cards */
            .dashboard-cards {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: var(--space-6);
                margin-bottom: var(--space-8);
            }

            .card {
                background: white;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-sm);
                padding: var(--space-8);
                display: flex;
                flex-direction: column;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid var(--gray-200);
                position: relative;
                overflow: hidden;
            }

            .card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 2px;
                background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
                transform: scaleX(0);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .card:hover {
                transform: translateY(-8px);
                box-shadow: var(--shadow-xl);
            }

            .card:hover::before {
                transform: scaleX(1);
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: var(--space-6);
            }

            .card-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--gray-900);
            }

            .card-icon {
                width: 3rem;
                height: 3rem;
                border-radius: var(--radius-lg);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                box-shadow: var(--shadow);
            }

            .card-icon-products {
                background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
                color: white;
            }

            .card-icon-suppliers {
                background: linear-gradient(135deg, var(--success-500), #059669);
                color: white;
            }

            .card-icon-stock {
                background: linear-gradient(135deg, var(--warning-500), #d97706);
                color: white;
            }

            .card-icon-sales {
                background: linear-gradient(135deg, var(--danger-500), #dc2626);
                color: white;
            }

            .card-content {
                margin-top: auto;
            }

            .card-description {
                color: var(--gray-600);
                margin-bottom: var(--space-6);
                line-height: 1.6;
            }

            .card-action {
                display: inline-flex;
                align-items: center;
                padding: var(--space-3) var(--space-5);
                background: var(--primary-50);
                color: var(--primary-600);
                text-decoration: none;
                border-radius: var(--radius-lg);
                font-weight: 600;
                font-size: 0.875rem;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                border: 2px solid var(--primary-200);
            }

            .card-action:hover {
                background: var(--primary-500);
                color: white;
                border-color: var(--primary-500);
                box-shadow: var(--shadow);
            }

            /* Loading overlay styles */
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 23, 42, 0.9);
                backdrop-filter: blur(8px);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                z-index: 1000;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                    visibility 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .loading-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            .loading-spinner {
                width: 4rem;
                height: 4rem;
                border: 3px solid var(--primary-100);
                border-radius: 50%;
                border-top-color: var(--primary-500);
                animation: spin 1s ease-in-out infinite;
                margin-bottom: var(--space-4);
            }

            .loading-text {
                color: white;
                font-size: 1.125rem;
                font-weight: 600;
                margin-top: var(--space-4);
            }

            .loading-progress {
                width: 12rem;
                height: 3px;
                background: var(--primary-100);
                border-radius: var(--radius);
                margin-top: var(--space-4);
                overflow: hidden;
            }

            .loading-progress-bar {
                height: 100%;
                width: 0%;
                background: var(--primary-500);
                border-radius: var(--radius);
                transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Media Queries for Responsive Design */
            @media (max-width: 1024px) {
                .dashboard-cards {
                    grid-template-columns: repeat(2, 1fr);
                }

                .stock-item.featured {
                    grid-column: span 1;
                }
            }

            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    box-shadow: var(--shadow-xl);
                }

                .sidebar.open {
                    transform: translateX(0);
                }

                .main-content {
                    margin-left: 0;
                }

                .menu-toggle {
                    display: flex;
                }

                .topbar {
                    padding: var(--space-4);
                }

                .content {
                    padding: var(--space-4);
                }

                .dashboard-cards,
                .stock-grid {
                    grid-template-columns: 1fr;
                }

                .welcome-section,
                .stock-overview,
                .card {
                    padding: var(--space-6);
                }

                .welcome-title {
                    font-size: 1.5rem;
                }
            }

            @media (max-width: 480px) {
                .user-info {
                    display: none;
                }

                .topbar-right {
                    gap: var(--space-2);
                }

                .card-header {
                    flex-direction: column;
                    gap: var(--space-3);
                }

                .card-icon {
                    align-self: flex-start;
                }
            }
        </style>
    </head>

    <body>
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon"><i class="fas fa-box-open"></i></div>
                <div class="logo-text">Inventory Pro</div>
            </div>

            <nav class="nav-items">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item active">
                        <i class="nav-icon fas fa-home"></i> Dashboard
                    </a>
                </div>

                <?php if ($user['RoleName'] == 'Admin'): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Inventory</div>
                        <a href="products/index.php" class="nav-item">
                            <i class="nav-icon fas fa-box"></i> Products
                        </a>
                        <a href="suppliers/index.php" class="nav-item">
                            <i class="nav-icon fas fa-truck"></i> Suppliers
                        </a>
                        <a href="stock/index.php" class="nav-item">
                            <i class="nav-icon fas fa-warehouse"></i> Stock Management
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Sales & Reports</div>
                        <a href="sales/index.php" class="nav-item">
                            <i class="nav-icon fas fa-shopping-cart"></i> Sales
                        </a>
                        <a href="reports/dashboard.php" class="nav-item">
                            <i class="nav-icon fas fa-chart-line"></i> Reports & Analytics
                        </a>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">Administration</div>
                        <a href="user_management.php" class="nav-item">
                            <i class="nav-icon fas fa-users-cog"></i> User Management
                        </a>
                        <a href="settings.php" class="nav-item">
                            <i class="nav-icon fas fa-cog"></i> Settings
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($user['RoleName'] == 'Staff'): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Operations</div>
                        <a href="sales/index.php" class="nav-item">
                            <i class="nav-icon fas fa-shopping-cart"></i> Sales
                        </a>
                        <a href="stock/index.php" class="nav-item">
                            <i class="nav-icon fas fa-warehouse"></i> Stock Management
                        </a>
                        <a href="reports/dashboard.php" class="nav-item">
                            <i class="nav-icon fas fa-chart-line"></i> Reports & Analytics
                        </a>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <a href="#" class="logout-btn" id="logoutButton">
                    <i class="logout-icon fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Loading overlay that will appear when logging out -->
            <div class="loading-overlay" id="loadingOverlay">
                <div class="glass-circle glass-circle-1"></div>
                <div class="glass-circle glass-circle-2"></div>
                <div class="loading-spinner"></div>
                <div class="loading-text">Logging out...</div>
                <div class="loading-progress">
                    <div class="loading-progress-bar" id="progressBar"></div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation Bar -->
            <div class="topbar">
                <div class="topbar-left">
                    <button id="menuToggle" class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Dashboard</h1>
                </div>

                <div class="topbar-right">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-indicator">3</span>
                    </div>

                    <div class="user-profile">
                        <div class="avatar">
                            <?php echo strtoupper(substr($user['Username'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($user['Username']) ?></span>
                            <span class="user-role"><?= $user['RoleName'] ?></span>
                        </div>
                        <i class="dropdown-icon fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <div class="content">
                <section class="welcome-section">
                    <h2 class="welcome-title">Welcome back, <?= htmlspecialchars($user['Username']) ?>!</h2>
                    <p class="welcome-subtitle">Here's what's happening with your inventory today.</p>

                    <!-- Product Stock Section - displays all products regardless of stock level -->
                    <?php if (!empty($productsStock)): ?>
                        <div class="stock-overview">
                            <h3 class="stock-overview-title">
                                <i class="fas fa-boxes"></i> Current Inventory Stocks Status
                                <span style="font-size: 0.8rem; margin-left: 10px; color: var(--text-light);">
                                    (Critical: ≤<?= $criticalStockThreshold ?>, Low: ≤<?= $lowStockThreshold ?>)
                                </span>
                            </h3>
                            <div class="stock-grid">
                                <?php foreach ($productsStock as $index => $product):
                                    $stockLevel = intval($product['CurrentStock']);

                                    // Determine the stock status using system settings
                                    if ($stockLevel <= $criticalStockThreshold) {
                                        $stockStatus = 'critical';
                                        $stockIcon = 'fa-exclamation-circle';
                                        $stockColor = '#ef4444';
                                    } elseif ($stockLevel <= $lowStockThreshold) {
                                        $stockStatus = 'warning';
                                        $stockIcon = 'fa-exclamation-triangle';
                                        $stockColor = '#f59e0b';
                                    } else {
                                        $stockStatus = 'normal';
                                        $stockIcon = 'fa-check-circle';
                                        $stockColor = '#10b981';
                                    }

                                    // Make the first item larger
                                    $isFirst = ($index === 0);
                                ?>
                                    <div class="stock-item <?= $stockStatus ?> <?= $isFirst ? 'featured' : '' ?>">
                                        <div class="stock-level-indicator" style="background-color: <?= $stockColor ?>;">
                                            <i class="fas <?= $stockIcon ?>"></i>
                                            <span><?= $stockLevel ?></span>
                                        </div>

                                        <div class="stock-details">
                                            <h4 class="stock-name"><?= htmlspecialchars($product['Name']) ?></h4>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="stock-overview-footer">
                                <a href="products/index.php" class="view-all-btn">
                                    <i class="fas fa-list"></i> View All Products
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-products-alert">
                            <i class="fas fa-info-circle"></i>
                            <p>No products found in the inventory system. <a href="products/add.php">Add your first product</a>.</p>
                        </div>
                    <?php endif; ?>
                </section>


                <div class="dashboard-cards">
                    <?php if ($user['RoleName'] == 'Admin'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Products</h3>
                                <div class="card-icon card-icon-products">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description">Manage your product catalog, add new items, update prices and more.</p>
                                <a href="products/index.php" class="card-action">Manage Products</a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Suppliers</h3>
                                <div class="card-icon card-icon-suppliers">
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description">View and manage your suppliers, track deliveries and orders.</p>
                                <a href="suppliers/index.php" class="card-action">Manage Suppliers</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Stock Management</h3>
                            <div class="card-icon card-icon-stock">
                                <i class="fas fa-warehouse"></i>
                            </div>
                        </div>
                        <div class="card-content">
                            <p class="card-description">Monitor inventory levels, process stock adjustments and transfers.</p>
                            <a href="stock/index.php" class="card-action">Manage Stock</a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Sales</h3>
                            <div class="card-icon card-icon-sales">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <div class="card-content">
                            <p class="card-description">Process sales transactions, view sales history and customer data.</p>
                            <a href="sales/index.php" class="card-action">View Sales</a>
                        </div>
                    </div>

                    <?php if ($user['RoleName'] == 'Admin'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Reports & Analytics</h3>
                                <div class="card-icon card-icon-products">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description">Generate detailed reports on sales, inventory, and overall performance.</p>
                                <a href="reports/dashboard.php" class="card-action">View Reports</a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">User Management</h3>
                                <div class="card-icon card-icon-suppliers">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description">Manage system users, set permissions and user roles.</p>
                                <a href="user_management.php" class="card-action">Manage Users</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['RoleName'] == 'Staff'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Reports</h3>
                                <div class="card-icon card-icon-products">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description">View detailed reports on sales and inventory status.</p>
                                <a href="reports/dashboard.php" class="card-action">View Reports</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Toggle Sidebar on Mobile
                document.getElementById('menuToggle').addEventListener('click', function() {
                    document.getElementById('sidebar').classList.toggle('open');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const sidebar = document.getElementById('sidebar');
                    const menuToggle = document.getElementById('menuToggle');

                    if (window.innerWidth <= 768) {
                        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
                            sidebar.classList.remove('open');
                        }
                    }
                });

                // Handle window resize to fix sidebar state on screen size change
                window.addEventListener('resize', function() {
                    const sidebar = document.getElementById('sidebar');

                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('open');
                    }
                });

                // Script for logout button with loading animation
                document.getElementById('logoutButton').addEventListener('click', function(e) {
                    e.preventDefault();

                    // Show loading overlay
                    const loadingOverlay = document.getElementById('loadingOverlay');
                    loadingOverlay.classList.add('active');

                    // Animate progress bar
                    const progressBar = document.getElementById('progressBar');
                    progressBar.style.width = '100%';

                    // Wait for 2 seconds and then redirect
                    setTimeout(function() {
                        window.location.href = 'logout.php';
                    }, 2000);
                });
            </script>
    </body>

    </html>