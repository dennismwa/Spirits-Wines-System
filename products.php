<?php
require_once 'config.php';
requireAuth();

$page_title = 'Products';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $barcode = sanitize($_POST['barcode']);
        $description = sanitize($_POST['description']);
        $cost_price = floatval($_POST['cost_price']);
        $selling_price = floatval($_POST['selling_price']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $reorder_level = (int)$_POST['reorder_level'];
        $supplier = sanitize($_POST['supplier']);
        $unit = sanitize($_POST['unit']);
        $sku = sanitize($_POST['sku']);
        $location = sanitize($_POST['location']);
        $expiry_date = !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null;
        
        if ($action === 'add') {
            // Check if barcode exists
            if (!empty($barcode)) {
                $check = $conn->query("SELECT id FROM products WHERE barcode='$barcode'");
                if ($check->num_rows > 0) {
                    respond(false, 'Barcode already exists');
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, barcode, description, cost_price, selling_price, stock_quantity, reorder_level, supplier, unit, sku, location, expiry_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssddiisssssi", $category_id, $name, $barcode, $description, $cost_price, $selling_price, $stock_quantity, $reorder_level, $supplier, $unit, $sku, $location, $expiry_date, $_SESSION['user_id']);
            $stmt->execute();
            $product_id = $conn->insert_id;
            
            // Log initial stock
            if ($stock_quantity > 0) {
                $stmt2 = $conn->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, notes) VALUES (?, ?, 'in', ?, 'Initial stock')");
                $stmt2->bind_param("iii", $product_id, $_SESSION['user_id'], $stock_quantity);
                $stmt2->execute();
                $stmt2->close();
            }
            
            logActivity('Product Added', "Added product: $name");
            respond(true, 'Product added successfully');
        } else {
            $id = (int)$_POST['id'];
            
            // Get old stock quantity for comparison
            $old_stock = $conn->query("SELECT stock_quantity FROM products WHERE id=$id")->fetch_assoc()['stock_quantity'];
            
            $stmt = $conn->prepare("UPDATE products SET category_id=?, name=?, barcode=?, description=?, cost_price=?, selling_price=?, stock_quantity=?, reorder_level=?, supplier=?, unit=?, sku=?, location=?, expiry_date=? WHERE id=?");
            $stmt->bind_param("isssddiiisssssi", $category_id, $name, $barcode, $description, $cost_price, $selling_price, $stock_quantity, $reorder_level, $supplier, $unit, $sku, $location, $expiry_date, $id);
            $stmt->execute();
            
            // Log stock adjustment if changed
            if ($old_stock != $stock_quantity) {
                $diff = $stock_quantity - $old_stock;
                $movement_type = $diff > 0 ? 'in' : 'out';
                $abs_diff = abs($diff);
                $stmt2 = $conn->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity, notes) VALUES (?, ?, ?, ?, 'Stock adjustment via edit')");
                $stmt2->bind_param("iisi", $id, $_SESSION['user_id'], $movement_type, $abs_diff);
                $stmt2->execute();
                $stmt2->close();
            }
            
            logActivity('Product Updated', "Updated product: $name");
            respond(true, 'Product updated successfully');
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        logActivity('Product Deleted', "Deleted product ID: $id");
        respond(true, 'Product deleted successfully');
    }
    
    if ($action === 'bulk_delete') {
        $ids = json_decode($_POST['ids'], true);
        if (empty($ids)) respond(false, 'No products selected');
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $stmt->close();
        
        logActivity('Bulk Delete', "Deleted " . count($ids) . " products");
        respond(true, count($ids) . ' products deleted successfully');
    }
    
    if ($action === 'bulk_update_category') {
        $ids = json_decode($_POST['ids'], true);
        $category_id = (int)$_POST['category_id'];
        
        if (empty($ids)) respond(false, 'No products selected');
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE products SET category_id=? WHERE id IN ($placeholders)");
        $types = 'i' . str_repeat('i', count($ids));
        $stmt->bind_param($types, $category_id, ...$ids);
        $stmt->execute();
        $stmt->close();
        
        logActivity('Bulk Update', "Updated category for " . count($ids) . " products");
        respond(true, 'Products updated successfully');
    }
    
    if ($action === 'duplicate') {
        $id = (int)$_POST['id'];
        $result = $conn->query("SELECT * FROM products WHERE id=$id");
        $product = $result->fetch_assoc();
        
        $new_name = $product['name'] . ' (Copy)';
        $new_sku = $product['sku'] . '-COPY';
        
        $stmt = $conn->prepare("INSERT INTO products (category_id, name, barcode, description, cost_price, selling_price, stock_quantity, reorder_level, supplier, unit, sku, location, expiry_date, created_by, status) VALUES (?, ?, NULL, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("issddiisssssi", $product['category_id'], $new_name, $product['description'], $product['cost_price'], $product['selling_price'], $product['reorder_level'], $product['supplier'], $product['unit'], $new_sku, $product['location'], $product['expiry_date'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        logActivity('Product Duplicated', "Duplicated product: {$product['name']}");
        respond(true, 'Product duplicated successfully');
    }
    
    if ($action === 'export_csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Category', 'SKU', 'Barcode', 'Cost Price', 'Selling Price', 'Stock', 'Reorder Level', 'Unit', 'Supplier', 'Location', 'Status']);
        
        $products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='active' ORDER BY p.name");
        
        while ($row = $products->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['category_name'],
                $row['sku'],
                $row['barcode'],
                $row['cost_price'],
                $row['selling_price'],
                $row['stock_quantity'],
                $row['reorder_level'],
                $row['unit'],
                $row['supplier'],
                $row['location'],
                $row['status']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    if ($action === 'print_labels') {
        $ids = json_decode($_POST['ids'], true);
        if (empty($ids)) respond(false, 'No products selected');
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
        
        respond(true, 'Labels ready', $products);
    }
    
    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        respond(true, '', $product);
    }
    
    if ($action === 'get_stock_history') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT sm.*, u.name as user_name FROM stock_movements sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.product_id = ? ORDER BY sm.created_at DESC LIMIT 20");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        
        respond(true, '', $history);
    }
    
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? sanitize($_GET['stock']) : 'all';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'name_asc';

