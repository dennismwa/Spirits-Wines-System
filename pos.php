<?php
require_once 'config.php';
requireAuth();

$page_title = 'Point of Sale';

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $items = json_decode($_POST['items'], true);
    $payment_method = sanitize($_POST['payment_method']);
    $amount_paid = floatval($_POST['amount_paid']);
    $mpesa_reference = isset($_POST['mpesa_reference']) ? sanitize($_POST['mpesa_reference']) : null;
    
    if (empty($items)) {
        respond(false, 'No items in cart');
    }
    
    $conn->begin_transaction();
    
    try {
        $subtotal = 0;
        $sale_number = generateSaleNumber();
        
        // Calculate subtotal
        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $settings = getSettings();
        $tax_amount = $subtotal * ($settings['tax_rate'] / 100);
        $total_amount = $subtotal + $tax_amount;
        $change_amount = $amount_paid - $total_amount;
        
        if ($amount_paid < $total_amount) {
            throw new Exception('Insufficient payment amount');
        }
        
        // Insert sale
        $stmt = $conn->prepare("INSERT INTO sales (sale_number, user_id, subtotal, tax_amount, total_amount, payment_method, mpesa_reference, amount_paid, change_amount, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sidddssdd", $sale_number, $_SESSION['user_id'], $subtotal, $tax_amount, $total_amount, $payment_method, $mpesa_reference, $amount_paid, $change_amount);
        $stmt->execute();
        $sale_id = $conn->insert_id;
        $stmt->close();
        
        // Insert sale items and update stock
        foreach ($items as $item) {
            $product_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];
            $unit_price = floatval($item['price']);
            $item_subtotal = $unit_price * $quantity;
            
            // Insert sale item
            $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisidd", $sale_id, $product_id, $item['name'], $quantity, $unit_price, $item_subtotal);
            $stmt->execute();
            $stmt->close();
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            $stmt->close();
            
            // Log stock movement
            $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, reference_type, reference_id) VALUES (?, ?, 'sale', ?, 'sale', ?)");
            $stmt->bind_param("iiii", $product_id, $_SESSION['user_id'], $quantity, $sale_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        logActivity('Sale Completed', "Sale #$sale_number completed");
        
        respond(true, 'Sale completed successfully', [
            'sale_id' => $sale_id,
            'sale_number' => $sale_number,
            'total' => $total_amount,
            'change' => $change_amount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, $e->getMessage());
    }
    exit;
}

// Get categories and products
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' AND p.stock_quantity > 0 ORDER BY p.name");

