/* ========================================
   QR ATTENDANCE PAGE JS
   Author: PLNHS
   Description: JavaScript for attendance_qr.php
======================================== */

let video = null;
let canvas = null;
let ctx = null;
let scanning = false;
let animationId = null;
let stream = null;
let lastScannedToken = null;
let scanTimeout = null;

const videoElement = document.getElementById('video');
const canvasElement = document.getElementById('canvas');
const scanLine = document.getElementById('scanLine');
const startCameraBtn = document.getElementById('startCameraBtn');
const stopCameraBtn = document.getElementById('stopCameraBtn');
const scanResult = document.getElementById('scanResult');
const scanResultText = document.getElementById('scanResultText');

// Auto-start camera when page loads
document.addEventListener('DOMContentLoaded', function() {
    startCamera();
});

// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.scanner-tab').forEach(t => t.classList.remove('active-tab'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    if(tab === 'camera') {
        document.getElementById('cameraScannerTab').classList.add('active-tab');
        document.querySelector('.tab-btn:first-child').classList.add('active');
        setTimeout(function() {
            startCamera();
        }, 100);
    } else {
        document.getElementById('uploadScannerTab').classList.add('active-tab');
        document.querySelector('.tab-btn:last-child').classList.add('active');
        stopCamera();
    }
    
    // Hide scan result when switching tabs
    scanResult.style.display = 'none';
    lastScannedToken = null;
}

// Start Camera
async function startCamera() {
    try {
        // Check if camera is already running
        if (stream && stream.active) {
            return;
        }
        
        const constraints = {
            video: {
                facingMode: "environment",
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };
        
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        videoElement.srcObject = stream;
        videoElement.setAttribute("playsinline", true);
        await videoElement.play();
        
        startCameraBtn.style.display = 'none';
        stopCameraBtn.style.display = 'inline-block';
        if (scanLine) scanLine.style.display = 'block';
        
        // Give video time to initialize dimensions
        setTimeout(function() {
            if (videoElement.videoWidth > 0) {
                canvasElement.width = videoElement.videoWidth;
                canvasElement.height = videoElement.videoHeight;
                ctx = canvasElement.getContext('2d');
                scanning = true;
                scanQRCode();
            }
        }, 500);
        
    } catch (err) {
        console.error("Camera error:", err);
        let errorMsg = "Cannot access camera. ";
        if (err.name === 'NotAllowedError') {
            errorMsg += "Please grant camera permission in your browser settings.";
        } else if (err.name === 'NotFoundError') {
            errorMsg += "No camera found on your device.";
        } else if (err.name === 'NotSupportedError') {
            errorMsg += "Camera access requires HTTPS. For localhost, please use Chrome or Edge.";
        } else {
            errorMsg += "Please ensure you have a camera connected and try again.";
        }
        showResult("error", errorMsg);
        startCameraBtn.style.display = 'inline-block';
        stopCameraBtn.style.display = 'none';
    }
}

// Stop Camera
function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => {
            track.stop();
        });
        stream = null;
    }
    if (videoElement) {
        videoElement.srcObject = null;
    }
    scanning = false;
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    startCameraBtn.style.display = 'inline-block';
    stopCameraBtn.style.display = 'none';
    if (scanLine) scanLine.style.display = 'none';
}

// Scan QR Code from video
// Scan QR Code from video
function scanQRCode() {
    if (!scanning || !videoElement || videoElement.readyState !== videoElement.HAVE_ENOUGH_DATA) {
        if (scanning) {
            animationId = requestAnimationFrame(scanQRCode);
        }
        return;
    }
    
    try {
        if (videoElement.videoWidth > 0 && videoElement.videoHeight > 0) {
            canvasElement.width = videoElement.videoWidth;
            canvasElement.height = videoElement.videoHeight;
            ctx.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
            const imageData = ctx.getImageData(0, 0, canvasElement.width, canvasElement.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });
            
            if (code && code.data) {
                const qrData = code.data;
                console.log("QR Code detected:", qrData);
                
                // Extract token from QR data - handles both formats
                let token = null;
                if (qrData.includes('process_attendance.php?token=')) {
                    const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                    if (tokenMatch) {
                        token = tokenMatch[1];
                    }
                } else if (qrData.length === 32 && /^[a-f0-9]{32}$/.test(qrData)) {
                    // Direct token (32 character MD5 hash)
                    token = qrData;
                } else if (qrData.includes('token=')) {
                    const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                    if (tokenMatch) {
                        token = tokenMatch[1];
                    }
                }
                
                if (token) {
                    // Prevent duplicate scans within 3 seconds
                    if (lastScannedToken === token) {
                        console.log("Duplicate scan prevented");
                        return;
                    }
                    lastScannedToken = token;
                    
                    // Clear previous timeout
                    if (scanTimeout) clearTimeout(scanTimeout);
                    
                    // Reset lastScannedToken after 3 seconds
                    scanTimeout = setTimeout(() => {
                        lastScannedToken = null;
                    }, 3000);
                    
                    stopCamera();
                    processAttendance(token);
                } else {
                    showResult("error", "Invalid QR code format. Please scan a valid teacher attendance QR code.");
                }
            }
        }
    } catch (e) {
        console.error("Scan error:", e);
    }
    
    if (scanning) {
        animationId = requestAnimationFrame(scanQRCode);
    }
}

// Upload and scan image
// Upload and scan image
function uploadImage(input) {
    const file = input.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const previewDiv = document.getElementById('previewImage');
            const previewImg = document.getElementById('previewImg');
            previewImg.src = e.target.result;
            previewDiv.style.display = 'block';
            
            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');
            tempCanvas.width = img.width;
            tempCanvas.height = img.height;
            tempCtx.drawImage(img, 0, 0, img.width, img.height);
            const imageData = tempCtx.getImageData(0, 0, img.width, img.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert",
            });
            
            if (code && code.data) {
                const qrData = code.data;
                console.log("QR Code from image:", qrData);
                
                let token = null;
                if (qrData.includes('process_attendance.php?token=')) {
                    const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                    if (tokenMatch) {
                        token = tokenMatch[1];
                    }
                } else if (qrData.length === 32 && /^[a-f0-9]{32}$/.test(qrData)) {
                    token = qrData;
                } else if (qrData.includes('token=')) {
                    const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                    if (tokenMatch) {
                        token = tokenMatch[1];
                    }
                }
                
                if (token) {
                    processAttendance(token);
                } else {
                    showResult("error", "Invalid QR code format. Please scan a valid teacher attendance QR code.");
                }
            } else {
                showResult("error", "No QR code found in the image. Please try another photo.");
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function showResult(type, message) {
    scanResult.className = 'scan-result ' + type;
    scanResultText.innerHTML = '<i class="fas ' + 
        (type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-spinner fa-pulse')) + 
        '"></i> ' + message;
    scanResult.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
}

// Process attendance via AJAX
function processAttendance(token) {
    showResult("loading", "Processing attendance...");
    
    fetch(`process_attendance.php?token=${token}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showResult("success", data.message);
        } else {
            showResult("error", data.message);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        showResult("error", "An error occurred. Please try again.");
    });
}

// Clean up when leaving page
window.addEventListener('beforeunload', function() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC to stop camera
    if (e.key === 'Escape') {
        stopCamera();
    }
    // Ctrl + G to generate QR
    if (e.ctrlKey && e.key === 'g') {
        e.preventDefault();
        const generateBtn = document.querySelector('.btn-generate:not(.regenerate)');
        if (generateBtn && generateBtn.href) {
            window.location.href = generateBtn.href;
        }
    }
});