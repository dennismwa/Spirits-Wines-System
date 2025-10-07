<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /index');
    exit;
}
$settings = getSettings();
$page_title = $page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . ' - ' . $settings['company_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
        }
        .bg-primary { background-color: var(--primary-color); }
        .text-primary { color: var(--primary-color); }
        .border-primary { border-color: var(--primary-color); }
        .hover-primary:hover { background-color: var(--primary-color); }
        
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        
        .sidebar { 
            transition: transform 0.3s ease-in-out;
            z-index: 40;
        }
        
        @media (max-width: 768px) {
            .sidebar { 
                transform: translateX(-100%); 
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                width: 280px;
            }
            .sidebar.open { transform: translateX(0); }
            .mobile-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 50;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar fixed left-0 top-0 h-screen w-64 bg-white shadow-lg">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="p-6 border-b bg-primary text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" 
                             alt="Logo" 
                             class="h-10 w-10 object-contain bg-white rounded-lg p-1"
                             onerror="this.style.display='none'">
                        <div>
                            <h2 class="font-bold text-lg leading-tight"><?php echo htmlspecialchars($settings['company_name']); ?></h2>
                            <p class="text-xs opacity-90"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        </div>
                    </div>
                    <button id="closeSidebar" class="md:hidden text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-4">
                <ul class="space-y-2">
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                    <li>
                        <a href="/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-chart-line w-5"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li>
                        <a href="/pos" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-cash-register w-5"></i>
                            <span>POS</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/products" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-wine-bottle w-5"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/categories" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-tags w-5"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/sales" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-receipt w-5"></i>
                            <span>Sales History</span>
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                    <li>
                        <a href="/inventory" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-boxes w-5"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/reports" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-chart-bar w-5"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/expenses" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'expenses.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-money-bill-wave w-5"></i>
                            <span>Expenses</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/users" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-users w-5"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/settings" class="flex items-center gap-3 px-4 py-3 rounded-lg hover-primary hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-primary text-white' : 'text-gray-700'; ?>">
                            <i class="fas fa-cog w-5"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t">
                <a href="/logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen flex flex-col pb-16 md:pb-0">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-20 no-print">
            <div class="px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button id="menuBtn" class="md:hidden text-gray-600">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600 hidden sm:inline">
                        <i class="far fa-clock mr-1"></i>
                        <span id="currentTime"></span>
                    </span>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-user-circle text-primary text-xl"></i>
                        <span class="hidden sm:inline font-medium"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-4 md:p-6">

<script>
// Sidebar toggle
const menuBtn = document.getElementById('menuBtn');
const closeSidebar = document.getElementById('closeSidebar');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('sidebarOverlay');

menuBtn?.addEventListener('click', () => {
    sidebar.classList.add('open');
    overlay.classList.remove('hidden');
});

closeSidebar?.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.add('hidden');
});

overlay?.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.add('hidden');
});

// Update time
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    document.getElementById('currentTime').textContent = timeStr;
}
updateTime();
setInterval(updateTime, 1000);
</script>