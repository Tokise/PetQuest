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

    // Initialize dashboard inline comments if on the dashboard page
    if (document.querySelector('.dashboard-content .recent-memories')) {
        initDashboardInlineComments();
    }
});

function initDashboardInlineComments() {
    const commentButtons = document.querySelectorAll('.dashboard-content .recent-memories .btn-comment');

    commentButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('Comment button clicked! Memory ID:', this.dataset.memoryId); // DEBUG
            const memoryId = this.dataset.memoryId;
            const commentsSectionId = 'comments-for-' + memoryId;
            const commentsSection = document.getElementById(commentsSectionId);
            console.log('Comments section element found:', commentsSection); // DEBUG

            if (commentsSection) {
                const isVisible = commentsSection.style.display === 'block';
                commentsSection.style.display = isVisible ? 'none' : 'block';

                if (!isVisible && !commentsSection.dataset.commentsLoaded) {
                    loadInlineComments(memoryId, commentsSection);
                }
            }
        });
    });
}

async function loadInlineComments(memoryId, targetDiv) {
    if (!targetDiv) return;
    console.log(`Loading inline comments for memoryId: ${memoryId} into target:`, targetDiv); // DEBUG
    targetDiv.innerHTML = '<p class="comments-loading" style="color: #777; padding: 10px 0;">Loading comments...</p>';
    targetDiv.dataset.commentsLoaded = 'true'; // Mark as attempting to load

    try {
        // Path relative to where dashboard.php is (which includes this main.js)
        // get_memory_comments.php is in src/profile/
        const response = await fetch(`../profile/get_memory_comments.php?memory_id=${memoryId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();

        targetDiv.innerHTML = ''; // Clear loading message

        if (result.success && result.comments && result.comments.length > 0) {
            result.comments.forEach(comment => addCommentToInlineUI(comment, targetDiv));
        } else if (result.success && result.comments.length === 0) {
            targetDiv.innerHTML = '<p class="no-comments-yet" style="color: #777; font-style: italic; padding: 10px 0;">No comments yet. Be the first!</p>';
        }
        // Append comment form after messages or "no comments yet"
        appendInlineCommentForm(memoryId, targetDiv);

    } catch (error) {
        console.error('Error loading inline comments:', error);
        targetDiv.innerHTML = '<p style="color: red; padding: 10px 0;">Could not load comments. Please try again.</p>';
        targetDiv.dataset.commentsLoaded = 'false'; // Reset loaded status on error
        // Optionally re-append form even on error if desired
        // appendInlineCommentForm(memoryId, targetDiv);
    }
}

function appendInlineCommentForm(memoryId, parentDiv) {
    const formHTML = `
        <form class="inline-comment-form" data-memory-id="${memoryId}" style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <input type="hidden" name="memory_id" value="${memoryId}">
            <textarea name="comment_text" placeholder="Write a comment..." required style="flex-grow: 1; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem; resize: vertical; min-height: 40px;"></textarea>
            <button type="submit" style="padding: 0.5rem 1rem; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">Post</button>
        </form>
        <div class="inline-comment-error" style="color: red; font-size: 0.8em; margin-top: 5px;"></div>
    `;
    parentDiv.insertAdjacentHTML('beforeend', formHTML);
    const newForm = parentDiv.querySelector(`.inline-comment-form[data-memory-id="${memoryId}"]`);
    if (newForm) {
        newForm.addEventListener('submit', handleInlineCommentSubmit);
    }
}

async function handleInlineCommentSubmit(event) {
    event.preventDefault();
    const form = event.target;
    console.log('Inline comment form submitted for memoryId:', form.dataset.memoryId); // DEBUG
    const memoryId = form.dataset.memoryId;
    const commentText = form.elements.comment_text.value.trim();
    const submitButton = form.querySelector('button[type="submit"]');
    const errorDiv = form.nextElementSibling; // Assuming error div is immediately after form

    if (!commentText) {
        if(errorDiv) errorDiv.textContent = 'Comment cannot be empty.';
        return;
    }
    if(errorDiv) errorDiv.textContent = '';
    if(submitButton) submitButton.disabled = true;

    try {
        const formData = new FormData(form);
        // Path relative to where dashboard.php is (which includes this main.js)
        // add_memory_comment.php is in src/profile/
        const response = await fetch('../profile/add_memory_comment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success && result.comment) {
            const commentsContainer = form.closest('.comments-section-inline');
            // Remove "no comments yet" if it exists
            const noCommentsP = commentsContainer.querySelector('.no-comments-yet');
            if (noCommentsP) noCommentsP.remove(); 
            // Prepend new comment to the list (target an actual list container if you create one, or just prepend to commentsSection)
            addCommentToInlineUI(result.comment, commentsContainer, true); 
            form.elements.comment_text.value = '';
        } else {
            if(errorDiv) errorDiv.textContent = result.message || 'Could not post comment.';
        }
    } catch (error) {
        console.error('Error posting inline comment:', error);
        if(errorDiv) errorDiv.textContent = 'An error occurred while posting.';
    }
    if(submitButton) submitButton.disabled = false;
}

function addCommentToInlineUI(comment, parentDiv, prepend = false) {
    const commentDiv = document.createElement('div');
    commentDiv.classList.add('comment-item'); // Use class for styling from dashboard.css
    // Assuming SITE_URL is globally available (e.g., from dashboard-header.php) or construct path carefully
    // For now, using relative paths that should work from dashboard perspective
    const profilePicPath = comment.user_profile_picture ? `../../uploads/profile/${escapeHTML(comment.user_profile_picture)}` : '../../assets/images/default-profile.png';
    const commentDate = comment.created_at ? new Date(comment.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '';

    commentDiv.innerHTML = `
        <img src="${profilePicPath}" alt="${escapeHTML(comment.user_name)}" class="comment-avatar">
        <div class="comment-content">
            <strong class="comment-author">${escapeHTML(comment.user_name)}</strong>
            <p class="comment-text">${nl2br(escapeHTML(comment.comment))}</p>
            <small class="comment-date">${commentDate}</small>
        </div>
    `;

    // If prepending, and there's a form, insert before the form.
    // Otherwise, just append to the parentDiv (which might contain "no comments" or other comments).
    const formElement = parentDiv.querySelector('.inline-comment-form');
    if (prepend && formElement) {
        parentDiv.insertBefore(commentDiv, formElement);
    } else {
        parentDiv.appendChild(commentDiv);
    }
}

// Helper function to escape HTML (if not already globally available from media-modal.js)
// It's better to have one source of truth for this, e.g., ensure media-modal.js loads first or use a shared utility file.
function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function (match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;' // Or &apos;
        }[match];
    });
}
function nl2br(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/\r\n|\r|\n/g, '<br>');
}