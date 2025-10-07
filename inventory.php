<?php
require_once 'config.php';
requireOwner();
$page_title = 'Inventory Management';

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust') {
    $product_id = (int)$_POST['product_id'];
    $movement_type = sanitize($_POST['movement_type']);
    $quantity = (int)$_POST['quantity'];
    $notes = sanitize($_POST['notes']);
    
    $conn->begin_transaction();
    try {
        if ($movement_type === 'in') {
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        }
        $stmt->bind_param("ii", $quantity, $product_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $product_id, $_SESSION['user_id'], $movement_type, $quantity, $notes);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        logActivity('Stock Adjusted', "Adjusted stock for product ID: $product_id");
        respond(true, 'Stock adjusted successfully');
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, 'Error adjusting stock');
    }
    exit;
}

$products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' ORDER BY p.stock_quantity ASC");
$inventory_value = $conn->query("SELECT SUM(stock_quantity * cost_price) as total_cost, SUM(stock_quantity * selling_price) as total_value FROM products WHERE status = 'active'")->fetch_assoc();
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status = 'active'")->fetch_assoc();

include 'header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-boxes text-blue-600 text-xl"></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo formatCurrency($inventory_value['total_cost']); ?></h3>
        <p class="text-sm text-gray-600">Total Inventory Cost</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo formatCurrency($inventory_value['total_value']); ?></h3>
        <p class="text-sm text-gray-600">Potential Revenue</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo $low_stock_count['count']; ?></h3>
        <p class="text-sm text-gray-600">Low Stock Items</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Inventory Status</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reorder Level</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($product = $products->fetch_assoc()): 
                    $is_low = $product['stock_quantity'] <= $product['reorder_level'];
                    $stock_value = $product['stock_quantity'] * $product['selling_price'];
                ?>
                <tr class="hover:bg-gray-50 <?php echo $is_low ? 'bg-red-50' : ''; ?>">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-wine-bottle text-primary"></i>
                            </div>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $is_low ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $product['stock_quantity']; ?> units
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo $product['reorder_level']; ?> units
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($stock_value); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <button onclick="adjustStock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" 
                                class="text-primary hover:underline">
                            <i class="fas fa-edit mr-1"></i>Adjust
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stockModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold">Adjust Stock</h3>
            <button onclick="closeStockModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="stockForm">
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" id="productId" name="product_id">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                <input type="text" id="productName" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-50">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Movement Type *</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="selectMovementType('in')" class="movement-btn px-4 py-3 border-2 rounded-lg hover:border-primary">
                        <i class="fas fa-plus-circle text-green-600 text-xl mb-1"></i>
                        <div class="text-sm font-medium">Stock In</div>
                    </button>
                    <button type="button" onclick="selectMovementType('out')" class="movement-btn px-4 py-3 border-2 rounded-lg hover:border-primary">
                        <i class="fas fa-minus-circle text-red-600 text-xl mb-1"></i>
                        <div class="text-sm font-medium">Stock Out</div>
                    </button>
                </div>
                <input type="hidden" id="movementType" name="movement_type" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity *</label>
                <input type="number" name="quantity" id="quantity" required min="1" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="2" 
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeStockModal()" class="flex-1 px-6 py-3 border rounded-lg hover:bg-gray-50 font-medium">
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
let selectedMovement = null;

function adjustStock(id, name) {
    document.getElementById('productId').value = id;
    document.getElementById('productName').value = name;
    document.getElementById('stockForm').reset();
    selectedMovement = null;
    document.querySelectorAll('.movement-btn').forEach(btn => {
        btn.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    });
    document.getElementById('stockModal').classList.remove('hidden');
    document.getElementById('stockModal').classList.add('flex');
}

function closeStockModal() {
    document.getElementById('stockModal').classList.add('hidden');
    document.getElementById('stockModal').classList.remove('flex');
}

function selectMovementType(type) {
    selectedMovement = type;
    document.getElementById('movementType').value = type;
    
    document.querySelectorAll('.movement-btn').forEach(btn => {
        btn.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    });
    event.target.closest('.movement-btn').classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
}

document.getElementById('stockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!selectedMovement) {
        alert('Please select movement type');
        return;
    }

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