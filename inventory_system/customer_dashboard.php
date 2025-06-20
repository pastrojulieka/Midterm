<?php
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 1);
session_start();
require_once "includes/db.php";

// Check if user is logged in and is a customer
if (!isset($_SESSION['user']) || $_SESSION['user']['RoleID'] != 3) {
    header("Location: login.php");
    exit();
}

// Get user information
$user = $_SESSION['user'];
$username = $user['Username'];
$userID = $user['UserID'];

// Handle product search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Get all categories for filter dropdown
$categoryQuery = "SELECT DISTINCT Category FROM products ORDER BY Category";
$categoryStmt = $pdo->query($categoryQuery);
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Build product query with filters
$productQuery = "SELECT p.ProductID, p.Name, p.Category, p.Price, 
                COALESCE(SUM(s.QuantityAdded), 0) - COALESCE((SELECT SUM(QuantitySold) FROM sales WHERE ProductID = p.ProductID), 0) as CurrentStock
                FROM products p
                LEFT JOIN stock s ON p.ProductID = s.ProductID";

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "p.Name LIKE :search";
    $params['search'] = "%$search%";
}

if (!empty($category)) {
    $whereConditions[] = "p.Category = :category";
    $params['category'] = $category;
}

if (!empty($whereConditions)) {
    $productQuery .= " WHERE " . implode(" AND ", $whereConditions);
}

$productQuery .= " GROUP BY p.ProductID HAVING CurrentStock > 0 ORDER BY p.Name";

$productStmt = $pdo->prepare($productQuery);
$productStmt->execute($params);
$products = $productStmt->fetchAll();

// Handle product purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
    $productID = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Check if product exists and has enough stock
    $stockCheckQuery = "SELECT p.ProductID, p.Name, p.Price, 
                        COALESCE(SUM(s.QuantityAdded), 0) - COALESCE((SELECT SUM(QuantitySold) FROM sales WHERE ProductID = p.ProductID), 0) as CurrentStock
                        FROM products p
                        LEFT JOIN stock s ON p.ProductID = s.ProductID
                        WHERE p.ProductID = :productID
                        GROUP BY p.ProductID";

    $stockStmt = $pdo->prepare($stockCheckQuery);
    $stockStmt->execute(['productID' => $productID]);
    $product = $stockStmt->fetch();

    if ($product && $product['CurrentStock'] >= $quantity && $quantity > 0) {
        // Calculate total amount
        $totalAmount = $product['Price'] * $quantity;

        // Insert sale record
        $saleQuery = "INSERT INTO sales (ProductID, QuantitySold, SaleDate, TotalAmount) 
                      VALUES (:productID, :quantity, NOW(), :totalAmount)";

        $saleStmt = $pdo->prepare($saleQuery);
        $saleResult = $saleStmt->execute([
            'productID' => $productID,
            'quantity' => $quantity,
            'totalAmount' => $totalAmount
        ]);

        if ($saleResult) {
            $purchaseSuccess = "Successfully purchased {$quantity} {$product['Name']} for $" . number_format($totalAmount, 2);
        } else {
            $purchaseError = "Error processing your purchase. Please try again.";
        }
    } else {
        $purchaseError = "Invalid product or insufficient stock.";
    }

    // Refresh product list after purchase
    $productStmt->execute($params);
    $products = $productStmt->fetchAll();
}

// Get purchase history for this user
$historyQuery = "SELECT s.SaleID, p.Name as ProductName, s.QuantitySold, s.SaleDate, s.TotalAmount  
                FROM sales s
                JOIN products p ON s.ProductID = p.ProductID
                ORDER BY s.SaleDate DESC
                LIMIT 5";

