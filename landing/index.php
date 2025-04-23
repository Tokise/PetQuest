<?php require_once '../config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetQuest - Help Find Lost Pets</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/landing.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>


    <nav class="landing-nav">
        <div class="container nav-container">
            <a href="/" class="nav-logo">
                <img src="../assets/images/logo.svg" alt="PetQuest">
            </a>
            <div class="nav-links">
                <a href="#features" class="nav-link">Features</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="../auth/login.php" class="nav-link">Login</a>
                <a href="../auth/register.php" class="btn btn-primary">Sign Up</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container hero-content">
            <h1>Help Reunite Lost Pets<br>With Their Families</h1>
            <p>Join our community of pet lovers and help bring lost pets back home. Report missing pets, generate QR codes, and connect with pet owners in your area.</p>
            <div class="hero-buttons">
                <a href="../auth/register.php" class="btn hero-btn hero-btn-primary">Get Started</a>
                <a href="#how-it-works" class="btn hero-btn hero-btn-secondary">Learn More</a>
            </div>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose PetQuest?</h2>
                <p>We provide powerful tools and features to help you find lost pets quickly and efficiently.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>QR Code Generation</h3>
                    <p>Generate unique QR codes for your pets that link directly to their profiles, making it easier for people to access information and contact you.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Location Tracking</h3>
                    <p>Track and update the last known location of missing pets, helping narrow down search areas and increase chances of finding them.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Instant Notifications</h3>
                    <p>Receive immediate notifications when someone has information about your missing pet or when new pets are reported missing in your area.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Finding lost pets has never been easier with our simple three-step process.</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create an Account</h3>
                    <p>Sign up for free and create your profile to start using PetQuest's features.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Report Missing Pet</h3>
                    <p>Add details about your missing pet, including photos and last known location.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Connect & Find</h3>
                    <p>Share the QR code and connect with people who might have seen your pet.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Ready to Start Finding Lost Pets?</h2>
            <p>Join our community today and help make a difference in reuniting pets with their families.</p>
            <a href="../auth/register.php" class="btn hero-btn hero-btn-secondary">Sign Up Now</a>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script>
    // Add scroll event for navbar
    window.addEventListener('scroll', function() {
        const nav = document.querySelector('.landing-nav');
        if (window.scrollY > 50) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    </script>
        
</body>
</html> 