/* ========================================
   VIEW SECTION PAGE JS
   Author: PLNHS
   Description: JavaScript for view_section.php
======================================== */

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });
}

// Add hover effect for schedule items
const scheduleItems = document.querySelectorAll('.schedule-item');
scheduleItems.forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + B to go back
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        window.location.href = 'classes.php';
    }
    // Ctrl + G to go to grades
    if (e.ctrlKey && e.key === 'g') {
        e.preventDefault();
        const gradesLink = document.querySelector('.action-btn.warning');
        if (gradesLink) {
            window.location.href = gradesLink.href;
        }
    }
    // Ctrl + S to go to schedule
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const scheduleLink = document.querySelector('.action-btn.secondary');
        if (scheduleLink && scheduleLink.href.includes('schedule.php')) {
            window.location.href = scheduleLink.href;
        }
    }
});

// Tooltip for action buttons
const actionButtons = document.querySelectorAll('.action-btn');
actionButtons.forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        const title = this.getAttribute('title');
        if (title) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = title;
            tooltip.style.position = 'absolute';
            tooltip.style.background = '#1e293b';
            tooltip.style.color = 'white';
            tooltip.style.padding = '4px 8px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '11px';
            tooltip.style.zIndex = '1000';
            const rect = this.getBoundingClientRect();
            tooltip.style.top = rect.top - 25 + window.scrollY + 'px';
            tooltip.style.left = rect.left + rect.width / 2 - 30 + 'px';
            document.body.appendChild(tooltip);
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, { once: true });
        }
    });
});

// Scroll to top button functionality
const scrollTopBtn = document.createElement('button');
scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
scrollTopBtn.className = 'scroll-top-btn';
scrollTopBtn.style.position = 'fixed';
scrollTopBtn.style.bottom = '20px';
scrollTopBtn.style.right = '20px';
scrollTopBtn.style.width = '45px';
scrollTopBtn.style.height = '45px';
scrollTopBtn.style.borderRadius = '50%';
scrollTopBtn.style.background = 'var(--primary)';
scrollTopBtn.style.color = 'white';
scrollTopBtn.style.border = 'none';
scrollTopBtn.style.cursor = 'pointer';
scrollTopBtn.style.display = 'none';
scrollTopBtn.style.zIndex = '1000';
scrollTopBtn.style.transition = 'all 0.3s';

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