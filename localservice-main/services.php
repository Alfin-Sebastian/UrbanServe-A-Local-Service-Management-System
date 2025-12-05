<?php
include 'db.php';
session_start();

// Handle favorite actions
if (isset($_GET['fav']) && isset($_GET['type']) && isset($_GET['id']) && isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    $user_role = $_SESSION['user']['role'];
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    $action = $_GET['fav'];
    
    // Check if user is allowed to use favorites (all roles can use it)
    if (in_array($user_role, ['customer', 'provider', 'admin'])) {
        if ($action === 'add') {
            // Check if already favorited
            $check_query = "";
            if ($type === 'provider') {
                $check_query = "SELECT id FROM favourites WHERE user_id = ? AND provider_id = ?";
            } else {
                $check_query = "SELECT id FROM favourites WHERE user_id = ? AND service_id = ?";
            }
            
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('ii', $user_id, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Add to favorites
                $insert_query = "";
                if ($type === 'provider') {
                    $insert_query = "INSERT INTO favourites (user_id, provider_id) VALUES (?, ?)";
                } else {
                    $insert_query = "INSERT INTO favourites (user_id, service_id) VALUES (?, ?)";
                }
                
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param('ii', $user_id, $id);
                $stmt->execute();
            }
            
        } elseif ($action === 'remove') {
            // Remove from favorites
            $delete_query = "";
            if ($type === 'provider') {
                $delete_query = "DELETE FROM favourites WHERE user_id = ? AND provider_id = ?";
            } else {
                $delete_query = "DELETE FROM favourites WHERE user_id = ? AND service_id = ?";
            }
            
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param('ii', $user_id, $id);
            $stmt->execute();
        }
    }
    
    // Redirect back to remove GET parameters
    header('Location: services.php?' . http_build_query(array_diff_key($_GET, ['fav' => '', 'type' => '', 'id' => ''])));
    exit;
}

// Get user's favorites
$favorite_services = [];
$favorite_providers = [];

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    
    // Get favorite services
    $stmt = $conn->prepare("SELECT service_id FROM favourites WHERE user_id = ? AND service_id IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $favorite_services[] = $row['service_id'];
    }
    $stmt->close();
    
    // Get favorite providers
    $stmt = $conn->prepare("SELECT provider_id FROM favourites WHERE user_id = ? AND provider_id IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $favorite_providers[] = $row['provider_id'];
    }
    $stmt->close();
}

// Get filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$min_rating = isset($_GET['min_rating']) ? floatval($_GET['min_rating']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;

// Fetch service categories
$categories = [];
$cat_query = $conn->query("SELECT id, name FROM service_categories");
if ($cat_query) {
    $categories = $cat_query->fetch_all(MYSQLI_ASSOC);
}

// Fetch available locations from providers (normalized to avoid duplicates)
$locations = [];
$loc_query = $conn->query("SELECT DISTINCT LOWER(TRIM(p.location)) as normalized_location, p.location as original_location 
                          FROM providers p
                          WHERE p.location IS NOT NULL AND p.location != '' 
                          ORDER BY p.location");
if ($loc_query) {
    $unique_locations = [];
    while ($row = $loc_query->fetch_assoc()) {
        $normalized_location = strtolower(trim($row['normalized_location']));
        // Only add if we haven't seen this normalized location before
        if (!in_array($normalized_location, array_keys($unique_locations))) {
            $unique_locations[$normalized_location] = $row['original_location'];
        }
    }
    $locations = array_values($unique_locations);
}

// Base query for counting total results
$count_sql = "
    SELECT COUNT(DISTINCT s.id) AS total
    FROM services s
    JOIN service_categories sc ON s.category_id = sc.id
    JOIN provider_services ps ON s.id = ps.service_id
    JOIN users u ON ps.provider_id = u.id
    JOIN providers p ON u.id = p.user_id
    WHERE p.is_verified = 1
";

// Main query to get services with calculated average rating
$sql = "
    SELECT 
        s.id,
        s.name,
        s.description,
        s.base_price,
        s.duration_minutes,
        s.image,
        sc.name AS category_name,
        GROUP_CONCAT(DISTINCT p.location SEPARATOR ', ') AS locations,
        (
            SELECT GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ')
            FROM provider_services ps
            JOIN users u ON ps.provider_id = u.id
            WHERE ps.service_id = s.id
        ) AS providers,
        (
            SELECT COUNT(DISTINCT ps.provider_id)
            FROM provider_services ps
            WHERE ps.service_id = s.id
        ) AS provider_count,
        (
            SELECT MIN(ps.price)
            FROM provider_services ps
            WHERE ps.service_id = s.id
        ) AS min_price,
        (
            SELECT MAX(ps.price)
            FROM provider_services ps
            WHERE ps.service_id = s.id
        ) AS max_price,
        (
            SELECT AVG(r.rating)
            FROM provider_services ps
            JOIN reviews r ON ps.provider_id = r.provider_id
            WHERE ps.service_id = s.id
        ) AS avg_rating,
        (
            SELECT image_url 
            FROM service_images si 
            WHERE si.service_id = s.id 
            LIMIT 1
        ) AS featured_image
    FROM services s
    JOIN service_categories sc ON s.category_id = sc.id
    JOIN provider_services ps ON s.id = ps.service_id
    JOIN users u ON ps.provider_id = u.id
    JOIN providers p ON u.id = p.user_id
    WHERE p.is_verified = 1
";

// Build WHERE conditions (excluding rating and price filters)
$conditions = [];
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $conditions[] = "(s.name LIKE '%$safe_search%' OR 
                     s.description LIKE '%$safe_search%' OR
                     sc.name LIKE '%$safe_search%')";
}

