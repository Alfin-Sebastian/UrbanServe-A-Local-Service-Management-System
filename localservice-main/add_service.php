<?php
include 'db.php';
session_start();

// ✅ Allow both admin and provider roles
$allowedRoles = ['admin', 'provider'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $allowedRoles)) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$categories = [];
$current_user_id = $_SESSION['user']['id'];
$is_provider = ($_SESSION['user']['role'] === 'provider');

// Fetch categories from database
$category_query = $conn->query("SELECT * FROM service_categories ORDER BY name");
if ($category_query) {
    $categories = $category_query->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission";
    } else {
        $name = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);

        // Validate inputs
        if (empty($name) || empty($category_id) || empty($description) || $price <= 0 || $duration <= 0) {
            $error = "Please fill all fields with valid values";
        } else {
            // Handle file upload
            $image_path = null;
            if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/services/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Validate file
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($file_info, $_FILES['service_image']['tmp_name']);
                finfo_close($file_info);

                if (!in_array($mime_type, $allowed_types)) {
                    $error = "Only JPG, PNG, and GIF images are allowed";
                } elseif ($_FILES['service_image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error = "Image size must be less than 5MB";
                } else {
                    // Generate unique filename
                    $extension = pathinfo($_FILES['service_image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $extension;
                    $destination = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['service_image']['tmp_name'], $destination)) {
                        $image_path = $destination;
                    } else {
                        $error = "Failed to upload image";
                    }
                }
            }

            if (!$error) {
                // Begin transaction
                $conn->begin_transaction();

                try {
                    // Add service to services table (without image)
                    $stmt = $conn->prepare("INSERT INTO services (name, category_id, description, base_price, duration_minutes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sisdi", $name, $category_id, $description, $price, $duration);

                    if (!$stmt->execute()) {
                        throw new Exception("Error adding service: " . $stmt->error);
                    }

                    $service_id = $conn->insert_id;

                    // If image was uploaded, add it to service_images table
                    if ($image_path) {
                        $stmt = $conn->prepare("INSERT INTO service_images (service_id, image_url) VALUES (?, ?)");
                        $stmt->bind_param("is", $service_id, $image_path);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error adding service image: " . $stmt->error);
                        }
                    }

                    // If provider, link to profile
                    if ($is_provider) {
                        $check_provider = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'provider'");
                        $check_provider->bind_param("i", $current_user_id);
                        $check_provider->execute();
                        $check_result = $check_provider->get_result();

                        if ($check_result->num_rows === 0) {
                            throw new Exception("Provider account not found!");
                        }

                        $provider_price = isset($_POST['provider_price']) ? floatval($_POST['provider_price']) : $price;

                        $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, service_id, price) VALUES (?, ?, ?)");
                        $stmt->bind_param("iid", $current_user_id, $service_id, $provider_price);

                        if (!$stmt->execute()) {
                            throw new Exception("Error linking service to provider: " . $stmt->error);
                        }
                    }

                    $conn->commit();
                    $success = $is_provider 
                        ? "Service added successfully and linked to your profile!" 
                        : "Service added successfully!";

                    // Clear form fields
                    $_POST = array();

                } catch (Exception $e) {
                    $conn->rollback();
                    // Delete uploaded file if transaction failed
                    if ($image_path && file_exists($image_path)) {
                        unlink($image_path);
                    }
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $is_provider ? 'Provider' : 'Admin' ?> Add Service | UrbanServe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 700px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { font-size: 24px; margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; }
        input, textarea, select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; }
        .btn { background: #4CAF50; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn:hover { background: #45a049; }
        .btn:disabled { background: #cccccc; cursor: not-allowed; }
        .alert { padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-link { display: inline-block; margin-top: 20px; color: #007BFF; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .provider-price-field small { display: block; margin-top: 4px; color: #777; }
        .image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; border-radius: 6px; border: 1px solid #ddd; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; }
        .file-input-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .file-input-label { display: block; padding: 15px; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .file-input-label:hover { background: #e9ecef; border-color: #007bff; }
        .file-input-label i { font-size: 24px; color: #6c757d; margin-bottom: 8px; display: block; }
        .required { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1>Add New Service</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="serviceForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-group">
            <label for="name">Service Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="category_id">Category <span class="required">*</span></label>
            <select id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description <span class="required">*</span></label>
            <textarea id="description" name="description" required placeholder="Describe the service in detail..."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>

        <div class="form-group">
            <label for="price">Base Price (₹) <span class="required">*</span></label>
            <input type="number" id="price" name="price" min="0.01" step="0.01" required value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="duration">Duration (minutes) <span class="required">*</span></label>
            <input type="number" id="duration" name="duration" min="1" required value="<?= isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : '' ?>">
        </div>

        <div class="form-group">
            <label>Service Image</label>
            <div class="file-input-wrapper">
                <label class="file-input-label" id="fileInputLabel">
                    <i class="fas fa-cloud-upload-alt"></i> 
                    <span id="fileLabelText">Choose an image (JPG, PNG, GIF - max 5MB)</span>
                    <input type="file" id="service_image" name="service_image" accept="image/*" onchange="previewImage(this)">
                </label>
            </div>
            <img id="imagePreview" class="image-preview" alt="Image preview">
        </div>

        <?php if ($is_provider): ?>
            <div class="form-group provider-price-field">
                <label for="provider_price">Your Price (₹)</label>
                <input type="number" id="provider_price" name="provider_price" min="0.01" step="0.01" 
                       value="<?= isset($_POST['provider_price']) ? htmlspecialchars($_POST['provider_price']) : '' ?>"
                       placeholder="Leave blank to use base price">
                <small>If left blank, the base price will be used</small>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn" id="submitBtn">Add Service</button>
    </form>

    <a href="<?= $is_provider ? 'provider_dashboard.php' : 'admin_dashboard.php' ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const labelText = document.getElementById('fileLabelText');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                labelText.textContent = input.files[0].name + ' (Click to change)';
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
            labelText.textContent = 'Choose an image (JPG, PNG, GIF - max 5MB)';
        }
    }

    // Form validation and submission handling
    document.getElementById('serviceForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const price = document.getElementById('price').value;
        const duration = document.getElementById('duration').value;
        
        if (price <= 0 || duration <= 0) {
            e.preventDefault();
            alert('Please enter valid price and duration values.');
            return;
        }
        
        // Disable button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding Service...';
    });

    // Real-time validation
    document.getElementById('price')?.addEventListener('input', function() {
        if (this.value <= 0) {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = '#ccc';
        }
    });

    document.getElementById('duration')?.addEventListener('input', function() {
        if (this.value <= 0) {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = '#ccc';
        }
    });
</script>
</body>
</html>