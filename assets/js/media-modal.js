document.addEventListener('DOMContentLoaded', function() {
    const mediaViewerModal = document.getElementById('mediaViewerModal');
    const modalImage = document.getElementById('modalImage');
    const modalVideo = document.getElementById('modalVideo');
    const modalVideoSource = document.getElementById('modalVideoSource');
    const mediaCloseBtn = mediaViewerModal ? mediaViewerModal.querySelector('.media-close-btn') : null;
    
    const modalMemoryAuthor = document.getElementById('modalMemoryAuthor');
    const modalMemoryDate = document.getElementById('modalMemoryDate');
    const modalMemoryDescription = document.getElementById('modalMemoryDescription');

    const modalCommentForm = document.getElementById('modalCommentForm');
    const modalMemoryIdInput = document.getElementById('modalMemoryId');
    const modalCommentsArea = document.getElementById('modalCommentsArea');
    const commentErrorDiv = document.getElementById('commentError');
    let currentMemoryIdForModal = null;
    let currentMemoryData = {}; 

    // Define SITE_URL if not already defined (e.g. via a global JS var from PHP)
    // const SITE_URL = window.SITE_URL || ''; // You'd need to set window.SITE_URL in your PHP templates

    if (mediaViewerModal) {
        document.querySelectorAll('.memory-media-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Data from the link itself
                const type = this.dataset.type;
                const src = this.dataset.src;
                currentMemoryIdForModal = this.dataset.memoryId; // Memory ID is usually on the link for modal triggering

                // Data from the parent card/post element
                const parentPostOrCard = this.closest('.memory-post, .memory-card-item, .memory-card');

                currentMemoryData.description = parentPostOrCard?.dataset.description || this.dataset.description || 'No description available.';
                currentMemoryData.authorName = parentPostOrCard?.dataset.authorName || this.dataset.authorName || 'Unknown Author';
                currentMemoryData.createdAt = parentPostOrCard?.dataset.createdAt || this.dataset.createdAt || '';
                // For author profile picture, ensure it's consistently available (e.g., data-author-profile-pic on parentPostOrCard or this)
                currentMemoryData.authorProfilePic = parentPostOrCard?.dataset.authorProfilePic || this.dataset.authorProfilePic || ''; 

                if (modalMemoryIdInput && currentMemoryIdForModal) {
                    modalMemoryIdInput.value = currentMemoryIdForModal;
                }

                if (modalMemoryAuthor) {
                    // Optional: Construct author display with image if available
                    let authorHTML = escapeHTML(currentMemoryData.authorName);
                    if (currentMemoryData.authorProfilePic) {
                        // Adjust path for profile picture based on context if necessary or ensure SITE_URL based path is in data attribute
                        // For simplicity, assuming data-author-profile-pic provides a usable path or SITE_URL is prefixed.
                        authorHTML = `<img src="${escapeHTML(currentMemoryData.authorProfilePic)}" alt="" style="width:24px; height:24px; border-radius:50%; margin-right:8px; vertical-align:middle;"> ${authorHTML}`;
                    }
                    modalMemoryAuthor.innerHTML = authorHTML;
                }
                if (modalMemoryDate) modalMemoryDate.textContent = currentMemoryData.createdAt ? new Date(currentMemoryData.createdAt).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
                if (modalMemoryDescription) modalMemoryDescription.innerHTML = escapeHTML(currentMemoryData.description).replace(/\n/g, '<br>');

                if(modalCommentsArea) {
                    modalCommentsArea.innerHTML = '<p class="no-comments-yet" style="color: #777;">Loading comments...</p>';
                }
                if(commentErrorDiv) commentErrorDiv.textContent = '';
                if(modalCommentForm) modalCommentForm.reset();
                
                if (currentMemoryIdForModal) {
                    loadCommentsForMemory(currentMemoryIdForModal);
                }

                if (type === 'image' && modalImage) {
                    modalImage.src = src;
                    modalImage.style.display = 'block';
                    if (modalVideo) modalVideo.style.display = 'none';
                } else if (type === 'video' && modalVideo && modalVideoSource) {
                    modalVideoSource.src = src;
                    modalVideoSource.type = 'video/' + src.split('.').pop();
                    modalVideo.load();
                    modalVideo.style.display = 'block';
                    if (modalImage) modalImage.style.display = 'none';
                }
                mediaViewerModal.style.display = 'flex';
            });
        });

        if (mediaCloseBtn) {
            mediaCloseBtn.addEventListener('click', closeModal);
        }
        window.addEventListener('click', function(event) {
            if (event.target == mediaViewerModal) closeModal();
        });
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && mediaViewerModal.style.display === 'flex') closeModal();
        });

        function closeModal() {
            mediaViewerModal.style.display = 'none';
            if (modalVideo) modalVideo.pause();
            if (modalImage) modalImage.src = '';
            if (modalVideoSource) modalVideoSource.src = '';
        }
    }

    if (modalCommentForm) {
        modalCommentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const commentText = this.elements.comment_text.value.trim();
            const memoryId = this.elements.memory_id.value;
            const submitButton = this.querySelector('button[type="submit"]');

            if (!commentText || !memoryId) {
                if(commentErrorDiv) commentErrorDiv.textContent = 'Comment cannot be empty and Memory ID must be present.';
                return;
            }
            if(commentErrorDiv) commentErrorDiv.textContent = '';
            if(submitButton) submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                // IMPORTANT: Adjust path if add_memory_comment.php is not in the same dir as the page loading this JS
                // For now, assuming it's relative to src/profile/ or src/dashboard/
                // Better to use an absolute path or a path relative to SITE_URL
                const addCommentPath = window.location.pathname.includes('/dashboard/') ? '../profile/add_memory_comment.php' : 'add_memory_comment.php';
                const response = await fetch(addCommentPath, { 
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success && result.comment) {
                    addCommentToUI(result.comment);
                    this.elements.comment_text.value = '';
                } else {
                    if(commentErrorDiv) commentErrorDiv.textContent = result.message || 'Could not post comment.';
                }
            } catch (error) {
                console.error('Error posting comment:', error);
                if(commentErrorDiv) commentErrorDiv.textContent = 'An error occurred.';
            }
            if(submitButton) submitButton.disabled = false;
        });
    }

    function addCommentToUI(comment) {
        if (!modalCommentsArea) return;
        const noCommentsP = modalCommentsArea.querySelector('.no-comments-yet');
        if (noCommentsP) noCommentsP.remove();
        const commentDiv = document.createElement('div');
        commentDiv.classList.add('modal-comment-item');
        commentDiv.style.cssText = 'display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;';
        const profilePicRoot = window.location.pathname.includes('/dashboard/') ? '../../uploads/profile/' : '../../uploads/profile/'; // Adjust if needed based on final JS location
        const profilePic = comment.user_profile_picture ? `${profilePicRoot}${comment.user_profile_picture}` : '../../assets/images/default-profile.png';
        const commentDate = new Date(comment.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        commentDiv.innerHTML = `
            <img src="${profilePic}" alt="${comment.user_name}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
            <div style="flex-grow: 1;">
                <strong style="font-size: 0.9em;">${escapeHTML(comment.user_name)}</strong>
                <p style="font-size: 0.95em; margin: 3px 0 3px; white-space: pre-wrap; word-wrap: break-word;">${escapeHTML(comment.comment)}</p>
                <small style="font-size: 0.75em; color: #777;">${commentDate}</small>
            </div>
        `;
        modalCommentsArea.appendChild(commentDiv);
        modalCommentsArea.scrollTop = modalCommentsArea.scrollHeight;
    }

    async function loadCommentsForMemory(memoryId) {
        if (!modalCommentsArea || !memoryId) return;
        modalCommentsArea.innerHTML = '<p class="no-comments-yet" style="color: #777;">Loading comments...</p>';
        try {
            // IMPORTANT: Adjust path if get_memory_comments.php is not in the same dir as the page loading this JS
            const getCommentsPath = window.location.pathname.includes('/dashboard/') ? `../profile/get_memory_comments.php?memory_id=${memoryId}` : `get_memory_comments.php?memory_id=${memoryId}`;
            const response = await fetch(getCommentsPath);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            modalCommentsArea.innerHTML = '';
            if (result.success && result.comments && result.comments.length > 0) {
                result.comments.forEach(comment => addCommentToUI(comment));
            } else if (result.success && result.comments.length === 0) {
                modalCommentsArea.innerHTML = '<p class="no-comments-yet" style="color: #777;">No comments yet. Be the first!</p>';
            } else {
                modalCommentsArea.innerHTML = `<p style="color: red;">Could not load comments: ${result.message || 'Unknown error'}</p>`;
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            modalCommentsArea.innerHTML = '<p style="color: red;">An error occurred while loading comments.</p>';
        }
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[match];
        });
    }
}); 