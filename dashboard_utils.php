<?php

function dashboard_month_label(int $month): string
{
    $months = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    return $months[$month] ?? '';
}

function dashboard_fetch_scalar(PDO $conexion, string $sql): int|float
{
    $stmt = $conexion->query($sql);
    $value = $stmt->fetchColumn();

    return is_numeric($value) ? $value + 0 : 0;
}

function get_dashboard_data(PDO $conexion): array
{
    try {
        $driver = $conexion->getAttribute(PDO::ATTR_DRIVER_NAME);
        $is_sqlite = $driver === 'sqlite';

        $products_total = (int) dashboard_fetch_scalar($conexion, "SELECT COUNT(*) FROM productos");
        $users_total = (int) dashboard_fetch_scalar($conexion, "SELECT COUNT(*) FROM usuarios");
        $orders_total = (int) dashboard_fetch_scalar($conexion, "SELECT COUNT(*) FROM pedidos");
        $products_active = (int) dashboard_fetch_scalar($conexion, "SELECT COUNT(*) FROM productos WHERE stock > 0");
        if ($is_sqlite) {
            $sales_this_month = (float) dashboard_fetch_scalar($conexion, "SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE strftime('%Y', created_at) = strftime('%Y', 'now') AND strftime('%m', created_at) = strftime('%m', 'now')");
        } else {
            $sales_this_month = (float) dashboard_fetch_scalar($conexion, "SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
        }
        $pending_orders = (int) dashboard_fetch_scalar($conexion, "SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'");

        if ($is_sqlite) {
            $sales_statement = $conexion->query("SELECT strftime('%Y-%m', created_at) AS period, COALESCE(SUM(total), 0) AS total FROM pedidos WHERE created_at >= date('now', '-5 months', 'start of month') GROUP BY period ORDER BY period ASC");
        } else {
            $sales_statement = $conexion->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COALESCE(SUM(total), 0) AS total FROM pedidos WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') GROUP BY period ORDER BY period ASC");
        }
        $sales_map = [];
        foreach ($sales_statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sales_map[$row['period']] = (float) $row['total'];
        }

        $monthly_sales = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = (new DateTimeImmutable('first day of this month'))->modify("-{$i} month");
            $period = $date->format('Y-m');
            $monthly_sales[] = [
                'period' => $period,
                'label' => dashboard_month_label((int) $date->format('n')),
                'year' => $date->format('Y'),
                'total' => $sales_map[$period] ?? 0,
            ];
        }

        $categories_statement = $conexion->query("SELECT categoria, COUNT(*) AS total FROM productos GROUP BY categoria ORDER BY total DESC, categoria ASC");
        $category_counts = [];
        foreach ($categories_statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $category_counts[] = [
                'category' => $row['categoria'],
                'total' => (int) $row['total'],
            ];
        }

        $recent_orders_statement = $conexion->query("SELECT id, cliente_nombre, estado, total, created_at FROM pedidos ORDER BY created_at DESC, id DESC LIMIT 5");
        $recent_orders = $recent_orders_statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'products_total' => $products_total,
            'users_total' => $users_total,
            'orders_total' => $orders_total,
            'products_active' => $products_active,
            'sales_this_month' => $sales_this_month,
            'pending_orders' => $pending_orders,
            'monthly_sales' => $monthly_sales,
            'category_counts' => $category_counts,
            'recent_orders' => $recent_orders,
        ];
    } catch (PDOException $e) {
        error_log($e->getMessage());

        return [
            'products_total' => 0,
            'users_total' => 0,
            'orders_total' => 0,
            'products_active' => 0,
            'sales_this_month' => 0,
            'pending_orders' => 0,
            'monthly_sales' => [],
            'category_counts' => [],
            'recent_orders' => [],
        ];
    }
}

?>