<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/landing.css">
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_landing = ($current_page === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/landing/') !== false);

if (!$is_landing):
?>
<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> PetQuest. All rights reserved.</p>
    </div>
</footer>
<?php endif; ?> 