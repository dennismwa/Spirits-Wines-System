<?php
require_once 'config.php';
requireOwner();
$page_title = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = sanitize($_POST['company_name']);
    $primary_color = sanitize($_POST['primary_color']);
    $tax_rate = floatval($_POST['tax_rate']);
    $receipt_footer = sanitize($_POST['receipt_footer']);
    
    $stmt = $conn->prepare("UPDATE settings SET company_name=?, primary_color=?, tax_rate=?, receipt_footer=? WHERE id=1");
    $stmt->bind_param("ssds", $company_name, $primary_color, $tax_rate, $receipt_footer);
    $stmt->execute();
    $stmt->close();
    
    logActivity('Settings Updated', 'System settings updated');
    $_SESSION['success_message'] = 'Settings updated successfully';
    header('Location: /settings');
    exit;
}

$settings = getSettings();
include 'header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl mb-6">
    <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">System Settings</h2>
        <p class="text-sm text-gray-600 mt-1">Configure your system preferences</p>
    </div>

    <form method="POST" class="p-6">
        <div class="max-w-2xl space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Logo Path</label>
                <input type="text" value="<?php echo htmlspecialchars($settings['logo_path']); ?>" disabled 
                       class="w-full px-4 py-2 border rounded-lg bg-gray-50">
                <p class="text-xs text-gray-500 mt-1">Upload logo as /logo.jpg in root directory</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color *</label>
                <div class="flex gap-3">
                    <input type="color" name="primary_color" id="colorPicker" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" 
                           class="w-20 h-12 rounded-lg border">
                    <input type="text" id="colorText" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" readonly
                           class="flex-1 px-4 py-2 border rounded-lg bg-gray-50">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                <input type="number" step="0.01" name="tax_rate" value="<?php echo $settings['tax_rate']; ?>" 
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <p class="text-xs text-gray-500 mt-1">Enter 0 for no tax</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Footer Text</label>
                <textarea name="receipt_footer" rows="3" 
                          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($settings['receipt_footer']); ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Text to display at the bottom of receipts</p>
            </div>

            <div class="border-t pt-6">
                <button type="submit" class="px-6 py-3 bg-primary text-white rounded-lg hover:opacity-90 font-medium">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">System Information</h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">PHP Version</span>
                <span class="font-semibold"><?php echo phpversion(); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Database</span>
                <span class="font-semibold">MySQL</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Timezone</span>
                <span class="font-semibold">Africa/Nairobi</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Currency</span>
                <span class="font-semibold"><?php echo $settings['currency']; ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-800 mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <a href="/reports" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                <i class="fas fa-chart-bar text-primary"></i>
                <span class="font-medium">View Reports</span>
            </a>
            <a href="/inventory" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                <i class="fas fa-boxes text-primary"></i>
                <span class="font-medium">Check Inventory</span>
            </a>
            <a href="/users" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50">
                <i class="fas fa-users text-primary"></i>
                <span class="font-medium">Manage Users</span>
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('colorPicker').addEventListener('input', function(e) {
    document.getElementById('colorText').value = e.target.value;
});
</script>

<?php include 'footer.php'; ?>
