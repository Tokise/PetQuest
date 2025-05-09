<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in (optional for viewing profiles, but good for consistency with header)
if (!isset($_SESSION['user_id'])) {
    // Or handle as guest view if you want public profiles
    // For now, let's assume login is preferred to see profiles fully
    // header('Location: ../../auth/login.php');
    // exit();
}

$current_user_id = $_SESSION['user_id'] ?? null;

// Get the ID of the user whose profile is being viewed
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Handle error: No ID or invalid ID provided
    // For example, redirect to a 404 page or show an error message
    echo "Invalid user ID."; // Simple error for now
    exit();
}
$viewed_user_id = (int)$_GET['id'];

// If the viewed user is the current logged-in user, redirect to their own profile page
if ($current_user_id && $viewed_user_id === $current_user_id) {
    header('Location: index.php');
    exit();
}

// Fetch data for the user being viewed
$stmt_viewed_user = $conn->prepare("SELECT id, name, email, profile_picture, cover_picture, bio, created_at FROM users WHERE id = ?");
$stmt_viewed_user->bind_param("i", $viewed_user_id);
$stmt_viewed_user->execute();
$result_viewed_user = $stmt_viewed_user->get_result();

if ($result_viewed_user->num_rows === 0) {
    // Handle error: User not found
    echo "User not found."; // Simple error for now
    exit();
}
$viewed_user = $result_viewed_user->fetch_assoc();
$stmt_viewed_user->close();

// Fetch user data with counts
$stmt = $conn->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM pets WHERE owner_id = u.id) as pet_count,
        (SELECT COUNT(*) FROM memories WHERE user_id = u.id) as memory_count
    FROM users u 
    WHERE u.id = ?
");
$stmt->bind_param("i", $viewed_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // If the main user query itself fails for view.php, it implies user ID might not exist.
    // The earlier check for $result_viewed_user->num_rows === 0 should catch non-existent users.
    // Redirecting to dashboard might be confusing. Show a "user not found" or a generic error.
    echo "User data could not be loaded."; // Or redirect to an error page
    exit();
}