// Build query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active'";

if (!empty($search)) {
    $query .= " AND (p.name LIKE '%$search%' OR p.barcode LIKE '%$search%' OR p.sku LIKE '%$search%')";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = $category_filter";
}

if ($stock_filter === 'low') {
    $query .= " AND p.stock_quantity <= p.reorder_level";
} elseif ($stock_filter === 'out') {
    $query .= " AND p.stock_quantity = 0";
} elseif ($stock_filter === 'in_stock') {
    $query .= " AND p.stock_quantity > p.reorder_level";
}

// Sorting
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY p.selling_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.selling_price DESC";
        break;
    case 'stock_asc':
        $query .= " ORDER BY p.stock_quantity ASC";
        break;
    case 'stock_desc':
        $query .= " ORDER BY p.stock_quantity DESC";
        break;
    case 'newest':
        $query .= " ORDER BY p.created_at DESC";
        break;
    default:
        $query .= " ORDER BY p.name ASC";
}

$products = $conn->query($query);
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE status='active'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status='active'")->fetch_assoc()['count'];
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND status='active'")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(stock_quantity * selling_price) as value FROM products WHERE status='active'")->fetch_assoc()['value'];

include 'header.php';
?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Total Products</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $total_products; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-wine-bottle text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Low Stock</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo $low_stock; ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Out of Stock</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $out_of_stock; ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-times-circle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Inventory Value</p>
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($total_value ?: 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Product Management</h2>
                <p class="text-sm text-gray-600 mt-1">Manage your inventory products</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="openProductModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Product
                </button>
                <button onclick="bulkActions()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:opacity-90 font-medium" id="bulkActionsBtn" style="display:none;">
                    <i class="fas fa-tasks mr-2"></i>Bulk Actions
                </button>
                <button onclick="exportCSV()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-file-export mr-2"></i>Export
                </button>
                <button onclick="printLabels()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:opacity-90 font-medium" id="printLabelsBtn" style="display:none;">
                    <i class="fas fa-print mr-2"></i>Print Labels
                </button>
            </div>
        </div>
    </div>

    <!-- Products Grid View (Hidden by default) -->
    <div id="gridView" class="p-6 hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php 
            $products->data_seek(0); // Reset pointer
            while ($product = $products->fetch_assoc()): 
                $stock_value = $product['stock_quantity'] * $product['selling_price'];
                $profit_margin = $product['selling_price'] > 0 ? (($product['selling_price'] - $product['cost_price']) / $product['selling_price']) * 100 : 0;
            ?>
            <div class="bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-primary transition">
                <div class="flex items-start justify-between mb-3">
                    <input type="checkbox" class="product-checkbox w-4 h-4" data-id="<?php echo $product['id']; ?>">
                    <?php 
                    $stock_status = 'green';
                    if ($product['stock_quantity'] <= 0) $stock_status = 'red';
                    elseif ($product['stock_quantity'] <= $product['reorder_level']) $stock_status = 'orange';
                    ?>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-<?php echo $stock_status; ?>-100 text-<?php echo $stock_status; ?>-800">
                        <?php echo $product['stock_quantity']; ?> in stock
                    </span>
                </div>
                
                <div class="w-16 h-16 mx-auto mb-3 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-wine-bottle text-primary text-2xl"></i>
                </div>
                
                <h3 class="font-bold text-center mb-1 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                <p class="text-xs text-gray-500 text-center mb-3"><?php echo htmlspecialchars($product['category_name']); ?></p>
                
                <div class="space-y-2 text-sm mb-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cost:</span>
                        <span class="font-semibold"><?php echo formatCurrency($product['cost_price']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Price:</span>
                        <span class="font-bold text-primary"><?php echo formatCurrency($product['selling_price']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Value:</span>
                        <span class="font-semibold text-green-600"><?php echo formatCurrency($stock_value); ?></span>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-3 border-t">
                    <button onclick="viewProduct(<?php echo $product['id']; ?>)" class="flex-1 px-2 py-2 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 text-sm">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editProduct(<?php echo $product['id']; ?>)" class="flex-1 px-2 py-2 bg-green-50 text-green-600 rounded hover:bg-green-100 text-sm">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="duplicateProduct(<?php echo $product['id']; ?>)" class="flex-1 px-2 py-2 bg-purple-50 text-purple-600 rounded hover:bg-purple-100 text-sm">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<!-- Advanced Filters -->
    <div class="p-6 border-b bg-gray-50">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, barcode, or SKU..." 
                           class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            
            <select name="category" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <option value="0">All Categories</option>
                <?php 
                $categories_copy = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
                while ($cat = $categories_copy->fetch_assoc()): 
                ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>

            <select name="stock" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <option value="all" <?php echo $stock_filter === 'all' ? 'selected' : ''; ?>>All Stock Levels</option>
                <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>

            <select name="sort" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low-High)</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High-Low)</option>
                <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low-High)</option>
                <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (High-Low)</option>
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            </select>

            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <a href="/products" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <!-- View Toggle -->
    <div class="px-6 py-3 border-b bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <input type="checkbox" id="selectAll" class="w-4 h-4 text-primary focus:ring-primary rounded">
            <label for="selectAll" class="text-sm text-gray-700">Select All</label>
            <span id="selectedCount" class="text-sm text-gray-500 ml-4"></span>
        </div>
        <div class="flex gap-2">
            <button onclick="toggleView('grid')" id="gridViewBtn" class="px-3 py-1 rounded hover:bg-gray-200">
                <i class="fas fa-th"></i>
            </button>
            <button onclick="toggleView('list')" id="listViewBtn" class="px-3 py-1 rounded bg-gray-200">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>

    <!-- Products Table View -->
    <div id="listView" class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left">
                        <input type="checkbox" class="select-all-checkbox w-4 h-4">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU/Barcode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($product = $products->fetch_assoc()): 
                    $stock_value = $product['stock_quantity'] * $product['selling_price'];
                    $profit_margin = $product['selling_price'] > 0 ? (($product['selling_price'] - $product['cost_price']) / $product['selling_price']) * 100 : 0;
                ?>
                <tr class="hover:bg-gray-50 product-row">
                    <td class="px-6 py-4">
                        <input type="checkbox" class="product-checkbox w-4 h-4" data-id="<?php echo $product['id']; ?>">
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-wine-bottle text-primary"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                <?php if ($product['description']): ?>
                                <p class="text-xs text-gray-500 line-clamp-1"><?php echo htmlspecialchars($product['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if ($product['sku']): ?>
                        <div class="font-mono text-xs"><?php echo htmlspecialchars($product['sku']); ?></div>
                        <?php endif; ?>
                        <?php if ($product['barcode']): ?>
                        <div class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($product['barcode']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo formatCurrency($product['cost_price']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-gray-900"><?php echo formatCurrency($product['selling_price']); ?></div>
                        <div class="text-xs text-green-600">+<?php echo number_format($profit_margin, 1); ?>% margin</div>
                    </td>
                    <td class="px-6 py-4">
                        <?php 
                        $stock_status = 'green';
                        if ($product['stock_quantity'] <= 0) $stock_status = 'red';
                        elseif ($product['stock_quantity'] <= $product['reorder_level']) $stock_status = 'orange';
                        ?>
                        <span class="px-3 py-1 text-xs font-medium rounded-full bg-<?php echo $stock_status; ?>-100 text-<?php echo $stock_status; ?>-800">
                            <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit'] ?: 'units'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($stock_value); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="flex items-center gap-2">
                            <button onclick="viewProduct(<?php echo $product['id']; ?>)" class="text-blue-600 hover:text-blue-800" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="editProduct(<?php echo $product['id']; ?>)" class="text-green-600 hover:text-green-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="duplicateProduct(<?php echo $product['id']; ?>)" class="text-purple-600 hover:text-purple-800" title="Duplicate">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button onclick="viewStockHistory(<?php echo $product['id']; ?>)" class="text-orange-600 hover:text-orange-800" title="Stock History">
                                <i class="fas fa-history"></i>
                            </button>
                            <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($products->num_rows === 0): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-5xl mb-4"></i>
                        <p>No products found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<!-- Product Modal -->
<div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b p-6 flex items-center justify-between z-10">
            <h3 class="text-xl font-bold" id="modalTitle">Add Product</h3>
            <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="productForm" class="p-6">
            <input type="hidden" id="productId" name="id">
            <input type="hidden" id="productAction" name="action" value="add">

            <!-- Basic Information -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-primary"></i>
                    Basic Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                        <input type="text" name="name" id="productName" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <select name="category_id" id="productCategory" required 
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit *</label>
                        <select name="unit" id="productUnit" required 
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="bottle">Bottle</option>
                            <option value="case">Case</option>
                            <option value="pack">Pack</option>
                            <option value="liter">Liter</option>
                            <option value="ml">ML</option>
                            <option value="piece">Piece</option>
                            <option value="box">Box</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">SKU (Stock Keeping Unit)</label>
                        <input type="text" name="sku" id="productSKU" 
                               placeholder="e.g., WNE-001" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Barcode</label>
                        <div class="flex gap-2">
                            <input type="text" name="barcode" id="productBarcode" 
                                   class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <button type="button" onclick="generateBarcode()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                                <i class="fas fa-barcode"></i>
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="productDescription" rows="3" 
                                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing & Stock -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-dollar-sign text-primary"></i>
                    Pricing & Stock
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price (KSh) *</label>
                        <input type="number" step="0.01" name="cost_price" id="productCost" required 
                               onchange="calculateMargin()"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Selling Price (KSh) *</label>
                        <input type="number" step="0.01" name="selling_price" id="productPrice" required 
                               onchange="calculateMargin()"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profit Margin</label>
                        <input type="text" id="profitMargin" readonly 
                               class="w-full px-4 py-2 border rounded-lg bg-gray-50 font-semibold text-green-600">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                        <input type="number" name="stock_quantity" id="productStock" required value="0"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reorder Level *</label>
                        <input type="number" name="reorder_level" id="productReorder" required value="10"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Alert when stock reaches this level</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock Value</label>
                        <input type="text" id="stockValue" readonly 
                               class="w-full px-4 py-2 border rounded-lg bg-gray-50 font-semibold text-primary">
                    </div>
                </div>
            </div>

            <!-- Additional Details -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-clipboard-list text-primary"></i>
                    Additional Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                        <input type="text" name="supplier" id="productSupplier" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Storage Location</label>
                        <input type="text" name="location" id="productLocation" 
                               placeholder="e.g., Shelf A3, Warehouse B"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date" id="productExpiry" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-6 border-t">
                <button type="button" onclick="closeProductModal()" 
                        class="flex-1 px-6 py-3 border rounded-lg hover:bg-gray-50 font-medium">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-save mr-2"></i>Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Product Modal -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b p-6 flex items-center justify-between z-10">
            <h3 class="text-xl font-bold">Product Details</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="viewProductContent" class="p-6"></div>
    </div>
</div>

<!-- Stock History Modal -->
<div id="stockHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b p-6 flex items-center justify-between z-10">
            <h3 class="text-xl font-bold">Stock Movement History</h3>
            <button onclick="closeStockHistoryModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="stockHistoryContent" class="p-6"></div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div id="bulkModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold">Bulk Actions</h3>
            <button onclick="closeBulkModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="space-y-3">
            <button onclick="bulkUpdateCategory()" class="w-full px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 text-left">
                <i class="fas fa-tags mr-2"></i>Change Category
            </button>
            <button onclick="bulkDelete()" class="w-full px-4 py-3 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 text-left">
                <i class="fas fa-trash mr-2"></i>Delete Selected
            </button>
            <button onclick="closeBulkModal()" class="w-full px-4 py-3 border rounded-lg hover:bg-gray-50">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// View toggle
function toggleView(view) {
    const listView = document.getElementById('listView');
    const gridView = document.getElementById('gridView');
    const listBtn = document.getElementById('listViewBtn');
    const gridBtn = document.getElementById('gridViewBtn');

    if (view === 'grid') {
        listView.classList.add('hidden');
        gridView.classList.remove('hidden');
        gridBtn.classList.add('bg-gray-200');
        listBtn.classList.remove('bg-gray-200');
    } else {
        gridView.classList.add('hidden');
        listView.classList.remove('hidden');
        listBtn.classList.add('bg-gray-200');
        gridBtn.classList.remove('bg-gray-200');
    }
    
    localStorage.setItem('productView', view);
}

// Load saved view preference
window.addEventListener('load', () => {
    const savedView = localStorage.getItem('productView') || 'list';
    toggleView(savedView);
});

// Selection management
let selectedProducts = new Set();

document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        if (this.checked) {
            selectedProducts.add(parseInt(cb.dataset.id));
        } else {
            selectedProducts.clear();
        }
    });
    updateSelectionUI();
});

