/* ========================================
   VIEW SECTION PAGE JS
   Author: PLNHS
   Description: JavaScript for view_section.php
======================================== */

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Handle window resize - reset sidebar state
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Initialize all functions
    initScrollToTop();
    initTooltips();
    initKeyboardShortcuts();
    initTableHoverEffects();
    initScheduleItemHover();
    initSearchFilter();
    initPrintFunction();
});

// Scroll to top button
function initScrollToTop() {
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollTopBtn.className = 'scroll-top-btn';
    scrollTopBtn.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
        display: none;
        z-index: 1000;
        transition: all 0.3s;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(scrollTopBtn);
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            scrollTopBtn.style.display = 'block';
        } else {
            scrollTopBtn.style.display = 'none';
        }
    });
    
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    scrollTopBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.background = 'var(--primary-light)';
    });
    
    scrollTopBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.background = 'var(--primary)';
    });
}

// Tooltips for action buttons
function initTooltips() {
    const actionButtons = document.querySelectorAll('.action-btn, .back-btn, .btn-print');
    
    actionButtons.forEach(btn => {
        let tooltipTimeout;
        
        btn.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('title') || this.innerText.trim();
            if (!text) return;
            
            tooltipTimeout = setTimeout(() => {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = text;
                tooltip.style.cssText = `
                    position: fixed;
                    background: #1e293b;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 500;
                    z-index: 10000;
                    white-space: nowrap;
                    pointer-events: none;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                `;
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - 30) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2 - 30) + 'px';
                
                document.body.appendChild(tooltip);
                
                this.addEventListener('mouseleave', function() {
                    clearTimeout(tooltipTimeout);
                    if (tooltip && tooltip.remove) tooltip.remove();
                }, { once: true });
            }, 500);
        });
        
        btn.addEventListener('mouseleave', function() {
            clearTimeout(tooltipTimeout);
        });
    });
}

// Keyboard shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + B to go back
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            window.history.back();
        }
        
        // Ctrl + P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printSectionDetails();
        }
        
        // Ctrl + S to search
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) searchInput.focus();
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                filterStudents('');
            }
        }
    });
}

// Table hover effects
function initTableHoverEffects() {
    const tableRows = document.querySelectorAll('.students-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'var(--bg-light)';
            this.style.transition = 'background-color 0.2s';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

// Schedule item hover effects
function initScheduleItemHover() {
    const scheduleItems = document.querySelectorAll('.schedule-item');
    scheduleItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.transition = 'transform 0.3s';
        });
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
}

// Search and filter students
function initSearchFilter() {
    // Create search input if not exists
    const cardHeader = document.querySelector('.card:first-child .card-header');
    if (cardHeader && !document.querySelector('.student-search')) {
        const searchDiv = document.createElement('div');
        searchDiv.className = 'student-search';
        searchDiv.style.cssText = 'margin-top: 15px; margin-bottom: 15px;';
        searchDiv.innerHTML = `
            <div class="search-wrapper" style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-gray);"></i>
                <input type="text" id="studentSearchInput" class="search-input" placeholder="Search students by name or ID..." 
                       style="width: 100%; padding: 10px 12px 10px 38px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px;">
            </div>
        `;
        
        const tableContainer = document.querySelector('.card:first-child .table-container');
        if (tableContainer) {
            tableContainer.insertBefore(searchDiv, tableContainer.firstChild);
            
            const searchInput = document.getElementById('studentSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    filterStudents(this.value);
                });
            }
        }
    }
}

