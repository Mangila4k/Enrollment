/* ========================================
   STUDENT SCHEDULE PAGE JAVASCRIPT
   Author: PLNHS
======================================== */

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Add hover effects to class items
    const classItems = document.querySelectorAll('.class-item');
    classItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(3px)';
        });
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add tooltips to class items
    const classItemsWithTooltip = document.querySelectorAll('.class-item');
    classItemsWithTooltip.forEach(item => {
        const subjectName = item.querySelector('.subject-name')?.innerText || '';
        const teacherName = item.querySelector('.teacher-name')?.innerText || '';
        
        if (subjectName || teacherName) {
            item.setAttribute('title', `${subjectName}\n${teacherName}`);
        }
    });
});

// Print schedule function
function printSchedule() {
    window.print();
}

// Export schedule as PDF (using browser print)
function exportAsPDF() {
    window.print();
}

// Toggle schedule view (weekly/monthly)
function toggleView(view) {
    const scheduleContainer = document.querySelector('.schedule-container');
    if (view === 'weekly') {
        // Show weekly view
        document.querySelector('.schedule-table')?.classList.remove('monthly-view');
        document.querySelector('.schedule-table')?.classList.add('weekly-view');
    } else if (view === 'monthly') {
        // Show monthly view
        document.querySelector('.schedule-table')?.classList.remove('weekly-view');
        document.querySelector('.schedule-table')?.classList.add('monthly-view');
    }
}

// Highlight current time period
function highlightCurrentPeriod() {
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    const currentTime = currentHour + (currentMinute / 60);
    
    const timeRows = document.querySelectorAll('.schedule-table tbody tr');
    timeRows.forEach(row => {
        const timeColumn = row.querySelector('.time-column');
        if (timeColumn) {
            const timeText = timeColumn.innerText;
            const timeMatch = timeText.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
            
            if (timeMatch) {
                let hour = parseInt(timeMatch[1]);
                const minute = parseInt(timeMatch[2]);
                const period = timeMatch[3].toUpperCase();
                
                if (period === 'PM' && hour !== 12) hour += 12;
                if (period === 'AM' && hour === 12) hour = 0;
                
                const periodTime = hour + (minute / 60);
                const nextPeriodTime = periodTime + 1;
                
                if (currentTime >= periodTime && currentTime < nextPeriodTime) {
                    row.style.backgroundColor = 'rgba(11, 79, 46, 0.05)';
                    row.style.borderLeft = '3px solid var(--primary)';
                } else {
                    row.style.backgroundColor = '';
                    row.style.borderLeft = '';
                }
            }
        }
    });
}

// Run highlight every minute
setInterval(highlightCurrentPeriod, 60000);
highlightCurrentPeriod();

// Search/filter schedule
function filterSchedule(searchTerm) {
    const classItems = document.querySelectorAll('.class-item');
    const term = searchTerm.toLowerCase().trim();
    
    classItems.forEach(item => {
        const text = item.innerText.toLowerCase();
        const parentCell = item.closest('td');
        
        if (term === '') {
            if (parentCell) parentCell.style.opacity = '1';
            item.style.opacity = '1';
        } else if (text.includes(term)) {
            if (parentCell) parentCell.style.opacity = '1';
            item.style.opacity = '1';
            item.style.backgroundColor = 'rgba(11, 79, 46, 0.15)';
        } else {
            if (parentCell) parentCell.style.opacity = '0.4';
            item.style.opacity = '0.4';
            item.style.backgroundColor = '';
        }
    });
}

// Reset filter
function resetFilter() {
    const classItems = document.querySelectorAll('.class-item');
    classItems.forEach(item => {
        const parentCell = item.closest('td');
        if (parentCell) parentCell.style.opacity = '1';
        item.style.opacity = '1';
        item.style.backgroundColor = '';
    });
    
    const searchInput = document.querySelector('.schedule-search');
    if (searchInput) searchInput.value = '';
}

// Add search bar dynamically
document.addEventListener('DOMContentLoaded', function() {
    const scheduleHeader = document.querySelector('.schedule-header');
    if (scheduleHeader && !document.querySelector('.schedule-search')) {
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'schedule-search-wrapper';
        searchWrapper.innerHTML = `
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" class="schedule-search" placeholder="Search subject, teacher, or room..." onkeyup="filterSchedule(this.value)">
                <button class="search-clear" onclick="resetFilter()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="view-buttons">
                <button class="view-btn active" onclick="toggleView('weekly')">
                    <i class="fas fa-calendar-week"></i> Weekly
                </button>
                <button class="view-btn" onclick="toggleView('monthly')">
                    <i class="fas fa-calendar-alt"></i> Monthly
                </button>
                <button class="view-btn" onclick="printSchedule()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        `;
        
        scheduleHeader.appendChild(searchWrapper);
        
        // Add styles for search wrapper
        const style = document.createElement('style');
        style.textContent = `
            .schedule-search-wrapper {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }
            .search-input-group {
                display: flex;
                align-items: center;
                background: var(--bg-light);
                border: 1px solid var(--border);
                border-radius: 30px;
                padding: 5px 15px;
                flex: 1;
                min-width: 200px;
            }
            .search-input-group i {
                color: var(--text-gray);
            }
            .schedule-search {
                border: none;
                background: transparent;
                padding: 10px;
                flex: 1;
                outline: none;
                font-size: 14px;
            }
            .search-clear {
                background: none;
                border: none;
                cursor: pointer;
                color: var(--text-gray);
                padding: 5px;
            }
            .search-clear:hover {
                color: var(--danger);
            }
            .view-buttons {
                display: flex;
                gap: 10px;
            }
            .view-btn {
                background: var(--bg-light);
                border: 1px solid var(--border);
                padding: 8px 15px;
                border-radius: 30px;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.3s;
            }
            .view-btn:hover {
                border-color: var(--primary);
                color: var(--primary);
            }
            .view-btn.active {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }
            @media (max-width: 768px) {
                .schedule-search-wrapper {
                    flex-direction: column;
                }
                .search-input-group {
                    width: 100%;
                }
                .view-buttons {
                    width: 100%;
                    justify-content: center;
                }
            }
        `;
        document.head.appendChild(style);
    }
});