<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Settings - PetQuest</title>
    <link rel="stylesheet" href="css/settings.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="dashboard">
        <?php include '../../includes/dashboard-header.php'; ?>
        
        <div class="main-content">
            <div class="settings-container">
                <!-- Settings sidebar navigation -->
                <div class="settings-sidebar">
                    <div class="settings-nav">
                        <a href="#security" class="settings-nav-item active" data-tab="security">
                            <i class="fas fa-shield-alt"></i>
                            <span>Password & Security</span>
                        </a>
                        <a href="#preferences" class="settings-nav-item" data-tab="preferences">
                            <i class="fas fa-sliders-h"></i>
                            <span>Preferences</span>
                        </a>
                        <a href="#notification" class="settings-nav-item" data-tab="notification">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="#privacy" class="settings-nav-item" data-tab="privacy">
                            <i class="fas fa-user-shield"></i>
                            <span>Privacy</span>
                        </a>
                    </div>
                </div>

                <div class="settings-content">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($_GET['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Security Section -->
                    <div class="settings-section active" id="security">
                        <h2>Password & Security</h2>
                        <p class="section-description">Update your password and manage security settings</p>
                        
                        <div class="card">
                            <h3>Change Password</h3>
                            <form action="update_password.php" method="POST">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>

                                <ul class="password-requirements">
                                    <li>At least 8 characters long</li>
                                    <li>Contains at least one uppercase letter</li>
                                    <li>Contains at least one number</li>
                                    <li>Contains at least one special character</li>
                                </ul>
                                
                                <button type="submit" name="update_password" class="btn-primary">Change Password</button>
                            </form>
                        </div>
                        
                        <div class="card">
                            <h3>Two-Factor Authentication</h3>
                            <p>Add an extra layer of security to your account</p>
                            <button class="btn-secondary" id="setupTwoFactor" disabled>Setup 2FA</button>
                            <small class="muted-text">Coming soon</small>
                        </div>
                    </div>
                    
                    <!-- Preferences Section -->
                    <div class="settings-section" id="preferences">
                        <h2>Preferences</h2>
                        <p class="section-description">Customize your experience on PetQuest</p>
                        
                        <div class="card">
                            <h3>Appearance</h3>
                            <div class="form-group">
                                <label for="theme">Theme</label>
                                <select id="theme" name="theme">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="system">System Default</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fontSize">Font Size</label>
                                <div class="font-size-selector">
                                    <button type="button" class="font-size-btn" data-size="small">A</button>
                                    <button type="button" class="font-size-btn active" data-size="medium">A</button>
                                    <button type="button" class="font-size-btn" data-size="large">A</button>
                                </div>
                            </div>
                            
                            <button class="btn-primary" id="savePreferences">Save Preferences</button>
                        </div>
                        
                        <div class="card">
                            <h3>Language</h3>
                            <div class="form-group">
                                <label for="language">Select Language</label>
                                <select id="language" name="language">
                                    <option value="en">English</option>
                                    <option value="es">Español</option>
                                    <option value="fr">Français</option>
                                    <option value="de">Deutsch</option>
                                </select>
                            </div>
                            <button class="btn-primary" id="saveLanguage">Update Language</button>
                        </div>
                    </div>
                    
                    <!-- Notification Section -->
                    <div class="settings-section" id="notification">
                        <h2>Notifications</h2>
                        <p class="section-description">Choose what notifications you receive</p>
                        
                        <div class="card">
                            <div class="toggle-setting">
                                <div>
                                    <h4>Email Notifications</h4>
                                    <p>Receive notifications by email</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            
                            <div class="toggle-setting">
                                <div>
                                    <h4>Pet Status Updates</h4>
                                    <p>Notify when your pet's status changes</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            
                            <div class="toggle-setting">
                                <div>
                                    <h4>Messages</h4>
                                    <p>Notify when you receive a new message</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            
                            <button class="btn-primary" id="saveNotifications">Save Notification Settings</button>
                        </div>
                    </div>
                    
                    <!-- Privacy Section -->
                    <div class="settings-section" id="privacy">
                        <h2>Privacy</h2>
                        <p class="section-description">Manage your privacy settings</p>
                        
                        <div class="card">
                            <div class="toggle-setting">
                                <div>
                                    <h4>Profile Visibility</h4>
                                    <p>Who can see your profile information</p>
                                </div>
                                <select id="profileVisibility">
                                    <option value="public">Everyone</option>
                                    <option value="registered">Registered Users</option>
                                    <option value="friends">Friends Only</option>
                                </select>
                            </div>
                            
                            <div class="toggle-setting">
                                <div>
                                    <h4>Show Email Address</h4>
                                    <p>Display your email on your profile</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            
                            <button class="btn-primary" id="savePrivacy">Save Privacy Settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/settings.js"></script>
</body>
</html>
