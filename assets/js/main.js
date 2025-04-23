// Mobile menu toggle
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

// Password visibility toggle
function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    let isValid = true;

    // Reset previous error states
    form.querySelectorAll('.is-invalid').forEach(element => {
        element.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(element => {
        element.remove();
    });

    // Required fields validation
    form.querySelectorAll('[required]').forEach(element => {
        if (!element.value.trim()) {
            isValid = false;
            showError(element, 'This field is required');
        }
    });

    // Email validation
    const emailInput = form.querySelector('input[type="email"]');
    if (emailInput && emailInput.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            isValid = false;
            showError(emailInput, 'Please enter a valid email address');
        }
    }

    // Password validation
    const passwordInput = form.querySelector('input[type="password"]');
    if (passwordInput && passwordInput.value) {
        if (passwordInput.value.length < 6) {
            isValid = false;
            showError(passwordInput, 'Password must be at least 6 characters long');
        }
    }

    // Confirm password validation
    const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');
    if (confirmPasswordInput && passwordInput) {
        if (confirmPasswordInput.value !== passwordInput.value) {
            isValid = false;
            showError(confirmPasswordInput, 'Passwords do not match');
        }
    }

    return isValid;
}

// Show error message
function showError(element, message) {
    element.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.textContent = message;
    element.parentNode.appendChild(feedback);
}

// File input preview
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

// Initialize tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize popovers
function initPopovers() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Format date to relative time
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString();
    }
}

// Update all relative timestamps
function updateRelativeTimes() {
    document.querySelectorAll('[data-relative-time]').forEach(element => {
        const timestamp = element.getAttribute('data-relative-time');
        element.textContent = formatRelativeTime(timestamp);
    });
}

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components if Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        initTooltips();
        initPopovers();
    }
    
    // Update relative times
    updateRelativeTimes();
    setInterval(updateRelativeTimes, 60000); // Update every minute
    
    // Add mobile menu event listener
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleMobileMenu);
    }
});