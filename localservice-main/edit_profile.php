<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$role = $user['role'];
$user_id = $user['id'];
$message = "";
$message_type = "";

// Determine dashboard based on user role
if ($role === 'admin') {
    $dashboard = "admin_dashboard.php";
} elseif ($role === 'provider') {
    $dashboard = "provider_dashboard.php";
} elseif ($role === 'customer') {
    $dashboard = "customer_dashboard.php";
} else {
    $dashboard = "index.php"; // fallback
}

// Handle update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $address  = ($role === 'customer') ? trim($_POST['address']) : null;
    $city     = trim($_POST['city']);
    $state    = trim($_POST['state']);
    $pincode  = trim($_POST['pincode']);

    // Validate phone number
    if (!empty($phone)) {
        if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            $message = "Phone number must be valid (10 digits, not starting with 0–5).";
            $message_type = "error";
        }
        // Extra check for all same digits like 0000000000, 1111111111, etc.
        elseif (preg_match('/^(\d)\1{9}$/', $phone)) {
            $message = "Phone number cannot be all repeating digits.";
            $message_type = "error";
        }
    }

    // Validate provider location (mandatory for providers)
    if ($role === 'provider') {
        $location = trim($_POST['location']);
        if (empty($location)) {
            $message = "Service location is required for providers.";
            $message_type = "error";
        }
    }

    // Handle profile image upload
    $profile_image = $user['profile_image'] ?? null;
    if (empty($message) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $filename;
        
        // Validate image
        $check = getimagesize($_FILES['profile_image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if (!empty($profile_image) && file_exists($profile_image)) {
                    unlink($profile_image);
                }
                $profile_image = $target_file;
            } else {
                $message = "Error uploading profile image.";
                $message_type = "error";
            }
        } else {
            $message = "File is not an image.";
            $message_type = "error";
        }
    }

    // Attempt to update user profile first
    if (empty($message)) {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, city=?, state=?, pincode=?, password=?, profile_image=? WHERE id=?");
            $stmt->bind_param("sssssssssi", $name, $email, $phone, $address, $city, $state, $pincode, $hashed, $profile_image, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, city=?, state=?, pincode=?, profile_image=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $email, $phone, $address, $city, $state, $pincode, $profile_image, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['address'] = $address;
            $_SESSION['user']['profile_image'] = $profile_image;
            $message = "Profile updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating profile: " . $conn->error;
            $message_type = "error";
        }
    }

    // Now, attempt to update provider details if the user is a provider,
    // regardless of the outcome of the user update.
    if ($role === 'provider') {
        $experience = trim($_POST['experience']);
        $location   = trim($_POST['location']);
        $bio        = trim($_POST['bio']);

        // Validate location again (in case it was empty)
        if (empty($location)) {
            $message = "Service location is required for providers.";
            $message_type = "error";
        } else {
            $check = $conn->prepare("SELECT id FROM providers WHERE user_id=?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $update = $conn->prepare("UPDATE providers SET experience=?, location=?, bio=? WHERE user_id=?");
                $update->bind_param("sssi", $experience, $location, $bio, $user_id);
                if ($update->execute()) {
                    // If user update was a success, extend the message.
                    // If it failed, don't overwrite the error.
                    if ($message_type === "success") {
                        $message = "Profile and provider details updated successfully!";
                    }
                } else {
                    $message = "Error updating provider details: " . $conn->error;
                    $message_type = "error";
                }
            } else {
                $insert = $conn->prepare("INSERT INTO providers (user_id, experience, location, bio) VALUES (?, ?, ?, ?)");
                $insert->bind_param("isss", $user_id, $experience, $location, $bio);
                if ($insert->execute()) {
                    if ($message_type === "success") {
                        $message = "Profile and provider details added successfully!";
                    }
                } else {
                    $message = "Error inserting provider details: " . $conn->error;
                    $message_type = "error";
                }
            }
        }
    }
}

// Fetch current user info
$query = $conn->prepare("SELECT * FROM users WHERE id=?");
$query->bind_param("i", $user_id);
$query->execute();
$current_user = $query->get_result()->fetch_assoc();

