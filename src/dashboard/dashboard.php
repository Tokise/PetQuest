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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Content -->
            <div class="dashboard-content fade-in">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['missing_pets']; ?></h3>
                            <p>Missing Pets</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['found_pets']; ?></h3>
                            <p>Found Pets</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-content-grid">
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
                                    <button type="submit" name="upload_memory">
                                        <i class="fas fa-share"></i>
                                        Share Memory
                                    </button>
                                </div>
                                <div id="image-preview" class="image-preview"></div>
                            </form>

                           
                        </div>
                        
                        <!-- Recent Memories -->
                        <div class="recent-memories">
                            <?php
                            $memories_stmt = $conn->prepare("
                                SELECT m.*, u.name, u.profile_picture 
                                FROM memories m 
                                JOIN users u ON m.user_id = u.id 
                                ORDER BY m.created_at DESC 
                                LIMIT 5
                            ");
                            $memories_stmt->execute();
                            $memories = $memories_stmt->get_result();
                            
                            while ($memory = $memories->fetch_assoc()):
                            ?>
                                <div class="memory-post">
                                    <div class="memory-post-header">
                                        <img src="<?php echo !empty($memory['profile_picture']) ? '../../uploads/profile/' . htmlspecialchars($memory['profile_picture']) : '../../assets/images/default-profile.png'; ?>" 
                                             alt="Profile" 
                                             class="user-avatar-small">
                                        <div class="memory-post-info">
                                            <span class="memory-author"><?php echo htmlspecialchars($memory['name']); ?></span>
                                            <span class="memory-time"><?php echo date('M d, Y', strtotime($memory['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <p class="memory-description"><?php echo htmlspecialchars($memory['description']); ?></p>
                                    <?php if (!empty($memory['image_path'])): ?>
                                        <img src="../../uploads/memories/<?php echo htmlspecialchars($memory['image_path']); ?>" 
                                             alt="Memory" 
                                             class="memory-image">
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Slideshow Section -->
                    <div class="slideshow-section">
                        <div class="slideshow-header">
                            <h3>Recent Activity</h3>
                        </div>
                        <div class="slideshow-container">
                            <?php if ($recent_pets->num_rows === 0): ?>
                                <div class="slide active">
                                    <div class="slide-content empty-state">
                                        <img src="../../assets/images/empty-pets.svg" alt="No pets">
                                        <h4>No Pets Found</h4>
                                        <p>You haven't reported any pets yet.</p>
                                        <a href="../report/report-pet.php" class="btn btn-primary">Report Missing Pet</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php 
                                $index = 0;
                                while ($pet = $recent_pets->fetch_assoc()): 
                                ?>
                                    <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <div class="slide-content">
                                            <?php if ($pet['image_path']): ?>
                                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pet['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($pet['name']); ?>" 
                                                     class="slide-image">
                                            <?php endif; ?>
                                            <div class="slide-info">
                                                <span class="slide-status status-<?php echo strtolower($pet['status']); ?>">
                                                    <?php echo ucfirst($pet['status']); ?>
                                                </span>
                                                <h4><?php echo htmlspecialchars($pet['name']); ?></h4>
                                                <?php if ($pet['status'] === 'missing' && $pet['last_seen_location']): ?>
                                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pet['last_seen_location']); ?></p>
                                                <?php endif; ?>
                                                <div class="slide-actions">
                                                    <a href="../details/pet-details.php?id=<?php echo $pet['id']; ?>" class="btn btn-primary">
                                                        View Details
                                                    </a>
                                                    <?php if ($pet['status'] === 'missing'): ?>
                                                        <button class="btn btn-success mark-found" data-pet-id="<?php echo $pet['id']; ?>">
                                                            <i class="fas fa-check"></i> Mark as Found
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                $index++;
                                endwhile; 
                                ?>
                                <div class="slideshow-nav">
                                    <?php for($i = 0; $i < $recent_pets->num_rows; $i++): ?>
                                        <div class="slide-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/qrcode.min.js"></script>
    <script src="../../assets/js/qr-code.js"></script>
    <script src="../../assets/js/main.js"></script>
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
    const searchInput = searchForm.querySelector('input');
    
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `../missing/missing-pets.php?search=${encodeURIComponent(query)}`;
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize QR codes
        initQRCodes();
        
        // Initialize slideshow
        initSlideshow();
        
        // Rest of your existing JavaScript...
    });

    // Updated slideshow initialization
    function initSlideshow() {
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.slide-dot');
        let currentSlide = 0;
        
        if (slides.length === 0) return;

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            slides[index].classList.add('active');
            dots[index].classList.add('active');
            currentSlide = index;
        }

        // Auto advance slides
        function autoAdvance() {
            const nextSlide = (currentSlide + 1) % slides.length;
            showSlide(nextSlide);
        }

        // Initialize first slide
        showSlide(0);

        // Add click handlers for dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => showSlide(index));
        });

        // Start auto-advance timer if there's more than one slide
        if (slides.length > 1) {
            setInterval(autoAdvance, 5000);
        }
    }

    // Add this to handle memory upload success
    function showSuccessMessage() {
        const success = document.getElementById('memorySuccess');
        success.style.display = 'block';
        setTimeout(() => {
            success.style.display = 'none';
        }, 3000);
    }

    document.querySelector('.memory-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                showSuccessMessage();
                this.reset();
                document.getElementById('image-preview').style.display = 'none';
                // Optionally reload memories
                location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
    </script>
</body>
</html>