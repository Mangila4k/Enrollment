/* ========================================
   REPORTS PAGE JS
   Author: PLNHS
   Description: JavaScript for reports.php
======================================== */

// DOM Elements
const exportExcelBtn = document.getElementById('exportExcelBtn');
const printBtn = document.getElementById('printBtn');
const reportTable = document.getElementById('reportTable');

// Export to Excel function
function exportToExcel() {
    if (!reportTable) return;
    
    const rows = Array.from(reportTable.querySelectorAll('tr'));
    let csv = [];
    
    rows.forEach(row => {
        const cols = Array.from(row.querySelectorAll('th, td'));
        const rowData = cols.map(col => {
            let text = col.innerText.replace(/"/g, '""').replace(/\s+/g, ' ').trim();
            return '"' + text + '"';
        }).join(',');
        csv.push(rowData);
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `report_${reportData.reportType}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Print function
function printReport() {
    if (!reportTable) return;
    
    const tableClone = reportTable.cloneNode(true);
    const reportTitle = document.querySelector('.report-header h2')?.innerText || 'Report';
    const dateRange = document.querySelector('.date-range')?.innerText || '';
    
    const newWindow = window.open('', '_blank');
    newWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>${reportTitle}</title>
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
                        margin-bottom: 5px;
                        font-size: 16px;
                    }
                    .date-range {
                        color: #666;
                        font-size: 13px;
                        margin-bottom: 10px;
                    }
                    .generated-date {
                        color: #999;
                        font-size: 12px;
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
                    tfoot td {
                        background: #f8f9fa;
                        font-weight: 600;
                        text-align: right;
                        border-top: 2px solid #ddd;
                    }
                    .footer {
                        margin-top: 30px;
                        text-align: center;
                        color: #999;
                        font-size: 11px;
                        padding-top: 20px;
                        border-top: 1px solid #eee;
                    }
                    @media print {
                        body {
                            padding: 20px;
                        }
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Placido L. Señor Senior High School</h2>
                    <h3>${reportTitle}</h3>
                    <div class="date-range">${dateRange}</div>
                    <div class="generated-date">Generated on: ${new Date().toLocaleString()}</div>
                </div>
                ${tableClone.outerHTML}
                <div class="footer">
                    <p>This is a system-generated report. For official purposes only.</p>
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
    printBtn.addEventListener('click', printReport);
}

// Auto-submit form when report type changes (optional)
const reportTypeSelect = document.querySelector('select[name="report_type"]');
if (reportTypeSelect) {
    reportTypeSelect.addEventListener('change', function() {
        this.closest('form').submit();
    });
}

// Date range validation
const dateFrom = document.querySelector('input[name="date_from"]');
const dateTo = document.querySelector('input[name="date_to"]');

if (dateFrom && dateTo) {
    dateTo.addEventListener('change', function() {
        if (dateFrom.value && this.value && this.value < dateFrom.value) {
            alert('Date To cannot be earlier than Date From');
            this.value = dateFrom.value;
        }
    });
    
    dateFrom.addEventListener('change', function() {
        if (dateTo.value && this.value > dateTo.value) {
            alert('Date From cannot be later than Date To');
            this.value = dateTo.value;
        }
    });
}