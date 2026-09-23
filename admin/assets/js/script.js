// // Sidebar Toggle
// document.addEventListener('DOMContentLoaded', function() {
//     const sidebarToggle = document.getElementById('sidebarToggle');
//     const sidebar = document.getElementById('sidebar');
//     const mainContent = document.getElementById('mainContent');
    
//     if (sidebarToggle) {
//         sidebarToggle.addEventListener('click', function() {
//             if (window.innerWidth <= 992) {
//                 sidebar.classList.toggle('show');
//             } else {
//                 sidebar.classList.toggle('collapsed');
//                 mainContent.classList.toggle('expanded');
//             }
//         });
//     }
    
//     // Close sidebar on mobile when clicking outside
//     document.addEventListener('click', function(e) {
//         if (window.innerWidth <= 992) {
//             if (sidebar && sidebar.classList.contains('show')) {
//                 if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
//                     sidebar.classList.remove('show');
//                 }
//             }
//         }
//     });
    
//     // Auto-hide alerts after 5 seconds
//     const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
//     alerts.forEach(function(alert) {
//         setTimeout(function() {
//             const bsAlert = new bootstrap.Alert(alert);
//             bsAlert.close();
//         }, 5000);
//     });
// });

// // Form validation
// function validateForm(formId) {
//     const form = document.getElementById(formId);
//     if (!form) return false;
    
//     const inputs = form.querySelectorAll('[required]');
//     let isValid = true;
    
//     inputs.forEach(function(input) {
//         if (!input.value.trim()) {
//             input.classList.add('is-invalid');
//             isValid = false;
//         } else {
//             input.classList.remove('is-invalid');
//         }
//     });
    
//     return isValid;
// }

// // Remove validation on input
// document.addEventListener('input', function(e) {
//     if (e.target.classList.contains('is-invalid')) {
//         if (e.target.value.trim()) {
//             e.target.classList.remove('is-invalid');
//         }
//     }
// });

// // Confirm delete
// function confirmDelete(message) {
//     return confirm(message || 'Are you sure you want to delete this item?');
// }

// // Number formatting
// function formatCurrency(amount) {
//     return '₹' + parseFloat(amount).toLocaleString('en-IN', {
//         minimumFractionDigits: 2,
//         maximumFractionDigits: 2
//     });
// }

// // Date formatting
// function formatDate(dateString) {
//     const date = new Date(dateString);
//     const options = { year: 'numeric', month: 'short', day: 'numeric' };
//     return date.toLocaleDateString('en-IN', options);
// }

// // Load course fees based on selected course
// function loadCourseFees(courseId, durationSelectId, feeInputId) {
//     if (!courseId) return;
    
//     fetch(`ajax/get_course_fees.php?course_id=${courseId}`)
//         .then(response => response.json())
//         .then(data => {
//             const durationSelect = document.getElementById(durationSelectId);
//             const feeInput = document.getElementById(feeInputId);
            
//             if (durationSelect && data.success) {
//                 // Update duration options
//                 durationSelect.innerHTML = '<option value="">Select Duration</option>';
//                 data.fees.forEach(fee => {
//                     const option = document.createElement('option');
//                     option.value = fee.duration_months;
//                     option.textContent = `${fee.duration_months} Months - ${formatCurrency(fee.fee_amount)}`;
//                     option.dataset.fee = fee.fee_amount;
//                     durationSelect.appendChild(option);
//                 });
//             }
//         })
//         .catch(error => console.error('Error:', error));
// }

// // Update total fee based on duration selection
// function updateTotalFee(durationSelectId, feeInputId) {
//     const durationSelect = document.getElementById(durationSelectId);
//     const feeInput = document.getElementById(feeInputId);
    
//     if (durationSelect && feeInput) {
//         const selectedOption = durationSelect.options[durationSelect.selectedIndex];
//         if (selectedOption && selectedOption.dataset.fee) {
//             feeInput.value = selectedOption.dataset.fee;
//         }
//     }
// }

// // Print receipt
// function printReceipt(receiptId) {
//     const printWindow = window.open('', '', 'height=600,width=800');
//     const receiptContent = document.getElementById(receiptId).innerHTML;
    
//     printWindow.document.write('<html><head><title>Payment Receipt</title>');
//     printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
//     printWindow.document.write('<style>body { padding: 20px; }</style>');
//     printWindow.document.write('</head><body>');
//     printWindow.document.write(receiptContent);
//     printWindow.document.write('</body></html>');
//     printWindow.document.close();
    
//     setTimeout(function() {
//         printWindow.print();
//         printWindow.close();
//     }, 250);
// }

// // DataTable initialization (if using DataTables)
// function initDataTable(tableId) {
//     if (typeof $.fn.dataTable !== 'undefined') {
//         $(`#${tableId}`).DataTable({
//             pageLength: 10,
//             order: [[0, 'desc']],
//             language: {
//                 search: "Search:",
//                 lengthMenu: "Show _MENU_ entries"
//             }
//         });
//     }
// }

// // Export table to CSV
// function exportTableToCSV(tableId, filename) {
//     const table = document.getElementById(tableId);
//     if (!table) return;
    
//     let csv = [];
//     const rows = table.querySelectorAll('tr');
    
//     rows.forEach(row => {
//         const cols = row.querySelectorAll('td, th');
//         const csvRow = [];
//         cols.forEach(col => csvRow.push(col.innerText));
//         csv.push(csvRow.join(','));
//     });
    
//     const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
//     const downloadLink = document.createElement('a');
//     downloadLink.download = filename || 'export.csv';
//     downloadLink.href = window.URL.createObjectURL(csvFile);
//     downloadLink.style.display = 'none';
//     document.body.appendChild(downloadLink);
//     downloadLink.click();
//     document.body.removeChild(downloadLink);
// }

// // Search functionality
// function searchTable(inputId, tableId) {
//     const input = document.getElementById(inputId);
//     const table = document.getElementById(tableId);
    
//     if (!input || !table) return;
    
//     input.addEventListener('keyup', function() {
//         const filter = this.value.toUpperCase();
//         const rows = table.getElementsByTagName('tr');
        
//         for (let i = 1; i < rows.length; i++) {
//             let found = false;
//             const cells = rows[i].getElementsByTagName('td');
            
//             for (let j = 0; j < cells.length; j++) {
//                 if (cells[j].innerText.toUpperCase().indexOf(filter) > -1) {
//                     found = true;
//                     break;
//                 }
//             }
            
//             rows[i].style.display = found ? '' : 'none';
//         }
//     });
// }