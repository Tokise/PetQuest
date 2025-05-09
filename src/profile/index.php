<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Fetch user data with cover image
$stmt = $conn->prepare("SELECT id, name, email, profile_picture, cover_picture, bio, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get pet count
$petStmt = $conn->prepare("SELECT COUNT(*) as pet_count FROM pets WHERE owner_id = ?");
$petStmt->bind_param("i", $_SESSION['user_id']);
$petStmt->execute();
$petResult = $petStmt->get_result();
$petCount = $petResult->fetch_assoc()['pet_count'];

// Get memories count
$memoryStmt = $conn->prepare("SELECT COUNT(*) as memory_count FROM memories WHERE user_id = ?");
$memoryStmt->bind_param("i", $_SESSION['user_id']);
$memoryStmt->execute();
$memoryResult = $memoryStmt->get_result();
$memoryCount = $memoryResult->fetch_assoc()['memory_count'];

// Get user's memories for the grid
$profile_user_id = $_SESSION['user_id']; // Assuming this is the user's own profile page

$memories_stmt = $conn->prepare("
    SELECT 
        m.id AS memory_id, 
        m.description AS memory_description, 
        m.created_at AS memory_created_at,
        mm.media_type,
        mm.file_path
    FROM memories m
    LEFT JOIN (
        SELECT 
            memory_id, 
            media_type, 
            file_path,
            ROW_NUMBER() OVER(PARTITION BY memory_id ORDER BY sort_order ASC, id ASC) as rn
        FROM memory_media
    ) mm ON m.id = mm.memory_id AND mm.rn = 1
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$memories_stmt->bind_param("i", $profile_user_id);
$memories_stmt->execute();
$memories_result = $memories_stmt->get_result();

// Get user's pets
$pets_stmt = $conn->prepare("SELECT id, name, species, image_path, status FROM pets WHERE owner_id = ? ORDER BY created_at DESC");
$pets_stmt->bind_param("i", $_SESSION['user_id']);
$pets_stmt->execute();
$pets = $pets_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile - PetQuest</title>
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../dashboard/css/dashboard.css">
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
                <?php
                    // Force browser to reload image by adding timestamp
                    $timestamp = time();
                    $coverImageUrl = !empty($user['cover_picture']) ? 
                        SITE_URL . '/uploads/cover/' . htmlspecialchars($user['cover_picture']) . '?t=' . $timestamp : 
                        SITE_URL . '/assets/images/default-cover.jpg';
                ?>
                <img src="<?php echo $coverImageUrl; ?>" 
                     alt="Cover Photo" 
                     class="profile-cover-image"
                     onerror="this.onerror=null; this.src='<?php echo SITE_URL . '/assets/images/default-cover.jpg'; ?>'">
                
                <div class="profile-info-overlay">
                    <div class="profile-image-container">
                        <?php
                            // Also add cache busting for profile image
                            $profileImageUrl = !empty($user['profile_picture']) ? 
                                SITE_URL . '/uploads/profile/' . htmlspecialchars($user['profile_picture']) . '?t=' . $timestamp : 
                                SITE_URL . '/assets/images/default-profile.png';
                        ?>
                        <img src="<?php echo $profileImageUrl; ?>" 
                             alt="Profile Picture" 
                             class="profile-image-large"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL . '/assets/images/default-profile.png'; ?>'">
                    </div>
                         
                    <div class="profile-text-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h1>
                        <?php if (!empty($user['bio'])): ?>
                            <p class="profile-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                        <?php else: ?>
                            <p class="empty-bio">No bio added yet. Click 'Edit Profile' to add one!</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button class="edit-cover-btn" id="openCoverEditBtn">
                    <i class="fas fa-camera"></i> Edit Cover
                </button>
                
                <button class="edit-profile-btn" id="openProfileEditBtn">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
            </div>
            
            <!-- Profile Edit Modal -->
            <div class="modal" id="profileEditModal" style="display: none;">
                <div class="modal-content">
                    <span class="close-btn" id="closeProfileModal">&times;</span>
                    <h2>Edit Profile</h2>
                    
                    <form action="update_profile.php" method="POST" enctype="multipart/form-data" id="profileEditForm">
                        <div class="modal-tabs">
                            <button type="button" class="tab-btn active" data-tab="profile-info">Profile Info</button>
                            <button type="button" class="tab-btn" data-tab="profile-picture">Profile Picture</button>
                            <button type="button" class="tab-btn" data-tab="cover-picture">Cover Photo</button>
                        </div>
                        
                        <div class="tab-content active" id="profile-info">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself and your pets..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                <small class="form-text">Maximum 500 characters</small>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="profile-picture">
                            <div class="profile-image-preview">
                                <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . '/uploads/profile/' . htmlspecialchars($user['profile_picture']) : SITE_URL . '/assets/images/default-profile.png'; ?>" 
                                     alt="Profile Picture" 
                                     class="preview-image" id="profileImagePreview"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL . '/assets/images/default-profile.png'; ?>'">
                            </div>
                            
                            <div class="form-group">
                                <label for="profile_picture" class="file-upload-btn">
                                    <i class="fas fa-upload"></i> Choose Profile Picture
                                </label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" hidden>
                                <small class="form-text">Allowed formats: JPG, JPEG, PNG, GIF (Max size: 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="cover-picture">
                            <div class="cover-image-preview">
                                <img src="<?php echo !empty($user['cover_picture']) ? SITE_URL . '/uploads/cover/' . htmlspecialchars($user['cover_picture']) : SITE_URL . '/assets/images/default-cover.jpg'; ?>" 
                                     alt="Cover Picture" 
                                     class="preview-image" id="coverImagePreview"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL . '/assets/images/default-cover.jpg'; ?>'">
                            </div>
                            
                            <div class="form-group">
                                <label for="cover_picture" class="file-upload-btn">
                                    <i class="fas fa-upload"></i> Choose Cover Photo
                                </label>
                                <input type="file" id="cover_picture" name="cover_picture" accept="image/*" hidden>
                                <small class="form-text">Recommended size: 1200 x 300 pixels</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" id="cancelEditBtn" class="btn-secondary">Cancel</button>
                            <button type="submit" name="update_profile" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="profile-content">
                <!-- Left Column: User Info -->
                <div class="profile-left-column">
                    <div class="profile-card">
                        <h2>About</h2>
                        <div class="profile-info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo isset($user['email']) ? htmlspecialchars($user['email']) : 'No email provided'; ?></span>
                        </div>
                        
                        <div class="profile-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined <?php 
                                if (isset($user['created_at']) && !empty($user['created_at'])) {
                                    echo date('F Y', strtotime($user['created_at']));
                                } else {
                                    echo 'Unknown date';
                                }
                            ?></span>
                        </div>
                        
                        <div class="profile-info-item">
                            <i class="fas fa-paw"></i>
                            <span><?php echo $petCount; ?> Pets</span>
                        </div>
                        
                        <div class="profile-info-item">
                            <i class="fas fa-camera"></i>
                            <span><?php echo $memoryCount; ?> Memories</span>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <h2>My Pets</h2>
                        <?php if ($pets->num_rows === 0): ?>
                            <div class="empty-pets">
                                <p>You haven't added any pets yet.</p>
                                <a href="../pets/add-pet.php" class="btn btn-sm btn-primary">Add Pet</a>
                            </div>
                        <?php else: ?>
                            <div class="pet-list">
                                <?php while ($pet = $pets->fetch_assoc()): ?>
                                    <div class="pet-list-item">
                                        <div class="pet-list-image">
                                            <?php if (!empty($pet['image_path'])): ?>
                                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pet['image_path']); ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>">
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
                        <div class="memories-header">
                            <h2>Pet Memories</h2>
                            <a href="../dashboard/dashboard.php" class="add-memory-btn">
                                <i class="fas fa-plus"></i> Add Memory
                            </a>
                        </div>
                        
                        <div class="memories-grid">
                            <?php if ($memories_result->num_rows === 0): ?>
                                <div class="empty-memories">
                                    <i class="fas fa-images"></i>
                                    <p>Share your pet memories</p>
                                    <p class="empty-subtitle">Add photos and stories of your pet adventures</p>
                                </div>
                            <?php else: ?>
                                <?php while ($memory_item = $memories_result->fetch_assoc()): 
                                    $media_src_path = '';
                                    if (!empty($memory_item['file_path'])) {
                                        // Path is relative to /uploads/memories/
                                        // e.g., images/image.jpg or videos/video.mp4
                                        $media_src_path = '../../uploads/memories/' . htmlspecialchars($memory_item['file_path']);
                                    }
                                ?>
                                    <div class="memory-card" 
                                         data-memory-id="<?php echo $memory_item['memory_id']; ?>"
                                         data-description="<?php echo htmlspecialchars($memory_item['memory_description'] ?? 'No description.'); ?>"
                                         data-author-name="<?php echo htmlspecialchars($user['name']); ?>" 
                                         data-created-at="<?php echo htmlspecialchars($memory_item['memory_created_at'] ?? ''); ?>">
                                        
                                        <?php if (!empty($memory_item['file_path']) && $memory_item['media_type'] === 'image'): ?>
                                            <a href="#" class="memory-media-link" 
                                               data-type="image" 
                                               data-src="<?php echo $media_src_path; ?>" 
                                               data-memory-id="<?php echo $memory_item['memory_id']; ?>">
                                                <img src="<?php echo $media_src_path; ?>" 
                                                     alt="Pet Memory Image" 
                                                     class="memory-thumbnail"
                                                     onerror="this.style.display='none'; console.error('Error loading image: <?php echo $media_src_path; ?>')">
                                            </a>
                                        <?php elseif (!empty($memory_item['file_path']) && $memory_item['media_type'] === 'video'): ?>
                                            <a href="#" class="memory-media-link" 
                                               data-type="video" 
                                               data-src="<?php echo $media_src_path; ?>" 
                                               data-memory-id="<?php echo $memory_item['memory_id']; ?>">
                                                <div class="video-placeholder-thumbnail" style="background-color: #000; color: #fff; display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 16/9; border-radius: 8px; position: relative; cursor: pointer;">
                                                    <i class="fas fa-play-circle" style="font-size: 3em; z-index: 1;"></i>
                                                     <video muted loop style="position: absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; border-radius: 8px; opacity:0.7;" preload="metadata">
                                                        <source src="<?php echo $media_src_path; ?>#t=0.5" type="video/<?php echo pathinfo($memory_item['file_path'], PATHINFO_EXTENSION); ?>">
                                                    </video>
                                                </div>
                                            </a>
                                        <?php elseif (empty($memory_item['file_path'])): // Case where memory has description but no media ?>
                                            <div class="memory-text-only-placeholder" style="display: flex; align-items: center; justify-content: center; width:100%; aspect-ratio: 16/9; background-color: #f0f0f0; border-radius: 8px;">
                                                <i class="far fa-file-alt" style="font-size: 3em; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="memory-overlay">
                                            <span class="memory-date"><?php echo date('M d, Y', strtotime($memory_item['memory_created_at'])); ?></span>
                                            <?php if(!empty($memory_item['memory_description'])): ?>
                                                <p><?php echo htmlspecialchars(mb_strimwidth($memory_item['memory_description'], 0, 100, "...")); ?></p>
                                            <?php endif; ?>
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

    <?php include_once '../../includes/media-viewer-modal.php'; ?>

    <script src="js/profile.js"></script>
    <script src="../../assets/js/media-modal.js"></script>
</body>
</html>
