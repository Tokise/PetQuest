<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Build the query
$query = "
    SELECT 
        p.*, 
        u.name as owner_name, 
        u.email as owner_email,
        mr.last_seen_date,
        mr.last_seen_location,
        DATEDIFF(CURRENT_DATE, mr.last_seen_date) as days_missing,
        COALESCE((SELECT SUM(owner_unread_count + founder_unread_count) 
                 FROM conversations 
                 WHERE pet_id = p.id), 0) as message_count
    FROM pets p 
    JOIN users u ON p.owner_id = u.id 
    LEFT JOIN missing_reports mr ON p.id = mr.pet_id
    WHERE p.status = 'missing'
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.breed LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if (!empty($type)) {
    $query .= " AND p.species = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($location)) {
    $query .= " AND mr.last_seen_location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pets = $stmt->get_result();

// Get pet types for filter
$typeStmt = $conn->query("SELECT DISTINCT species FROM pets WHERE status = 'missing' ORDER BY species");
$petTypes = $typeStmt->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missing Pets - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="css/missing.css">
    <link rel="stylesheet" href="../dashboard/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/qr-code.css">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <!-- Main content -->
        <div class="main-content">
            <div class="container">
                <div class="section-header">
                    <h2>Missing Pets</h2>
                  
                </div>

                <!-- Search and Filter Section -->
                <div class="search-filters">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search by name, breed, or description..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        </div>
                        <div class="form-group">
                            <select name="type" class="filter-select">
                                <option value="">All Pet Types</option>
                                <?php foreach ($petTypes as $petType): ?>
                                    <option value="<?php echo htmlspecialchars($petType['species']); ?>"
                                            <?php echo $type === $petType['species'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($petType['species']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="text" name="location" placeholder="Filter by location" 
                                   value="<?php echo htmlspecialchars($location); ?>" class="location-input">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>

                <?php if ($pets->num_rows === 0): ?>
                    <div class="empty-state">
                        <img src="../../assets/images/empty-pets.svg" alt="No pets">
                        <h3>No Missing Pets Found</h3>
                        <p>There are currently no missing pets matching your search criteria.</p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="../report/report-pet.php" class="btn btn-primary">Report Missing Pet</a>
                        <?php else: ?>
                            <a href="../../auth/login.php" class="btn btn-primary">Login to Report Missing Pet</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="pets-grid">
                        <?php while ($pet = $pets->fetch_assoc()): ?>
                            <div class="pet-card" data-qr="<?php echo SITE_URL . '/src/details/pet-details.php?id=' . $pet['id']; ?>" 
                                 data-qr-element="qr-<?php echo $pet['id']; ?>">
                                <div class="pet-image-container">
                                    <?php if ($pet['image_path']): ?>
                                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pet['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($pet['name']); ?>" class="pet-image">
                                    <?php else: ?>
                                        <div class="pet-image-placeholder">
                                            <i class="fas fa-paw"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="pet-image-overlay">
                                        <span class="days-missing">
                                            <?php
                                            if ($pet['last_seen_date']) {
                                                echo (int)$pet['days_missing'] . (abs((int)$pet['days_missing']) === 1 ? ' day' : ' days') . ' missing';
                                            } else {
                                                $days = floor((time() - strtotime($pet['created_at'])) / (60 * 60 * 24));
                                                echo $days . ($days === 1 ? ' day' : ' days') . ' missing';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="pet-info">
                                    <div class="pet-header">
                                        <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                                        <span class="status missing">Missing</span>
                                    </div>

                                    <div class="pet-details">
                                        <p><i class="fas fa-tag"></i> <strong>Type:</strong> <?php echo htmlspecialchars($pet['species']); ?></p>
                                        <?php if ($pet['breed']): ?>
                                            <p><i class="fas fa-info-circle"></i> <strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($pet['last_seen_date']): ?>
                                            <p><i class="fas fa-calendar"></i> <strong>Last Seen:</strong> <?php echo date('F j, Y', strtotime($pet['last_seen_date'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($pet['last_seen_location']): ?>
                                            <p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($pet['last_seen_location']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="reporter-info">
                                            <div class="reporter-avatar">
                                                <div class="avatar-placeholder">
                                                    <?php echo strtoupper(substr($pet['owner_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="reporter-details">
                                                <p class="reporter-name">Posted by <?php echo htmlspecialchars($pet['owner_name']); ?></p>
                                                <p class="message-count">
                                                    <i class="fas fa-comments"></i> <?php echo $pet['message_count']; ?> messages
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pet-actions">
                                        <a href="../details/pet-details.php?id=<?php echo $pet['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <button class="btn btn-secondary share-btn" data-pet-id="<?php echo $pet['id']; ?>">
                                            <i class="fas fa-share-alt"></i> Share
                                        </button>
                                    </div>
                                </div>

                                <div class="qr-code-container">
                                    <div id="qr-<?php echo $pet['id']; ?>"></div>
                                    <button class="btn btn-secondary download-qr" data-pet-id="<?php echo $pet['id']; ?>">
                                        <i class="fas fa-qrcode"></i> Save QR
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <script src="../../assets/js/qrcode.min.js"></script>
    <script src="../../assets/js/qr-code.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize QR codes
        if (typeof initQRCodes === 'function') {
            initQRCodes();
        }

        // Share functionality
        document.querySelectorAll('.share-btn').forEach(button => {
            button.addEventListener('click', function() {
                const petId = this.dataset.petId;
                const url = `${window.location.origin}/PetQuest/src/details/pet-details.php?id=${petId}`;
                
                if (navigator.share) {
                    navigator.share({
                        title: 'Help Find This Pet',
                        text: 'Please help us find this missing pet!',
                        url: url
                    });
                } else {
                    navigator.clipboard.writeText(url).then(() => {
                        alert('Link copied to clipboard!');
                    });
                }
            });
        });

        // Filter form enhancements
        const filterForm = document.querySelector('.filter-form');
        if (filterForm) {
            const inputs = filterForm.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                input.addEventListener('change', () => filterForm.submit());
            });
        }
    });
    </script>
</body>
</html>