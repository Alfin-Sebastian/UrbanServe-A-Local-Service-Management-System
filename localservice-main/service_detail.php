<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: services.php");
    exit;
}

$service_id = intval($_GET['id']);

// Fetch service details with provider info
$service_stmt = $conn->prepare("
    SELECT 
        s.*, 
        sc.name AS category_name,
        u.id AS provider_id,
        u.name AS provider_name,
        u.profile_image AS provider_image,
        p.experience,
        p.avg_rating,
        p.bio AS provider_bio
    FROM services s
    JOIN service_categories sc ON s.category_id = sc.id
    JOIN provider_services ps ON s.id = ps.service_id
    JOIN users u ON ps.provider_id = u.id
    JOIN providers p ON u.id = p.user_id
    WHERE s.id = ?
");
$service_stmt->bind_param("i", $service_id);
$service_stmt->execute();
$service_result = $service_stmt->get_result();

if ($service_result->num_rows === 0) {
    header("Location: services.php?error=not_found");
    exit;
}
$service = $service_result->fetch_assoc();

// Fetch service images
$images_stmt = $conn->prepare("
    SELECT image_url FROM service_images 
    WHERE service_id = ?
    ORDER BY id ASC
");
$images_stmt->bind_param("i", $service_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$service_images = $images_result->fetch_all(MYSQLI_ASSOC);

// Handle Save for Later/Remove from Favorites functionality
if (isset($_POST['favorite_action']) && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer') {
    $user_id = $_SESSION['user']['id'];
    $action = $_POST['favorite_action'];
    
    if ($action === 'add') {
        // Check if service is already in favorites
        $check_stmt = $conn->prepare("SELECT id FROM favourites WHERE user_id = ? AND service_id = ?");
        $check_stmt->bind_param("ii", $user_id, $service_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Add to favorites
            $insert_stmt = $conn->prepare("INSERT INTO favourites (user_id, service_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $user_id, $service_id);
            
            if ($insert_stmt->execute()) {
                $success_message = "Service added to your favorites!";
                $is_favorite = true;
            } else {
                $error_message = "Failed to add service to favorites. Please try again.";
            }
        } else {
            $info_message = "This service is already in your favorites!";
            $is_favorite = true;
        }
    } elseif ($action === 'remove') {
        // Remove from favorites
        $delete_stmt = $conn->prepare("DELETE FROM favourites WHERE user_id = ? AND service_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $service_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Service removed from your favorites!";
            $is_favorite = false;
        } else {
            $error_message = "Failed to remove service from favorites. Please try again.";
        }
    }
}

// Check if service is already in user's favorites
$is_favorite = false;
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer') {
    $user_id = $_SESSION['user']['id'];
    $check_fav_stmt = $conn->prepare("SELECT id FROM favourites WHERE user_id = ? AND service_id = ?");
    $check_fav_stmt->bind_param("ii", $user_id, $service_id);
    $check_fav_stmt->execute();
    $check_fav_result = $check_fav_stmt->get_result();
    $is_favorite = $check_fav_result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> | UrbanServe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... (keep all existing styles) ... */
        :root {
            --primary: #f76d2b;
            --primary-light: rgba(247, 109, 43, 0.1);
            --primary-dark: #e05b1a;
            --secondary: #2d3748;
            --accent: #f0f4f8;
            --text: #2d3748;
            --light-text: #718096;
            --border: #e2e8f0;
            --white: #ffffff;
            --warning: #dd6b20;
            --success: #38a169;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text);
            line-height: 1.6;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 8px;
            background-color: var(--primary);
            transition: all 0.3s ease;
            border: 1px solid var(--primary);
            box-shadow: 0 2px 4px rgba(247, 109, 43, 0.3);
        }

        .back-link:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(247, 109, 43, 0.4);
        }

        .back-link i {
            font-size: 0.9rem;
        }
        
        .service-header {
            display: flex;
            gap: 40px;
            margin-bottom: 40px;
            flex-wrap: wrap;
            background: var(--white);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.3s ease;
        }

        .service-header:hover {
            box-shadow: var(--hover-shadow);
        }

        .service-gallery {
            flex: 1;
            min-width: 300px;
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .thumbnail-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .thumbnail {
            width: 100%;
            height: 85px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .thumbnail:hover, .thumbnail.active {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .service-info {
            flex: 1;
            min-width: 300px;
        }

        h1 {
            color: var(--secondary);
            margin-bottom: 15px;
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .service-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .service-category {
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .service-duration {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--light-text);
            font-weight: 500;
        }

        .service-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-note {
            font-size: 0.9rem;
            color: var(--light-text);
            font-weight: 400;
        }

        .service-description {
            margin-bottom: 30px;
            line-height: 1.8;
            padding: 15px;
            background-color: var(--primary-light);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .section-title {
            color: var(--secondary);
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .provider-section,
        .booking-section,
        .reviews-section {
            margin-top: 30px;
            padding: 25px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .provider-section:hover,
        .booking-section:hover,
        .reviews-section:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-3px);
        }

        .provider-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .provider-image {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .provider-info h2 {
            color: var(--secondary);
            margin-bottom: 8px;
            font-size: 1.4rem;
        }

        .provider-rating {
            color: var(--warning);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 8px;
        }

        .provider-experience {
            color: var(--light-text);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .provider-bio {
            line-height: 1.7;
            color: var(--text);
            padding: 15px;
            background-color: var(--light-bg);
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        
        .booking-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            box-shadow: 0 2px 4px rgba(247, 109, 43, 0.3);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(247, 109, 43, 0.4);
            color: var(--white);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-secondary:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .review-card {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 15px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .review-card:hover {
            background-color: var(--light-bg);
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .reviewer-image {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }

        .reviewer-info h3 {
            color: var(--secondary);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .review-rating {
            color: var(--warning);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .review-date {
            color: var(--light-text);
            font-size: 0.85rem;
            margin-left: 10px;
        }

        .review-comment {
            margin-top: 10px;
            line-height: 1.7;
            color: var(--text);
            padding: 10px 15px;
            background-color: var(--primary-light);
            border-radius: 8px;
        }

        .no-reviews {
            color: var(--light-text);
            font-style: italic;
            text-align: center;
            padding: 30px;
            background-color: var(--light-bg);
            border-radius: 8px;
        }

        .login-prompt {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .login-prompt p {
            margin-bottom: 10px;
            color: var(--light-text);
        }

        /* Animation for gallery */
        @keyframes fadeIn {
            from { opacity: 0.7; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 968px) {
            .service-header {
                gap: 30px;
            }
            
            .thumbnail-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .service-header {
                flex-direction: column;
                padding: 20px;
            }
            
            .thumbnail-container {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .main-image {
                height: 350px;
            }

            .provider-header {
                flex-direction: column;
                text-align: center;
            }

            .booking-options {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 15px;
            }
            
            .service-header {
                padding: 15px;
            }
            
            .thumbnail-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-image {
                height: 280px;
            }

            h1 {
                font-size: 1.8rem;
            }

            .service-price {
                font-size: 1.5rem;
            }

            .provider-section,
            .booking-section,
            .reviews-section {
                padding: 20px;
            }
        }
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #e6fffa;
            color: #2d6a4f;
            border-left: 4px solid #38a169;
        }
        
        .alert-error {
            background-color: #fed7d7;
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }
        
        .alert-info {
            background-color: #ebf8ff;
            color: #2c5282;
            border-left: 4px solid #3182ce;
        }
        
        .btn-favorited {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-favorited:hover {
            background-color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="services.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($info_message)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= $info_message ?>
            </div>
        <?php endif; ?>
        
        <div class="service-header">
            <div class="service-gallery">
                <?php if (!empty($service_images)): ?>
                    <img id="mainImage" src="<?= htmlspecialchars($service_images[0]['image_url']) ?>" 
                         alt="<?= htmlspecialchars($service['name']) ?>" class="main-image">
                    
                    <div class="thumbnail-container">
                        <?php foreach ($service_images as $index => $image): ?>
                            <img src="<?= htmlspecialchars($image['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($service['name']) ?> - Image <?= $index + 1 ?>" 
                                 class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                 onclick="changeImage(this, '<?= htmlspecialchars($image['image_url']) ?>')">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <img id="mainImage" src="<?= htmlspecialchars($service['image'] ?? 'https://via.placeholder.com/600x400?text=Service+Image') ?>" 
                         alt="<?= htmlspecialchars($service['name']) ?>" class="main-image">
                <?php endif; ?>
            </div>
            
            <div class="service-info">
                <h1><?= htmlspecialchars($service['name']) ?></h1>
                <div class="service-meta">
                    <span class="service-category"><?= htmlspecialchars($service['category_name']) ?></span>
                    <span class="service-duration"><i class="far fa-clock"></i> <?= htmlspecialchars($service['duration_minutes']) ?> mins</span>
                </div>
                <div class="service-price">
                    ₹<?= number_format($service['base_price'], 2) ?>
                    <span class="price-note">(starting price)</span>
                </div>
                <div class="service-description">
                    <?= nl2br(htmlspecialchars($service['description'])) ?>
                </div>
                
                <div class="provider-section">
                    <h2 class="section-title"><i class="fas fa-user-tie"></i> Service Provider</h2>
                    <div class="provider-header">
                        <img src="<?= htmlspecialchars($service['provider_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($service['provider_name']) . '&background=f76d2b&color=fff') ?>" 
                             alt="<?= htmlspecialchars($service['provider_name']) ?>" class="provider-image">
                        <div class="provider-info">
                            <h2><?= htmlspecialchars($service['provider_name']) ?></h2>
                            <?php if ($service['avg_rating'] > 0): ?>
                                <div class="provider-rating">
                                    <i class="fas fa-star"></i> <?= number_format($service['avg_rating'], 1) ?> Rating
                                </div>
                            <?php endif; ?>
                            <div class="provider-experience">
                                <i class="fas fa-briefcase"></i> <?= htmlspecialchars($service['experience']) ?> years experience
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($service['provider_bio'])): ?>
                        <div class="provider-bio">
                            <?= nl2br(htmlspecialchars($service['provider_bio'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="booking-section">
            <h2 class="section-title"><i class="fas fa-calendar-check"></i> Booking Options</h2>
            
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($_SESSION['user']['role'] === 'customer'): ?>
                    <div class="booking-options">
                        <form method="GET" action="book_service.php" class="booking-form">
                            <input type="hidden" name="service_id" value="<?= $service_id ?>">
                            <input type="hidden" name="from_detail" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </button>
                        </form>
                        
                        <form method="POST" action="" class="favorite-form">
                            <input type="hidden" name="favorite_action" value="<?= $is_favorite ? 'remove' : 'add' ?>">
                            <button type="submit" class="btn <?= $is_favorite ? 'btn-secondary' : 'btn-secondary' ?>">
                                <i class="<?= $is_favorite ? 'fas' : 'far' ?> fa-heart"></i> 
                                <?= $is_favorite ? 'Remove from Favorites' : 'Save for Later' ?>
                            </button>
                        </form>
                    </div>
                <?php elseif ($_SESSION['user']['role'] === 'provider' && $_SESSION['user']['id'] == $service['provider_id']): ?>
                    <a href="edit_my_services.php?id=<?= $service_id ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Service
                    </a>
                <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="edit_services.php?id=<?= $service_id ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Service
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="login-prompt">
                    <p>Login to book this service or save it for later</p>
                    <div class="booking-options">
                        <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login to Book
                        </a>
                        <a href="register.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i> Create Account
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="reviews-section">
            <h2 class="section-title"><i class="fas fa-star"></i> Customer Reviews</h2>
            
            <?php
            // Fetch reviews for this service
            $reviews_stmt = $conn->prepare("
                SELECT r.*, u.name AS customer_name, u.profile_image AS customer_image
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.service_id = ?
                ORDER BY r.created_at DESC
                LIMIT 10
            ");
            $reviews_stmt->bind_param("i", $service_id);
            $reviews_stmt->execute();
            $reviews_result = $reviews_stmt->get_result();
            $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <?php if (count($reviews) > 0): ?>
                <div class="reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <img src="<?= htmlspecialchars($review['customer_image'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($review['customer_name']))) ?>" 
                                     alt="<?= htmlspecialchars($review['customer_name']) ?>" 
                                     class="reviewer-image">
                                <div class="reviewer-info">
                                    <h3><?= htmlspecialchars($review['customer_name']) ?></h3>
                                    <div class="review-rating">
                                        <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
                                        <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($review['comment'])): ?>
                                <div class="review-comment">
                                    <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-reviews">No reviews yet for this service. Be the first to leave a review after trying this service!</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Enhanced image gallery functionality
        function changeImage(thumbnail, imageUrl) {
            const mainImage = document.getElementById('mainImage');
            
            // Add fade animation
            mainImage.classList.add('fade-in');
            
            // Change image source
            mainImage.src = imageUrl;
            
            // Remove active class from all thumbnails
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            // Add active class to clicked thumbnail
            thumbnail.classList.add('active');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                mainImage.classList.remove('fade-in');
            }, 500);
        }

        // Add hover effects to interactive elements
        document.querySelectorAll('.service-header, .provider-section, .booking-section, .reviews-section').forEach(section => {
            section.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            section.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>