if ($category_id > 0) {
    $conditions[] = "s.category_id = $category_id";
}

if (!empty($location)) {
    $safe_location = $conn->real_escape_string($location);
    $conditions[] = "LOWER(TRIM(p.location)) = LOWER(TRIM('$safe_location'))";
}

// Append conditions to SQL
$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " AND " . implode(" AND ", $conditions);
    $count_sql .= $where_clause;
    $sql .= $where_clause;
}

// Group by service ID
$sql .= " GROUP BY s.id";

// Add HAVING clause for rating and price filters (after GROUP BY)
$having_conditions = [];
if ($min_rating > 0) {
    $having_conditions[] = "avg_rating >= $min_rating";
}

// Add price range conditions to HAVING clause
if ($min_price > 0) {
    $having_conditions[] = "min_price >= $min_price";
}

if ($max_price > 0) {
    $having_conditions[] = "max_price <= $max_price";
}

if (!empty($having_conditions)) {
    $sql .= " HAVING " . implode(" AND ", $having_conditions);
}

// For the count query with filters
if ($min_rating > 0 || $min_price > 0 || $max_price > 0) {
    $count_sql = "
        SELECT COUNT(*) AS total FROM (
            SELECT s.id
            FROM services s
            JOIN service_categories sc ON s.category_id = sc.id
            JOIN provider_services ps ON s.id = ps.service_id
            JOIN users u ON ps.provider_id = u.id
            JOIN providers p ON u.id = p.user_id
            WHERE p.is_verified = 1
            $where_clause
            GROUP BY s.id
            HAVING 1=1
    ";
    
    if ($min_rating > 0) {
        $count_sql .= " AND (
            SELECT AVG(r.rating)
            FROM provider_services ps
            JOIN reviews r ON ps.provider_id = r.provider_id
            WHERE ps.service_id = s.id
        ) >= $min_rating";
    }
    
    if ($min_price > 0) {
        $count_sql .= " AND (
            SELECT MIN(ps.price)
            FROM provider_services ps
            WHERE ps.service_id = s.id
        ) >= $min_price";
    }
    
    if ($max_price > 0) {
        $count_sql .= " AND (
            SELECT MAX(ps.price)
            FROM provider_services ps
            WHERE ps.service_id = s.id
        ) <= $max_price";
    }
    
    $count_sql .= ") AS filtered_services";
}

// Get total services and calculate pagination
$total_result = $conn->query($count_sql)->fetch_assoc();
$total_services = $total_result['total'];
$total_pages = ceil($total_services / $per_page);
$offset = ($page - 1) * $per_page;
$sql .= " ORDER BY avg_rating DESC, s.name ASC LIMIT $offset, $per_page";

