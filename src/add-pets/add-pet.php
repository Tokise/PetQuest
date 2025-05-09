<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to add a pet.";
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
$description = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $pet_name = trim($_POST['pet_name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate input
    if (empty($pet_name) || empty($species)) {
        $errors[] = "Pet Name and Pet Type are required fields.";
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
        // Insert into pets table with status 'safe' (or 'active')
        $stmt = $conn->prepare("INSERT INTO pets (owner_id, name, species, breed, color, age, gender, description, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'safe')");
        $stmt->bind_param("issssssss", $user_id, $pet_name, $species, $breed, $color, $age, $gender, $description, $image_path);
        
        if ($stmt->execute()) {
            $pet_id = $stmt->insert_id;
            
            // Generate QR Code URL
            $qr_data = SITE_URL . "/src/details/pet-details.php?id=" . $pet_id;
            $qr_filename = uniqid() . '_qr.png';
            $qr_path = '../../uploads/qrcodes/' . $qr_filename;
            
            if (!file_exists('../../uploads/qrcodes/')) {
                mkdir('../../uploads/qrcodes/', 0777, true);
            }
            
            $qr_image = file_get_contents('https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_data));
            file_put_contents($qr_path, $qr_image);
            
            $qr_url = 'uploads/qrcodes/' . $qr_filename;
            $stmt_qr = $conn->prepare("UPDATE pets SET qr_code_path = ? WHERE id = ?");
            $stmt_qr->bind_param("si", $qr_url, $pet_id);
            $stmt_qr->execute();
            
            $_SESSION['success_message'] = "Pet added successfully! You can view your pet on the dashboard.";
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
    <title>Add New Pet - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../dashboard/css/dashboard.css">
    <link rel="stylesheet" href="css/add-pet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <div class="report-container">
            <div class="report-header">
                <h2>Add New Pet</h2>
                <p>Fill in the details below to add your pet to your profile. You can report them missing later if needed.</p>
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
                    <label for="description">Description / Notes</label>
                    <textarea id="description" name="description" class="form-control" 
                              placeholder="Provide any additional details or notes about your pet (e.g., temperament, medical conditions)"><?php echo $description; ?></textarea>
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
                    <i class="fas fa-plus-circle"></i> Add Pet
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