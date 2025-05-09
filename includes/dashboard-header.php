<link rel="stylesheet" href="/PetQuest/css/dashboard.css">
<!-- Dashboard Header -->
<header class="dashboard-header">
    <div class="header-left">
        <a href="<?php echo SITE_URL; ?>/src/dashboard/dashboard.php" class="logo">
            <span class="logo-text">Pet<span class="logo-highlight">Quest</span></span>
        </a>
        <?php
        // Check if we're on profile or settings page
        $currentPage = $_SERVER['REQUEST_URI'];
        $isDashboard = strpos($currentPage, '/dashboard/') !== false;
        $isMissing = strpos($currentPage, '/missing/') !== false;
        $isAddPet  = strpos($currentPage, '/add-pets/') !== false;
        $isProfilePage = strpos($currentPage, '/profile/') !== false;
        $isSettingsPage = strpos($currentPage, '/settings/') !== false;
        
        if ($isProfilePage || $isSettingsPage):
        ?>
            <a href="<?php echo SITE_URL; ?>/src/dashboard/dashboard.php" class="close-section show" title="Back to Dashboard">
                <i class="fas fa-times"></i>
            </a>
        <?php endif; ?>
    </div>
    
    <div class="header-center">
        <nav class="main-nav">
            <?php if (!$isProfilePage && !$isSettingsPage): ?>
                <a href="<?php echo SITE_URL; ?>/src/dashboard/dashboard.php" class="nav-item <?php echo $isDashboard ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="tooltip">Dashboard</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/src/add-pets/add-pet.php" class="nav-item <?php echo $isAddPet ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span class="tooltip">Add Pet</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/src/missing/missing-pets.php" class="nav-item <?php echo $isMissing ? 'active' : ''; ?>">
                    <i class="fas fa-paw"></i>
                    <span class="tooltip">Missing Pets</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/src/messages/chat.php" class="nav-item <?php echo strpos($currentPage, '/messages/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-comment-dots"></i>
                    <span class="tooltip">Messages</span>
                    <?php if (isset($unread_count) && $unread_count > 0): ?>
                        <span class="nav-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="header-right">
        <div class="search-container">
            <div class="expandable-search" id="headerSearchContainer">
                <input 
                    type="search" 
                    id="headerSearchInput" 
                    placeholder="Search users..." 
                    autocomplete="off"
                    aria-label="Search users"
                    aria-controls="searchResultsContainer"
                    aria-expanded="false">
                <button 
                    type="button" 
                    id="searchButton"
                    aria-label="Toggle search">
                    <i class="fas fa-search"></i>
                </button>
                
                <div class="search-results-dropdown" id="searchResultsDropdown">
                    <div class="search-results-container" id="searchResultsContainer">
                        <!-- Search results will be loaded here -->
                    </div>
<div class="search-see-more-wrapper" id="searchSeeMoreWrapper">
                        <!-- See more button will be dynamically added here -->
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            // Get unread messages count first
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("
                SELECT 
                    SUM(owner_unread_count) as total_unread,
                    c.id,
                    c.pet_id,
                    c.founder_name as sender_name,
                    c.founder_email as sender_email,
                    c.owner_unread_count as unread_count,
                    c.last_message_time as created_at,
                    p.name as pet_name
                FROM conversations c
                JOIN pets p ON c.pet_id = p.id
                WHERE c.owner_id = ?
                GROUP BY c.id
                ORDER BY c.last_message_time DESC
                LIMIT 5
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $unread_messages = $stmt->get_result();
            
            // Get total unread count
            $stmt = $conn->prepare("
                SELECT SUM(owner_unread_count) as total_unread
                FROM conversations
                WHERE owner_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $unread_count = $row['total_unread'] ?: 0;
            ?>
            <div class="notifications-dropdown">
                <button class="notifications-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-content">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <?php if ($unread_count > 0): ?>
                            <a href="<?php echo SITE_URL; ?>/src/messages/chat.php" class="view-all">View All</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notifications-list">
                        <?php if ($unread_count > 0 && $unread_messages->num_rows > 0): ?>
                            <?php while ($conversation = $unread_messages->fetch_assoc()): ?>
                                <a href="<?php echo SITE_URL; ?>/src/messages/chat.php?conversation=<?php echo $conversation['id']; ?>" class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-comment"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p class="notification-title">New message about <?php echo htmlspecialchars($conversation['pet_name']); ?></p>
                                        <p class="notification-text">From: <?php echo htmlspecialchars($conversation['sender_name']); ?></p>
                                        <p class="notification-time"><?php echo date('M j, g:i a', strtotime($conversation['created_at'])); ?></p>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No new notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="user-menu" id="userMenu">
                <?php
                // Get user information including profile picture and bio
                $stmt = $conn->prepare("SELECT name, profile_picture, cover_picture, bio FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                // If no name field found, try to retrieve it from SESSION
                if(!isset($user['name']) && isset($_SESSION['username'])) {
                    $user['name'] = $_SESSION['username'];
                }
                ?>
                
                <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . '/uploads/profile/' . htmlspecialchars($user['profile_picture']) : SITE_URL . '/assets/images/default-profile.png'; ?>" 
                     alt="Profile"
                     class="user-avatar">
                     
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-info">
                            <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . '/uploads/profile/' . htmlspecialchars($user['profile_picture']) : SITE_URL . '/assets/images/default-profile.png'; ?>" 
                                 alt="Profile"
                                 class="dropdown-avatar">
                            <div>
                                <span class="dropdown-username"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></span>
                                <span class="view-profile">
                                    <a href="<?php echo SITE_URL; ?>/src/profile/index.php">View your profile</a>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dropdown-menu">
                        <a href="<?php echo SITE_URL; ?>/src/profile/index.php" 
                           class="<?php echo $isProfilePage ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            My Profile
                        </a>
                    
                        <a href="<?php echo SITE_URL; ?>/src/settings/index.php" 
                           class="<?php echo $isSettingsPage ? 'active' : ''; ?>">
                            <i class="fas fa-sliders-h"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="text-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="auth-buttons">
                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-secondary">Login</a>
                <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-primary">Sign Up</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/src/search/js/search.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User dropdown toggle
    const userMenu = document.getElementById('userMenu');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (userMenu && profileDropdown) {
        userMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
    
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
    
    // Expandable search functionality
    const searchContainer = document.getElementById('headerSearchContainer');
    const searchInput = document.getElementById('headerSearchInput');
    
    if (searchContainer && searchInput) {
        searchInput.addEventListener('focus', function() {
            searchContainer.classList.add('expanded');
        });
        
        searchInput.addEventListener('blur', function() {
            if (searchInput.value === '') {
                searchContainer.classList.remove('expanded');
            }
        });
    }
});
</script>