<?php
require_once 'config.php';
requireOwner();
$page_title = 'Expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $category = sanitize($_POST['category']);
        $amount = floatval($_POST['amount']);
        $description = sanitize($_POST['description']);
        $expense_date = sanitize($_POST['expense_date']);
        
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $_SESSION['user_id'], $category, $amount, $description, $expense_date);
        $stmt->execute();
        $stmt->close();
        
        logActivity('Expense Added', "Added expense: $category - " . formatCurrency($amount));
        respond(true, 'Expense added successfully');
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logActivity('Expense Deleted', "Deleted expense ID: $id");
        respond(true, 'Expense deleted successfully');
    }
    exit;
}

$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$expenses = $conn->query("SELECT e.*, u.name as added_by FROM expenses e LEFT JOIN users u ON e.user_id = u.id WHERE expense_date BETWEEN '$date_from' AND '$date_to' ORDER BY expense_date DESC");

$total_expenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();

$category_breakdown = $conn->query("SELECT category, SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '$date_from' AND '$date_to' GROUP BY category ORDER BY total DESC");

include 'header.php';
?>

<div class="bg-white rounded-xl shadow-sm mb-6">
    <div class="p-6 border-b">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <h2 class="text-xl font-bold text-gray-800">Expense Management</h2>
            <form method="GET" class="flex flex-wrap gap-3 ml-auto">
                <input type="date" name="from" value="<?php echo $date_from; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <input type="date" name="to" value="<?php echo $date_to; ?>" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <button type="button" onclick="openExpenseModal()" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Expense
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
        <div>
            <p class="text-sm text-gray-600 mb-1">Total Expenses</p>
            <p class="text-3xl font-bold text-red-600"><?php echo formatCurrency($total_expenses['total']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-600 mb-2">Breakdown by Category</p>
            <div class="space-y-2">
                <?php while ($cat = $category_breakdown->fetch_assoc()): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-700"><?php echo htmlspecialchars($cat['category']); ?></span>
                    <span class="font-semibold"><?php echo formatCurrency($cat['total']); ?></span>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <h3 class="font-bold text-gray-800">Expense Records</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($expense = $expenses->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo date('M d, Y', strtotime($expense['expense_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                            <?php echo htmlspecialchars($expense['category']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo htmlspecialchars($expense['description']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600">
                        <?php echo formatCurrency($expense['amount']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?php echo htmlspecialchars($expense['added_by']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <button onclick="deleteExpense(<?php echo $expense['id']; ?>)" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($expenses->num_rows === 0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">No expenses recorded</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="expenseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold">Add Expense</h3>
            <button onclick="closeExpenseModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="expenseForm">
            <input type="hidden" name="action" value="add">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                <select name="category" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">Select Category</option>
                    <option value="Utilities">Utilities</option>
                    <option value="Rent">Rent</option>
                    <option value="Salaries">Salaries</option>
                    <option value="Supplies">Supplies</option>
                    <option value="Transport">Transport</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount (KSh) *</label>
                <input type="number" step="0.01" name="amount" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                <textarea name="description" rows="3" required 
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeExpenseModal()" class="flex-1 px-6 py-3 border rounded-lg hover:bg-gray-50 font-medium">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openExpenseModal() {
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseModal').classList.remove('hidden');
    document.getElementById('expenseModal').classList.add('flex');
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
    document.getElementById('expenseModal').classList.remove('flex');
}

function deleteExpense(id) {
    if (!confirm('Delete this expense?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + data.message);
    });
}

document.getElementById('expenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});
</script>

<?php include 'footer.php'; ?>
