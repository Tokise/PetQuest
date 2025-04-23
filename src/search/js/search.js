document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('headerSearchInput');
    const searchResultsDropdown = document.getElementById('searchResultsDropdown');
    const searchResultsContainer = document.getElementById('searchResultsContainer');
    let searchTimeout;

    // Function to escape HTML to prevent XSS
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Function to create user result HTML
    function createUserResultHTML(user) {
        return `
            <a href="${SITE_URL}/src/profile/view.php?id=${user.id}" class="search-result-item">
                <img src="${escapeHtml(user.profile_picture)}" alt="${escapeHtml(user.name)}" class="search-result-avatar">
                <div class="search-result-info">
                    <div class="search-result-name">${escapeHtml(user.name)}</div>
                    <div class="search-result-bio">${escapeHtml(user.bio || 'No bio available')}</div>
                </div>
            </a>
        `;
    }

    // Function to show loading state
    function showLoading() {
        searchResultsContainer.innerHTML = `
            <div class="search-loading">
                <i class="fas fa-circle-notch fa-spin"></i>
                <span>Searching...</span>
            </div>
        `;
    }

    // Function to show error message
    function showError(message) {
        searchResultsContainer.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
    }

    // Function to show no results message
    function showNoResults() {
        searchResultsContainer.innerHTML = `
            <div class="no-results">
                <i class="fas fa-user-slash"></i>
                <span>No users found</span>
            </div>
        `;
    }

    // Function to perform search
    async function performSearch(query) {
        try {
            const response = await fetch(`${SITE_URL}/src/search/users.php?query=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                if (data.users.length > 0) {
                    const resultsHTML = data.users.map(user => createUserResultHTML(user)).join('');
                    searchResultsContainer.innerHTML = resultsHTML;
                } else {
                    showNoResults();
                }
            } else {
                showError(data.message);
            }
        } catch (error) {
            showError('An error occurred while searching');
            console.error('Search error:', error);
        }
    }

    // Event listener for search input
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Show/hide dropdown based on input
            if (query.length >= 2) {
                searchResultsDropdown.classList.add('show');
                showLoading();
                
                // Set new timeout
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            } else {
                searchResultsDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResultsDropdown.contains(e.target)) {
                searchResultsDropdown.classList.remove('show');
            }
        });

        // Prevent dropdown from closing when clicking inside it
        searchResultsDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
}); 