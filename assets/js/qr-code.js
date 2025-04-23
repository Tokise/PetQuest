// QR Code Generation
function initQRCodes() {
    document.querySelectorAll('[data-qr]').forEach(element => {
        const qrContainer = element.querySelector('.qr-code-container');
        if (!qrContainer) return;
        
        const qrElement = qrContainer.querySelector('div');
        if (!qrElement) return;
        
        const qrUrl = element.dataset.qr;
        
        // Clear existing content
        qrElement.innerHTML = '';
        
        // Create QR code using QR Server API
        const qrImg = document.createElement('img');
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(qrUrl)}&size=150x150&margin=10`;
        qrImg.alt = 'QR Code';
        qrImg.style.width = '100px';
        qrImg.style.height = '100px';
        
        // Add loading indicator
        qrImg.style.opacity = '0';
        qrImg.onload = () => {
            qrImg.style.opacity = '1';
        };
        
        qrElement.appendChild(qrImg);
        
        // Add download functionality
        const downloadBtn = qrContainer.querySelector('.download-qr');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                // Use higher resolution for download
                const downloadUrl = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(qrUrl)}&size=300x300&margin=10`;
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `pet-qr-code-${element.dataset.petId}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    });
}