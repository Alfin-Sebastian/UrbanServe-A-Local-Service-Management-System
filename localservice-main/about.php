<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | UrbanServe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Base Styles */
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        body.loaded {
            opacity: 1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h2 {
            color: #2d3748;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f76d2b;
        }
        
        /* About Sections */
        .about-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #f76d2b;
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1.8rem;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .about-content {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .about-text {
            line-height: 1.7;
            color: #4a5568;
        }
        
        .about-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .highlight-box {
            background-color: #f0f7ff;
            border-left: 4px solid #f76d2b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        
        .steps-list {
            list-style-type: none;
            padding: 0;
        }
        
        .steps-list li {
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            gap: 15px;
        }
        
        .steps-list li:last-child {
            border-bottom: none;
        }
        
        .step-number {
            background-color: #f76d2b;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: bold;
        }
        
        /* Back Link */
        .back-link-container {
            text-align: center;
            margin-top: 40px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
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
        
        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity 0.5s ease;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #f76d2b;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mission and Vision Styles */
        .mission-vision {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        @media (min-width: 768px) {
            .mission-vision {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .mission-box, .vision-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-top: 4px solid #f76d2b;
        }
        
        .mission-box h3, .vision-box h3 {
            color: #f76d2b;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .why-choose-us {
            margin: 30px 0;
        }
        
        .benefits-list {
            list-style-type: none;
            padding: 0;
        }
        
        .benefits-list li {
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .benefits-list li:last-child {
            border-bottom: none;
        }
        
        .benefit-icon {
            color: #f76d2b;
            font-size: 1.2rem;
            width: 25px;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="container">
        <h2>About UrbanServe</h2>
        
        <!-- Welcome Section -->
        <div class="about-section">
            <h3 class="section-title"><i class="fas fa-handshake"></i> Welcome to UrbanServe</h3>
            <div class="about-content">
                <div class="about-text">
                    <p>Welcome to <strong>UrbanServe</strong> – your trusted platform for connecting customers with local service providers.</p>
                    <p>We understand how challenging it can be to find reliable professionals for everyday needs. That's why we built this system: to make booking services simple, fast, and transparent. Whether you're looking for a plumber, electrician, cleaner, or any other local expert, UrbanServe ensures you find the right professional in just a few clicks.</p>
                </div>
                <img src="https://images.unsplash.com/photo-1560472354-b33ff0c44a43?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="UrbanServe Connection" class="about-image">
            </div>
        </div>
        
        <!-- Mission and Vision Section -->
        <div class="about-section">
            <h3 class="section-title"><i class="fas fa-bullseye"></i> Our Purpose</h3>
            <div class="mission-vision">
                <div class="mission-box">
                    <h3><i class="fas fa-flag"></i> Our Mission</h3>
                    <p>To bridge the gap between customers and service providers by offering a secure, user-friendly platform that makes local services accessible to everyone.</p>
                </div>
                <div class="vision-box">
                    <h3><i class="fas fa-eye"></i> Our Vision</h3>
                    <p>To create a reliable digital space where every household and business can find trusted local services without hassle, while empowering professionals to reach more customers.</p>
                </div>
            </div>
        </div>
        
        <!-- Why Choose Us Section -->
        <div class="about-section">
            <h3 class="section-title"><i class="fas fa-star"></i> Why Choose Us?</h3>
            <div class="why-choose-us">
                <ul class="benefits-list">
                    <li>
                        <span class="benefit-icon"><i class="fas fa-check-circle"></i></span>
                        <span><strong>Verified Providers</strong> – Every provider submits ID proof, reviewed by our admin team, to ensure trust and safety.</span>
                    </li>
                    <li>
                        <span class="benefit-icon"><i class="fas fa-calendar-check"></i></span>
                        <span><strong>Easy Booking</strong> – Book services directly from your dashboard, with clear details of the provider and service.</span>
                    </li>
                    <li>
                        <span class="benefit-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span><strong>Location-Based Matching</strong> – Get connected with providers available in your city, state, and pincode for faster response.</span>
                    </li>
                    <li>
                        <span class="benefit-icon"><i class="fas fa-chart-line"></i></span>
                        <span><strong>Fair Opportunities</strong> – A platform where service providers can showcase their skills, build their profile, and grow their business.</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Community Section -->
        <div class="about-section">
            <h3 class="section-title"><i class="fas fa-users"></i> Our Community</h3>
            <div class="about-content">
                <img src="https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Community Trust" class="about-image">
                <div class="about-text">
                    <p>At UrbanServe, we're not just a service platform – we're building a <strong>community of trust</strong> between customers and providers.</p>
                    <div class="highlight-box">
                        We believe in creating meaningful connections that benefit both customers seeking quality services and professionals looking to grow their business.
                    </div>
                    <p>Our platform is designed to foster long-term relationships built on transparency, quality service, and mutual respect.</p>
                </div>
            </div>
        </div>
        
        <!-- Back Link -->
        <div class="back-link-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
<?php include 'footer.php'; ?>

    <script>
        // Simple loading simulation
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loading-overlay').style.opacity = '0';
                document.body.classList.add('loaded');
                setTimeout(function() {
                    document.getElementById('loading-overlay').style.display = 'none';
                }, 500);
            }, 800);
        });
    </script>
</body>
</html>