document.querySelectorAll('.select-all-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            if (this.checked) {
                selectedProducts.add(parseInt(cb.dataset.id));
            } else {
                selectedProducts.clear();
            }
        });
        updateSelectionUI();
    });
});

document.querySelectorAll('.product-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const id = parseInt(this.dataset.id);
        if (this.checked) {
            selectedProducts.add(id);
        } else {
            selectedProducts.delete(id);
        }
        updateSelectionUI();
    });
});

function updateSelectionUI() {
    const count = selectedProducts.size;
    document.getElementById('selectedCount').textContent = count > 0 ? `${count} selected` : '';
    document.getElementById('bulkActionsBtn').style.display = count > 0 ? 'inline-block' : 'none';
    document.getElementById('printLabelsBtn').style.display = count > 0 ? 'inline-block' : 'none';
}

// Calculate margin and stock value
function calculateMargin() {
    const cost = parseFloat(document.getElementById('productCost').value) || 0;
    const price = parseFloat(document.getElementById('productPrice').value) || 0;
    const stock = parseFloat(document.getElementById('productStock').value) || 0;
    
    if (price > 0) {
        const margin = ((price - cost) / price) * 100;
        document.getElementById('profitMargin').value = margin.toFixed(2) + '%';
    }
    
    const stockValue = stock * price;
    document.getElementById('stockValue').value = 'KSh ' + stockValue.toFixed(2);
}

