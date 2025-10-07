<?php
require_once 'config.php';
requireOwner();
$page_title = 'User Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $name = sanitize($_POST['name']);
        $pin_code = sanitize($_POST['pin_code']);
        $role = sanitize($_POST['role']);
        
        if ($action === 'add') {
            $check = $conn->query("SELECT id FROM users WHERE pin_code='$pin_code'");
            if ($check->num_rows > 0) {
                respond(false, 'PIN code already exists');
            }
            
            $stmt = $conn->prepare("INSERT INTO users (name, pin_code, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $pin_code, $role);
            $stmt->execute();
            logActivity('User Added', "Added user: $name");
            respond(true, 'User added successfully');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE users SET name=?, pin_code=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $pin_code, $role, $id);
            $stmt->execute();
            logActivity('User Updated', "Updated user: $name");
            respond(true, 'User updated successfully');
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id == $_SESSION['user_id']) {
            respond(false, 'Cannot delete your own account');
        }
        $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logActivity('User Deleted', "Deleted user ID: $id");
        respond(true, 'User deleted successfully');
    }
    
    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        respond(true, '', $result->fetch_assoc());
    }
    exit;
}

$users = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM sales WHERE user_id = u.id) as sales_count FROM users u WHERE status='active' ORDER BY name");

include 'header.php';
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">User Management</h2>
            <p class="text-sm text-gray-600 mt-1">Manage system users and permissions</p>
        </div>
        <button onclick="openUserModal()" class="px-6 py-3 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
            <i class="fas fa-plus mr-2"></i>Add User
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PIN Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary bg-opacity-10 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-primary"></i>
                            </div>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-mono text-sm bg-gray-100 px-3 py-1 rounded">••••••</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-xs font-medium rounded-full <?php echo $user['role'] === 'owner' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo $user['sales_count']; ?> transactions
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold" id="modalTitle">Add User</h3>
            <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="userForm">
            <input type="hidden" id="userId" name="id">
            <input type="hidden" id="userAction" name="action" value="add">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                <input type="text" name="name" id="userName" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">PIN Code (6 digits) *</label>
                <input type="text" name="pin_code" id="userPin" required maxlength="6" pattern="[0-9]{6}"
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary font-mono">
                <p class="text-xs text-gray-500 mt-1">User will use this PIN to login</p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                <select name="role" id="userRole" required 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="seller">Seller</option>
                    <option value="owner">Owner</option>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeUserModal()" class="flex-1 px-6 py-3 border rounded-lg hover:bg-gray-50 font-medium">
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
function openUserModal() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userAction').value = 'add';
    document.getElementById('userForm').reset();
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userModal').classList.add('flex');
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('userModal').classList.remove('flex');
}

function editUser(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('userAction').value = 'edit';
            document.getElementById('userId').value = data.data.id;
            document.getElementById('userName').value = data.data.name;
            document.getElementById('userPin').value = data.data.pin_code;
            document.getElementById('userRole').value = data.data.role;
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('userModal').classList.add('flex');
        }
    });
}

function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"?`)) return;
    
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

document.getElementById('userForm').addEventListener('submit', function(e) {
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