document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('headerSearchInput');
    const searchButton = document.getElementById('searchButton');
    const searchResultsContainer = document.getElementById('searchResultsContainer');
    const searchDropdown = document.getElementById('searchResultsDropdown');
    const seeMoreWrapper = document.getElementById('searchSeeMoreWrapper');
    let searchTimeout;
    let currentPage = 1;
    let isLoading = false;
    let currentQuery = '';
    let hasMore = false;

    // Show search results dropdown when input is focused
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim().length >= 2) {
            searchDropdown.classList.add('show');
        }
    });

    // Hide search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.expandable-search')) {
            searchDropdown.classList.remove('show');
        }
    });

    // Search input handler
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        currentQuery = query;

        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                performSearch(query, true);
            }, 300);
        } else {
            searchResultsContainer.innerHTML = '';
            searchDropdown.classList.remove('show');
            seeMoreWrapper.style.display = 'none';
        }
    });

    // Perform search function
    async function performSearch(query, isNewSearch) {
        if (isLoading) return;
        
        try {
            isLoading = true;
            if (isNewSearch) {
                currentPage = 1;
                hasMore = false;
                showLoading(true);
            } else {
                showLoading(false);
            }
            
            searchDropdown.classList.add('show');

            const response = await fetch(
                `${SITE_URL}/src/search/users.php?query=${encodeURIComponent(query)}&page=${currentPage}`
            );
            const data = await response.json();

            if (data.success) {
                hasMore = data.hasMore;
                
                if (data.users.length > 0) {
                    const resultsHTML = data.users.map(user => createUserResultHTML(user)).join('');
                    
                    if (isNewSearch) {
                        searchResultsContainer.innerHTML = resultsHTML;
                    } else {
                        searchResultsContainer.insertAdjacentHTML('beforeend', resultsHTML);
                    }
                    
                    // Update see more button
                    updateSeeMoreButton();
                } else if (isNewSearch) {
                    showNoResults();
                }
            } else {
                showError(data.message);
            }
        } catch (error) {
            showError('An error occurred while searching');
            console.error('Search error:', error);
        } finally {
            isLoading = false;
        }
    }

    // Update see more button visibility and handler
    function updateSeeMoreButton() {
        if (hasMore) {
            seeMoreWrapper.style.display = 'block';
            seeMoreWrapper.innerHTML = `
                <div class="search-see-more">
                    See More (5) <i class="fas fa-chevron-down"></i>
                </div>
            `;
            
            const seeMoreButton = seeMoreWrapper.querySelector('.search-see-more');
            if (seeMoreButton) {
                seeMoreButton.onclick = () => {
                    if (!isLoading) {
                        currentPage++;
                        performSearch(currentQuery, false);
                    }
                };
            }
        } else {
            seeMoreWrapper.style.display = 'none';
        }
    }

    // Show loading state
    function showLoading(isInitial) {
        if (isInitial) {
            searchResultsContainer.innerHTML = `
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i> Searching...
                </div>
            `;
            seeMoreWrapper.style.display = 'none';
        } else {
            const seeMoreButton = seeMoreWrapper.querySelector('.search-see-more');
            if (seeMoreButton) {
                seeMoreButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading more...';
            }
        }
    }

    // Show no results
    function showNoResults() {
        searchResultsContainer.innerHTML = `
            <div class="no-search-results">
                No users found
            </div>
        `;
        seeMoreWrapper.style.display = 'none';
    }

    // Show error message
    function showError(message) {
        searchResultsContainer.innerHTML = `
            <div class="search-error">
                ${message}
            </div>
        `;
        seeMoreWrapper.style.display = 'none';
    }

    // Modified scroll handler
    searchResultsContainer.addEventListener('scroll', function() {
        if (isLoading || !hasMore) return;

        const { scrollTop, scrollHeight, clientHeight } = this;
        
        // If scrolled near bottom (within 50px)
        if (scrollHeight - scrollTop - clientHeight < 20) {
            const seeMoreButton = seeMoreWrapper.querySelector('.search-see-more');
            if (seeMoreButton && !isLoading) {
                seeMoreButton.click();
            }
        }
    });

    // Function to create user result HTML
    function createUserResultHTML(user) {
        return `
            <div class="search-result-item">
                <img src="${user.profile_picture}" alt="${user.name}" class="search-result-avatar">
                <div class="search-result-info">
                    <span class="search-result-name">${user.name}</span>
                    <span class="search-result-bio">${user.bio || 'No bio available'}</span>
                </div>
                <a href="${SITE_URL}/src/profile/view.php?id=${user.id}" class="search-result-action">
                    View
                </a>
            </div>
        `;
    }
});