function filterStudents(searchTerm) {
    const rows = document.querySelectorAll('.students-table tbody tr');
    const term = searchTerm.toLowerCase().trim();
    
    rows.forEach(row => {
        const studentName = row.querySelector('.student-details h4')?.innerText.toLowerCase() || '';
        const studentId = row.querySelector('.student-meta span:first-child')?.innerText.toLowerCase() || '';
        const studentEmail = row.querySelector('.student-meta span:last-child')?.innerText.toLowerCase() || '';
        
        if (studentName.includes(term) || studentId.includes(term) || studentEmail.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    const noResultsMsg = document.querySelector('.no-results-message');
    
    if (visibleRows.length === 0 && rows.length > 0) {
        if (!noResultsMsg) {
            const tbody = document.querySelector('.students-table tbody');
            const msgRow = document.createElement('tr');
            msgRow.className = 'no-results-message';
            msgRow.innerHTML = `<td colspan="3"><div class="no-data"><i class="fas fa-search"></i><p>No students found matching "${searchTerm}"</p></div></td>`;
            if (tbody) tbody.appendChild(msgRow);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

// Print section details
function initPrintFunction() {
    // Add print button if not exists
    const headerActions = document.querySelector('.header-actions');
    if (headerActions && !document.querySelector('.btn-print')) {
        const printBtn = document.createElement('button');
        printBtn.className = 'btn-print';
        printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
        printBtn.style.cssText = `
            background: var(--bg-white);
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-dark);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s;
        `;
        
        printBtn.addEventListener('mouseenter', function() {
            this.style.borderColor = 'var(--primary)';
            this.style.color = 'var(--primary)';
            this.style.transform = 'translateY(-2px)';
        });
        
        printBtn.addEventListener('mouseleave', function() {
            this.style.borderColor = 'var(--border)';
            this.style.color = 'var(--text-dark)';
            this.style.transform = 'translateY(0)';
        });
        
        printBtn.addEventListener('click', function() {
            printSectionDetails();
        });
        
        headerActions.appendChild(printBtn);
    }
}

function printSectionDetails() {
    const sectionHeader = document.querySelector('.section-header');
    const statsGrid = document.querySelector('.stats-grid');
    const studentsCard = document.querySelector('.card:first-child');
    const scheduleCard = document.querySelector('.card:last-child');
    
    if (!sectionHeader) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${document.title}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Inter', Arial, sans-serif; padding: 40px; background: white; }
                .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #0B4F2E; }
                h1 { color: #0B4F2E; font-size: 24px; margin-bottom: 5px; }
                h2 { color: #333; font-size: 18px; margin: 20px 0 10px; }
                .subtitle { color: #666; font-size: 14px; margin-bottom: 10px; }
                .date { color: #999; font-size: 12px; margin-top: 10px; }
                .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                .stat-card { background: #f5f5f5; padding: 15px; border-radius: 10px; text-align: center; }
                .stat-number { font-size: 28px; font-weight: bold; color: #0B4F2E; }
                .stat-label { font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background: #f0f0f0; padding: 10px; text-align: left; border: 1px solid #ddd; }
                td { padding: 10px; border: 1px solid #ddd; }
                .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; }
                .status-enrolled { background: #d4edda; color: #155724; }
                .status-pending { background: #fff3cd; color: #856404; }
                .schedule-item { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
                .day-time { font-weight: bold; color: #0B4F2E; }
                .footer { margin-top: 30px; text-align: center; color: #999; font-size: 11px; padding-top: 20px; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Placido L. Señor National High School</h1>
                <h2>${document.querySelector('.section-title-info h1')?.innerText || 'Section Details'}</h2>
                <div class="subtitle">${document.querySelector('.badge-grade')?.innerText || ''}</div>
                <div class="date">Generated on: ${new Date().toLocaleString()}</div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">${sectionData.studentCount || 0}</div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${sectionData.scheduleCount || 0}</div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${sectionData.attendanceRate || 0}%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${sectionData.subjectsTaught || 0}</div>
                    <div class="stat-label">Your Subjects</div>
                </div>
            </div>
            
            <h2>Enrolled Students</h2>
            ${studentsCard ? studentsCard.querySelector('.table-container')?.innerHTML || '<p>No students found</p>' : '<p>No students found</p>'}
            
            <h2>Class Schedule</h2>
            ${scheduleCard ? scheduleCard.querySelector('.schedule-list')?.innerHTML || '<p>No schedule found</p>' : '<p>No schedule found</p>'}
            
            <div class="footer">
                This is a system-generated document. For official purposes only.
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Export data to CSV
function exportToCSV() {
    const rows = document.querySelectorAll('.students-table tbody tr');
    if (!rows.length) return;
    
    let csv = [['Student Name', 'ID Number', 'Email', 'Status']];
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const name = row.querySelector('.student-details h4')?.innerText || '';
            const id = row.querySelector('.student-meta span:first-child')?.innerText.replace('ID:', '').trim() || '';
            const email = row.querySelector('.student-meta span:last-child')?.innerText.replace('✉️', '').trim() || '';
            const status = row.querySelector('.status-badge')?.innerText || '';
            
            csv.push([name, id, email, status]);
        }
    });
    
    const csvContent = csv.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students_${sectionData.name}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Add export button
document.addEventListener('DOMContentLoaded', function() {
    const cardHeader = document.querySelector('.card:first-child .card-header');
    if (cardHeader && !document.querySelector('.export-btn')) {
        const exportBtn = document.createElement('button');
        exportBtn.className = 'export-btn';
        exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
        exportBtn.style.cssText = `
            background: var(--primary-light);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
        `;
        
        exportBtn.addEventListener('click', exportToCSV);
        
        const badge = cardHeader.querySelector('.badge');
        if (badge) {
            badge.parentNode.insertBefore(exportBtn, badge.nextSibling);
        }
    }
});