document.getElementById('productStock').addEventListener('input', calculateMargin);

// Generate barcode
function generateBarcode() {
    const barcode = 'ZWS' + Date.now().toString().slice(-10);
    document.getElementById('productBarcode').value = barcode;
}

// Product modal functions
function openProductModal() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('productAction').value = 'add';
    document.getElementById('productForm').reset();
    document.getElementById('profitMargin').value = '';
    document.getElementById('stockValue').value = '';
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productModal').classList.remove('flex');
}

function editProduct(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const product = data.data;
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category_id;
            document.getElementById('productUnit').value = product.unit || 'bottle';
            document.getElementById('productSKU').value = product.sku || '';
            document.getElementById('productBarcode').value = product.barcode || '';
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productCost').value = product.cost_price;
            document.getElementById('productPrice').value = product.selling_price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productReorder').value = product.reorder_level;
            document.getElementById('productSupplier').value = product.supplier || '';
            document.getElementById('productLocation').value = product.location || '';
            document.getElementById('productExpiry').value = product.expiry_date || '';
            
            calculateMargin();
            
            document.getElementById('productModal').classList.remove('hidden');
            document.getElementById('productModal').classList.add('flex');
        }
    });
}

function viewProduct(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const p = data.data;
            const stockValue = p.stock_quantity * p.selling_price;
            const margin = p.selling_price > 0 ? (((p.selling_price - p.cost_price) / p.selling_price) * 100).toFixed(2) : 0;
            
            let stockStatus = 'In Stock';
            let stockColor = 'green';
            if (p.stock_quantity <= 0) {
                stockStatus = 'Out of Stock';
                stockColor = 'red';
            } else if (p.stock_quantity <= p.reorder_level) {
                stockStatus = 'Low Stock';
                stockColor = 'orange';
            }
            
            const html = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">${p.name}</h2>
                            <p class="text-gray-600 mt-1">${p.description || 'No description'}</p>
                        </div>
                        <span class="px-4 py-2 rounded-full bg-${stockColor}-100 text-${stockColor}-800 font-semibold">
                            ${stockStatus}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Cost Price</p>
                            <p class="text-xl font-bold text-blue-600">KSh ${parseFloat(p.cost_price).toFixed(2)}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Selling Price</p>
                            <p class="text-xl font-bold text-green-600">KSh ${parseFloat(p.selling_price).toFixed(2)}</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Stock</p>
                            <p class="text-xl font-bold text-purple-600">${p.stock_quantity} ${p.unit || 'units'}</p>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Stock Value</p>
                            <p class="text-xl font-bold text-orange-600">KSh ${stockValue.toFixed(2)}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <h3 class="font-bold text-gray-800">Product Information</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">SKU:</span>
                                    <span class="font-semibold">${p.sku || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Barcode:</span>
                                    <span class="font-semibold font-mono">${p.barcode || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Unit:</span>
                                    <span class="font-semibold">${p.unit || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Profit Margin:</span>
                                    <span class="font-semibold text-green-600">${margin}%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <h3 class="font-bold text-gray-800">Additional Details</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Supplier:</span>
                                    <span class="font-semibold">${p.supplier || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Location:</span>
                                    <span class="font-semibold">${p.location || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Reorder Level:</span>
                                    <span class="font-semibold">${p.reorder_level} units</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Expiry Date:</span>
                                    <span class="font-semibold">${p.expiry_date || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 pt-6 border-t">
                        <button onclick="editProduct(${p.id}); closeViewModal();" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90">
                            <i class="fas fa-edit mr-2"></i>Edit Product
                        </button>
                        <button onclick="viewStockHistory(${p.id}); closeViewModal();" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:opacity-90">
                            <i class="fas fa-history mr-2"></i>Stock History
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('viewProductContent').innerHTML = html;
            document.getElementById('viewModal').classList.remove('hidden');
            document.getElementById('viewModal').classList.add('flex');
        }
    });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
    document.getElementById('viewModal').classList.remove('flex');
}

function viewStockHistory(id) {
    const formData = new FormData();
    formData.append('action', 'get_stock_history');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let html = '<div class="space-y-3">';
            
            if (data.data.length === 0) {
                html += '<p class="text-center text-gray-400 py-8">No stock movements yet</p>';
            } else {
                data.data.forEach(movement => {
                    const icon = movement.movement_type === 'in' ? 'plus-circle' : movement.movement_type === 'out' ? 'minus-circle' : 'exchange-alt';
                    const color = movement.movement_type === 'in' ? 'green' : movement.movement_type === 'out' ? 'red' : 'blue';
                    
                    html += `
                        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-${color}-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-${icon} text-${color}-600"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-semibold text-gray-800">${movement.movement_type.toUpperCase()}</span>
                                    <span class="text-sm text-gray-600">${new Date(movement.created_at).toLocaleString()}</span>
                                </div>
                                <p class="text-sm text-gray-600">Quantity: <span class="font-semibold">${movement.quantity} units</span></p>
                                <p class="text-sm text-gray-600">By: ${movement.user_name}</p>
                                ${movement.notes ? `<p class="text-sm text-gray-500 mt-1">${movement.notes}</p>` : ''}
                            </div>
                        </div>
                    `;
                });
            }
            
            html += '</div>';
            
            document.getElementById('stockHistoryContent').innerHTML = html;
            document.getElementById('stockHistoryModal').classList.remove('hidden');
            document.getElementById('stockHistoryModal').classList.add('flex');
        }
    });
}

