// Schedule Page JavaScript

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle) {
    menuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function (event) {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && sidebar && menuToggle) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.display = 'none';
            }
        }, 300);
    });
}, 5000);

// Add tooltip functionality for class items
const classItems = document.querySelectorAll('.class-item');
classItems.forEach(item => {
    item.addEventListener('mouseenter', function(e) {
        this.style.cursor = 'pointer';
    });
});

// Make schedule rows clickable (optional feature)
const scheduleRows = document.querySelectorAll('.schedule-table tbody tr');
scheduleRows.forEach(row => {
    row.addEventListener('click', function(e) {
        // Don't trigger if clicking on a class item
        if (e.target.closest('.class-item')) {
            const subject = e.target.closest('.class-item').querySelector('.subject-name')?.textContent;
            if (subject) {
                console.log(`Clicked on subject: ${subject}`);
                // You can add additional functionality here
                // e.g., show subject details modal
            }
        }
    });
});

// Highlight current time slot (if within school hours)
function highlightCurrentTimeSlot() {
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    const currentTime = currentHour + (currentMinute / 60);
    
    const timeColumns = document.querySelectorAll('.time-column');
    timeColumns.forEach(col => {
        const timeText = col.querySelector('strong')?.textContent;
        if (timeText) {
            // Parse time range (e.g., "08:00 AM - 09:00 AM")
            const timeMatch = timeText.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
            if (timeMatch) {
                let hour = parseInt(timeMatch[1]);
                const minute = parseInt(timeMatch[2]);
                const period = timeMatch[3].toUpperCase();
                
                if (period === 'PM' && hour !== 12) hour += 12;
                if (period === 'AM' && hour === 12) hour = 0;
                
                const slotStartTime = hour + (minute / 60);
                
                // If current time is within this time slot (±30 minutes)
                if (Math.abs(currentTime - slotStartTime) <= 0.5) {
                    col.style.background = 'rgba(11, 79, 46, 0.1)';
                    col.style.fontWeight = 'bold';
                }
            }
        }
    });
}

// Call highlight function if within school hours (7 AM - 5 PM)
const currentHour = new Date().getHours();
if (currentHour >= 7 && currentHour <= 17) {
    highlightCurrentTimeSlot();
}

// Add print functionality (optional)
function printSchedule() {
    const scheduleContainer = document.querySelector('.schedule-container');
    const sectionCard = document.querySelector('.section-card');
    const originalTitle = document.title;
    
    if (scheduleContainer) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>My Class Schedule - <?php echo htmlspecialchars($section_name); ?></title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <style>
                    body {
                        font-family: 'Inter', sans-serif;
                        padding: 20px;
                        margin: 0;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    .print-header h1 {
                        color: #0B4F2E;
                        margin-bottom: 5px;
                    }
                    .schedule-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    .schedule-table th, .schedule-table td {
                        border: 1px solid #ddd;
                        padding: 10px;
                        text-align: left;
                    }
                    .schedule-table th {
                        background: #0B4F2E;
                        color: white;
                    }
                    .class-item {
                        margin: 5px 0;
                    }
                    .subject-name {
                        font-weight: bold;
                    }
                    @media print {
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h1>Placido L. Señor Senior High School</h1>
                    <p>Class Schedule - ${document.querySelector('.section-title h2')?.textContent || ''}</p>
                    <p>School Year: ${document.querySelector('.detail-item:nth-child(2)')?.textContent?.replace('School Year: ', '') || ''}</p>
                </div>
                ${scheduleContainer.cloneNode(true).innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

// Add keyboard shortcut for printing (Ctrl+P)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printSchedule();
    }
});

// Handle window resize - reset sidebar state on desktop
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    }, 250);
});

// Add smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href !== '#!' && href !== '#0') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// Add current time indicator to schedule
function addCurrentTimeIndicator() {
    const now = new Date();
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' });
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    
    // Find the column for today
    const todayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].indexOf(currentDay);
    if (todayIndex !== -1) {
        const todayColumn = document.querySelectorAll('.schedule-table th')[todayIndex + 1];
        if (todayColumn && todayColumn.classList.contains('today-highlight')) {
            // Already highlighted
        }
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    addCurrentTimeIndicator();
    
    // Add active class to current nav item
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-items a');
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
});