$query = $conn->query($sql);
$services = $query ? $query->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services | UrbanServe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
          
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        
        }
        
        h2 {
            color: #2d3748;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f76d2b;
        }
        
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .category-select, .location-select, .rating-select, .price-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background: white;
        }
        
        .price-select {
            min-width: 120px;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
        }
        
        .search-button {
            padding: 10px 20px;
            background-color: #f76d2b;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-left: 450px;
        }
        
        .search-button:hover {
            background-color: #e05b1a;
        }
        
        .reset-button {
            padding: 10px 20px;
            background-color: #e2e8f0;
            color: #4a5568;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .reset-button:hover {
            background-color: #cbd5e0;
        }
        
        .services-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .service-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .service-image-container {
            height: 200px;
            position: relative;
            overflow: hidden;
        }
        
        .service-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .service-info {
            padding: 20px;
        }
        
        .service-name {
            color: #f76d2b;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        
        .service-category {
            display: inline-block;
            background: #e2e8f0;
            color: #4a5568;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .service-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 8px 0;
            color: #f8c537;
        }
        
        .service-price {
            font-weight: bold;
            color: #2d3748;
            margin: 10px 0;
            font-size: 1.1rem;
        }
        
        .service-price small {
            font-size: 0.9rem;
            color: #718096;
            font-weight: normal;
        }
        
        .service-duration {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .service-location {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .service-providers {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .service-description {
            color: #4a5568;
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .action-links {
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .action-links a {
            flex: 1;
            text-align: center;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .action-links a:first-child {
            background-color: #f0f4f8;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        
        .action-links a:first-child:hover {
            background-color: #e2e8f0;
        }
        
        .action-links a:last-child {
            background-color: #f76d2b;
            color: white;
            border: 1px solid #f76d2b;
        }
        
        .action-links a:last-child:hover {
            background-color: #e05b1a;
            border-color: #e05b1a;
        }
        
        .no-results {
            text-align: center;
            grid-column: 1 / -1;
            padding: 40px;
            color: #718096;
        }
        
        .back-link-container {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #f76d2b;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .back-link:hover {
            background-color: #e05b1a;
        }
        
        .back-link i {
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        
        .pagination a:hover {
            background-color: #f76d2b;
            color: white;
            border-color: #f76d2b;
        }
        
        .pagination .active {
            background-color: #f76d2b;
            color: white;
            border-color: #f76d2b;
        }
        
        .pagination .disabled {
            color: #cbd5e0;
            pointer-events: none;
        }

        /* Favorite Button Styles - Positioned at top right corner */
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 10;
            text-decoration: none;
        }

        .favorite-btn:hover {
            color: red;
            background: rgba(255, 255, 255, 1);
            transform: scale(1.1);
        }

        .favorite-btn.active {
            color: red;
            background: rgba(255, 255, 255, 1);
        }

        .favorite-btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .favorite-btn:disabled:hover {
            color: #ccc;
            transform: none;
            background: rgba(255, 255, 255, 0.9);
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .location-select, 
            .rating-select, 
            .price-select {
                width: 100%;
            }
            
            .services-list {
                grid-template-columns: 1fr;
            }
            
            .action-links {
                flex-direction: column;
            }
            
            .search-button {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <div class="container">
        <h2>Browse Services</h2>
        
        <!-- Search Form -->
        <div class="search-container">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search services..." value="<?= htmlspecialchars($search) ?>">
                
                <select name="category" class="category-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <div class="filter-group">
                    <select name="location" class="location-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>" 
                                <?= (strtolower(trim($location)) == strtolower(trim($loc))) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="min_rating" class="rating-select">
                        <option value="0">Any Rating</option>
                        <option value="4.5" <?= ($min_rating == 4.5) ? 'selected' : '' ?>>4.5+ Stars</option>
                        <option value="4" <?= ($min_rating == 4) ? 'selected' : '' ?>>4+ Stars</option>
                        <option value="3" <?= ($min_rating == 3) ? 'selected' : '' ?>>3+ Stars</option>
                    </select>
                    
                    <!-- Add Price Range Filter -->
                    <select name="min_price" class="price-select">
                        <option value="0">Min Price</option>
                        <option value="100" <?= ($min_price == 100) ? 'selected' : '' ?>>₹100+</option>
                        <option value="250" <?= ($min_price == 250) ? 'selected' : '' ?>>₹250+</option>
                        <option value="500" <?= ($min_price == 500) ? 'selected' : '' ?>>₹500+</option>
                        <option value="1000" <?= ($min_price == 1000) ? 'selected' : '' ?>>₹1000+</option>
                        <option value="2000" <?= ($min_price == 2000) ? 'selected' : '' ?>>₹2000+</option>
                    </select>
                    
                    <select name="max_price" class="price-select">
                        <option value="0">Max Price</option>
                        <option value="500" <?= ($max_price == 500) ? 'selected' : '' ?>>Up to ₹500</option>
                        <option value="1000" <?= ($max_price == 1000) ? 'selected' : '' ?>>Up to ₹1000</option>
                        <option value="2000" <?= ($max_price == 2000) ? 'selected' : '' ?>>Up to ₹2000</option>
                        <option value="5000" <?= ($max_price == 5000) ? 'selected' : '' ?>>Up to ₹5000</option>
                        <option value="10000" <?= ($max_price == 10000) ? 'selected' : '' ?>>Up to ₹10000</option>
                    </select>
                </div>
                <button type="submit" class="search-button">Search</button>
                <a href="services.php" class="reset-button">Reset</a>
            </form>
        </div>
        
        <!-- Services List -->
        <div class="services-list">
            <?php if (count($services) > 0): ?>
                <?php foreach ($services as $service): 
                    $imagePath = $service['featured_image'] ?: ($service['image'] ?: 'images/services/default.jpg');
                    $is_favorite = in_array($service['id'], $favorite_services);
                    $fav_action = $is_favorite ? 'remove' : 'add';
                ?>
                    <div class="service-card">
                        <div class="service-image-container">
                            <img src="<?= htmlspecialchars($imagePath) ?>" 
                                 alt="<?= htmlspecialchars($service['name']) ?>" 
                                 class="service-image"
                                 onerror="this.src='images/services/default.jpg'">
                            
                            <!-- Favorite Button at Top Right Corner -->
                            <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['customer', 'provider', 'admin'])): ?>
                                <a href="services.php?<?= http_build_query(array_merge($_GET, ['fav' => $fav_action, 'type' => 'service', 'id' => $service['id']])) ?>" 
                                   class="favorite-btn <?= $is_favorite ? 'active' : '' ?>"
                                   title="<?= $is_favorite ? 'Remove from favorites' : 'Add to favorites' ?>">
                                    <i class="<?= $is_favorite ? 'fas' : 'far' ?> fa-heart"></i>
                                </a>
                            <?php else: ?>
                                <button class="favorite-btn" disabled title="Login to add favorites">
                                    <i class="far fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="service-info">
                            <h3 class="service-name"><?= htmlspecialchars($service['name']) ?></h3>
                            <span class="service-category"><?= htmlspecialchars($service['category_name']) ?></span>
                            <div class="service-rating">
                            <?php if ($service['avg_rating'] > 0): ?>
                                
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= round($service['avg_rating']) ? '' : '-half-alt' ?>"></i>
                                    <?php endfor; ?>
                                    <span>(<?= number_format($service['avg_rating'], 1) ?>)</span>
                       <?php else: ?>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= round($service['avg_rating']) ? '' : '-half-alt' ?>"></i>
                                    <?php endfor; ?>
                                    <span>(<?= number_format($service['avg_rating'], 1) ?>)</span>

                            <?php endif; ?>
                                     </div>
                            <div class="service-price">
                                <?php if ($service['provider_count'] > 1): ?>
                                    <span>From ₹<?= number_format($service['min_price'], 2) ?></span>
                                    <small>(Base: ₹<?= number_format($service['base_price'], 2) ?>)</small>
                                <?php else: ?>
                                    <span>₹<?= number_format($service['base_price'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="service-duration">
                                <i class="far fa-clock"></i> <?= $service['duration_minutes'] ?> minutes
                            </div>
                            
                            <?php if (!empty($service['locations'])): ?>
                                <div class="service-location">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($service['locations']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['providers'])): ?>
                                <div class="service-providers">
                                    <small>
                                        <?php if ($service['provider_count'] > 1): ?>
                                            <?= $service['provider_count'] ?> providers available
                                        <?php else: ?>
                                            Provider: <?= htmlspecialchars($service['providers']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <p class="service-description"><?= htmlspecialchars($service['description']) ?></p>
                            
                            <div class="action-links">
                                <a href="service_detail.php?id=<?= $service['id'] ?>">Details</a>
                                <a href="book_service.php?service_id=<?= $service['id'] ?>">Book Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <p>No services found matching your search criteria.</p>
                    <p><a href="services.php">Show all services</a></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Back Link -->
        <div class="back-link-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>