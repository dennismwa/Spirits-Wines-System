     </main>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav bg-white border-t shadow-lg md:hidden no-print">
        <div class="flex justify-around items-center py-2">
            <?php if ($_SESSION['role'] === 'owner'): ?>
            <a href="/dashboard" class="flex flex-col items-center py-2 px-3 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <?php endif; ?>
            
            <a href="/pos" class="flex flex-col items-center py-2 px-3 <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-cash-register text-xl mb-1"></i>
                <span class="text-xs">POS</span>
            </a>
            
            <a href="/products" class="flex flex-col items-center py-2 px-3 <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-wine-bottle text-xl mb-1"></i>
                <span class="text-xs">Products</span>
            </a>
            
            <a href="/sales" class="flex flex-col items-center py-2 px-3 <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-receipt text-xl mb-1"></i>
                <span class="text-xs">Sales</span>
            </a>
            
            <?php if ($_SESSION['role'] === 'owner'): ?>
            <a href="/settings" class="flex flex-col items-center py-2 px-3 <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-cog text-xl mb-1"></i>
                <span class="text-xs">Settings</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>
</body>
</html>

