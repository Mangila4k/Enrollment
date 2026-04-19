/* ========================================
   CLASSES PAGE JS
   Author: PLNHS
   Description: JavaScript for classes.php
======================================== */

// Tab switching functionality
const tabBtns = document.querySelectorAll('.tab-btn');
const tabContents = {
    'advisory': document.getElementById('advisory'),
    'subjects': document.getElementById('subjects'),
    'schedule': document.getElementById('schedule')
};

tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const targetTab = this.getAttribute('data-tab');
        
        // Remove active class from all tabs
        tabBtns.forEach(btn => btn.classList.remove('active'));
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active-tab');
        });
        
        // Show selected tab content
        if (tabContents[targetTab]) {
            tabContents[targetTab].classList.add('active-tab');
        }
    });
});

// Add hover effect for class cards
const classCards = document.querySelectorAll('.class-card');
classCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + 1 for Advisory Classes
    if (e.altKey && e.key === '1') {
        e.preventDefault();
        document.querySelector('.tab-btn[data-tab="advisory"]').click();
    }
    // Alt + 2 for Subjects
    if (e.altKey && e.key === '2') {
        e.preventDefault();
        document.querySelector('.tab-btn[data-tab="subjects"]').click();
    }
    // Alt + 3 for Schedule
    if (e.altKey && e.key === '3') {
        e.preventDefault();
        document.querySelector('.tab-btn[data-tab="schedule"]').click();
    }
});