$historyStmt = $pdo->query($historyQuery);
$recentSales = $historyStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Inventory System</title>
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
            min-height: 100vh;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-8);
        }

        /* Header Styles */
        header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-8);
            position: sticky;
            top: 0;
            z-index: 30;
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.95);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-4) var(--space-8);
            max-width: 1200px;
            margin: 0 auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
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
            box-shadow: var(--shadow);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .user-details {
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

        .header-buttons {
            display: flex;
            gap: var(--space-4);
        }

        .btn-header {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            background: var(--danger-50);
            color: var(--danger-500);
            text-decoration: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--danger-200);
        }

        .btn-header:hover {
            background: var(--danger-500);
            color: white;
            box-shadow: var(--shadow);
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-8);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            padding: var(--space-6) var(--space-8);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .card-title i {
            color: var(--primary-500);
            font-size: 1.5rem;
        }

        .card-body {
            padding: var(--space-8);
        }

        /* Filter Container */
        .filter-container {
            display: flex;
            gap: var(--space-4);
            margin-bottom: var(--space-8);
            flex-wrap: wrap;
            align-items: center;
        }

        .search-container {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: var(--space-3) var(--space-4) var(--space-3) 2.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.875rem;
            background: white;
            color: var(--gray-900);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-icon {
            position: absolute;
            left: var(--space-3);
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.875rem;
        }

        .filter-select {
            padding: var(--space-3) 2.5rem var(--space-3) var(--space-4);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.875rem;
            min-width: 200px;
            background: white;
            color: var(--gray-900);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236366f1' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - var(--space-4)) center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .filter-button {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            background: var(--primary-500);
            color: white;
            border: none;
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .filter-button:hover {
            background: var(--primary-600);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--space-6);
        }

        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-200);
        }

        .product-image {
            height: 160px;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary-500);
            border-bottom: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .product-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 0%, rgba(99, 102, 241, 0.05) 50%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image::before {
            opacity: 1;
        }

        .product-details {
            padding: var(--space-6);
        }

        .product-name {
            font-weight: 700;
            margin-bottom: var(--space-2);
            color: var(--gray-900);
            font-size: 1.125rem;
            line-height: 1.4;
        }

        .product-category {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-weight: 500;
        }

        .product-category i {
            color: var(--primary-500);
            font-size: 0.75rem;
        }

        .product-price {
            font-weight: 700;
            color: var(--primary-600);
            font-size: 1.5rem;
            margin-bottom: var(--space-3);
            display: flex;
            align-items: baseline;
            gap: var(--space-1);
        }

        .product-stock {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-6);
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .stock-badge {
            background: var(--success-50);
            color: var(--success-500);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.75rem;
            border: 1px solid var(--success-200);
            text-align: center;
        }

        .product-actions {
            margin-top: var(--space-6);
        }

        .purchase-form {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .quantity-input {
            padding: var(--space-3);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            background: white;
            color: var(--gray-900);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .purchase-button {
            background: var(--primary-500);
            color: white;
            border: none;
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
        }

        .purchase-button:hover {
            background: var(--primary-600);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        .purchase-button:active {
            transform: translateY(0);
        }

        /* Alert Styles */
        .alert {
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.05;
            background: currentColor;
        }

        .alert-success {
            background: var(--success-50);
            border-color: var(--success-500);
            color: var(--success-500);
        }

        .alert-error {
            background: var(--danger-50);
            border-color: var(--danger-500);
            color: var(--danger-500);
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        th,
        td {
            padding: var(--space-4) var(--space-6);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        th {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            font-weight: 700;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--primary-500);
        }

        tbody tr {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        td {
            font-size: 0.875rem;
            color: var(--gray-700);
            font-weight: 500;
        }

        /* Empty State */
        .alert[style*="grid-column"] {
            grid-column: 1 / -1;
            text-align: center;
            padding: var(--space-8);
            background: var(--gray-50);
            border: 2px dashed var(--gray-300);
            color: var(--gray-500);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: var(--space-4);
            }

            .header-content {
                flex-direction: column;
                gap: var(--space-4);
                align-items: flex-start;
            }

            .user-info {
                order: -1;
                align-self: flex-end;
            }

            .brand {
                font-size: 1.25rem;
            }

            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                min-width: unset;
            }

            .filter-select {
                min-width: unset;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: var(--space-4);
            }

            .card-header,
            .card-body {
                padding: var(--space-4);
            }

            .card-title {
                font-size: 1.125rem;
            }

            table {
                font-size: 0.75rem;
            }

            th,
            td {
                padding: var(--space-3) var(--space-4);
            }
        }

        @media (max-width: 480px) {
            .user-details {
                display: none;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                padding: var(--space-4);
            }

            .brand {
                font-size: 1.125rem;
            }

            .logo-icon {
                width: 2rem;
                height: 2rem;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="brand">
                    <div class="logo-icon"><i class="fas fa-box-open"></i></div>
                    <span>Commerce</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($username) ?></span>
                        <span class="user-role">Customer</span>
                    </div>
                </div>
                <div class="header-buttons">
                    <a href="logout.php" class="btn-header">
                        <i class="fas fa-sign-out-alt"></i>
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-store"></i>
                    Available Products
                </h2>
            </div>
            <div class="card-body">
                <?php if (isset($purchaseSuccess)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= $purchaseSuccess ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($purchaseError)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $purchaseError ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="" class="filter-container">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="filter-button">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                </form>

                <div class="product-grid">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php
                                    // Display different icons based on category
                                    $category = strtolower($product['Category']);
                                    $icon = 'box';

                                    if (strpos($category, 'electronics') !== false) {
                                        $icon = 'laptop';
                                    } elseif (strpos($category, 'clothing') !== false) {
                                        $icon = 'tshirt';
                                    } elseif (strpos($category, 'food') !== false) {
                                        $icon = 'utensils';
                                    } elseif (strpos($category, 'book') !== false) {
                                        $icon = 'book';
                                    } elseif (strpos($category, 'furniture') !== false) {
                                        $icon = 'chair';
                                    }
                                    ?>
                                    <i class="fas fa-<?= $icon ?>"></i>
                                </div>
                                <div class="product-details">
                                    <h3 class="product-name"><?= htmlspecialchars($product['Name']) ?></h3>
                                    <div class="product-category">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($product['Category']) ?>
                                    </div>
                                    <div class="product-price">
                                        ₱<?= number_format($product['Price'], 2) ?>
                                    </div>
                                    <div class="product-stock">
                                        <span>Available:</span>
                                        <span class="stock-badge"><?= number_format($product['CurrentStock']) ?></span>
                                    </div>
                                    <div class="product-actions">
                                        <form method="post" class="purchase-form">
                                            <input type="hidden" name="product_id" value="<?= $product['ProductID'] ?>">
                                            <input type="number" name="quantity" class="quantity-input" min="1" max="<?= $product['CurrentStock'] ?>" value="1" required>
                                            <button type="submit" name="purchase" class="purchase-button">
                                                <i class="fas fa-shopping-cart"></i>
                                                Purchase
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-error" style="grid-column: 1 / -1;">
                            <i class="fas fa-exclamation-circle"></i>
                            No products available matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Recent Purchase History
                </h2>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentSales) > 0): ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?= $sale['SaleID'] ?></td>
                                    <td><?= htmlspecialchars($sale['ProductName']) ?></td>
                                    <td><?= number_format($sale['QuantitySold']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($sale['SaleDate'])) ?></td>
                                    <td>₱<?= number_format($sale['TotalAmount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No purchase history available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Enable quantity validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const value = parseInt(this.value);

                if (value > max) {
                    this.value = max;
                }

                if (value < 1) {
                    this.value = 1;
                }
            });
        });
    </script>
</body>

</html>