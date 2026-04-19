/* ========================================
   STUDENTS PAGE JS
   Author: PLNHS
   Description: JavaScript for students.php
======================================== */

// DOM Elements
const exportExcelBtn = document.getElementById('exportExcelBtn');
const printBtn = document.getElementById('printBtn');

// Export to Excel function
function exportToExcel() {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tr'));
    let csv = [];
    
    rows.forEach(row => {
        const cols = Array.from(row.querySelectorAll('th, td'));
        // Exclude action buttons column (last column)
        const rowData = cols.slice(0, -1).map(col => {
            // Get text content, remove extra spaces and commas
            let text = col.innerText.replace(/"/g, '""').replace(/\s+/g, ' ').trim();
            return '"' + text + '"';
        }).join(',');
        csv.push(rowData);
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students_export_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Print function
function printTable() {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    const tableClone = table.cloneNode(true);
    // Remove action buttons column
    tableClone.querySelectorAll('tr').forEach(row => {
        const lastCell = row.lastElementChild;
        if (lastCell && (lastCell.textContent.includes('View') || 
            lastCell.textContent.includes('Edit') ||
            lastCell.textContent.includes('Delete'))) {
            row.removeChild(lastCell);
        }
    });
    
    const newWindow = window.open('', '_blank');
    newWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>Student Records</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        font-family: 'Inter', Arial, sans-serif;
                        padding: 40px;
                        background: white;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #0B4F2E;
                    }
                    h2 {
                        color: #0B4F2E;
                        margin-bottom: 5px;
                        font-size: 24px;
                    }
                    h3 {
                        color: #666;
                        font-weight: 400;
                        margin-bottom: 10px;
                        font-size: 16px;
                    }
                    .date {
                        color: #999;
                        font-size: 12px;
                        margin-top: 10px;
                    }
                    table {
                        border-collapse: collapse;
                        width: 100%;
                        margin-top: 20px;
                    }
                    th {
                        background: #f0f0f0;
                        padding: 12px;
                        text-align: left;
                        font-size: 13px;
                        font-weight: 600;
                        border-bottom: 2px solid #ddd;
                    }
                    td {
                        padding: 10px;
                        border-bottom: 1px solid #ddd;
                        font-size: 13px;
                    }
                    .badge {
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        display: inline-block;
                    }
                    .badge-pending {
                        background: #fff3cd;
                        color: #856404;
                    }
                    .badge-enrolled {
                        background: #d4edda;
                        color: #155724;
                    }
                    .badge-rejected {
                        background: #f8d7da;
                        color: #721c24;
                    }
                    .grade-tag {
                        background: #e9ecef;
                        padding: 3px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        display: inline-block;
                        margin-right: 5px;
                    }
                    .footer {
                        margin-top: 30px;
                        text-align: center;
                        color: #999;
                        font-size: 11px;
                        padding-top: 20px;
                        border-top: 1px solid #eee;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Placido L. Señor Senior High School</h2>
                    <h3>Student Records</h3>
                    <div class="date">Generated on: ${new Date().toLocaleString()}</div>
                </div>
                ${tableClone.outerHTML}
                <div class="footer">
                    This is a system-generated document. For official purposes only.
                </div>
            </body>
        </html>
    `);
    newWindow.document.close();
    newWindow.print();
}

// Export button event
if (exportExcelBtn) {
    exportExcelBtn.addEventListener('click', exportToExcel);
}

// Print button event
if (printBtn) {
    printBtn.addEventListener('click', printTable);
}

// Confirm delete for all delete links
const deleteLinks = document.querySelectorAll('.action-btn.delete');
deleteLinks.forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this student? This will also delete all associated records (enrollments, attendance, etc.). This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});

// Add hover effect for table rows
const tableRows = document.querySelectorAll('.data-table tbody tr');
tableRows.forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});

// Filter form auto-submit on select change (optional)
const filterSelects = document.querySelectorAll('.filter-select');
filterSelects.forEach(select => {
    select.addEventListener('change', function() {
        this.closest('form').submit();
    });
});