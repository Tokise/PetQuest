<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If no name field found, try to retrieve it from SESSION
if(!isset($user['name']) && isset($_SESSION['username'])) {
    $user['name'] = $_SESSION['username'];
}

// Get statistics
$stats = [
    'total_pets' => 0,
    'missing_pets' => 0,
    'found_pets' => 0,
    'unread_messages' => 0
];

// Total pets
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pets WHERE owner_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_pets'] = $result->fetch_assoc()['count'];

// Missing pets
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pets WHERE owner_id = ? AND status = 'missing'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['missing_pets'] = $result->fetch_assoc()['count'];

// Found pets
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pets WHERE owner_id = ? AND status = 'found'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['found_pets'] = $result->fetch_assoc()['count'];

// Unread messages
$stmt = $conn->prepare("
    SELECT 
        SUM(owner_unread_count) as count 
    FROM conversations
    WHERE owner_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['unread_messages'] = $result->fetch_assoc()['count'] ?: 0;

// Get user's pets for "My Pets" section
$pets_stmt = $conn->prepare("SELECT id, name, species, image_path, status FROM pets WHERE owner_id = ? ORDER BY created_at DESC");
$pets_stmt->bind_param("i", $user_id);
$pets_stmt->execute();
$pets = $pets_stmt->get_result();

// Get recent pets
$stmt = $conn->prepare("
    SELECT p.*, 
           COALESCE((SELECT SUM(owner_unread_count + founder_unread_count) 
                     FROM conversations 
                     WHERE pet_id = p.id), 0) as message_count,
           mr.last_seen_date, 
           mr.last_seen_location
    FROM pets p 
    LEFT JOIN missing_reports mr ON p.id = mr.pet_id
    WHERE p.owner_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 6
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_pets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="../profile/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Content -->
            <div class="dashboard-content fade-in">
                

                <div class="dashboard-content-grid">
                    <!-- New Wrapper for Feed and Creation Form -->
                    <div class="feed-column-wrapper">

                        <!-- Memories Section -->
                        <div class="memories-creation-section">
                            <div class="create-memory-card">
                                <div class="memory-header">
                                    <img src="<?php echo !empty($user['profile_picture']) ? '../../uploads/profile/' . htmlspecialchars($user['profile_picture']) : '../../assets/images/default-profile.png'; ?>" 
                                         alt="Profile Picture" 
                                         class="user-avatar-small">
                                    <span class="memory-prompt">Share a pet memory...</span>
                                </div>
                                <form action="../profile/upload_memory.php" method="POST" enctype="multipart/form-data" class="memory-form">
                                    <textarea name="description" placeholder="Write something about this memory..."></textarea>
                                    <div class="memory-actions">
                                        <div class="memory-upload">
                                            <label for="memory_image">
                                                <i class="fas fa-image"></i> Add Photo
                                            </label>
                                            <input type="file" id="memory_image" name="memory_image" accept="image/*" hidden>
                                        </div>
                                        <div class="memory-upload">
                                            <label for="memory_video">
                                                <i class="fas fa-video"></i> Add Video
                                            </label>
                                            <input type="file" id="memory_video" name="memory_video" accept="video/*" hidden>
                                        </div>
                                        <button type="submit" name="upload_memory">
                                            <i class="fas fa-share"></i>
                                            Share Memory
                                        </button>
                                    </div>
                                    <div id="image-preview" class="image-preview"></div>
                                    <div id="video-preview-name" style="margin-top: 5px; font-size: 0.9em; color: #555;"></div>
                                </form>
                            </div>
                        </div> <!-- End memories-creation-section -->

                        <!-- Recent Memories -->
                        <div class="recent-memories">
                            <?php
                            // Updated query to fetch memories and their first media item
                            $user_id_for_feed = $_SESSION['user_id']; // For now, current user's memories
                            // To make it a global feed, you would remove/comment out "WHERE m.user_id = ?"
                            // and its binding. Or, for a curated feed, adjust the WHERE clause.

                            $memories_feed_stmt = $conn->prepare("
                                SELECT 
                                    m.id AS memory_id, 
                                    m.description AS memory_description, 
                                    m.created_at AS memory_created_at,
                                    u.id AS author_id,
                                    u.name AS author_name, 
                                    u.profile_picture AS author_profile_picture,
                                    mm.media_type,
                                    mm.file_path
                                FROM memories m
                                JOIN users u ON m.user_id = u.id
                                LEFT JOIN (
                                    SELECT 
                                        memory_id, 
                                        media_type, 
                                        file_path,
                                        ROW_NUMBER() OVER(PARTITION BY memory_id ORDER BY sort_order ASC, id ASC) as rn
                                    FROM memory_media
                                ) mm ON m.id = mm.memory_id AND mm.rn = 1
                                /* WHERE m.user_id = ? */ /* Uncomment for user-specific feed */
                                ORDER BY m.created_at DESC
                                LIMIT 20
                            ");
                            // $memories_feed_stmt->bind_param("i", $user_id_for_feed); // Uncomment if WHERE m.user_id = ? is active
                            $memories_feed_stmt->execute();
                            $feed_items = $memories_feed_stmt->get_result();
                            
                            if ($feed_items->num_rows === 0): ?>
                                <div class="empty-feed" style="text-align: center; padding: 20px; color: #777;">
                                    <i class="fas fa-stream fa-3x" style="margin-bottom: 10px;"></i>
                                    <p>No memories to show in the feed yet.</p>
                                    <p>Why not share one using the form above?</p>
                                </div>
                            <?php else:
                                while ($item = $feed_items->fetch_assoc()):
                                    $media_src_path = '';
                                    if (!empty($item['file_path'])) {
                                         $media_src_path = '../../uploads/memories/' . htmlspecialchars($item['file_path']);
                                    }
                            ?>
                                    <div class="memory-post" 
                                         data-memory-id="<?php echo $item['memory_id']; ?>"
                                         data-description="<?php echo htmlspecialchars($item['memory_description'] ?? 'No description.'); ?>"
                                         data-author-name="<?php echo htmlspecialchars($item['author_name']); ?>" 
                                         data-created-at="<?php echo htmlspecialchars($item['memory_created_at'] ?? ''); ?>">
                                        <div class="memory-post-header">
                                            <img src="<?php echo !empty($item['author_profile_picture']) ? '../../uploads/profile/' . htmlspecialchars($item['author_profile_picture']) : '../../assets/images/default-profile.png'; ?>" 
                                                 alt="Profile" 
                                                 class="user-avatar-small"
                                                 onerror="this.onerror=null; this.src='../../assets/images/default-profile.png';">
                                            <div class="memory-post-info">
                                                <span class="memory-author"><?php echo htmlspecialchars($item['author_name']); ?></span>
                                                <span class="memory-time"><?php echo date('M d, Y \a\t H:i', strtotime($item['memory_created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <?php if (!empty($item['memory_description'])):
                                        ?>
                                            <p class="memory-description"><?php echo nl2br(htmlspecialchars($item['memory_description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($item['file_path'])):
                                        ?>
                                            <?php if ($item['media_type'] === 'image'): ?>
                                                <a href="#" class="memory-media-link" 
                                                   data-type="image" 
                                                   data-src="<?php echo $media_src_path; ?>" 
                                                   data-memory-id="<?php echo $item['memory_id']; ?>">
                                                    <img src="<?php echo $media_src_path; ?>" 
                                                         alt="Memory Image" 
                                                         class="memory-image" 
                                                         style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 10px; cursor: pointer;"
                                                         onerror="this.style.display='none'; console.error('Error loading image: <?php echo $media_src_path; ?>')">
                                                </a>
                                            <?php elseif ($item['media_type'] === 'video'): ?>
                                                 <a href="#" class="memory-media-link" 
                                                    data-type="video" 
                                                    data-src="<?php echo $media_src_path; ?>" 
                                                    data-memory-id="<?php echo $item['memory_id']; ?>">
                                                    <div class="video-placeholder-thumbnail" style="background-color: #000; color: #fff; display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 16/9; border-radius: 8px; margin-bottom: 10px; position: relative; cursor: pointer;">
                                                        <i class="fas fa-play-circle" style="font-size: 3em; z-index: 1;"></i>
                                                        <video muted loop style="position: absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; border-radius: 8px; opacity:0.7;" preload="metadata">
                                                            <source src="<?php echo $media_src_path; ?>#t=0.5" type="video/<?php echo pathinfo($item['file_path'], PATHINFO_EXTENSION); ?>">
                                                        </video>
                                                    </div>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="memory-post-interactions" style="padding: 10px 0; border-top: 1px solid #eee; margin-top: 10px; display:flex; justify-content: space-around;">
                                            <button class="btn-interaction btn-like" data-memory-id="<?php echo $item['memory_id']; ?>">
                                                <i class="far fa-thumbs-up"></i> Like
                                            </button>
                                            <button class="btn-interaction btn-comment" data-memory-id="<?php echo $item['memory_id']; ?>">
                                                <i class="far fa-comment"></i> Comment
                                            </button>
                                            <button class="btn-interaction btn-share" data-memory-id="<?php echo $item['memory_id']; ?>">
                                                <i class="fas fa-share"></i> Share
                                            </button>
                                        </div>
                                        <div class="comments-section-inline" id="comments-for-<?php echo $item['memory_id']; ?>" style="display:none; margin-top:10px;">
                                            <!-- Inline comments will be loaded/added here -->
                                        </div>
                                    </div>
                                <?php 
                                    endwhile;
                                endif; 
                                $memories_feed_stmt->close();
                                ?>
                        </div> <!-- End recent-memories -->

                    </div> <!-- End feed-column-wrapper -->

                    <!-- My Pets Section - Updated for Slideshow -->
                    <div class="my-pets-slideshow-section"> 
                        <div class="section-header">
                            <h3>My Pets</h3>
                            <div class="pets-slideshow-controls">
                                <button class="pets-slideshow-arrow prev"><i class="fas fa-chevron-left"></i></button>
                                <button class="pets-slideshow-arrow next"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <a href="../add-pets/add-pet.php" class="btn btn-sm btn-primary add-pet-btn-slideshow">
                                <i class="fas fa-plus"></i> Add Pet
                            </a>
                        </div>
                        <div class="pets-slideshow-wrapper"> 
                            <div class="pets-slideshow-container"> 
                                <?php if ($pets->num_rows === 0): ?>
                                    <div class="empty-pets-slide" style="width: 100%; text-align: center; padding: 2rem;">
                                        <p>You haven't added any pets yet.</p>
                                        <a href="../add-pets/add-pet.php" class="btn btn-sm btn-primary">Add Pet</a>
                                    </div>
                                <?php else:
                                    mysqli_data_seek($pets, 0); // Rewind the $pets result set if it was already looped through elsewhere
                                    while ($pet = $pets->fetch_assoc()): 
                                ?>
                                    <div class="pets-slide-item">
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
                                            <h4 class="pet-list-name-slideshow"><?php echo htmlspecialchars($pet['name']); ?></h4>
                                            <p class="pet-list-type-slideshow"><?php echo htmlspecialchars($pet['species']); ?></p>
                                            <span class="pet-status-badge <?php echo strtolower($pet['status']); ?>">
                                                <?php echo ucfirst($pet['status']); ?>
                                            </span>
                                            <?php if ($pet['status'] === 'safe'): ?>
                                                <i class="fas fa-eye status-icon-display safe"></i> 
                                            <?php elseif ($pet['status'] === 'missing'): ?>
                                                <i class="fas fa-eye-slash status-icon-display missing"></i> 
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-actions-dashboard">
                                            <button class="btn btn-xs btn-warning report-missing-btn" data-pet-id="<?php echo $pet['id']; ?>" <?php echo ($pet['status'] === 'missing') ? 'disabled' : ''; ?>>
                                                <i class="fas fa-exclamation-triangle"></i> Report Missing
                                            </button>
                                            <button class="btn btn-xs btn-success report-found-btn" data-pet-id="<?php echo $pet['id']; ?>" <?php echo ($pet['status'] === 'found' || $pet['status'] === 'safe') ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check-circle"></i> Mark Found/Safe
                                            </button>
                                        </div>
                                         <a href="../details/pet-details.php?id=<?php echo $pet['id']; ?>" class="btn btn-xs btn-info view-details-btn-slideshow" style="margin-top: 5px;">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                <?php 
                                    endwhile; 
                                endif; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Report Missing Pet Modal -->
    <div id="reportMissingModal" class="modal" style="display: none;">
        <div class="modal-content report-missing-modal-content">
            <span class="close-btn" onclick="document.getElementById('reportMissingModal').style.display='none'">&times;</span>
            <h2>Report Missing Pet</h2>
            <div class="modal-pet-info">
                <img id="modalPetImage" src="" alt="Pet Image" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; margin-bottom: 10px;">
                <h4 id="modalPetName"></h4>
            </div>
            <form id="reportMissingForm">
                <input type="hidden" id="modalPetId" name="pet_id" value="">
                
                <div class="form-group">
                    <label for="modal_last_seen_date" class="required">Last Seen Date</label>
                    <input type="date" id="modal_last_seen_date" name="last_seen_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_last_seen_location" class="required">Last Seen Location</label>
                    <input type="text" id="modal_last_seen_location" name="last_seen_location" class="form-control" 
                           placeholder="Enter the location where your pet was last seen" required>
                </div>

                <div class="form-group">
                    <label for="modal_additional_info">Additional Details (Optional)</label>
                    <textarea id="modal_additional_info" name="additional_info" class="form-control" 
                              placeholder="Any other details (e.g., what they were wearing, behavior)"></textarea>
                </div>
                
                <div id="reportMissingError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>

                <button type="submit" class="btn btn-primary btn-block">Submit Report</button>
            </form>
        </div>
    </div>

    <?php include_once '../../includes/media-viewer-modal.php'; ?>

    <script src="../../assets/js/qrcode.min.js"></script>
    <script src="../../assets/js/qr-code.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/media-modal.js"></script>
    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }

    // Mark pet as found
    document.querySelectorAll('.mark-found').forEach(button => {
        button.addEventListener('click', async function() {
            const petId = this.dataset.petId;
            if (confirm('Are you sure you want to mark this pet as found?')) {
                try {
                    const response = await fetch('mark_found.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `pet_id=${petId}`
                    });
                    
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        alert('Failed to update pet status. Please try again.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                }
            }
        });
    });

    // Search functionality
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        const searchInput = searchForm.querySelector('input');
        if (searchInput) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `../missing/missing-pets.php?search=${encodeURIComponent(query)}`;
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize QR codes
        if (typeof initQRCodes === 'function') {
            initQRCodes();
        }
        
        // initSlideshow(); // Ensure this call is removed if function is removed
        
        // Rest of your existing JavaScript...

        // Report Missing Pet Modal Logic (MOVED INSIDE DOMContentLoaded)
        const reportMissingModal = document.getElementById('reportMissingModal');
        console.log('Report Missing Modal Element:', reportMissingModal); // Log 1: Check if modal is found
        const reportMissingForm = document.getElementById('reportMissingForm');
        const modalPetImage = document.getElementById('modalPetImage');
        const modalPetName = document.getElementById('modalPetName');
        const modalPetIdInput = document.getElementById('modalPetId');
        const reportMissingErrorDiv = document.getElementById('reportMissingError');

        if (reportMissingModal) {
            document.querySelectorAll('.report-missing-btn').forEach(button => {
                console.log('Attaching listener to button:', button); // Log 2: Check if buttons are found
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Report Missing button clicked:', this); // Log 3: Confirm click event
                    if (this.disabled) {
                        console.log('Button is disabled.');
                        return;
                    }

                    const petListItem = this.closest('.pet-list-item');
                    if (!petListItem) return; // Should not happen if button is inside item

                    const petId = this.dataset.petId;
                    const petNameElem = petListItem.querySelector('.pet-list-name');
                    const petName = petNameElem ? petNameElem.firstChild.textContent.trim() : 'Unknown Pet';
                    
                    let petImageSrc = petListItem.querySelector('.pet-list-image img')?.src;
                    if (!petImageSrc) {
                        petImageSrc = '../../assets/images/default-profile.png'; // Fallback
                    }

                    if (modalPetIdInput) modalPetIdInput.value = petId;
                    if (modalPetName) modalPetName.textContent = petName;
                    if (modalPetImage) modalPetImage.src = petImageSrc;
                    
                    if (reportMissingErrorDiv) {
                        reportMissingErrorDiv.style.display = 'none';
                        reportMissingErrorDiv.textContent = '';
                    }
                    if (reportMissingForm) reportMissingForm.reset();
                    console.log('Modal display BEFORE setting:', reportMissingModal.style.display); // Log 4
                    reportMissingModal.style.display = 'flex';
                    console.log('Modal display AFTER setting:', reportMissingModal.style.display); // Log 5
                });
            });

            if (reportMissingForm) {
                reportMissingForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Submitting...';
                    }
                    if (reportMissingErrorDiv) reportMissingErrorDiv.style.display = 'none';

                    try {
                        const response = await fetch('report_missing_pet.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            reportMissingModal.style.display = 'none';
                            // alert('Pet reported missing successfully!');
                            Swal.fire({
                                title: 'Reported!',
                                text: 'Pet reported missing successfully.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload(); 
                            });
                        } else {
                            // if (reportMissingErrorDiv) { // Old error display
                            //     reportMissingErrorDiv.textContent = result.message || 'An unknown error occurred.';
                            //     reportMissingErrorDiv.style.display = 'block';
                            // }
                            Swal.fire({
                                title: 'Error!',
                                text: result.message || 'An unknown error occurred while reporting the pet.',
                                icon: 'error'
                            });
                        }
                    } catch (error) {
                        console.error('Error reporting missing pet:', error);
                        // if (reportMissingErrorDiv) { // Old error display
                        //     reportMissingErrorDiv.textContent = 'An error occurred. Please try again.';
                        //     reportMissingErrorDiv.style.display = 'block';
                        // }
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred. Please try again.',
                            icon: 'error'
                        });
                    }
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Submit Report';
                    }
                });
            }

            // Close modal if user clicks outside of it
            window.addEventListener('click', function(event) {
                if (event.target == reportMissingModal) {
                    reportMissingModal.style.display = "none";
                }
            });

            // Add click listener to modal's own close button if it exists
            const modalCloseButton = reportMissingModal.querySelector('.close-btn');
            if (modalCloseButton) {
                modalCloseButton.onclick = function() {
                    reportMissingModal.style.display = "none";
                }
            } // This might conflict with the inline onclick, better to have one way.
              // The inline onclick="document.getElementById('reportMissingModal').style.display='none'" is fine.

        } // end if (reportMissingModal)

        // "Mark Found/Safe" Button Logic
        document.querySelectorAll('.report-found-btn').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                if (this.disabled) return;

                const petId = this.dataset.petId;
                const petName = this.closest('.pet-list-item')?.querySelector('.pet-list-name')?.firstChild.textContent.trim() || 'this pet';

                // if (confirm(`Are you sure you want to mark ${petName} as found/safe?`)) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you want to mark ${petName} as found/safe?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, mark safe!'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const originalButtonText = this.innerHTML;
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

                        try {
                            const formData = new FormData();
                            formData.append('pet_id', petId);
                            formData.append('status', 'safe'); // New status is 'safe'

                            const response = await fetch('mark_pet_safe.php', { // New backend script
                                method: 'POST',
                                body: formData
                            });
                            const fetchResult = await response.json(); // Renamed to avoid conflict with Swal result

                            if (fetchResult.success) {
                                // alert(`${petName} has been marked as safe.`); // Or a nicer notification
                                Swal.fire({
                                    title: 'Marked Safe!',
                                    text: `${petName} has been marked as safe.`,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload(); // Reload to see updated status and button states
                                });
                            } else {
                                // alert(`Failed to update status: ${fetchResult.message || 'Unknown error'}`);
                                Swal.fire({
                                    title: 'Update Failed',
                                    text: `Failed to update status: ${fetchResult.message || 'Unknown error'}`,
                                    icon: 'error'
                                });
                                this.disabled = false;
                                this.innerHTML = originalButtonText;
                            }
                        } catch (error) {
                            console.error('Error marking pet safe:', error);
                            // alert('An error occurred while updating the pet status. Please try again.');
                             Swal.fire({
                                title: 'Error!',
                                text: 'An error occurred while updating the pet status. Please try again.',
                                icon: 'error'
                            });
                            this.disabled = false;
                            this.innerHTML = originalButtonText;
                        }
                    }
                });
            });
        });

        // Memory form logic (should also be inside DOMContentLoaded or ensure elements exist)
        const memoryForm = document.querySelector('.memory-form');
        if (memoryForm) {
            memoryForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                try {
                    const formData = new FormData(this);
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        if (typeof showSuccessMessage === 'function') showSuccessMessage();
                        this.reset();
                        const imagePreview = document.getElementById('image-preview');
                        if (imagePreview) imagePreview.style.display = 'none';
                        location.reload(); 
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        }

        // Display selected video filename for memory upload
        const memoryVideoInput = document.getElementById('memory_video');
        const videoPreviewNameDiv = document.getElementById('video-preview-name');
        if (memoryVideoInput && videoPreviewNameDiv) {
            memoryVideoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    videoPreviewNameDiv.textContent = `Selected video: ${this.files[0].name}`;
                } else {
                    videoPreviewNameDiv.textContent = '';
                }
            });
        }

        // My Pets Slideshow Logic (MOVED INSIDE DOMContentLoaded)
        const petsSlideshowContainer = document.querySelector('.pets-slideshow-container');
        if (petsSlideshowContainer) {
            const petSlides = petsSlideshowContainer.querySelectorAll('.pets-slide-item');
            const prevPetSlideBtn = document.querySelector('.pets-slideshow-arrow.prev');
            const nextPetSlideBtn = document.querySelector('.pets-slideshow-arrow.next');
            const addPetButtonInSlideshow = document.querySelector('.add-pet-btn-slideshow'); // Make sure this class exists on the add pet button in slideshow header
            let currentPetSlide = 0;
            let petSlideInterval;

            function showPetSlide(index) {
                if (!petSlides || petSlides.length === 0) return;
                currentPetSlide = (index + petSlides.length) % petSlides.length;
                const offset = -currentPetSlide * 100;
                petsSlideshowContainer.style.transform = `translateX(${offset}%`;
            }

            function nextPetSlide() {
                showPetSlide(currentPetSlide + 1);
            }

            function prevPetSlide() {
                showPetSlide(currentPetSlide - 1);
            }

            function startPetSlideshow() {
                if (petSlides.length > 1) {
                    stopPetSlideshow();
                    petSlideInterval = setInterval(nextPetSlide, 5000);
                }
            }

            function stopPetSlideshow() {
                clearInterval(petSlideInterval);
            }

            if (petSlides.length > 0) {
                showPetSlide(currentPetSlide);

                if (prevPetSlideBtn) {
                    prevPetSlideBtn.addEventListener('click', () => {
                        prevPetSlide();
                        startPetSlideshow(); // Restart slideshow after manual navigation
                    });
                }
                if (nextPetSlideBtn) {
                    nextPetSlideBtn.addEventListener('click', () => {
                        nextPetSlide();
                        startPetSlideshow(); // Restart slideshow after manual navigation
                    });
                }

                if (petSlides.length > 1) {
                    petsSlideshowContainer.addEventListener('mouseenter', stopPetSlideshow);
                    petsSlideshowContainer.addEventListener('mouseleave', startPetSlideshow);
                    startPetSlideshow(); // Initial start
                    if(prevPetSlideBtn) prevPetSlideBtn.style.display = 'flex';
                    if(nextPetSlideBtn) nextPetSlideBtn.style.display = 'flex';
                } else {
                    // Only one pet or no pets, hide arrows and stop slideshow
                    stopPetSlideshow();
                    if(prevPetSlideBtn) prevPetSlideBtn.style.display = 'none';
                    if(nextPetSlideBtn) nextPetSlideBtn.style.display = 'none';
                }
            } else {
                // No pets, ensure arrows are hidden (redundant if previous block catches it, but safe)
                if(prevPetSlideBtn) prevPetSlideBtn.style.display = 'none';
                if(nextPetSlideBtn) nextPetSlideBtn.style.display = 'none';
            }
        }
        // End My Pets Slideshow Logic

    }); // End of DOMContentLoaded

    // showSuccessMessage function (can be outside or inside DOMContentLoaded if not DOM dependent)
    function showSuccessMessage() {
        const success = document.getElementById('memorySuccess'); // This ID needs to exist in HTML
        if (success) {
            success.style.display = 'block';
            setTimeout(() => {
                success.style.display = 'none';
            }, 3000);
        }
    }

    </script>
</body>
</html>