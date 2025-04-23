<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to report a missing pet.";
    header("Location: ../../auth/login.php");
    exit();
}

// Initialize variables
$errors = [];
$pet_name = '';
$species = '';
$breed = '';
$color = '';
$age = '';
$gender = '';
$last_seen_date = '';
$last_seen_location = '';
$description = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $pet_name = trim($_POST['pet_name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $last_seen_date = trim($_POST['last_seen_date'] ?? '');
    $last_seen_location = trim($_POST['last_seen_location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate input
    if (empty($pet_name) || empty($species) || empty($last_seen_date) || empty($last_seen_location)) {
        $errors[] = "Please fill in all required fields";
    }
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['pet_image']) && $_FILES['pet_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['pet_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($filetype), $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
        } else {
            $upload_path = '../../uploads/pets/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $filetype;
            $upload_file = $upload_path . $new_filename;
            
            if (move_uploaded_file($_FILES['pet_image']['tmp_name'], $upload_file)) {
                $image_path = 'uploads/pets/' . $new_filename;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    if (empty($errors)) {
        // First insert into pets table
        $stmt = $conn->prepare("INSERT INTO pets (owner_id, name, species, breed, color, age, gender, description, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'missing')");
        $stmt->bind_param("issssssss", $user_id, $pet_name, $species, $breed, $color, $age, $gender, $description, $image_path);
        
        if ($stmt->execute()) {
            $pet_id = $stmt->insert_id;
            
            // Now insert into missing_reports table
            // Get user email for contact info
            $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $user = $userResult->fetch_assoc();
            $contact_info = $user['email'] ?? '';
            
            $stmt2 = $conn->prepare("INSERT INTO missing_reports (pet_id, owner_id, last_seen_date, last_seen_location, contact_info, additional_info, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt2->bind_param("iissss", $pet_id, $user_id, $last_seen_date, $last_seen_location, $contact_info, $description);
            $stmt2->execute();
            
            // Generate QR Code URL
            $qr_data = SITE_URL . "/pet-details.php?id=" . $pet_id;
            $qr_filename = uniqid() . '_qr.png';
            $qr_path = '../../uploads/qrcodes/' . $qr_filename;
            
            // Create QR Code directory if it doesn't exist
            if (!file_exists('../../uploads/qrcodes/')) {
                mkdir('../../uploads/qrcodes/', 0777, true);
            }
            
            // Generate QR Code using Google Charts API
            $qr_image = file_get_contents('https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_data));
            file_put_contents($qr_path, $qr_image);
            
            // Update pet record with QR code URL
            $qr_url = 'uploads/qrcodes/' . $qr_filename;
            $stmt = $conn->prepare("UPDATE pets SET qr_code_path = ? WHERE id = ?");
            $stmt->bind_param("si", $qr_url, $pet_id);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Pet reported successfully! You can download the QR code from your dashboard.";
            header("Location: ../dashboard/dashboard.php");
            exit();
        } else {
            $errors[] = "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Missing Pet - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../dashboard/css/dashboard.css">
    <link rel="stylesheet" href="css/report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <div class="report-container">
            <div class="report-header">
                <h2>Report Missing Pet</h2>
                <p>Fill in the details below to report your missing pet. The more information you provide, the better chance of finding your pet.</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form class="report-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" onsubmit="return handleSubmit(this);">
                <div class="form-group">
                    <label for="pet_name" class="required">Pet Name</label>
                    <input type="text" id="pet_name" name="pet_name" class="form-control" 
                           placeholder="Enter your pet's name" value="<?php echo $pet_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="species" class="required">Pet Type</label>
                    <select id="species" name="species" class="form-control" required>
                        <option value="">Select pet type</option>
                        <option value="Dog" <?php echo $species === 'Dog' ? 'selected' : ''; ?>>Dog</option>
                        <option value="Cat" <?php echo $species === 'Cat' ? 'selected' : ''; ?>>Cat</option>
                        <option value="Bird" <?php echo $species === 'Bird' ? 'selected' : ''; ?>>Bird</option>
                        <option value="Rabbit" <?php echo $species === 'Rabbit' ? 'selected' : ''; ?>>Rabbit</option>
                        <option value="Other" <?php echo $species === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="breed">Breed</label>
                    <input type="text" id="breed" name="breed" class="form-control" 
                           placeholder="Enter breed (if known)" value="<?php echo $breed; ?>">
                </div>
                
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="text" id="color" name="color" class="form-control" 
                           placeholder="Enter color(s)" value="<?php echo $color; ?>">
                </div>
                
                <div class="form-group">
                    <label for="age">Age (Years)</label>
                    <input type="number" id="age" name="age" class="form-control" 
                           placeholder="Enter age" value="<?php echo $age; ?>">
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="Unknown" <?php echo $gender === 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                        <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="last_seen_date" class="required">Last Seen Date</label>
                    <input type="date" id="last_seen_date" name="last_seen_date" class="form-control" 
                           value="<?php echo $last_seen_date; ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="last_seen_location" class="required">Last Seen Location</label>
                    <input type="text" id="last_seen_location" name="last_seen_location" class="form-control" 
                           placeholder="Enter the location where your pet was last seen" 
                           value="<?php echo $last_seen_location; ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" 
                              placeholder="Provide any additional details that might help identify your pet"><?php echo $description; ?></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="pet_image">Pet Image</label>
                    <div class="image-upload" onclick="document.getElementById('pet_image').click();">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload an image of your pet</p>
                        <input type="file" id="pet_image" name="pet_image" accept="image/*" style="display: none;" 
                               onchange="previewImage(this, 'image_preview')">
                    </div>
                    <img id="image_preview" class="image-preview">
                    <p class="form-hint">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus-circle"></i> Report Missing Pet
                </button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
    function handleSubmit(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('btn-loading');
        return true;
    }

    // Preview uploaded image
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }
    </script>
</body>
</html> 