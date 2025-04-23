// Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    initSearchFunctionality();
});

/**
 * Initialize search functionality
 */
function initSearchFunctionality() {
    const searchElements = {
        container: document.getElementById('headerSearchContainer'),
        input: document.getElementById('headerSearchInput'),
        button: document.getElementById('searchButton'),
        results: document.getElementById('searchResultsDropdown'),
        resultsContainer: document.getElementById('searchResultsContainer')
    };
    
    // Validate that all required elements exist
    if (!validateSearchElements(searchElements)) {
        console.error('Search elements not found in the DOM');
        return;
    }
    
    // Set up event listeners
    setupSearchEventListeners(searchElements);
}

/**
 * Validate search elements exist in DOM
 * 
 * @param {Object} elements Search UI elements
 * @return {boolean} Whether all elements exist
 */
function validateSearchElements(elements) {
    return elements.container && 
           elements.input && 
           elements.button && 
           elements.results && 
           elements.resultsContainer;
}

/**
 * Set up event listeners for search functionality
 * 
 * @param {Object} elements Search UI elements
 */
function setupSearchEventListeners(elements) {
    // Toggle expanded state when clicking the search button
    elements.button.addEventListener('click', function(e) {
        e.preventDefault();
        toggleSearchExpansion(elements);
    });
    
    // Handle search input with debounce
    let debounceTimer;
    elements.input.addEventListener('input', function() {
        const query = elements.input.value.trim();
        
        // Clear previous timeout
        clearTimeout(debounceTimer);
        
        if (query.length >= 2) {
            // Show loading state
            showSearchResults(elements, true);
            elements.resultsContainer.innerHTML = getLoadingHTML();
            
            // Debounce search to avoid too many requests
            debounceTimer = setTimeout(() => {
                searchUsers(query, elements.resultsContainer);
            }, 500);
        } else {
            hideSearchResults(elements);
        }
    });
    
    // Focus on input should expand it
    elements.input.addEventListener('focus', function() {
        elements.container.classList.add('expanded');
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!elements.container.contains(e.target)) {
            hideSearchResults(elements);
            if (elements.input.value.trim() === '') {
                elements.container.classList.remove('expanded');
            }
        }
    });
    
    // Handle escape key to close dropdown
    elements.input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSearchResults(elements);
            if (elements.input.value.trim() === '') {
                elements.container.classList.remove('expanded');
                elements.input.blur();
            }
        }
    });
}

/**
 * Toggle search expansion state
 * 
 * @param {Object} elements Search UI elements
 */
function toggleSearchExpansion(elements) {
    elements.container.classList.toggle('expanded');
    
    if (elements.container.classList.contains('expanded')) {
        elements.input.focus();
        elements.input.setAttribute('aria-expanded', 'true');
    } else {
        elements.input.blur();
        hideSearchResults(elements);
    }
}

/**
 * Show search results dropdown
 * 
 * @param {Object} elements Search UI elements
 * @param {boolean} expanded Whether to set aria-expanded
 */
function showSearchResults(elements, expanded = true) {
    elements.results.classList.add('show');
    if (expanded) {
        elements.input.setAttribute('aria-expanded', 'true');
    }
}

/**
 * Hide search results dropdown
 * 
 * @param {Object} elements Search UI elements
 */
function hideSearchResults(elements) {
    elements.results.classList.remove('show');
    elements.input.setAttribute('aria-expanded', 'false');
}

/**
 * Get HTML for loading indicator
 * 
 * @return {string} Loading HTML
 */
function getLoadingHTML() {
    return '<div class="search-loading"><i class="fas fa-circle-notch fa-spin"></i> <span>Searching...</span></div>';
}

/**
 * Search users with the given query
 * 
 * @param {string} query Search query
 * @param {HTMLElement} resultsContainer Container for search results
 */
function searchUsers(query, resultsContainer) {
    // Make sure SITE_URL is defined - fallback to relative path if not
    const baseUrl = typeof SITE_URL !== 'undefined' ? SITE_URL : '';
    
    if (!resultsContainer) {
        console.error('Search results container not found');
        return;
    }
    
    fetch(`${baseUrl}/src/search/users.php?q=${encodeURIComponent(query)}`)
        .then(handleResponse)
        .then(data => {
            displaySearchResults(data, resultsContainer, baseUrl);
        })
        .catch(error => {
            console.error('Error searching users:', error);
            resultsContainer.innerHTML = '<div class="error">Error searching users</div>';
        });
}

/**
 * Handle fetch response
 * 
 * @param {Response} response Fetch response
 * @return {Promise} Parsed JSON promise
 */
function handleResponse(response) {
    if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
    }
    return response.json();
}

/**
 * Display search results
 * 
 * @param {Object} data Response data
 * @param {HTMLElement} resultsContainer Container for search results
 * @param {string} baseUrl Base URL for links
 */
function displaySearchResults(data, resultsContainer, baseUrl) {
    if (data.status === 'error') {
        resultsContainer.innerHTML = `<div class="error">${data.message || 'Error searching users'}</div>`;
        return;
    }
    
    if (data.data.length === 0 || data.message === 'No users found') {
        resultsContainer.innerHTML = '<div class="no-results">No users found</div>';
        return;
    }
    
    let html = '';
    data.data.forEach(user => {
        html += generateUserResultHTML(user, baseUrl);
    });
    
    resultsContainer.innerHTML = html;
}

/**
 * Generate HTML for a user search result
 * 
 * @param {Object} user User data
 * @param {string} baseUrl Base URL for links
 * @return {string} User result HTML
 */
function generateUserResultHTML(user, baseUrl) {
    const profilePic = user.profile_picture;
    const displayName = user.name || user.username || 'User';
    
    return `
        <div class="user-result">
            <img src="${profilePic}" alt="${displayName}" loading="lazy">
            <div class="user-info">
                <div class="username">${displayName}</div>
                ${user.email ? `<div class="email">${user.email}</div>` : ''}
            </div>
            <a href="${baseUrl}/src/profile/view.php?id=${user.id}" class="view-profile" aria-label="View ${displayName}'s profile">View</a>
        </div>
    `;
} 