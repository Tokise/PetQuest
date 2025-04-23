<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if pet ID is set
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../missing/missing-pets.php');
    exit();
}

$pet_id = (int)$_GET['id'];

// Get pet details
$stmt = $conn->prepare("
    SELECT p.*, 
           p.name as pet_name, 
           p.species as type,
           u.name as owner_name,
           u.email as owner_email,
           mr.last_seen_date,
           mr.last_seen_location,
           mr.contact_info,
           mr.additional_info
    FROM pets p
    JOIN users u ON p.owner_id = u.id
    LEFT JOIN missing_reports mr ON p.id = mr.pet_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $pet = null;
} else {
    $pet = $result->fetch_assoc();
    
    // View count code removed - column doesn't exist in schema
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pet)) {
    $sender_name = trim($_POST['sender_name']);
    $sender_email = trim($_POST['sender_email']);
    $message = trim($_POST['message']);
    
    if (!empty($sender_name) && !empty($sender_email) && !empty($message)) {
        // First, check if a conversation already exists
        $conv_stmt = $conn->prepare("
            SELECT id FROM conversations 
            WHERE pet_id = ? AND founder_email = ?
        ");
        $conv_stmt->bind_param("is", $pet_id, $sender_email);
        $conv_stmt->execute();
        $conv_result = $conv_stmt->get_result();
        
        if ($conv_result->num_rows === 0) {
            // Create a new conversation
            $conv_stmt = $conn->prepare("
                INSERT INTO conversations (
                    pet_id, 
                    founder_name, 
                    founder_email, 
                    owner_id,
                    owner_name,
                    owner_email,
                    owner_unread_count,
                    last_message_time
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $conv_stmt->bind_param(
                "issssi", 
                $pet_id, 
                $sender_name, 
                $sender_email, 
                $pet['owner_id'],
                $pet['owner_name'],
                $pet['owner_email']
            );
            $conv_stmt->execute();
            $conversation_id = $conn->insert_id;
        } else {
            // Update existing conversation
            $conversation = $conv_result->fetch_assoc();
            $conversation_id = $conversation['id'];
            
            // Update last message time and increment owner's unread count
            $update_stmt = $conn->prepare("
                UPDATE conversations 
                SET last_message_time = NOW(), 
                    owner_unread_count = owner_unread_count + 1
                WHERE id = ?
            ");
            $update_stmt->bind_param("i", $conversation_id);
            $update_stmt->execute();
        }
        
        // Insert the message into founder_messages
        $stmt = $conn->prepare("
            INSERT INTO founder_messages (conversation_id, pet_id, founder_name, founder_email, message, is_read)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->bind_param("iisss", $conversation_id, $pet_id, $sender_name, $sender_email, $message);
        
        if ($stmt->execute()) {
            // Create a notification for the pet owner
            $notification_title = "New message about {$pet['pet_name']}";
            $notification_message = "{$sender_name} has sent you a message about your pet {$pet['pet_name']}.";
            
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, pet_id, type, title, message)
                VALUES (?, ?, 'founder_message', ?, ?)
            ");
            $stmt->bind_param("iiss", $pet['owner_id'], $pet_id, $notification_title, $notification_message);
            $stmt->execute();
            
            $_SESSION['message'] = "Your message has been sent successfully! The pet owner will be notified.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to send message. Please try again.";
            $_SESSION['message_type'] = "error";
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    } else {
        $_SESSION['message'] = "Please fill in all fields.";
        $_SESSION['message_type'] = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Details - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../dashboard/css/dashboard.css">
    <link rel="stylesheet" href="css/details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        input[readonly] {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <div class="details-container">
            <?php if (isset($pet)): ?>
                <div class="pet-details">
                    <div class="pet-header">
                        <h2><?php echo htmlspecialchars($pet['pet_name']); ?></h2>
                        <div class="status-badge <?php echo strtolower($pet['status']); ?>">
                            <?php echo htmlspecialchars($pet['status']); ?>
                        </div>
                    </div>

                    <div class="pet-content">
                        <div class="pet-image">
                            <?php if (!empty($pet['image_path'])): ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pet['image_path']); ?>" alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                            <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-paw"></i>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="pet-info">
                            <div class="info-group">
                                <label>Type:</label>
                                <span><?php echo htmlspecialchars($pet['type']); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Breed:</label>
                                <span><?php echo htmlspecialchars($pet['breed']); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Color:</label>
                                <span><?php echo htmlspecialchars($pet['color']); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Age:</label>
                                <span><?php echo htmlspecialchars($pet['age']); ?> years</span>
                            </div>
                            
                            <div class="info-group">
                                <label>Gender:</label>
                                <span><?php echo htmlspecialchars($pet['gender']); ?></span>
                            </div>
                            
                            <?php if ($pet['status'] === 'missing' && !empty($pet['last_seen_date'])): ?>
                            <div class="info-group">
                                <label>Last Seen:</label>
                                <span><?php echo htmlspecialchars($pet['last_seen_date']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($pet['status'] === 'missing' && !empty($pet['last_seen_location'])): ?>
                            <div class="info-group full-width">
                                <label>Location:</label>
                                <span><?php echo htmlspecialchars($pet['last_seen_location']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-group full-width">
                                <label>Description:</label>
                                <p class="description"><?php echo nl2br(htmlspecialchars($pet['description'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-section">
                        <h3>Contact Pet Owner</h3>
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                                <?php 
                                    echo $_SESSION['message'];
                                    unset($_SESSION['message']);
                                    unset($_SESSION['message_type']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $pet['owner_id']): ?>
                            <div class="alert alert-info">
                                This is your pet. You can view messages from the <a href="../messages/chat.php">Messages</a> section.
                            </div>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <div class="contact-actions">
                                <a href="../messages/chat.php?pet_id=<?php echo $pet_id; ?>&founder_email=<?php echo urlencode($_SESSION['email']); ?>" class="btn btn-primary">
                                    <i class="fas fa-comments"></i> Start Chat with Owner
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Please <a href="../../auth/login.php">login</a> or <a href="../../auth/register.php">register</a> to contact the pet owner.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="share-section">
                        <h3>Share</h3>
                        <div class="share-buttons">
                            <button onclick="sharePet('facebook')" class="share-btn facebook">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button onclick="sharePet('twitter')" class="share-btn twitter">
                                <i class="fab fa-twitter"></i>
                            </button>
                            <button onclick="sharePet('whatsapp')" class="share-btn whatsapp">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                            <button onclick="generateQRCode()" class="share-btn qr">
                                <i class="fas fa-qrcode"></i>
                            </button>
                        </div>
                        <div id="qrcode" class="qr-code"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="not-found">
                    <i class="fas fa-exclamation-circle"></i>
                    <h2>Pet Not Found</h2>
                    <p>Sorry, we couldn't find the pet you're looking for.</p>
                    <a href="../missing/missing-pets.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Missing Pets
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
        function sharePet(platform) {
            const url = window.location.href;
            const title = "Help find <?php echo isset($pet) ? htmlspecialchars($pet['pet_name']) : 'this pet'; ?>";
            
            let shareUrl;
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        function generateQRCode() {
            const qrcodeContainer = document.getElementById('qrcode');
            qrcodeContainer.innerHTML = '';
            
            new QRCode(qrcodeContainer, {
                text: window.location.href,
                width: 128,
                height: 128
            });
            
            qrcodeContainer.style.display = 'block';
        }
    </script>
</body>
</html> 