// Get user's public pets
$pets_stmt = $conn->prepare("
    SELECT id, name, species, image_path, status 
    FROM pets 
    WHERE owner_id = ? 
    ORDER BY created_at DESC
");
$pets_stmt->bind_param("i", $viewed_user_id);
$pets_stmt->execute();
$pets = $pets_stmt->get_result();

// Get user's public memories (UPDATED QUERY)
$memories_stmt = $conn->prepare("
    SELECT
        m.id AS memory_id,
        m.description AS memory_description,
        m.created_at AS memory_created_at,
        u.id AS author_id,
        u.name AS author_name,
        u.profile_picture AS author_profile_picture,
        mm_first.media_type,
        mm_first.file_path
    FROM memories m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN (
        SELECT
            memory_id,
            media_type,
            file_path,
            ROW_NUMBER() OVER(PARTITION BY memory_id ORDER BY sort_order ASC, id ASC) as rn
        FROM memory_media
    ) mm_first ON m.id = mm_first.memory_id AND mm_first.rn = 1
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$memories_stmt->bind_param("i", $viewed_user_id);
$memories_stmt->execute();
$memories_result = $memories_stmt->get_result(); // Changed variable name to avoid conflict

// Store the viewed profile data separately from the logged-in user data
$viewed_profile = $user;
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($viewed_user['name']); ?>'s Profile - PetQuest</title>
    <link rel="stylesheet" href="../profile/css/profile.css">
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
                            <?php if ($memories_result->num_rows === 0): ?>
                                <div class="empty-memories" style="text-align: center; padding: 20px; color: #777;">
                                    <i class="fas fa-images fa-2x" style="margin-bottom: 10px;"></i>
                                    <p>No memories to show yet.</p>
                                </div>
                            <?php else: ?>
                                <?php while ($memory = $memories_result->fetch_assoc()): ?>
                                    <?php
                                        $media_display_path = '';
                                        $media_modal_src_path = '';
                                        if (!empty($memory['file_path'])) {
                                            // Path for display in grid (relative to view.php)
                                            $media_display_path = '../../uploads/memories/' . htmlspecialchars($memory['file_path']);
                                            // Full path for modal's data-src (assuming SITE_URL is defined and ends without a slash)
                                            $media_modal_src_path = SITE_URL . '/uploads/memories/' . htmlspecialchars($memory['file_path']);
                                        }
                                    ?>
                                    <div class="memory-card-item"> <!-- Changed class from memory-card to avoid potential style conflicts if any, and to be more specific -->
                                        <?php if (!empty($memory['file_path'])): ?>
                                            <a href="#" class="memory-media-link"
                                               data-memory-id="<?php echo htmlspecialchars($memory['memory_id']); ?>"
                                               data-description="<?php echo htmlspecialchars($memory['memory_description'] ?? 'No description available.'); ?>"
                                               data-author-name="<?php echo htmlspecialchars($memory['author_name']); ?>"
                                               data-author-profile-pic="<?php echo !empty($memory['author_profile_picture']) ? SITE_URL . '/uploads/profile/' . htmlspecialchars($memory['author_profile_picture']) : SITE_URL . '/assets/images/default-profile.png'; ?>"
                                               data-created-at="<?php echo htmlspecialchars($memory['memory_created_at'] ?? ''); ?>"
                                               data-type="<?php echo htmlspecialchars($memory['media_type']); ?>"
                                               data-src="<?php echo $media_modal_src_path; ?>">

                                                <?php if ($memory['media_type'] === 'image'): ?>
                                                    <img src="<?php echo $media_display_path; ?>"
                                                         alt="Memory Image"
                                                         class="memory-grid-image"
                                                         onerror="this.style.display='none'; console.error('Error loading image: <?php echo $media_display_path; ?>')">
                                                <?php elseif ($memory['media_type'] === 'video'): ?>
                                                    <div class="memory-grid-video-container">
                                                        <video muted loop preload="metadata" class="memory-grid-video">
                                                            <source src="<?php echo $media_display_path; ?>#t=0.5" type="video/<?php echo pathinfo($memory['file_path'], PATHINFO_EXTENSION); ?>">
                                                        </video>
                                                        <div class="play-icon-overlay"><i class="fas fa-play"></i></div>
                                                    </div>
                                                <?php else: // Fallback for unknown media type or no media ?>
                                                    <div class="memory-placeholder-text">
                                                        <p><?php echo nl2br(htmlspecialchars(substr($memory['memory_description'] ?? 'View Memory', 0, 100))); ?>...</p>
                                                        <small>Click to see details</small>
                                                    </div>
                                                <?php endif; ?>
                                            </a>
                                        <?php else: // No media, just text - make card clickable too if desired for consistency with modal interaction ?>
                                             <a href="#" class="memory-media-link"
                                               data-memory-id="<?php echo htmlspecialchars($memory['memory_id']); ?>"
                                               data-description="<?php echo htmlspecialchars($memory['memory_description'] ?? 'No description available.'); ?>"
                                               data-author-name="<?php echo htmlspecialchars($memory['author_name']); ?>"
                                               data-author-profile-pic="<?php echo !empty($memory['author_profile_picture']) ? SITE_URL . '/uploads/profile/' . htmlspecialchars($memory['author_profile_picture']) : SITE_URL . '/assets/images/default-profile.png'; ?>"
                                               data-created-at="<?php echo htmlspecialchars($memory['memory_created_at'] ?? ''); ?>"
                                               data-type="text"
                                               data-src="">  <!-- No media source -->
                                                <div class="memory-text-only-card">
                                                     <p class="memory-description-preview"><?php echo nl2br(htmlspecialchars(substr($memory['memory_description'] ?? 'Memory (no media)', 0, 150))); ?><?php if(strlen($memory['memory_description'] ?? '') > 150) echo "..."; ?></p>
                                                     <small class="memory-date-preview"><?php echo date('M d, Y', strtotime($memory['memory_created_at'])); ?></small>
                                                </div>
                                             </a>
                                        <?php endif; ?>
                                        
                                        <!-- Add the memory overlay for date and description -->
                                        <div class="memory-overlay">
                                            <span class="memory-date"><?php echo date('M d, Y', strtotime($memory['memory_created_at'])); ?></span>
                                            <?php if(!empty($memory['memory_description'])): ?>
                                                <p><?php echo htmlspecialchars(mb_strimwidth($memory['memory_description'], 0, 100, "...")); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                            <?php $memories_stmt->close(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once '../../includes/media-viewer-modal.php'; ?>
    <script src="../../assets/js/media-modal.js"></script> 
    <!-- We will NOT include js/profile.js here as it handles editing for one's own profile -->
</body>
</html>
