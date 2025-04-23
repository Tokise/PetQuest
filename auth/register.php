<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username already exists";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hashed_password);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Registration successful! Please login.";
                    header("Location: login.php");
                    exit();
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PetQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="../assets/images/logo.svg" alt="PetQuest" class="auth-logo">
                <h1>Create Account</h1>
                <p>Join our community to help find lost pets</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return handleSubmit(this);">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Choose a username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Create a password" required>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <small class="form-text text-muted">Must be at least 6 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Confirm your password" required>
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Create Account
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    function handleSubmit(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('btn-loading');
        return true;
    }
    </script>
</body>
</html> 