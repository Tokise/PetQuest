<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../src/dashboard/dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
                $_SESSION['email'] = $email;
                header("Location: ../src/dashboard/dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PetQuest</title>
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
                <h1>Welcome Back!</h1>
                <p>Sign in to your account to continue</p>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return handleSubmit(this);">
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
                               placeholder="Enter your password" required>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Create one now</a></p>
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