include 'header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-180px)]">
    <!-- Products Section -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Search and Filter -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex gap-3 mb-3">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchProduct" placeholder="Search products..." 
                           class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <button onclick="showBarcodeScanner()" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90">
                    <i class="fas fa-barcode"></i>
                </button>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-2">
                <button onclick="filterByCategory('all')" class="category-filter px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap bg-primary text-white">
                    All
                </button>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                <button onclick="filterByCategory('<?php echo $cat['id']; ?>')" 
                        class="category-filter px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap bg-gray-100 hover:bg-gray-200 text-gray-700">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" id="productsGrid">
                <?php while ($product = $products->fetch_assoc()): ?>
                <div class="product-card bg-white border-2 border-gray-200 rounded-lg p-3 hover:border-primary cursor-pointer transition" 
                     data-category="<?php echo $product['category_id']; ?>"
                     data-name="<?php echo strtolower($product['name']); ?>"
                     onclick='addToCart(<?php echo json_encode($product); ?>)'>
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-2 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                            <i class="fas fa-wine-bottle text-primary text-xl"></i>
                        </div>
                        <h3 class="font-semibold text-sm mb-1 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="text-xs text-gray-500 mb-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        <p class="text-lg font-bold text-primary"><?php echo formatCurrency($product['selling_price']); ?></p>
                        <p class="text-xs text-gray-500">Stock: <?php echo $product['stock_quantity']; ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Cart Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="p-4 border-b bg-primary text-white">
            <h2 class="font-bold text-lg">Current Sale</h2>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-4" id="cartItems">
            <div class="text-center text-gray-400 py-12">
                <i class="fas fa-shopping-cart text-5xl mb-3"></i>
                <p>Cart is empty</p>
            </div>
        </div>

        <!-- Cart Summary -->
        <div class="border-t p-4 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span class="font-semibold" id="cartSubtotal">KSh 0.00</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Tax:</span>
                <span class="font-semibold" id="cartTax">KSh 0.00</span>
            </div>
            <div class="flex justify-between text-lg font-bold border-t pt-3">
                <span>Total:</span>
                <span class="text-primary" id="cartTotal">KSh 0.00</span>
            </div>
            
            <div class="flex gap-2">
                <button onclick="clearCart()" class="flex-1 px-4 py-3 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">
                    <i class="fas fa-trash mr-2"></i>Clear
                </button>
                <button onclick="showPaymentModal()" id="checkoutBtn" disabled 
                        class="flex-1 px-4 py-3 bg-primary text-white rounded-lg font-medium hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-2"></i>Checkout
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold">Complete Payment</h3>
            <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="mb-6">
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600">Total Amount:</span>
                    <span class="text-2xl font-bold text-primary" id="modalTotal">KSh 0.00</span>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="selectPaymentMethod('cash')" class="payment-method-btn px-4 py-3 border-2 rounded-lg hover:border-primary active">
                        <i class="fas fa-money-bill-wave text-xl mb-1"></i>
                        <div class="text-xs font-medium">Cash</div>
                    </button>
                    <button onclick="selectPaymentMethod('mpesa')" class="payment-method-btn px-4 py-3 border-2 rounded-lg hover:border-primary">
                        <i class="fas fa-mobile-alt text-xl mb-1"></i>
                        <div class="text-xs font-medium">M-Pesa</div>
                    </button>
                    <button onclick="selectPaymentMethod('mpesa_till')" class="payment-method-btn px-4 py-3 border-2 rounded-lg hover:border-primary">
                        <i class="fas fa-store text-xl mb-1"></i>
                        <div class="text-xs font-medium">Till</div>
                    </button>
                </div>
            </div>

            <div id="mpesaRefField" class="mb-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">M-Pesa Reference</label>
                <input type="text" id="mpesaReference" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="e.g., QA12BC34DE">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount Paid</label>
                <input type="number" id="amountPaid" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" step="0.01" min="0">
            </div>

            <div id="changeDisplay" class="bg-green-50 rounded-lg p-4 mb-4 hidden">
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-medium">Change:</span>
                    <span class="text-xl font-bold text-green-600" id="changeAmount">KSh 0.00</span>
                </div>
            </div>
        </div>

        <button onclick="completeSale()" id="completeSaleBtn" class="w-full px-6 py-3 bg-primary text-white rounded-lg font-medium hover:opacity-90">
            Complete Sale
        </button>
    </div>
</div>

<script>
let cart = [];
let selectedPaymentMethod = 'cash';
const settings = <?php echo json_encode(getSettings()); ?>;

// Add to cart
function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        if (existingItem.quantity < product.stock_quantity) {
            existingItem.quantity++;
        } else {
            alert('Insufficient stock');
            return;
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            stock: product.stock_quantity
        });
    }
    
    updateCart();
}

// Update cart display
function updateCart() {
    const cartItemsDiv = document.getElementById('cartItems');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        cartItemsDiv.innerHTML = `
            <div class="text-center text-gray-400 py-12">
                <i class="fas fa-shopping-cart text-5xl mb-3"></i>
                <p>Cart is empty</p>
            </div>
        `;
        checkoutBtn.disabled = true;
    } else {
        cartItemsDiv.innerHTML = cart.map(item => `
            <div class="flex items-center gap-3 mb-3 pb-3 border-b">
                <div class="flex-1">
                    <h4 class="font-semibold text-sm">${item.name}</h4>
                    <p class="text-xs text-gray-500">${settings.currency} ${item.price.toFixed(2)} each</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="updateQuantity(${item.id}, -1)" class="w-8 h-8 bg-gray-100 rounded hover:bg-gray-200">
                        <i class="fas fa-minus text-xs"></i>
                    </button>
                    <span class="w-8 text-center font-semibold">${item.quantity}</span>
                    <button onclick="updateQuantity(${item.id}, 1)" class="w-8 h-8 bg-gray-100 rounded hover:bg-gray-200">
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </div>
                <div class="text-right">
                    <p class="font-bold">${settings.currency} ${(item.price * item.quantity).toFixed(2)}</p>
                    <button onclick="removeFromCart(${item.id})" class="text-red-500 text-xs hover:underline">Remove</button>
                </div>
            </div>
        `).join('');
        checkoutBtn.disabled = false;
    }
    
    updateTotals();
}

