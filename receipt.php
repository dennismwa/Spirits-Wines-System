<?php
require_once 'config.php';
requireAuth();

if (!isset($_GET['id'])) {
    die('Invalid receipt ID');
}

$sale_id = (int)$_GET['id'];

// Get sale details
$stmt = $conn->prepare("SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Receipt not found');
}

$sale = $result->fetch_assoc();
$stmt->close();

// Check permission
if ($_SESSION['role'] === 'seller' && $sale['user_id'] != $_SESSION['user_id']) {
    die('Access denied');
}

// Get sale items
$stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($sale['sale_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.5; padding: 20px; max-width: 400px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px dashed #000; padding-bottom: 15px; }
        .logo { max-width: 80px; height: auto; margin-bottom: 10px; }
        .company-name { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
        .receipt-title { font-size: 16px; font-weight: bold; margin: 10px 0; }
        .info-section { margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; }
        .items-table { width: 100%; margin-bottom: 15px; }
        .items-table th { text-align: left; border-bottom: 1px solid #000; padding: 5px 0; font-size: 12px; }
        .items-table td { padding: 8px 0; font-size: 12px; vertical-align: top; }
        .item-name { font-weight: bold; }
        .totals-section { border-top: 2px solid #000; padding-top: 10px; margin-top: 15px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total-row.grand-total { font-size: 18px; font-weight: bold; margin-top: 10px; border-top: 1px dashed #000; padding-top: 10px; }
        .payment-info { margin: 15px 0; padding: 10px; background: #f5f5f5; border-radius: 5px; }
        .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px dashed #000; font-size: 12px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: <?php echo $settings['primary_color']; ?>; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="header">
        <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Logo" class="logo" onerror="this.style.display='none'">
        <div class="company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div>
        <div class="receipt-title">SALES RECEIPT</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span>Receipt #:</span>
            <strong><?php echo htmlspecialchars($sale['sale_number']); ?></strong>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></span>
        </div>
        <div class="info-row">
            <span>Served by:</span>
            <span><?php echo htmlspecialchars($sale['seller_name']); ?></span>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Item</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 20%; text-align: right;">Price</th>
                <th style="width: 15%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                <td style="text-align: right;"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align: right;"><?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <strong><?php echo formatCurrency($sale['subtotal']); ?></strong>
        </div>
        <?php if ($sale['tax_amount'] > 0): ?>
        <div class="total-row">
            <span>Tax:</span>
            <strong><?php echo formatCurrency($sale['tax_amount']); ?></strong>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <strong><?php echo formatCurrency($sale['total_amount']); ?></strong>
        </div>
    </div>

    <div class="payment-info">
        <div class="total-row">
            <span>Payment Method:</span>
            <strong><?php echo strtoupper(str_replace('_', ' ', $sale['payment_method'])); ?></strong>
        </div>
        <?php if ($sale['mpesa_reference']): ?>
        <div class="total-row">
            <span>M-Pesa Ref:</span>
            <strong><?php echo htmlspecialchars($sale['mpesa_reference']); ?></strong>
        </div>
        <?php endif; ?>
        <div class="total-row">
            <span>Amount Paid:</span>
            <strong><?php echo formatCurrency($sale['amount_paid']); ?></strong>
        </div>
        <div class="total-row">
            <span>Change:</span>
            <strong><?php echo formatCurrency($sale['change_amount']); ?></strong>
        </div>
    </div>

    <div class="footer">
        <?php if ($settings['receipt_footer']): ?>
        <p><?php echo nl2br(htmlspecialchars($settings['receipt_footer'])); ?></p>
        <?php else: ?>
        <p>Thank you for your business!</p>
        <p>Please come again</p>
        <?php endif; ?>
    </div>

    <script>
        // Auto print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>