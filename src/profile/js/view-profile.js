document.addEventListener('DOMContentLoaded', function() {
    // Message Pet Modal
    const messagePetModal = document.getElementById('messagePetModal');
    const messagePetBtn = document.getElementById('messagePetBtn');
    const closeMessageModal = document.getElementById('closeMessageModal');
    const cancelMessageBtn = document.getElementById('cancelMessageBtn');
    const messagePetForm = document.getElementById('messagePetForm');

    // Report Modal
    const reportModal = document.getElementById('reportModal');
    const reportProfileBtn = document.getElementById('reportProfileBtn');
    const closeReportModal = document.getElementById('closeReportModal');
    const cancelReportBtn = document.getElementById('cancelReportBtn');
    const reportForm = document.getElementById('reportForm');

    // Open Message Pet Modal
    if (messagePetBtn) {
        messagePetBtn.addEventListener('click', function() {
            messagePetModal.style.display = 'flex';
        });
    }

    // Close Message Pet Modal
    if (closeMessageModal) {
        closeMessageModal.addEventListener('click', function() {
            messagePetModal.style.display = 'none';
        });
    }

    if (cancelMessageBtn) {
        cancelMessageBtn.addEventListener('click', function() {
            messagePetModal.style.display = 'none';
        });
    }

    // Open Report Modal
    if (reportProfileBtn) {
        reportProfileBtn.addEventListener('click', function() {
            reportModal.style.display = 'flex';
        });
    }

    // Close Report Modal
    if (closeReportModal) {
        closeReportModal.addEventListener('click', function() {
            reportModal.style.display = 'none';
        });
    }

    if (cancelReportBtn) {
        cancelReportBtn.addEventListener('click', function() {
            reportModal.style.display = 'none';
        });
    }

    // Submit Message Pet Form
    if (messagePetForm) {
        messagePetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const petId = document.getElementById('petSelect').value;
            const message = document.getElementById('messageText').value;
            const userId = new URLSearchParams(window.location.search).get('id');
            
            if (!petId || !message || !userId) {
                showAlert('Please fill all required fields', 'error');
                return;
            }
            
            // Send message via AJAX
            fetch('../messages/send_pet_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `pet_id=${petId}&message=${encodeURIComponent(message)}&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Message sent successfully!', 'success');
                    messagePetModal.style.display = 'none';
                    messagePetForm.reset();
                } else {
                    showAlert(data.message || 'Error sending message', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while sending the message', 'error');
            });
        });
    }

    // Submit Report Form
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reason = document.getElementById('reportReason').value;
            const details = document.getElementById('reportDetails').value;
            const userId = new URLSearchParams(window.location.search).get('id');
            
            if (!reason || !userId) {
                showAlert('Please select a reason for your report', 'error');
                return;
            }
            
            // Send report via AJAX
            fetch('../profile/report_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}&reason=${encodeURIComponent(reason)}&details=${encodeURIComponent(details)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Report submitted successfully!', 'success');
                    reportModal.style.display = 'none';
                    reportForm.reset();
                } else {
                    showAlert(data.message || 'Error submitting report', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while submitting the report', 'error');
            });
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === messagePetModal) {
            messagePetModal.style.display = 'none';
        }
        if (event.target === reportModal) {
            reportModal.style.display = 'none';
        }
    });

    // Helper function to show alerts
    function showAlert(message, type = 'info') {
        const alertElement = document.createElement('div');
        alertElement.className = `alert alert-${type}`;
        alertElement.textContent = message;
        
        document.body.appendChild(alertElement);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            alertElement.classList.add('fade-out');
            setTimeout(() => {
                alertElement.remove();
            }, 500);
        }, 3000);
    }
}); 