if ($role === 'provider') {
    $prov = $conn->query("SELECT * FROM providers WHERE user_id = $user_id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f76d2b;
            --primary-hover: #e05b1a;
            --secondary-color: #4a5568;
            --success-color: #28a745;
            --error-color: #dc3545;
            --light-bg: #f8f9fa;
            --dark-text: #2d3748;
            --light-text: #718096;
            --border-color: #e2e8f0;
            --card-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--light-bg); 
            padding: 20px;
            color: var(--dark-text);
            line-height: 1.6;
        }
        
        .container { 
            max-width: 900px; 
            margin: 20px auto; 
            background: #ffffff; 
            padding: 0;
            border-radius: 12px; 
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(120deg, #f76d2b, #ff9b5c);
            padding: 25px 30px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header h2 {
            font-weight: 600;
            font-size: 1.8rem;
            margin: 0;
        }
        
        .header p {
            margin-top: 5px;
            opacity: 0.9;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .content {
            padding: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .profile-sidebar {
            background: var(--light-bg);
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .profile-image-container { 
            margin-bottom: 20px; 
        }
        
        .profile-image { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin: 0 auto 15px;
        }
        
        .image-upload { 
            position: relative;
            margin-top: 15px;
        }
        
        .image-upload-btn { 
            background: white; 
            color: var(--primary-color);
            padding: 10px 18px; 
            border-radius: 6px; 
            cursor: pointer; 
            border: 1px dashed var(--primary-color);
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .image-upload-btn:hover { 
            background: var(--primary-color);
            color: white;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .section-title i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        label { 
            font-weight: 500; 
            display: block; 
            margin-bottom: 8px; 
            color: var(--secondary-color);
        }
        
        .required::after {
            content: " *";
            color: var(--error-color);
        }
        
        input, textarea, select { 
            width: 100%; 
            padding: 14px 16px; 
            border-radius: 8px; 
            border: 1px solid var(--border-color); 
            font-family: inherit;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(247, 109, 43, 0.2);
        }
        
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn { 
            padding: 14px 28px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.3s;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary { 
            background: var(--primary-color); 
            color: white; 
        }
        
        .btn-primary:hover { 
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(247, 109, 43, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--light-bg);
        }
        
        .alert { 
            padding: 16px 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success { 
            background: #e6f7ee; 
            color: #0d6832; 
            border-left: 4px solid var(--success-color); 
        }
        
        .alert-error { 
            background: #fde8e8; 
            color: #8b1a1a; 
            border-left: 4px solid var(--error-color); 
        }
        
        .error { 
            color: var(--error-color); 
            font-size: 14px; 
            margin-top: 6px; 
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--light-text);
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: #ffe4cc;
            color: #cc5500;
        }
        
        .role-provider {
            background: #ccf0ff;
            color: #0066cc;
        }
        
        .role-customer {
            background: #d6f5d6;
            color: #228b22;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h2>Edit My Profile</h2>
            <p>Update your personal and professional information</p>
        </div>
        <div class="user-badge">
            <i class="fas fa-user"></i>
            <?php echo htmlspecialchars($user['name']); ?>
            <div class="role-badge role-<?php echo $role; ?>"><?php echo $role; ?></div>
        </div>
    </div>

    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="<?= $message_type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" novalidate>
            <div class="form-grid">
                <div class="profile-sidebar">
                    <div class="profile-image-container">
                        <img src="<?= htmlspecialchars($current_user['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($current_user['name']) . '&background=f76d2b&color=fff') ?>" 
                             alt="Profile Image" class="profile-image" id="profileImagePreview">
                        <div class="image-upload">
                            <label for="profile_image" class="image-upload-btn">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;">
                        </div>
                        <div class="error" id="imageError"></div>
                    </div>
                    <p>Upload a clear photo of yourself for your profile. Max size 2MB.</p>
                </div>

                <div class="form-main">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-circle"></i>
                            <span>Personal Information</span>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="required">Full Name</label>
                                <input type="text" name="name" id="name" required value="<?= htmlspecialchars($current_user['name']) ?>">
                                <div class="error" id="nameError"></div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" name="email" id="email" required value="<?= htmlspecialchars($current_user['email']) ?>">
                                <div class="error" id="emailError"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>" 
                                   pattern="[6-9][0-9]{9}" title="Phone number must be 10 digits starting with 6-9">
                            <div class="error" id="phoneError"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Location Details</span>
                        </div>
                        
                        <?php if ($role === 'customer'): ?>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" name="address" id="address" value="<?= htmlspecialchars($current_user['address'] ?? '') ?>">
                            </div>
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" name="city" id="city" value="<?= htmlspecialchars($current_user['city'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="state">State</label>
                                <input type="text" name="state" id="state" value="<?= htmlspecialchars($current_user['state'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pincode">Postal Code</label>
                            <input type="text" name="pincode" id="pincode" value="<?= htmlspecialchars($current_user['pincode'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-lock"></i>
                            <span>Security</span>
                        </div>
                        
                        <div class="form-group password-toggle">
                            <label for="password">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" id="password" placeholder="••••••••">
                            <span class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </span>
                            <div class="error" id="passwordError"></div>
                        </div>
                    </div>

                    <?php if ($role === 'provider'): ?>
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-briefcase"></i>
                                <span>Professional Information</span>
                            </div>

                            <div class="form-group">
                                <label for="experience">Years of Experience</label>
                                <input type="text" name="experience" id="experience" value="<?= htmlspecialchars($prov['experience'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="location" class="required">Service Location</label>
                                <input type="text" name="location" id="location" required value="<?= htmlspecialchars($prov['location'] ?? '') ?>">
                                <div class="error" id="locationError"></div>
                            </div>

                            <div class="form-group">
                                <label for="bio">Professional Bio</label>
                                <textarea name="bio" id="bio" rows="4" placeholder="Tell us about your services and expertise"><?= htmlspecialchars($prov['bio'] ?? '') ?></textarea>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="<?php echo $dashboard; ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Preview image when selected
    document.getElementById('profile_image').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                document.getElementById('imageError').textContent = 'Please select a valid image file (JPEG, PNG, GIF).';
                this.value = '';
                return;
            }
            
            // Validate file size (2MB max)
            if (file.size > 2000000) {
                document.getElementById('imageError').textContent = 'Image must be less than 2MB.';
                this.value = '';
                return;
            }
            
            document.getElementById('imageError').textContent = '';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profileImagePreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate name
        const name = document.getElementById('name');
        if (!name.value.trim()) {
            document.getElementById('nameError').textContent = 'Name is required.';
            isValid = false;
        } else {
            document.getElementById('nameError').textContent = '';
        }
        
        // Validate email
        const email = document.getElementById('email');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email.value.trim()) {
            document.getElementById('emailError').textContent = 'Email is required.';
            isValid = false;
        } else if (!emailPattern.test(email.value)) {
            document.getElementById('emailError').textContent = 'Please enter a valid email address.';
            isValid = false;
        } else {
            document.getElementById('emailError').textContent = '';
        }
        
        // Validate phone
        const phone = document.getElementById('phone');
        const phonePattern = /^[6-9]\d{9}$/;
        if (phone.value && !phonePattern.test(phone.value)) {
            document.getElementById('phoneError').textContent = 'Please enter a valid phone number (10 digits starting with 6-9).';
            isValid = false;
        } else {
            document.getElementById('phoneError').textContent = '';
        }
        
        // Validate provider location (mandatory for providers)
        <?php if ($role === 'provider'): ?>
            const location = document.getElementById('location');
            if (!location.value.trim()) {
                document.getElementById('locationError').textContent = 'Service location is required.';
                isValid = false;
            } else {
                document.getElementById('locationError').textContent = '';
            }
        <?php endif; ?>
        
        if (!isValid) {
            e.preventDefault();
            // Scroll to first error
            const firstError = document.querySelector('.error:not(:empty)');
            if (firstError) {
                firstError.closest('.form-group').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
</script>
</body>
</html>