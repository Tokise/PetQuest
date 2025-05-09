document.addEventListener('DOMContentLoaded', function() {
    initProfileEditModal();
    setupImagePreview();

    // Note: We still need to add data-memory-id to the .memory-card elements in profile/index.php HTML
    // This note should be outdated as data-memory-id was added.
});

function initProfileEditModal() {
    const modal = document.getElementById('profileEditModal');
    const openProfileBtn = document.getElementById('openProfileEditBtn');
    const openCoverBtn = document.getElementById('openCoverEditBtn');
    const closeBtn = document.getElementById('closeProfileModal');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (!modal || !openProfileBtn || !openCoverBtn) return;

    // Open modal with appropriate tab
    openProfileBtn.addEventListener('click', function() {
        showTab('profile-info');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });
    
    openCoverBtn.addEventListener('click', function() {
        showTab('cover-picture');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });
    
    // Close modal handlers
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    
    // Close when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Tab navigation
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            showTab(tabId);
        });
    });
    
    function showTab(tabId) {
        // Hide all tabs
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Show selected tab
        const selectedTab = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
        const selectedContent = document.getElementById(tabId);
        
        if (selectedTab && selectedContent) {
            selectedTab.classList.add('active');
            selectedContent.classList.add('active');
        }
    }
    
    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Handle form submission with AJAX
    const profileForm = document.getElementById('profileEditForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate form before submission
            if (!validateForm()) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append('update_profile', '1');
            
            // Disable submit button and show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            }
            
            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Profile updated successfully!', 'success');
                    // Wait a moment before reloading to show the success message
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Error updating profile');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage(error.message || 'An error occurred while updating your profile', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    }
}

function validateForm() {
    const nameInput = document.getElementById('name');
    const bioInput = document.getElementById('bio');
    const profileInput = document.getElementById('profile_picture');
    const coverInput = document.getElementById('cover_picture');
    
    // Reset previous errors
    clearErrors();
    
    let isValid = true;
    
    // Validate name
    if (!nameInput.value.trim()) {
        showFieldError(nameInput, 'Name is required');
        isValid = false;
    }
    
    // Validate bio length if provided
    if (bioInput.value.trim().length > 500) {
        showFieldError(bioInput, 'Bio must not exceed 500 characters');
        isValid = false;
    }
    
    // Validate profile picture if selected
    if (profileInput.files.length > 0 && !validateImageFile(profileInput, 'profile')) {
        isValid = false;
    }
    
    // Validate cover photo if selected
    if (coverInput.files.length > 0 && !validateImageFile(coverInput, 'cover')) {
        isValid = false;
    }
    
    return isValid;
}

function clearErrors() {
    document.querySelectorAll('.field-error').forEach(error => error.remove());
    document.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function setupImagePreview() {
    // Profile picture preview
    const profileInput = document.getElementById('profile_picture');
    const profilePreview = document.getElementById('profileImagePreview');
    
    if (profileInput && profilePreview) {
        profileInput.addEventListener('change', function() {
            previewImage(this, profilePreview);
            validateImageFile(this, 'profile');
        });
    }
    
    // Cover photo preview
    const coverInput = document.getElementById('cover_picture');
    const coverPreview = document.getElementById('coverImagePreview');
    
    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', function() {
            previewImage(this, coverPreview);
            validateImageFile(this, 'cover');
        });
    }
}

function previewImage(input, previewElement) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewElement.src = e.target.result;
            previewElement.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function validateImageFile(input, type) {
    const file = input.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (file) {
        if (!allowedTypes.includes(file.type)) {
            showFieldError(input, `Please select a valid image file (JPG, PNG, or GIF)`);
            input.value = '';
            return false;
        }
        
        if (file.size > maxSize) {
            showFieldError(input, `Image size should not exceed 5MB`);
            input.value = '';
            return false;
        }
    }
    return true;
}

function showMessage(message, type = 'info') {
    const existingMessage = document.querySelector('.message-popup');
    if (existingMessage) {
        existingMessage.remove();
    }

    const messageDiv = document.createElement('div');
    messageDiv.className = `message-popup message-${type} show`;
    messageDiv.textContent = message;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.classList.remove('show');
        setTimeout(() => messageDiv.remove(), 300);
    }, 3000);
}