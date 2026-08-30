<?php

use System\Engine\Controller;

class ShopAdminDashboard extends Controller {
    public function indexAction(): void
    {
        // Get stats for the current store
        $stats = $this->getDashboardStats();

        echo $this->view->inline(function ($view) use ($stats) {
            // Stats cards
            echo "<div class='row g-4'>";

            // Total Products
            echo "<div class='col-md-3'>";
            echo "<div class='card text-bg-primary h-100'>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex justify-content-between align-items-center'>";
            echo "<div>";
            echo "<h6 class='card-title text-uppercase fw-bold small'>Products</h6>";
            echo "<h2 class='card-text'>" . number_format($stats['products']) . "</h2>";
            echo "</div>";
            echo "<i class='bi bi-box fs-1 opacity-50'></i>";
            echo "</div>";
            echo "<a href='" . $view->url->to('shop/admin/product') . "' class='btn btn-light btn-sm mt-2'>Manage Products</a>";
            echo "</div></div>";
            echo "</div>";

            // Total Orders
            echo "<div class='col-md-3'>";
            echo "<div class='card text-bg-success h-100'>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex justify-content-between align-items-center'>";
            echo "<div>";
            echo "<h6 class='card-title text-uppercase fw-bold small'>Orders</h6>";
            echo "<h2 class='card-text'>" . number_format($stats['orders']) . "</h2>";
            echo "</div>";
            echo "<i class='bi bi-cart fs-1 opacity-50'></i>";
            echo "</div>";
            echo "<a href='" . $view->url->to('shop/orders') . "' class='btn btn-light btn-sm mt-2'>View Orders</a>";
            echo "</div></div>";
            echo "</div>";

            // Revenue
            echo "<div class='col-md-3'>";
            echo "<div class='card text-bg-warning h-100'>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex justify-content-between align-items-center'>";
            echo "<div>";
            echo "<h6 class='card-title text-uppercase fw-bold small'>Revenue</h6>";
            echo "<h2 class='card-text'>$" . number_format($stats['revenue'], 2) . "</h2>";
            echo "</div>";
            echo "<i class='bi bi-currency-dollar fs-1 opacity-50'></i>";
            echo "</div>";
            echo "<a href='" . $view->url->to('shop/orders') . "' class='btn btn-light btn-sm mt-2'>View Orders</a>";
            echo "</div></div>";
            echo "</div>";

            // Categories
            echo "<div class='col-md-3'>";
            echo "<div class='card text-bg-info h-100'>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex justify-content-between align-items-center'>";
            echo "<div>";
            echo "<h6 class='card-title text-uppercase fw-bold small'>Categories</h6>";
            echo "<h2 class='card-text'>" . number_format($stats['categories']) . "</h2>";
            echo "</div>";
            echo "<i class='bi bi-tags fs-1 opacity-50'></i>";
            echo "</div>";
            echo "<a href='" . $view->url->to('shop/admin/category') . "' class='btn btn-light btn-sm mt-2'>Manage Categories</a>";
            echo "</div></div>";
            echo "</div>";

            echo "</div>"; // row

            // Recent Orders
            echo "<div class='row mt-4'>";
            echo "<div class='col-12'>";
            echo "<div class='card'>";
            echo "<div class='card-header'><h5 class='mb-0'>Recent Orders</h5></div>";
            echo "<div class='card-body'>";

            if (empty($stats['recent_orders'])) {
                echo "<p class='text-muted'>No recent orders.</p>";
            } else {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr></thead>";
                echo "<tbody>";
                foreach ($stats['recent_orders'] as $order) {
                    $statusBadge = match ($order['status']) {
                        'pending'   => 'warning',
                        'paid'      => 'info',
                        'shipped'   => 'primary',
                        'completed' => 'success',
                        'canceled'  => 'danger',
                        default     => 'secondary',
                    };
                    echo "<tr>";
                    echo "<td>#" . (int)$order['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($order['user_name'] ?? 'Guest') . "</td>";
                    echo "<td>$" . number_format((float)$order['total_amount'], 2) . "</td>";
                    echo "<td><span class='badge bg-" . $statusBadge . "'>" . htmlspecialchars($order['status']) . "</span></td>";
                    echo "<td>" . htmlspecialchars($order['created_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            }
            echo "</div></div>";
            echo "</div>";
            echo "</div>";

            // Quick Actions
            echo "<div class='row mt-4'>";
            echo "<div class='col-12'>";
            echo "<div class='card'>";
            echo "<div class='card-header'><h5 class='mb-0'>Quick Actions</h5></div>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex flex-wrap gap-2'>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('shop/admin/product/add') . "'>Add Product</a>";
            echo "<a class='btn btn-success' href='" . $view->url->to('shop/admin/category/add') . "'>Add Category</a>";
            echo "<a class='btn btn-warning' href='" . $view->url->to('shop/orders') . "'>View All Orders</a>";
            echo "</div>";
            echo "</div></div>";
            echo "</div>";
            echo "</div>";

        }, 'admin');
    }

    /**
     * Get dashboard statistics for the current store.
     */
    private function getDashboardStats(): array
    {
        // Products count
        $products = (int) $this->db->query(
            "SELECT COUNT(*) AS total FROM #__shop_product")->value;

        // Orders count and revenue
        $orderStats = $this->db->query("SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS revenue FROM #__shop_orders")->row;

        // Categories count
        $categories = (int) $this->db->query("SELECT COUNT(*) AS total FROM #__shop_categories")->value;

        // Recent orders (last 5)
        $recentOrders = $this->db->query("SELECT o.*, u.username AS user_name FROM #__shop_orders o LEFT JOIN #__users u ON u.id = o.customer_id ORDER BY o.created_at DESC LIMIT 5")->rows;

        return [
            'products'      => $products,
            'orders'        => (int) ($orderStats['total'] ?? 0),
            'revenue'       => (float) ($orderStats['revenue'] ?? 0),
            'categories'    => $categories,
            'recent_orders' => $recentOrders,
        ];
    }
}