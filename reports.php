<?php
require_once 'config.php';
requireOwner();
$page_title = 'Reports';

$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Sales Summary
$sales_summary = $conn->query("SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue, COALESCE(AVG(total_amount), 0) as avg_sale FROM sales WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();

// Payment Method Breakdown
$payment_breakdown = $conn->query("SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to' GROUP BY payment_method");

// Top Sellers
$top_sellers = $conn->query("SELECT u.name, COUNT(s.id) as sales_count, SUM(s.total_amount) as total_sales FROM sales s JOIN users u ON s.user_id = u.id WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to' GROUP BY u.id ORDER BY total_sales DESC LIMIT 5");

// Best Selling Products
$best_products = $conn->query("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_revenue FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to' GROUP BY p.id ORDER BY total_qty DESC LIMIT 10");

// Daily Sales Data for Chart
$daily_data = [];
for ($i = 0; $i <= (strtotime($date_to) - strtotime($date_from)) / 86400; $i++) {
    $date = date('Y-m-d', strtotime($date_from . " +$i days"));
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = '$date'")->fetch_assoc();
    $daily_data[] = ['date' => date('M d', strtotime($date)), 'total' => $result['total']];
}

include 'header.php';
?>

<div class="bg-white rounded-xl shadow-sm mb-6">
    <div class="p-6 border-b">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <h2 class="text-xl font-bold text-gray-800">Sales Reports</h2>
            <form method="GET" class="flex flex-wrap gap-3 ml-auto">
                <input type="date" name="from" value="<?php echo $date_from; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <input type="date" name="to" value="<?php echo $date_to; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-filter mr-2"></i>Generate
                </button>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 border-b">
        <div>
            <p class="text-sm text-gray-600 mb-1">Total Sales</p>
            <p class="text-3xl font-bold text-primary"><?php echo $sales_summary['total_sales']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
            <p class="text-3xl font-bold text-gray-800"><?php echo formatCurrency($sales_summary['total_revenue']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-1">Average Sale</p>
            <p class="text-3xl font-bold text-gray-800"><?php echo formatCurrency($sales_summary['avg_sale']); ?></p>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="p-6">
        <h3 class="font-bold text-gray-800 mb-4">Sales Trend</h3>
        <canvas id="salesChart" height="80"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Payment Methods -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="font-bold text-gray-800">Payment Methods</h3>
        </div>
        <div class="p-6">
            <canvas id="paymentChart" height="200"></canvas>
        </div>
    </div>

    <!-- Top Sellers -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="font-bold text-gray-800">Top Performing Sellers</h3>
        </div>
        <div class="p-6 space-y-4">
            <?php 
            $rank = 1;
            while ($seller = $top_sellers->fetch_assoc()): 
            ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 bg-primary bg-opacity-10 rounded-full flex items-center justify-center text-primary font-bold text-sm">
                        <?php echo $rank++; ?>
                    </span>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($seller['name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo $seller['sales_count']; ?> sales</p>
                    </div>
                </div>
                <p class="font-bold text-primary"><?php echo formatCurrency($seller['total_sales']); ?></p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Best Selling Products -->
<div class="bg-white rounded-xl shadow-sm mt-6">
    <div class="p-6 border-b">
        <h3 class="font-bold text-gray-800">Best Selling Products</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $rank = 1;
                while ($product = $best_products->fetch_assoc()): 
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="w-8 h-8 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center text-primary font-bold inline-flex">
                            <?php echo $rank++; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo $product['total_qty']; ?> units
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($product['total_revenue']); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';

// Sales Trend Chart
const salesData = <?php echo json_encode($daily_data); ?>;
new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: salesData.map(d => d.date),
        datasets: [{
            label: 'Sales (KSh)',
            data: salesData.map(d => d.total),
            borderColor: primaryColor,
            backgroundColor: primaryColor + '33',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: value => 'KSh ' + value.toLocaleString() }
            }
        }
    }
});

// Payment Methods Chart
const paymentData = <?php 
    $payment_data = ['labels' => [], 'values' => []];
    while ($payment = $payment_breakdown->fetch_assoc()) {
        $payment_data['labels'][] = strtoupper(str_replace('_', ' ', $payment['payment_method']));
        $payment_data['values'][] = $payment['total'];
    }
    echo json_encode($payment_data);
?>;

new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: paymentData.labels,
        datasets: [{
            data: paymentData.values,
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php include 'footer.php'; ?>