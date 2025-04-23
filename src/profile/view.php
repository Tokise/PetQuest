<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Get the profile ID from URL
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id === 0) {
    header('Location: index.php');
    exit();
}

// Fetch user data with counts
$stmt = $conn->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM pets WHERE owner_id = u.id) as pet_count,
        (SELECT COUNT(*) FROM memories WHERE user_id = u.id) as memory_count
    FROM users u 
    WHERE u.id = ?
");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: ../dashboard/dashboard.php');
    exit();
}

// Get user's public pets
$pets_stmt = $conn->prepare("
    SELECT id, name, species, image_path, status 
    FROM pets 
    WHERE owner_id = ? 
    ORDER BY created_at DESC
");
$pets_stmt->bind_param("i", $profile_id);
$pets_stmt->execute();
$pets = $pets_stmt->get_result();

// Get user's public memories
$memories_stmt = $conn->prepare("
    SELECT * FROM memories 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 6
");
$memories_stmt->bind_param("i", $profile_id);
$memories_stmt->execute();
$memories = $memories_stmt->get_result();

// Store the viewed profile data separately from the logged-in user data
$viewed_profile = $user;
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($viewed_profile['name']); ?>'s Profile - PetQuest</title>
    <link rel="stylesheet" href="../profile/css/profile.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <div class="main-content">
            <!-- Cover Photo and Profile Info -->
            <div class="profile-cover-section">
                <img src="<?php echo !empty($viewed_profile['cover_picture']) ? 
                    SITE_URL . '/uploads/cover/' . htmlspecialchars($viewed_profile['cover_picture']) : 
                    SITE_URL . '/assets/images/default-cover.jpg'; ?>" 
                     alt="Cover Photo" 
                     class="profile-cover-image">
                
                <div class="profile-info-overlay">
                    <div class="profile-image-container">
                        <img src="<?php echo !empty($viewed_profile['profile_picture']) ? 
                            SITE_URL . '/uploads/profile/' . htmlspecialchars($viewed_profile['profile_picture']) : 
                            SITE_URL . '/assets/images/default-profile.png'; ?>" 
                             alt="Profile Picture" 
                             class="profile-image-large">
                    </div>
                         
                    <div class="profile-text-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($viewed_profile['name']); ?></h1>
                        <?php if (!empty($viewed_profile['bio'])): ?>
                            <p class="profile-bio"><?php echo htmlspecialchars($viewed_profile['bio']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <!-- Left Column: User Info -->
                <div class="profile-left-column">
                    <div class="profile-card">
                        <h2>About</h2>
                        <div class="profile-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined <?php echo !empty($viewed_profile['created_at']) ? 
                                date('F Y', strtotime($viewed_profile['created_at'])) : 
                                'Unknown date'; ?></span>
                        </div>
                        
                        <div class="profile-info-item">
                            <i class="fas fa-paw"></i>
                            <span><?php echo (int)$viewed_profile['pet_count']; ?> Pets</span>
                        </div>
                        
                        <div class="profile-info-item">
                            <i class="fas fa-camera"></i>
                            <span><?php echo (int)$viewed_profile['memory_count']; ?> Memories</span>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <h2>Pets</h2>
                        <?php if ($pets->num_rows === 0): ?>
                            <div class="empty-pets">
                                <p>No pets to show</p>
                            </div>
                        <?php else: ?>
                            <div class="pet-list">
                                <?php while ($pet = $pets->fetch_assoc()): ?>
                                    <div class="pet-list-item">
                                        <div class="pet-list-image">
                                            <?php if (!empty($pet['image_path'])): ?>
                                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pet['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($pet['name']); ?>">
                                            <?php else: ?>
                                                <div class="pet-image-placeholder">
                                                    <i class="fas fa-paw"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-list-info">
                                            <div class="pet-list-name">
                                                <?php echo htmlspecialchars($pet['name']); ?>
                                                <span class="pet-status-badge <?php echo strtolower($pet['status']); ?>">
                                                    <?php echo ucfirst($pet['status']); ?>
                                                </span>
                                            </div>
                                            <div class="pet-list-type"><?php echo htmlspecialchars($pet['species']); ?></div>
                                        </div>
                                        <a href="../details/pet-details.php?id=<?php echo $pet['id']; ?>" class="pet-list-action">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column: Memories -->
                <div class="profile-right-column">
                    <div class="profile-card memories-card">
                        <h2>Pet Memories</h2>
                        
                        <div class="memories-grid">
                            <?php if ($memories->num_rows === 0): ?>
                                <div class="empty-memories">
                                    <i class="fas fa-images"></i>
                                    <p>No memories to show</p>
                                </div>
                            <?php else: ?>
                                <?php while ($memory = $memories->fetch_assoc()): ?>
                                    <div class="memory-card">
                                        <?php if (!empty($memory['image_path'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/uploads/memories/<?php echo htmlspecialchars($memory['image_path']); ?>" 
                                                 alt="Pet Memory">
                                        <?php endif; ?>
                                        <div class="memory-overlay">
                                            <span class="memory-date"><?php echo date('M d, Y', strtotime($memory['created_at'])); ?></span>
                                            <p><?php echo htmlspecialchars($memory['description']); ?></p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
