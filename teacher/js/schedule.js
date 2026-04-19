/* ========================================
   SCHEDULE PAGE JS
   Author: PLNHS
   Description: JavaScript for schedule.php
======================================== */

// DOM Elements
const scheduleTable = document.querySelector('.schedule-table');
const classItems = document.querySelectorAll('.class-item');

// Add hover effect for class items
classItems.forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});

// Tooltip functionality for class items (optional)
function showClassDetails(element, details) {
    const tooltip = document.createElement('div');
    tooltip.className = 'class-tooltip';
    tooltip.innerHTML = details;
    tooltip.style.position = 'absolute';
    tooltip.style.background = '#1e293b';
    tooltip.style.color = 'white';
    tooltip.style.padding = '10px';
    tooltip.style.borderRadius = '8px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '1000';
    tooltip.style.maxWidth = '250px';
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = rect.top - 10 + window.scrollY + 'px';
    tooltip.style.left = rect.left + rect.width / 2 - 125 + 'px';
    
    document.body.appendChild(tooltip);
    
    setTimeout(() => {
        tooltip.remove();
    }, 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + P to print schedule
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printSchedule();
    }
});

// Print schedule function
function printSchedule() {
    const scheduleContainer = document.querySelector('.schedule-container');
    const originalTitle = document.title;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>My Schedule - <?php echo $teacher_name; ?></title>
                <style>
                    body {
                        font-family: 'Inter', Arial, sans-serif;
                        padding: 30px;
                        background: white;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    h1 {
                        color: #0B4F2E;
                        margin-bottom: 5px;
                    }
                    h3 {
                        color: #666;
                        font-weight: 400;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    th {
                        background: #f0f0f0;
                        padding: 12px;
                        text-align: center;
                        border: 1px solid #ddd;
                    }
                    td {
                        padding: 10px;
                        border: 1px solid #ddd;
                        vertical-align: top;
                    }
                    .time-column {
                        background: #f8f9fa;
                        font-weight: 600;
                    }
                    .class-item {
                        background: rgba(11, 79, 46, 0.08);
                        border-left: 3px solid #0B4F2E;
                        padding: 8px;
                        border-radius: 4px;
                    }
                    .empty-cell {
                        color: #999;
                        text-align: center;
                        padding: 15px;
                        font-style: italic;
                    }
                    .footer {
                        margin-top: 30px;
                        text-align: center;
                        color: #666;
                        font-size: 12px;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Placido L. Señor Senior High School</h1>
                    <h3>Teacher's Schedule</h3>
                    <p>Teacher: <?php echo $teacher_name; ?></p>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                </div>
                ${scheduleContainer.querySelector('.schedule-table').outerHTML}
                <div class="footer">
                    <p>This is a system-generated document. For official purposes only.</p>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Refresh schedule data (optional)
function refreshSchedule() {
    location.reload();
}

// Auto-refresh every 5 minutes (optional)
// setInterval(refreshSchedule, 300000);