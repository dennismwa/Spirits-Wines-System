<?php
require_once 'config.php';
requireAuth();
$page_title = 'Sales History';

// Filter parameters
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Build query
$query = "SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'";

if ($user_filter > 0) {
    $query .= " AND s.user_id = $user_filter";
}

// For sellers, only show their own sales
if ($_SESSION['role'] === 'seller') {
    $query .= " AND s.user_id = " . $_SESSION['user_id'];
}

$query .= " ORDER BY s.sale_date DESC";
$sales = $conn->query($query);

// Calculate totals
$totals_query = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(CASE WHEN payment_method='cash' THEN total_amount ELSE 0 END), 0) as cash_total, COALESCE(SUM(CASE WHEN payment_method IN ('mpesa','mpesa_till') THEN total_amount ELSE 0 END), 0) as mpesa_total FROM sales WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to'";

if ($_SESSION['role'] === 'seller') {
    $totals_query .= " AND user_id = " . $_SESSION['user_id'];
} elseif ($user_filter > 0) {
    $totals_query .= " AND user_id = $user_filter";
}

$totals = $conn->query($totals_query)->fetch_assoc();

// Get users for filter (only for owners)
$users = null;
if ($_SESSION['role'] === 'owner') {
    $users = $conn->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");
}

include 'header.php';
?>

<div class="bg-white rounded-xl shadow-sm mb-6">
    <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Sales Summary</h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 p-6">
        <div>
            <p class="text-sm text-gray-600 mb-1">Total Sales</p>
            <p class="text-2xl font-bold text-primary"><?php echo $totals['count']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($totals['total']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-1">Cash Sales</p>
            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['cash_total']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-1">M-Pesa Sales</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($totals['mpesa_total']); ?></p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <h2 class="text-xl font-bold text-gray-800">Sales Transactions</h2>
            <form method="GET" class="flex flex-wrap gap-3 ml-auto">
                <input type="date" name="from" value="<?php echo $date_from; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <input type="date" name="to" value="<?php echo $date_to; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <?php if ($_SESSION['role'] === 'owner'): ?>
                <select name="user" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="0">All Sellers</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sale #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($sale = $sales->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></span>
                        <span class="text-xs text-gray-500 block"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></span>
                    </td>
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($sale['seller_name']); ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $sale['payment_method'] === 'cash' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $sale['payment_method'])); ?>
                        </span>
                        <?php if ($sale['mpesa_reference']): ?>
                        <span class="block text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($sale['mpesa_reference']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($sale['total_amount']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <button onclick="viewSaleDetails(<?php echo $sale['id']; ?>)" class="text-blue-600 hover:underline mr-3">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <a href="/receipt?id=<?php echo $sale['id']; ?>" target="_blank" class="text-primary hover:underline">
                            <i class="fas fa-print mr-1"></i>Print
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($sales->num_rows === 0): ?>
                <tr>
                    <td colspan="<?php echo $_SESSION['role'] === 'owner' ? '6' : '5'; ?>" class="px-6 py-12 text-center text-gray-400">
                        No sales found for the selected period
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Sale Details Modal -->
<div id="saleModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b p-6 flex items-center justify-between">
            <h3 class="text-xl font-bold">Sale Details</h3>
            <button onclick="closeSaleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="saleDetails" class="p-6"></div>
    </div>
</div>

<script>
function viewSaleDetails(saleId) {
    fetch('/api/sale-details?id=' + saleId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sale = data.data.sale;
                const items = data.data.items;
                
                let html = `
                    <div class="mb-6">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">Sale Number</p>
                                <p class="font-semibold">${sale.sale_number}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Date & Time</p>
                                <p class="font-semibold">${new Date(sale.sale_date).toLocaleString()}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Seller</p>
                                <p class="font-semibold">${sale.seller_name}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Payment Method</p>
                                <p class="font-semibold">${sale.payment_method.toUpperCase().replace('_', ' ')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="font-bold text-gray-800 mb-3">Items</h4>
                    <div class="border rounded-lg divide-y mb-6">
                `;
                
                items.forEach(item => {
                    html += `
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-semibold">${item.product_name}</p>
                                <p class="text-sm text-gray-600">${item.quantity} × KSh ${parseFloat(item.unit_price).toFixed(2)}</p>
                            </div>
                            <p class="font-bold">KSh ${parseFloat(item.subtotal).toFixed(2)}</p>
                        </div>
                    `;
                });
                
                html += `
                    </div>
                    
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-semibold">KSh ${parseFloat(sale.subtotal).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax</span>
                            <span class="font-semibold">KSh ${parseFloat(sale.tax_amount).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                            <span>Total</span>
                            <span class="text-primary">KSh ${parseFloat(sale.total_amount).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount Paid</span>
                            <span class="font-semibold">KSh ${parseFloat(sale.amount_paid).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Change</span>
                            <span class="font-semibold">KSh ${parseFloat(sale.change_amount).toFixed(2)}</span>
                        </div>
                    </div>
                `;
                
                document.getElementById('saleDetails').innerHTML = html;
                document.getElementById('saleModal').classList.remove('hidden');
                document.getElementById('saleModal').classList.add('flex');
            }
        });
}

function closeSaleModal() {
    document.getElementById('saleModal').classList.add('hidden');
    document.getElementById('saleModal').classList.remove('flex');
}
</script>

<?php include 'footer.php'; ?>