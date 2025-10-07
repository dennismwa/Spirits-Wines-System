<?php
require_once 'config.php';
requireAuth();
$page_title = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            $stmt->execute();
            logActivity('Category Added', "Added category: $name");
            respond(true, 'Category added successfully');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $description, $id);
            $stmt->execute();
            logActivity('Category Updated', "Updated category: $name");
            respond(true, 'Category updated successfully');
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE categories SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logActivity('Category Deleted', "Deleted category ID: $id");
        respond(true, 'Category deleted successfully');
    }
    
    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        respond(true, '', $result->fetch_assoc());
    }
    exit;
}

$categories = $conn->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id AND p.status='active' WHERE c.status='active' GROUP BY c.id ORDER BY c.name");

include 'header.php';
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Categories</h2>
            <p class="text-sm text-gray-600 mt-1">Organize your products</p>
        </div>
        <button onclick="openModal()" class="px-6 py-3 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
            <i class="fas fa-plus mr-2"></i>Add Category
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-6">
        <?php while ($cat = $categories->fetch_assoc()): ?>
        <div class="border-2 border-gray-200 rounded-xl p-6 hover:border-primary transition">
            <div class="flex items-start justify-between mb-4">
                <div class="w-12 h-12 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tags text-primary text-xl"></i>
                </div>
                <div class="flex gap-2">
                    <button onclick="editCategory(<?php echo $cat['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <h3 class="font-bold text-lg text-gray-800 mb-2"><?php echo htmlspecialchars($cat['name']); ?></h3>
            <?php if ($cat['description']): ?>
            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($cat['description']); ?></p>
            <?php endif; ?>
            <div class="flex items-center text-sm text-gray-500">
                <i class="fas fa-wine-bottle mr-2"></i>
                <span><?php echo $cat['product_count']; ?> products</span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold" id="modalTitle">Add Category</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="categoryForm">
            <input type="hidden" id="categoryId" name="id">
            <input type="hidden" id="categoryAction" name="action" value="add">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                <input type="text" name="name" id="categoryName" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" id="categoryDescription" rows="3" 
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 border rounded-lg hover:bg-gray-50 font-medium">
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
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Category';
    document.getElementById('categoryAction').value = 'add';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryModal').classList.remove('hidden');
    document.getElementById('categoryModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.getElementById('categoryModal').classList.remove('flex');
}

function editCategory(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('categoryAction').value = 'edit';
            document.getElementById('categoryId').value = data.data.id;
            document.getElementById('categoryName').value = data.data.name;
            document.getElementById('categoryDescription').value = data.data.description || '';
            document.getElementById('categoryModal').classList.remove('hidden');
            document.getElementById('categoryModal').classList.add('flex');
        }
    });
}

function deleteCategory(id, name) {
    if (!confirm(`Delete category "${name}"?`)) return;
    
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

document.getElementById('categoryForm').addEventListener('submit', function(e) {
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