<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

echo "Running database migrations...\n";

$pdo = app_pdo();

$migrations = [
    '001_add_promo_expiry' => "
        ALTER TABLE promo_codes ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL;
        ALTER TABLE promo_codes ADD COLUMN IF NOT EXISTS max_uses INT UNSIGNED NULL;
        ALTER TABLE promo_codes ADD COLUMN IF NOT EXISTS used_count INT UNSIGNED NOT NULL DEFAULT 0;
    ",
    '002_add_indexes' => "
        CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
        CREATE INDEX IF NOT EXISTS idx_orders_delivery_staff ON orders(delivery_staff_id);
        CREATE INDEX IF NOT EXISTS idx_orders_delivery_zone ON orders(delivery_zone_id);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_payments_order ON payments(order_id);
        CREATE INDEX IF NOT EXISTS idx_payments_reference ON payments(reference);
        CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_notifications_order ON notifications(order_id);
        CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);
        CREATE INDEX IF NOT EXISTS idx_menu_items_category ON menu_items(category_id);
        CREATE INDEX IF NOT EXISTS idx_delivery_staff_phone ON delivery_staff(phone);
    ",
];

foreach ($migrations as $name => $sql) {
    echo "  - {$name}... ";
    try {
        foreach (array_filter(explode(';', $sql)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        echo "OK\n";
    } catch (Throwable $e) {
        echo "SKIP ({$e->getMessage()})\n";
    }
}

echo "Done.\n";