// Update quantity
function updateQuantity(productId, change) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;
    
    const newQuantity = item.quantity + change;
    
    if (newQuantity <= 0) {
        removeFromCart(productId);
    } else if (newQuantity <= item.stock) {
        item.quantity = newQuantity;
        updateCart();
    } else {
        alert('Insufficient stock');
    }
}

// Remove from cart
function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    updateCart();
}

// Clear cart
function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Clear all items from cart?')) {
        cart = [];
        updateCart();
    }
}

// Update totals
function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxRate = parseFloat(settings.tax_rate) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    document.getElementById('cartSubtotal').textContent = `${settings.currency} ${subtotal.toFixed(2)}`;
    document.getElementById('cartTax').textContent = `${settings.currency} ${tax.toFixed(2)}`;
    document.getElementById('cartTotal').textContent = `${settings.currency} ${total.toFixed(2)}`;
}

// Filter by category
function filterByCategory(categoryId) {
    const products = document.querySelectorAll('.product-card');
    const buttons = document.querySelectorAll('.category-filter');
    
    buttons.forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-700');
    });
    event.target.classList.remove('bg-gray-100', 'text-gray-700');
    event.target.classList.add('bg-primary', 'text-white');
    
    products.forEach(product => {
        if (categoryId === 'all' || product.dataset.category === categoryId) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
}

// Search products
document.getElementById('searchProduct').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const products = document.querySelectorAll('.product-card');
    
    products.forEach(product => {
        const productName = product.dataset.name;
        if (productName.includes(searchTerm)) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
});

// Show payment modal
function showPaymentModal() {
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxRate = parseFloat(settings.tax_rate) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    document.getElementById('modalTotal').textContent = `${settings.currency} ${total.toFixed(2)}`;
    document.getElementById('amountPaid').value = total.toFixed(2);
    document.getElementById('paymentModal').classList.remove('hidden');
    document.getElementById('paymentModal').classList.add('flex');
    
    selectPaymentMethod('cash');
    calculateChange();
}

// Close payment modal
function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.getElementById('paymentModal').classList.remove('flex');
}

// Select payment method
function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    
    const buttons = document.querySelectorAll('.payment-method-btn');
    buttons.forEach(btn => {
        btn.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'active');
    });
    event.target.closest('.payment-method-btn').classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'active');
    
    if (method === 'mpesa' || method === 'mpesa_till') {
        document.getElementById('mpesaRefField').classList.remove('hidden');
    } else {
        document.getElementById('mpesaRefField').classList.add('hidden');
    }
}

// Calculate change
document.getElementById('amountPaid').addEventListener('input', calculateChange);

function calculateChange() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxRate = parseFloat(settings.tax_rate) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const change = amountPaid - total;
    
    const changeDisplay = document.getElementById('changeDisplay');
    const changeAmount = document.getElementById('changeAmount');
    
    if (change >= 0 && amountPaid > 0) {
        changeAmount.textContent = `${settings.currency} ${change.toFixed(2)}`;
        changeDisplay.classList.remove('hidden');
    } else {
        changeDisplay.classList.add('hidden');
    }
}

// Complete sale
function completeSale() {
    if (cart.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxRate = parseFloat(settings.tax_rate) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
    
    if (amountPaid < total) {
        alert('Insufficient payment amount');
        return;
    }
    
    if ((selectedPaymentMethod === 'mpesa' || selectedPaymentMethod === 'mpesa_till') && !document.getElementById('mpesaReference').value) {
        alert('Please enter M-Pesa reference');
        return;
    }
    
    const btn = document.getElementById('completeSaleBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    const formData = new FormData();
    formData.append('action', 'complete_sale');
    formData.append('items', JSON.stringify(cart));
    formData.append('payment_method', selectedPaymentMethod);
    formData.append('amount_paid', amountPaid);
    
    if (selectedPaymentMethod === 'mpesa' || selectedPaymentMethod === 'mpesa_till') {
        formData.append('mpesa_reference', document.getElementById('mpesaReference').value);
    }
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Sale completed successfully!\nSale #' + data.data.sale_number);
            cart = [];
            updateCart();
            closePaymentModal();
            
            // Ask to print receipt
            if (confirm('Print receipt?')) {
                window.open('/receipt?id=' + data.data.sale_id, '_blank');
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Connection error. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = 'Complete Sale';
    });
}

// Barcode scanner placeholder
function showBarcodeScanner() {
    alert('Barcode scanner feature - Connect your barcode scanner device');
}
</script>

<?php include 'footer.php'; ?>