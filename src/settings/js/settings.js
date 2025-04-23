document.addEventListener('DOMContentLoaded', function() {
    initTabNavigation();
    initPreferences();
});

function initTabNavigation() {
    const navItems = document.querySelectorAll('.settings-nav-item');
    const sections = document.querySelectorAll('.settings-section');
    
    // Handle tab navigation
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the tab id from data-tab attribute
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all nav items
            navItems.forEach(nav => nav.classList.remove('active'));
            
            // Add active class to clicked nav item
            this.classList.add('active');
            
            // Hide all sections
            sections.forEach(section => section.classList.remove('active'));
            
            // Show the selected section
            document.getElementById(tabId).classList.add('active');
            
            // Update URL hash without scrolling
            history.pushState(null, null, '#' + tabId);
        });
    });
    
    // Check if URL has a hash to show specific tab
    if (window.location.hash) {
        const tabId = window.location.hash.substring(1);
        const navItem = document.querySelector(`.settings-nav-item[data-tab="${tabId}"]`);
        if (navItem) {
            navItem.click();
        }
    }
}

function initPreferences() {
    // Font size selector
    const fontSizeBtns = document.querySelectorAll('.font-size-btn');
    fontSizeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            fontSizeBtns.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get the font size from data-size attribute
            const fontSize = this.getAttribute('data-size');
            
            // Save to local storage
            localStorage.setItem('fontSize', fontSize);
            
            // Apply the font size
            applyFontSize(fontSize);
        });
    });
    
    // Theme selector
    const themeSelect = document.getElementById('theme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            // Save to local storage
            localStorage.setItem('theme', this.value);
            
            // Apply the theme
            applyTheme(this.value);
        });
        
        // Set initial value from localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            themeSelect.value = savedTheme;
        }
    }
    
    // Language selector
    const languageSelect = document.getElementById('language');
    if (languageSelect) {
        // Set initial value from localStorage
        const savedLanguage = localStorage.getItem('language');
        if (savedLanguage) {
            languageSelect.value = savedLanguage;
        }
    }
    
    // Save preferences button
    const savePreferencesBtn = document.getElementById('savePreferences');
    if (savePreferencesBtn) {
        savePreferencesBtn.addEventListener('click', function() {
            // Save theme
            const theme = document.getElementById('theme').value;
            localStorage.setItem('theme', theme);
            
            // Apply settings
            applyTheme(theme);
            
            // Show success message
            showSuccessMessage('Preferences saved successfully');
        });
    }
    
    // Save language button
    const saveLanguageBtn = document.getElementById('saveLanguage');
    if (saveLanguageBtn) {
        saveLanguageBtn.addEventListener('click', function() {
            // Save language
            const language = document.getElementById('language').value;
            localStorage.setItem('language', language);
            
            // Show success message
            showSuccessMessage('Language preferences saved successfully');
        });
    }
    
    // Save notification settings
    const saveNotificationsBtn = document.getElementById('saveNotifications');
    if (saveNotificationsBtn) {
        saveNotificationsBtn.addEventListener('click', function() {
            // Here you would save notification preferences to the server
            // For now, we'll just show a success message
            showSuccessMessage('Notification preferences saved successfully');
        });
    }
    
    // Save privacy settings
    const savePrivacyBtn = document.getElementById('savePrivacy');
    if (savePrivacyBtn) {
        savePrivacyBtn.addEventListener('click', function() {
            // Here you would save privacy settings to the server
            // For now, we'll just show a success message
            showSuccessMessage('Privacy settings saved successfully');
        });
    }
    
    // Apply saved preferences on page load
    applySavedPreferences();
}

function applySavedPreferences() {
    // Apply saved font size
    const savedFontSize = localStorage.getItem('fontSize');
    if (savedFontSize) {
        applyFontSize(savedFontSize);
        
        // Update the active button
        const fontSizeBtn = document.querySelector(`.font-size-btn[data-size="${savedFontSize}"]`);
        if (fontSizeBtn) {
            document.querySelectorAll('.font-size-btn').forEach(btn => btn.classList.remove('active'));
            fontSizeBtn.classList.add('active');
        }
    }
    
    // Apply saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        applyTheme(savedTheme);
    }
}

function applyFontSize(size) {
    let rootSize;
    
    switch (size) {
        case 'small':
            rootSize = '14px';
            break;
        case 'medium':
            rootSize = '16px';
            break;
        case 'large':
            rootSize = '18px';
            break;
        default:
            rootSize = '16px';
    }
    
    document.documentElement.style.fontSize = rootSize;
}

function applyTheme(theme) {
    // Remove any existing theme classes
    document.body.classList.remove('theme-light', 'theme-dark');
    
    // Add the selected theme class
    document.body.classList.add('theme-' + theme);
    
    // If system, check preference
    if (theme === 'system') {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('theme-dark');
        } else {
            document.body.classList.add('theme-light');
        }
    }
}

function showSuccessMessage(message) {
    // Check if success alert already exists
    let successAlert = document.querySelector('.alert-success');
    
    // If not, create a new one
    if (!successAlert) {
        successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success';
        const content = document.querySelector('.settings-content');
        content.insertBefore(successAlert, content.firstChild);
    }
    
    // Set the message
    successAlert.textContent = message;
    
    // Remove the alert after 3 seconds
    setTimeout(() => {
        successAlert.remove();
    }, 3000);
} 