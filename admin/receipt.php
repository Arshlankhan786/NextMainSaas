<?php
// receipt.php - Payment Receipt Generator with WhatsApp Share
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id === 0) {
    die("Invalid payment ID");
}

// Get payment details with student info
$query = "SELECT 
    p.*,
    s.student_code,
    s.full_name as student_name,
    s.phone,
    s.email,
    s.address,
    s.total_fees,
    c.name as course_name,
    cat.name as category_name,
    a.full_name as admin_name,
    COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id), 0) as total_paid_till_now,
    (s.total_fees - COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id), 0)) as remaining_after_payment
FROM payments p
JOIN students s ON p.student_id = s.id
JOIN courses c ON s.course_id = c.id
JOIN categories cat ON s.category_id = cat.id
JOIN admins a ON p.created_by = a.id
WHERE p.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Payment not found");
}

$payment = $result->fetch_assoc();
$stmt->close();

// Check if we need to send WhatsApp
$send_whatsapp = isset($_GET['whatsapp']) && $_GET['whatsapp'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $payment['receipt_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .receipt-header h1 {
            color: #7c3aed;
            margin-bottom: 5px;
        }
        
        .receipt-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .receipt-table {
            width: 100%;
            margin-top: 20px;
        }
        
        .receipt-table th {
            background: #7c3aed;
            color: white;
            padding: 12px;
        }
        
        .receipt-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .total-section {
            background: #e9d5ff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .footer-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #7c3aed;
            text-align: center;
            color: #6c757d;
        }
        
        .action-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn-whatsapp {
            background: #25D366;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        
        .btn-whatsapp:hover {
            background: #128C7E;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body style="background: #f0f0f0;">

    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button onclick="downloadPDF()" class="btn btn-primary btn-lg me-2">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <button onclick="sendWhatsApp()" class="btn btn-whatsapp btn-lg">
            <i class="fab fa-whatsapp"></i> Send to WhatsApp
        </button>
        <button onclick="window.print()" class="btn btn-secondary btn-lg me-2">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="student_details.php?id=<?php echo $payment['student_id']; ?>" class="btn btn-outline-secondary btn-lg">
            <i class="fas fa-times"></i> Close
        </a>
    </div>

    <!-- Receipt Content -->
    <div class="receipt-container" id="receipt">
        <!-- Header -->
        <div class="receipt-header">
            <h1>🎓NEXT ACADEMY </h1>
            <p class="mb-0">Payment Receipt</p>
            <h3 class="mt-2" style="color: #7c3aed;">#<?php echo htmlspecialchars($payment['receipt_number']); ?></h3>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($payment['payment_date'])); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p><strong>Receipt Date:</strong> <?php echo date('d M Y, h:i A'); ?></p>
                    <p><strong>Received By:</strong> <?php echo htmlspecialchars($payment['admin_name']); ?></p>
                </div>
            </div>
        </div>

        <!-- Student Details -->
        <h5 style="color: #7c3aed; border-bottom: 2px solid #7c3aed; padding-bottom: 10px;">Student Details</h5>
        <div class="row mt-3">
            <div class="col-md-6">
                <p><strong>Student Code:</strong> <?php echo htmlspecialchars($payment['student_code']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($payment['student_name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($payment['phone']); ?></p>
                <?php if ($payment['email']): ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p><strong>Category:</strong> <?php echo htmlspecialchars($payment['category_name']); ?></p>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($payment['course_name']); ?></p>
                <?php if ($payment['address']): ?>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($payment['address']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Details Table -->
        <table class="receipt-table table table-bordered mt-4">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Total Course Fees</strong></td>
                    <td class="text-end">₹<?php echo number_format($payment['total_fees'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>Amount Paid (This Payment)</strong></td>
                    <td class="text-end"><strong style="color: #10b981;">₹<?php echo number_format($payment['amount_paid'], 2); ?></strong></td>
                </tr>
                <tr>
                    <td><strong>Total Paid Till Now</strong></td>
                    <td class="text-end">₹<?php echo number_format($payment['total_paid_till_now'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Total Section -->
        <div class="total-section">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-0">Remaining Balance:</h4>
                </div>
                <div class="col-md-6 text-end">
                    <h4 class="mb-0" style="color: <?php echo $payment['remaining_after_payment'] > 0 ? '#ef4444' : '#10b981'; ?>;">
                        ₹<?php echo number_format($payment['remaining_after_payment'], 2); ?>
                    </h4>
                    <?php if ($payment['remaining_after_payment'] <= 0): ?>
                    <p class="text-success mb-0"><strong>✓ Fully Paid</strong></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($payment['notes']): ?>
        <div class="mt-4">
            <h6 style="color: #7c3aed;">Notes:</h6>
            <p class="text-muted"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-section">
            <p class="mb-1"><strong>Thank you for your payment!</strong></p>
            <p class="mb-0">This is a computer-generated receipt and does not require a signature.</p>
            <p class="mt-3">
                <small>For any queries, please contact the academy office.</small>
            </p>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-kit.js"></script>
    <script>
        // Store payment data for WhatsApp
        const paymentData = {
            receiptNumber: '<?php echo addslashes($payment['receipt_number']); ?>',
            studentName: '<?php echo addslashes($payment['student_name']); ?>',
            phone: '<?php echo preg_replace('/[^0-9]/', '', $payment['phone']); ?>',
            amount: '<?php echo number_format($payment['amount_paid'], 2); ?>',
            date: '<?php echo date('d M Y', strtotime($payment['payment_date'])); ?>',
            course: '<?php echo addslashes($payment['course_name']); ?>',
            totalFees: '<?php echo number_format($payment['total_fees'], 2); ?>',
            totalPaid: '<?php echo number_format($payment['total_paid_till_now'], 2); ?>',
            remaining: '<?php echo number_format($payment['remaining_after_payment'], 2); ?>'
        };

        // Download PDF
        function downloadPDF() {
            const element = document.getElementById('receipt');
            const opt = {
                margin: 5,
                filename: `Receipt_${paymentData.studentName}_${paymentData.date}.pdf`,
                image: { type: 'jpeg', quality: 1 },
                html2canvas: { scale: 0.9 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        // Send to WhatsApp
        function sendWhatsApp() {
            // Clean phone number
            let phone = paymentData.phone.replace(/\D/g, '');
            
            // Add country code if not present (assuming India +91)
            if (!phone.startsWith('91') && phone.length === 10) {
                phone = '91' + phone;
            }

            // Create WhatsApp message
            const message = `
🎓 *ACADEMY FEES MANAGEMENT*
📄 *Payment Receipt*

Receipt #: ${paymentData.receiptNumber}
📅 Date: ${paymentData.date}

👤 *Student Details:*
Name: ${paymentData.studentName}
Course: ${paymentData.course}

💰 *Payment Summary:*
Amount Paid: ₹${paymentData.amount}
Total Fees: ₹${paymentData.totalFees}
Total Paid: ₹${paymentData.totalPaid}
Remaining: ₹${paymentData.remaining}

✅ Payment received successfully!

Thank you for your payment! 🙏
            `.trim();

            // Open WhatsApp
            const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }

        // Auto-send to WhatsApp if parameter is set
        <?php if ($send_whatsapp): ?>
        setTimeout(() => {
            sendWhatsApp();
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>