function closeStockHistoryModal() {
    document.getElementById('stockHistoryModal').classList.add('hidden');
    document.getElementById('stockHistoryModal').classList.remove('flex');
}

function duplicateProduct(id) {
    if (!confirm('Duplicate this product?')) return;
    
    const formData = new FormData();
    formData.append('action', 'duplicate');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteProduct(id, name) {
    if (!confirm(`Delete product "${name}"?`)) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Bulk actions
function bulkActions() {
    if (selectedProducts.size === 0) {
        alert('Please select products first');
        return;
    }
    document.getElementById('bulkModal').classList.remove('hidden');
    document.getElementById('bulkModal').classList.add('flex');
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.add('hidden');
    document.getElementById('bulkModal').classList.remove('flex');
}

function bulkUpdateCategory() {
    const category = prompt('Enter new category ID:');
    if (!category) return;
    
    const formData = new FormData();
    formData.append('action', 'bulk_update_category');
    formData.append('ids', JSON.stringify(Array.from(selectedProducts)));
    formData.append('category_id', category);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function bulkDelete() {
    if (!confirm(`Delete ${selectedProducts.size} selected products?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'bulk_delete');
    formData.append('ids', JSON.stringify(Array.from(selectedProducts)));

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function exportCSV() {
    window.location.href = '?action=export_csv';
}

function printLabels() {
    if (selectedProducts.size === 0) {
        alert('Please select products first');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'print_labels');
    formData.append('ids', JSON.stringify(Array.from(selectedProducts)));

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const printWindow = window.open('', '_blank');
            let html = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Product Labels</title>
                    <style>
                        @page { size: 4in 2in; margin: 0.1in; }
                        body { font-family: Arial; margin: 0; padding: 0; }
                        .label { 
                            width: 4in; 
                            height: 2in; 
                            border: 1px dashed #ccc; 
                            padding: 0.2in; 
                            page-break-after: always;
                            display: flex;
                            flex-direction: column;
                            justify-content: space-between;
                        }
                        .product-name { font-size: 16pt; font-weight: bold; }
                        .price { font-size: 20pt; font-weight: bold; color: #ea580c; }
                        .barcode { font-family: monospace; font-size: 12pt; }
                        .info { font-size: 10pt; color: #666; }
                    </style>
                </head>
                <body>
            `;
            
            data.data.forEach(product => {
                html += `
                    <div class="label">
                        <div>
                            <div class="product-name">${product.name}</div>
                            <div class="info">SKU: ${product.sku || 'N/A'}</div>
                        </div>
                        <div>
                            <div class="price">KSh ${parseFloat(product.selling_price).toFixed(2)}</div>
                            ${product.barcode ? `<div class="barcode">${product.barcode}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</body></html>';
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 500);
        }
    });
}

// Submit product form
document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Product';
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K = Add Product
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openProductModal();
    }
    
    // Escape = Close modals
    if (e.key === 'Escape') {
        closeProductModal();
        closeViewModal();
        closeStockHistoryModal();
        closeBulkModal();
    }
});

// Show keyboard shortcuts hint
console.log('Keyboard Shortcuts:');
console.log('Ctrl/Cmd + K: Add Product');
console.log('Escape: Close Modal');
</script>

<?php include 'footer.php'; ?>
