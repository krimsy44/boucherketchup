<?php

declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const SESSION_FILE = APP_ROOT . '/storage/sessions.json';
const RATE_LIMIT_FILE = APP_ROOT . '/storage/rate-limits.json';
const UPLOAD_ROOT = APP_ROOT . '/uploads/menu';
const UPLOAD_PUBLIC_PATH = 'uploads/menu';

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . app_env('APP_URL', '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_csv_response(string $filename, array $headers, array $rows): void
{
    http_response_code(200);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function app_request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function app_env(string $key, string $fallback = ''): string
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $path = APP_ROOT . '/.env';
        if (file_exists($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $env[trim($name)] = trim(trim($value), "\"'");
            }
        }
    }

    return $env[$key] ?? getenv($key) ?: $fallback;
}

function app_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', '127.0.0.1');
    $port = app_env('DB_PORT', '3306');
    $database = app_env('DB_DATABASE', 'chez_ketchup');
    $username = app_env('DB_USERNAME', 'root');
    $password = app_env('DB_PASSWORD', '');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $options);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, $options);

    $flagFile = APP_ROOT . '/storage/.db_installed';
    if (!file_exists($flagFile)) {
        app_install_schema($pdo);
        app_seed_database($pdo);
        file_put_contents($flagFile, date('c'));
    }

    return $pdo;
}

function app_is_production(): bool
{
    return app_env('APP_ENV', 'local') === 'production';
}

function app_install_schema(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS categories (
            id VARCHAR(60) PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS menu_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id VARCHAR(60) NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            price INT UNSIGNED NOT NULL,
            max_price INT UNSIGNED NULL,
            image_url VARCHAR(255) NULL,
            emoji VARCHAR(16) NULL,
            available BOOLEAN NOT NULL DEFAULT TRUE,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            popular BOOLEAN NOT NULL DEFAULT FALSE,
            daily_special BOOLEAN NOT NULL DEFAULT FALSE,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES categories(id)
        )",
        "CREATE TABLE IF NOT EXISTS customers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            phone VARCHAR(40) NOT NULL UNIQUE,
            default_address VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS delivery_staff (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            access_code_hash VARCHAR(255) NULL,
            status ENUM('available', 'busy', 'absent') NOT NULL DEFAULT 'available',
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            number VARCHAR(32) NOT NULL UNIQUE,
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(140) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            address VARCHAR(255) NOT NULL,
            note TEXT NULL,
            fulfillment ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
            status ENUM('pending', 'prep', 'delivery', 'done', 'cancelled') NOT NULL DEFAULT 'pending',
            total INT UNSIGNED NOT NULL DEFAULT 0,
            delivery_staff_id BIGINT UNSIGNED NULL,
            cancellation_reason VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_orders_delivery_staff FOREIGN KEY (delivery_staff_id) REFERENCES delivery_staff(id),
            CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            menu_item_id BIGINT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            unit_price INT UNSIGNED NOT NULL,
            line_total INT UNSIGNED NOT NULL,
            CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_items_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        )",
        "CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            method ENUM('cash', 'orange_money', 'wave') NOT NULL DEFAULT 'orange_money',
            provider VARCHAR(80) NULL,
            reference VARCHAR(120) NULL,
            checkout_url VARCHAR(255) NULL,
            amount INT UNSIGNED NOT NULL,
            status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS order_status_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending', 'prep', 'delivery', 'done', 'cancelled') NOT NULL,
            changed_by BIGINT UNSIGNED NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            message TEXT NOT NULL,
            handled BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NULL,
            channel ENUM('whatsapp', 'sms') NOT NULL DEFAULT 'whatsapp',
            recipient VARCHAR(40) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
            provider VARCHAR(80) NULL,
            provider_reference VARCHAR(120) NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            user_name VARCHAR(140) NULL,
            action VARCHAR(80) NOT NULL,
            entity VARCHAR(80) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            details TEXT NULL,
            ip_address VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",
<<<<<<< HEAD
=======
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS business_hours (
            weekday TINYINT UNSIGNED PRIMARY KEY,
            open_time TIME NULL,
            close_time TIME NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE
        )",
        "CREATE TABLE IF NOT EXISTS delivery_zones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            fee INT UNSIGNED NOT NULL DEFAULT 0,
            minimum_order INT UNSIGNED NOT NULL DEFAULT 0,
            estimated_minutes INT UNSIGNED NOT NULL DEFAULT 45,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS menu_item_options (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_item_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            price_delta INT NOT NULL DEFAULT 0,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_option_menu_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS promo_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            label VARCHAR(140) NOT NULL,
            type ENUM('percent', 'fixed') NOT NULL DEFAULT 'fixed',
            value INT UNSIGNED NOT NULL DEFAULT 0,
            minimum_order INT UNSIGNED NOT NULL DEFAULT 0,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS customer_reviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )",
>>>>>>> 5f92b50 (mise a jour)
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    app_ensure_column($pdo, 'menu_items', 'active', 'ALTER TABLE menu_items ADD active BOOLEAN NOT NULL DEFAULT TRUE AFTER available');
    app_ensure_column($pdo, 'menu_items', 'daily_special', 'ALTER TABLE menu_items ADD daily_special BOOLEAN NOT NULL DEFAULT FALSE AFTER popular');
    app_ensure_column($pdo, 'payments', 'checkout_url', 'ALTER TABLE payments ADD checkout_url VARCHAR(255) NULL AFTER reference');
    app_ensure_column($pdo, 'notifications', 'provider_reference', 'ALTER TABLE notifications ADD provider_reference VARCHAR(120) NULL AFTER provider');
    app_ensure_column($pdo, 'delivery_staff', 'access_code_hash', 'ALTER TABLE delivery_staff ADD access_code_hash VARCHAR(255) NULL AFTER phone');
<<<<<<< HEAD
    app_backfill_delivery_codes($pdo);
=======
    app_ensure_column($pdo, 'orders', 'subtotal', 'ALTER TABLE orders ADD subtotal INT UNSIGNED NOT NULL DEFAULT 0 AFTER status');
    app_ensure_column($pdo, 'orders', 'delivery_fee', 'ALTER TABLE orders ADD delivery_fee INT UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal');
    app_ensure_column($pdo, 'orders', 'discount_total', 'ALTER TABLE orders ADD discount_total INT UNSIGNED NOT NULL DEFAULT 0 AFTER delivery_fee');
    app_ensure_column($pdo, 'orders', 'promo_code', 'ALTER TABLE orders ADD promo_code VARCHAR(40) NULL AFTER discount_total');
    app_ensure_column($pdo, 'orders', 'delivery_zone_id', 'ALTER TABLE orders ADD delivery_zone_id BIGINT UNSIGNED NULL AFTER fulfillment');
    app_ensure_column($pdo, 'orders', 'scheduled_at', 'ALTER TABLE orders ADD scheduled_at DATETIME NULL AFTER delivery_zone_id');
    app_ensure_column($pdo, 'order_items', 'options_text', 'ALTER TABLE order_items ADD options_text TEXT NULL AFTER name');
    app_ensure_column($pdo, 'menu_items', 'stock_enabled', 'ALTER TABLE menu_items ADD stock_enabled BOOLEAN NOT NULL DEFAULT FALSE AFTER daily_special');
    app_ensure_column($pdo, 'menu_items', 'stock_qty', 'ALTER TABLE menu_items ADD stock_qty INT NOT NULL DEFAULT 0 AFTER stock_enabled');
    app_ensure_column($pdo, 'menu_items', 'low_stock_threshold', 'ALTER TABLE menu_items ADD low_stock_threshold INT NOT NULL DEFAULT 3 AFTER stock_qty');
    app_backfill_delivery_codes($pdo);
    app_seed_operations($pdo);
>>>>>>> 5f92b50 (mise a jour)
}

function app_ensure_column(PDO $pdo, string $table, string $column, string $alter): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec($alter);
    }
}

function app_backfill_delivery_codes(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id FROM delivery_staff WHERE access_code_hash IS NULL OR access_code_hash = ""');
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($ids === []) {
        return;
    }

    $hash = password_hash(app_env('DELIVERY_DEFAULT_CODE', '1234'), PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE delivery_staff SET access_code_hash = ? WHERE id = ?');
    foreach ($ids as $id) {
        $update->execute([$hash, $id]);
    }
}

function app_seed_payload(): array
{
    return [
        'categories' => [
            ['id' => 'fast-food', 'name' => 'Fast Food', 'sort' => 10],
            ['id' => 'gastro', 'name' => 'Gastro', 'sort' => 20],
            ['id' => 'pizzeria', 'name' => 'Pizzeria', 'sort' => 30],
            ['id' => 'petit-dej', 'name' => 'Petit Déj', 'sort' => 40],
            ['id' => 'entrees', 'name' => 'Entrées', 'sort' => 50],
            ['id' => 'desserts', 'name' => 'Desserts', 'sort' => 60],
            ['id' => 'tapas', 'name' => 'Tapas', 'sort' => 70],
            ['id' => 'repas', 'name' => 'Repas', 'sort' => 80],
        ],
        'menu' => [
            ['name' => 'Sandwich Viande', 'description' => 'Sandwich garni viande et crudités.', 'price' => 1000, 'category' => 'fast-food', 'emoji' => '🥪', 'popular' => false],
            ['name' => 'Club Sandwich Poulet', 'description' => 'Poulet, salade et sauce maison.', 'price' => 1500, 'category' => 'fast-food', 'emoji' => '🥪', 'popular' => false],
            ['name' => 'Norvégienne', 'description' => 'Sandwich frais façon nordique.', 'price' => 1500, 'category' => 'fast-food', 'emoji' => '🥪', 'popular' => false],
            ['name' => 'Chawarma', 'description' => 'Pain roulé, viande marinée et sauce.', 'price' => 1500, 'category' => 'fast-food', 'emoji' => '🥙', 'popular' => true],
            ['name' => 'Burger', 'description' => 'Burger généreux et frites.', 'price' => 2000, 'category' => 'fast-food', 'emoji' => '🍔', 'popular' => true],
            ['name' => 'Tacos Viande', 'description' => 'Tacos viande avec sauce fromagère.', 'price' => 3000, 'category' => 'fast-food', 'emoji' => '🌮', 'popular' => false],
            ['name' => 'Tacos Poulet', 'description' => 'Tacos poulet croustillant.', 'price' => 3500, 'category' => 'fast-food', 'emoji' => '🌮', 'popular' => false],
            ['name' => 'Tacos Ketchup', 'description' => 'La spécialité maison.', 'price' => 4000, 'category' => 'fast-food', 'emoji' => '🌮', 'popular' => true],
            ['name' => 'Sautés aux Poulet', 'description' => 'Poulet sauté, garniture du jour.', 'price' => 3000, 'category' => 'gastro', 'emoji' => '🍗', 'popular' => false],
            ['name' => 'Tajine', 'description' => 'Plat mijoté aux épices douces.', 'price' => 4000, 'category' => 'gastro', 'emoji' => '🥘', 'popular' => false],
            ['name' => 'Basquaise aux Poulet', 'description' => 'Poulet sauce basquaise.', 'price' => 4000, 'category' => 'gastro', 'emoji' => '🍗', 'popular' => false],
            ['name' => 'Brochettes de Viande', 'description' => 'Brochettes grillées et accompagnement.', 'price' => 4000, 'category' => 'gastro', 'emoji' => '🍢', 'popular' => false],
            ['name' => 'Tonga Crevettes Crispy', 'description' => 'Crevettes croustillantes sauce maison.', 'price' => 4000, 'category' => 'gastro', 'emoji' => '🦐', 'popular' => true],
            ['name' => 'Taryaki de Boeuf', 'description' => 'Boeuf façon teriyaki.', 'price' => 5000, 'category' => 'gastro', 'emoji' => '🥩', 'popular' => false],
            ['name' => 'Poisson Braisé', 'description' => 'Poisson braisé et accompagnement.', 'price' => 6000, 'category' => 'gastro', 'emoji' => '🐟', 'popular' => true],
            ['name' => 'Plateau Fruits de Mer', 'description' => 'Assortiment généreux de la mer.', 'price' => 10000, 'category' => 'gastro', 'emoji' => '🦞', 'popular' => true],
            ['name' => 'Pizza Margarita', 'description' => 'Tomate, mozzarella, basilic.', 'price' => 3000, 'max_price' => 4000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Pizza Reine', 'description' => 'Jambon, champignons, fromage.', 'price' => 3500, 'max_price' => 4500, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Pizza 4 Fromages', 'description' => 'Mélange de fromages fondants.', 'price' => 4000, 'max_price' => 6000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Pizza Spéciale', 'description' => 'Garniture spéciale maison.', 'price' => 4000, 'max_price' => 6000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Pizza Standard', 'description' => 'Pizza classique selon disponibilité.', 'price' => 4000, 'max_price' => 6000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Pizza Ketchup', 'description' => 'Pizza signature Chez Ketchup.', 'price' => 4000, 'max_price' => 6000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => true],
            ['name' => 'Pizza Fruits de Mer', 'description' => 'Fruits de mer et fromage.', 'price' => 4000, 'max_price' => 6000, 'category' => 'pizzeria', 'emoji' => '🍕', 'popular' => false],
            ['name' => 'Petit déjeuner complet', 'description' => 'Formule complète du matin.', 'price' => 2000, 'category' => 'petit-dej', 'emoji' => '☕', 'popular' => false],
            ['name' => 'Café + Viennoiserie', 'description' => 'Café chaud et viennoiserie.', 'price' => 1000, 'category' => 'petit-dej', 'emoji' => '🥐', 'popular' => false],
            ['name' => 'Jus frais', 'description' => 'Jus pressé selon arrivage.', 'price' => 500, 'category' => 'petit-dej', 'emoji' => '🥤', 'popular' => false],
            ['name' => 'Entrée du jour', 'description' => 'Entrée fraîche du moment.', 'price' => 1500, 'category' => 'entrees', 'emoji' => '🥗', 'popular' => false],
            ['name' => 'Dessert du chef', 'description' => 'Création sucrée du chef.', 'price' => 1500, 'category' => 'desserts', 'emoji' => '🍮', 'popular' => false],
            ['name' => 'Glace maison', 'description' => 'Glace artisanale.', 'price' => 1000, 'category' => 'desserts', 'emoji' => '🍨', 'popular' => false],
            ['name' => 'Fondant chocolat', 'description' => 'Fondant chocolat coulant.', 'price' => 1500, 'category' => 'desserts', 'emoji' => '🍫', 'popular' => false],
            ['name' => 'Tapas assortis', 'description' => 'Assortiment à partager.', 'price' => 2500, 'category' => 'tapas', 'emoji' => '🫒', 'popular' => false],
            ['name' => 'Thiéboudienne', 'description' => 'Plat local sénégalais.', 'price' => 2500, 'category' => 'repas', 'emoji' => '🍚', 'popular' => true],
        ],
        'users' => [
            ['name' => 'Super Admin', 'email' => app_env('ADMIN_EMAIL', 'admin@chezketchup.com'), 'password' => app_env('ADMIN_PASSWORD', 'ChangeMe123!'), 'role' => 'super_admin'],
            ['name' => 'Gérant', 'email' => 'gerant@chezketchup.com', 'password' => app_env('ADMIN_PASSWORD', 'ChangeMe123!'), 'role' => 'manager'],
            ['name' => 'Caisse', 'email' => 'caisse@chezketchup.com', 'password' => app_env('ADMIN_PASSWORD', 'ChangeMe123!'), 'role' => 'cashier'],
        ],
    ];
}

function app_seed_database(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0) {
        app_seed_delivery_staff($pdo);
        return;
    }

    $seed = app_seed_payload();
    $pdo->beginTransaction();

    $categoryStmt = $pdo->prepare('INSERT INTO categories (id, name, sort_order) VALUES (:id, :name, :sort_order)');
    foreach ($seed['categories'] as $category) {
        $categoryStmt->execute(['id' => $category['id'], 'name' => $category['name'], 'sort_order' => $category['sort']]);
    }

    $menuStmt = $pdo->prepare(
        'INSERT INTO menu_items (category_id, name, description, price, max_price, emoji, available, popular, daily_special, sort_order)
         VALUES (:category_id, :name, :description, :price, :max_price, :emoji, 1, :popular, 0, :sort_order)'
    );
    foreach ($seed['menu'] as $index => $item) {
        $menuStmt->execute([
            'category_id' => $item['category'],
            'name' => $item['name'],
            'description' => $item['description'],
            'price' => $item['price'],
            'max_price' => $item['max_price'] ?? null,
            'emoji' => $item['emoji'],
            'popular' => $item['popular'] ? 1 : 0,
            'sort_order' => ($index + 1) * 10,
        ]);
    }

    $userStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)');
    foreach ($seed['users'] as $user) {
        $userStmt->execute([
            'name' => $user['name'],
            'email' => $user['email'],
            'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
            'role' => $user['role'],
        ]);
    }

    $customerStmt = $pdo->prepare('INSERT INTO customers (name, phone, default_address) VALUES (:name, :phone, :address)');
    $customerStmt->execute(['name' => 'Mamadou Diallo', 'phone' => '+221 77 123 45 67', 'address' => 'Yoff, Dakar']);
    $customerId = (int) $pdo->lastInsertId();

    $orderStmt = $pdo->prepare(
        'INSERT INTO orders (number, customer_id, customer_name, phone, address, fulfillment, status, total)
         VALUES (:number, :customer_id, :customer_name, :phone, :address, :fulfillment, :status, :total)'
    );
    $orderStmt->execute([
        'number' => 'CK-' . date('Y') . '-0001',
        'customer_id' => $customerId,
        'customer_name' => 'Mamadou Diallo',
        'phone' => '+221 77 123 45 67',
        'address' => 'Yoff, Dakar',
        'fulfillment' => 'delivery',
        'status' => 'prep',
        'total' => 8000,
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$orderId, 8, 'Tacos Ketchup', 2, 4000, 8000]);
    $pdo->prepare('INSERT INTO payments (order_id, method, amount, status) VALUES (?, ?, ?, ?)')
        ->execute([$orderId, 'cash', 8000, 'pending']);
    app_seed_delivery_staff($pdo);

    $pdo->commit();
}

function app_seed_delivery_staff(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM delivery_staff')->fetchColumn() > 0) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO delivery_staff (name, phone, access_code_hash, status) VALUES (?, ?, ?, ?)');
    foreach ([
        ['Moussa Diop', '+221 77 400 10 10', 'available'],
        ['Cheikh Fall', '+221 78 401 20 20', 'available'],
        ['Abdou Sarr', '+221 76 402 30 30', 'absent'],
    ] as $driver) {
        $stmt->execute([$driver[0], $driver[1], password_hash(app_env('DELIVERY_DEFAULT_CODE', '1234'), PASSWORD_DEFAULT), $driver[2]]);
<<<<<<< HEAD
=======
    }
}

function app_seed_operations(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM business_hours')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO business_hours (weekday, open_time, close_time, active) VALUES (?, ?, ?, 1)');
        for ($day = 1; $day <= 7; $day++) {
            $stmt->execute([$day, '08:00:00', '23:00:00']);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM delivery_zones')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO delivery_zones (name, fee, minimum_order, estimated_minutes, active, sort_order) VALUES (?, ?, ?, ?, 1, ?)');
        foreach ([['Yoff / Virage', 500, 2000, 30], ['Ngor / Almadies', 1000, 3000, 40], ['Ouakam / Mermoz', 1500, 4000, 50], ['Plateau / Medina', 2000, 5000, 60]] as $index => $zone) {
            $stmt->execute([$zone[0], $zone[1], $zone[2], $zone[3], ($index + 1) * 10]);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM promo_codes')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO promo_codes (code, label, type, value, minimum_order, active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute(['KETCHUP10', 'Bienvenue Chez Ketchup', 'percent', 10, 5000]);
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ([
        'restaurant_name' => 'Chez Ketchup',
        'restaurant_phone' => '+221 33 856 62 53',
        'restaurant_whatsapp' => '+221 78 828 98 79',
        'restaurant_address' => '19 Cite Alia Diene, Yoff, Dakar',
        'social_instagram' => app_env('SOCIAL_INSTAGRAM_URL', 'https://www.instagram.com/boucher_ketchup/'),
        'social_tiktok' => app_env('SOCIAL_TIKTOK_URL', ''),
        'social_facebook' => app_env('SOCIAL_FACEBOOK_URL', ''),
        'social_youtube' => app_env('SOCIAL_YOUTUBE_URL', ''),
        'orders_paused' => '0',
        'pickup_eta_minutes' => '25',
    ] as $key => $value) {
        $stmt->execute([$key, $value]);
>>>>>>> 5f92b50 (mise a jour)
    }
}

function app_sessions(): array
{
    if (!file_exists(SESSION_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(SESSION_FILE), true);
    if (!is_array($decoded)) {
        return [];
    }

    $sessions = app_pruned_sessions($decoded);
    if (count($sessions) !== count($decoded)) {
        app_save_sessions($sessions);
    }

    return $sessions;
}

function app_session_ttl_seconds(): int
{
    return max(1, (int) app_env('SESSION_TTL_HOURS', '12')) * 3600;
}

function app_pruned_sessions(array $sessions): array
{
    $now = time();
    return array_filter($sessions, function (array $session) use ($now): bool {
        $expiresAt = strtotime((string) ($session['expires_at'] ?? ''));
        if ($expiresAt > 0) {
            return $expiresAt > $now;
        }

        $createdAt = strtotime((string) ($session['created_at'] ?? ''));
        return $createdAt > 0 && ($createdAt + app_session_ttl_seconds()) > $now;
    });
}

function app_save_sessions(array $sessions): void
{
    if (!is_dir(dirname(SESSION_FILE))) {
        mkdir(dirname(SESSION_FILE), 0775, true);
    }

    file_put_contents(SESSION_FILE, json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function app_rate_limits(): array
{
    if (!file_exists(RATE_LIMIT_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(RATE_LIMIT_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function app_save_rate_limits(array $limits): void
{
    if (!is_dir(dirname(RATE_LIMIT_FILE))) {
        mkdir(dirname(RATE_LIMIT_FILE), 0775, true);
    }

    file_put_contents(RATE_LIMIT_FILE, json_encode($limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function app_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'local'));
}

function app_escape_like(string $value): string
{
    return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
}

function app_rate_limit_key(string $scope, string $identifier): string
{
    return hash('sha256', $scope . '|' . strtolower(trim($identifier)) . '|' . app_client_ip());
}

function app_check_rate_limit(string $scope, string $identifier): void
{
    $maxAttempts = max(1, (int) app_env('AUTH_MAX_ATTEMPTS', '5'));
    $windowSeconds = max(60, (int) app_env('AUTH_LOCK_MINUTES', '15') * 60);
    $key = app_rate_limit_key($scope, $identifier);
    $limits = app_rate_limits();
    $entry = $limits[$key] ?? null;

    if (!is_array($entry)) {
        return;
    }

    $firstAt = (int) ($entry['first_at'] ?? 0);
    $attempts = (int) ($entry['attempts'] ?? 0);
    if ($firstAt <= 0 || time() - $firstAt > $windowSeconds) {
        unset($limits[$key]);
        app_save_rate_limits($limits);
        return;
    }

    if ($attempts >= $maxAttempts) {
        $retryAfter = max(1, $windowSeconds - (time() - $firstAt));
        header('Retry-After: ' . $retryAfter);
        app_json_response(['ok' => false, 'message' => 'Trop de tentatives. Reessayez plus tard.'], 429);
    }
}

function app_record_rate_limit(string $scope, string $identifier, bool $success): void
{
    $windowSeconds = max(60, (int) app_env('AUTH_LOCK_MINUTES', '15') * 60);
    $key = app_rate_limit_key($scope, $identifier);
    $limits = app_rate_limits();

    if ($success) {
        unset($limits[$key]);
        app_save_rate_limits($limits);
        return;
    }

    $entry = $limits[$key] ?? ['attempts' => 0, 'first_at' => time()];
    if (!is_array($entry) || time() - (int) ($entry['first_at'] ?? 0) > $windowSeconds) {
        $entry = ['attempts' => 0, 'first_at' => time()];
    }
    $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
    $entry['last_at'] = time();
    $limits[$key] = $entry;

    app_save_rate_limits($limits);
}

function app_rate_limits(): array
{
    if (!file_exists(RATE_LIMIT_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(RATE_LIMIT_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function app_save_rate_limits(array $limits): void
{
    if (!is_dir(dirname(RATE_LIMIT_FILE))) {
        mkdir(dirname(RATE_LIMIT_FILE), 0775, true);
    }

    file_put_contents(RATE_LIMIT_FILE, json_encode($limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function app_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value !== '') {
            return trim(explode(',', $value)[0]);
        }
    }

    return 'local';
}

function app_rate_limit_key(string $scope, string $identifier): string
{
    return hash('sha256', $scope . '|' . strtolower(trim($identifier)) . '|' . app_client_ip());
}

function app_check_rate_limit(string $scope, string $identifier): void
{
    $maxAttempts = max(1, (int) app_env('AUTH_MAX_ATTEMPTS', '5'));
    $windowSeconds = max(60, (int) app_env('AUTH_LOCK_MINUTES', '15') * 60);
    $key = app_rate_limit_key($scope, $identifier);
    $limits = app_rate_limits();
    $entry = $limits[$key] ?? null;

    if (!is_array($entry)) {
        return;
    }

    $firstAt = (int) ($entry['first_at'] ?? 0);
    $attempts = (int) ($entry['attempts'] ?? 0);
    if ($firstAt <= 0 || time() - $firstAt > $windowSeconds) {
        unset($limits[$key]);
        app_save_rate_limits($limits);
        return;
    }

    if ($attempts >= $maxAttempts) {
        $retryAfter = max(1, $windowSeconds - (time() - $firstAt));
        header('Retry-After: ' . $retryAfter);
        app_json_response(['ok' => false, 'message' => 'Trop de tentatives. Reessayez plus tard.'], 429);
    }
}

function app_record_rate_limit(string $scope, string $identifier, bool $success): void
{
    $windowSeconds = max(60, (int) app_env('AUTH_LOCK_MINUTES', '15') * 60);
    $key = app_rate_limit_key($scope, $identifier);
    $limits = app_rate_limits();

    if ($success) {
        unset($limits[$key]);
        app_save_rate_limits($limits);
        return;
    }

    $entry = $limits[$key] ?? ['attempts' => 0, 'first_at' => time()];
    if (!is_array($entry) || time() - (int) ($entry['first_at'] ?? 0) > $windowSeconds) {
        $entry = ['attempts' => 0, 'first_at' => time()];
    }
    $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
    $entry['last_at'] = time();
    $limits[$key] = $entry;

    app_save_rate_limits($limits);
}

function app_authorization_header(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
        if (!empty($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (is_array($headers)) {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                return (string) $value;
            }
        }
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }
    }

    return '';
}

function app_require_user(): array
{
    $header = app_authorization_header();
    $token = preg_match('/Bearer\s+(.+)/i', $header, $matches) ? trim($matches[1]) : '';
    $sessions = app_sessions();

    if ($token === '' || !isset($sessions[$token])) {
        app_json_response(['ok' => false, 'message' => 'Authentification requise.'], 401);
    }

    return $sessions[$token];
}

function app_logout(): void
{
    $header = app_authorization_header();
    $token = preg_match('/Bearer\s+(.+)/i', $header, $matches) ? trim($matches[1]) : '';
    if ($token === '') {
        return;
    }

    $sessions = app_sessions();
    unset($sessions[$token]);
    app_save_sessions($sessions);
}

function app_require_role(array $user, array $roles): void
{
    if (!in_array($user['role'] ?? '', $roles, true)) {
        app_json_response(['ok' => false, 'message' => 'Accès refusé pour ce rôle.'], 403);
    }
}

function app_role_permissions(string $role): array
{
    return [
        'can_manage_users' => $role === 'super_admin',
        'can_manage_menu' => in_array($role, ['super_admin', 'manager'], true),
        'can_view_stats' => in_array($role, ['super_admin', 'manager'], true),
        'can_manage_payments' => in_array($role, ['super_admin', 'manager'], true),
        'can_manage_delivery' => in_array($role, ['super_admin', 'manager'], true),
        'can_manage_operations' => in_array($role, ['super_admin', 'manager'], true),
        'can_export' => in_array($role, ['super_admin', 'manager'], true),
        'can_use_pos' => in_array($role, ['super_admin', 'manager', 'cashier'], true),
    ];
}

function app_categories(): array
{
    return app_pdo()->query('SELECT id, name, sort_order AS sort FROM categories WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
}

function app_settings(): array
{
    $settings = [];
    foreach (app_pdo()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll() as $row) {
        $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
    return $settings;
}

function app_delivery_zones(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE active = 1' : '';
    $rows = app_pdo()->query("SELECT id, name, fee, minimum_order, estimated_minutes, active, sort_order FROM delivery_zones {$where} ORDER BY sort_order, name")->fetchAll();
    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['fee'] = (int) $row['fee'];
        $row['minimum_order'] = (int) $row['minimum_order'];
        $row['estimated_minutes'] = (int) $row['estimated_minutes'];
        $row['active'] = (bool) $row['active'];
        return $row;
    }, $rows);
}

function app_business_hours(): array
{
    return array_map(function (array $row): array {
        return [
            'weekday' => (int) $row['weekday'],
            'open_time' => substr((string) $row['open_time'], 0, 5),
            'close_time' => substr((string) $row['close_time'], 0, 5),
            'active' => (bool) $row['active'],
        ];
    }, app_pdo()->query('SELECT weekday, open_time, close_time, active FROM business_hours ORDER BY weekday')->fetchAll());
}

function app_is_open_now(): array
{
    $settings = app_settings();
    if (($settings['orders_paused'] ?? '0') === '1') {
        return ['open' => false, 'message' => 'Les commandes en ligne sont temporairement en pause.'];
    }
    $stmt = app_pdo()->prepare('SELECT open_time, close_time, active FROM business_hours WHERE weekday = ?');
    $stmt->execute([(int) date('N')]);
    $row = $stmt->fetch();
    if (!$row || !(bool) $row['active']) {
        return ['open' => false, 'message' => 'Le restaurant est ferme aujourd hui.'];
    }
    $now = date('H:i:s');
    $open = (string) $row['open_time'];
    $close = (string) $row['close_time'];
    $isOpen = $open <= $close ? ($now >= $open && $now <= $close) : ($now >= $open || $now <= $close);
    return ['open' => $isOpen, 'message' => $isOpen ? 'Commandes ouvertes.' : 'Le restaurant est ferme pour le moment.'];
}

function app_menu_options(array $menuIds): array
{
    if ($menuIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
    $stmt = app_pdo()->prepare("SELECT id, menu_item_id, name, price_delta FROM menu_item_options WHERE active = 1 AND menu_item_id IN ({$placeholders}) ORDER BY sort_order, name");
    $stmt->execute($menuIds);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[(int) $row['menu_item_id']][] = [
            'id' => (int) $row['id'],
            'menu_item_id' => (int) $row['menu_item_id'],
            'name' => $row['name'],
            'price_delta' => (int) $row['price_delta'],
        ];
    }
    return $grouped;
}

function app_catalog(string $category = 'all'): array
{
    $params = [];
    $where = 'WHERE m.active = 1';
    if ($category !== 'all') {
        $where .= ' AND m.category_id = :category';
        $params['category'] = $category;
    }

    $stmt = app_pdo()->prepare(
        "SELECT m.id, m.name, m.description, m.price, m.max_price, m.category_id AS category,
                m.image_url, m.emoji, m.available, m.popular, m.daily_special,
                m.stock_enabled, m.stock_qty, m.low_stock_threshold, c.name AS category_name
         FROM menu_items m
         JOIN categories c ON c.id = m.category_id
         {$where}
         ORDER BY c.sort_order, m.sort_order, m.name"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['price'] = (int) $item['price'];
        $item['max_price'] = $item['max_price'] === null ? null : (int) $item['max_price'];
        $item['available'] = (bool) $item['available'];
        $item['popular'] = (bool) $item['popular'];
        $item['daily_special'] = (bool) $item['daily_special'];
        $item['stock_enabled'] = (bool) ($item['stock_enabled'] ?? false);
        $item['stock_qty'] = (int) ($item['stock_qty'] ?? 0);
        $item['low_stock_threshold'] = (int) ($item['low_stock_threshold'] ?? 3);
    }

    $options = app_menu_options(array_map(fn ($item) => (int) $item['id'], $items));
    foreach ($items as &$item) {
        $item['options'] = $options[$item['id']] ?? [];
    }

    return [
        'categories' => app_categories(),
        'items' => $items,
        'zones' => app_delivery_zones(),
        'hours' => app_business_hours(),
        'settings' => app_settings(),
        'open_state' => app_is_open_now(),
    ];
}

function app_order_items(array $orderIds): array
{
    if ($orderIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = app_pdo()->prepare("SELECT order_id, menu_item_id, name, options_text, quantity, unit_price FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id");
    $stmt->execute($orderIds);
    $grouped = [];

    foreach ($stmt->fetchAll() as $row) {
        $grouped[(int) $row['order_id']][] = [
            'menu_id' => $row['menu_item_id'] === null ? null : (int) $row['menu_item_id'],
            'name' => $row['name'],
            'options_text' => $row['options_text'] ?? '',
            'qty' => (int) $row['quantity'],
            'unit_price' => (int) $row['unit_price'],
        ];
    }

    return $grouped;
}

function app_orders(string $query = '', int $limit = 20): array
{
    $sql = 'SELECT o.*, p.id AS payment_id, p.method AS payment_method, p.status AS payment_status,
                   p.reference AS payment_reference, p.provider AS payment_provider, p.checkout_url AS payment_checkout_url,
                   d.name AS delivery_name, d.phone AS delivery_phone, d.status AS delivery_staff_status,
                   z.name AS delivery_zone_name, z.estimated_minutes AS delivery_estimated_minutes
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            LEFT JOIN delivery_staff d ON d.id = o.delivery_staff_id
            LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id';
    $params = [];

    if ($query !== '') {
        $sql .= ' WHERE UPPER(o.number) LIKE :query OR REPLACE(o.phone, " ", "") LIKE :phone';
        $params['query'] = '%' . app_escape_like(strtoupper($query)) . '%';
        $params['phone'] = '%' . app_escape_like(str_replace(' ', '', $query)) . '%';
    }

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . max(1, $limit);
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    $items = app_order_items(array_map(fn ($order) => (int) $order['id'], $orders));

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['subtotal'] = (int) ($order['subtotal'] ?? 0);
        $order['delivery_fee'] = (int) ($order['delivery_fee'] ?? 0);
        $order['discount_total'] = (int) ($order['discount_total'] ?? 0);
        $order['total'] = (int) $order['total'];
        $order['payment_id'] = $order['payment_id'] === null ? null : (int) $order['payment_id'];
        $order['delivery_staff_id'] = $order['delivery_staff_id'] === null ? null : (int) $order['delivery_staff_id'];
        $order['delivery_zone_id'] = $order['delivery_zone_id'] === null ? null : (int) $order['delivery_zone_id'];
        $order['delivery_estimated_minutes'] = $order['delivery_estimated_minutes'] === null ? null : (int) $order['delivery_estimated_minutes'];
        $order['items'] = $items[$order['id']] ?? [];
    }

    return $orders;
}

function app_normalized_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 9) {
        $digits = '221' . $digits;
    }
    return $digits;
}

function app_delivery_dashboard(array $body): array
{
    $phone = trim((string) ($body['phone'] ?? ''));
    $code = trim((string) ($body['code'] ?? ''));
    $normalized = app_normalized_phone($phone);
    if ($normalized === '' || $code === '') {
        app_json_response(['ok' => false, 'message' => 'Telephone et code livreur requis.'], 422);
    }
    app_check_rate_limit('driver', $normalized);

    $pdo = app_pdo();
    $rows = $pdo->query('SELECT id, name, phone, access_code_hash, status, active FROM delivery_staff WHERE active = 1')->fetchAll();
    $driverRow = null;
    foreach ($rows as $entry) {
        if (app_normalized_phone((string) $entry['phone']) === $normalized) {
            $driverRow = $entry;
            break;
        }
    }

    if ($driverRow === null || !password_verify($code, (string) ($driverRow['access_code_hash'] ?? ''))) {
        app_record_rate_limit('driver', $normalized, false);
        app_json_response(['ok' => false, 'message' => 'Acces livreur invalide.'], 403);
    }
    if (app_is_production() && hash_equals(app_env('DELIVERY_DEFAULT_CODE', '1234'), $code)) {
        app_record_rate_limit('driver', $normalized, false);
        app_json_response(['ok' => false, 'message' => 'Code livreur de demonstration refuse en production.'], 403);
    }
    app_record_rate_limit('driver', $normalized, true);

    $driver = app_delivery_staff_one((int) $driverRow['id']);
    $stmt = $pdo->prepare(
        "SELECT o.*, p.method AS payment_method, p.status AS payment_status, p.reference AS payment_reference,
                d.name AS delivery_name, d.phone AS delivery_phone, d.status AS delivery_staff_status
         FROM orders o
         LEFT JOIN payments p ON p.order_id = o.id
         LEFT JOIN delivery_staff d ON d.id = o.delivery_staff_id
         WHERE o.delivery_staff_id = ?
           AND o.status IN ('prep', 'delivery')
         ORDER BY o.created_at ASC, o.id ASC"
    );
    $stmt->execute([(int) $driver['id']]);
    $orders = $stmt->fetchAll();
    $items = app_order_items(array_map(fn ($order) => (int) $order['id'], $orders));

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['total'] = (int) $order['total'];
        $order['delivery_staff_id'] = (int) $order['delivery_staff_id'];
        $order['items'] = $items[$order['id']] ?? [];
    }

    return [
        'driver' => $driver,
        'orders' => $orders,
        'summary' => [
            'assigned' => count($orders),
            'delivery' => count(array_filter($orders, fn ($order) => $order['status'] === 'delivery')),
            'prep' => count(array_filter($orders, fn ($order) => $order['status'] === 'prep')),
        ],
    ];
}

function app_update_delivery_order_status(array $body): array
{
    $phone = trim((string) ($body['phone'] ?? ''));
    $code = trim((string) ($body['code'] ?? ''));
    $orderId = (int) ($body['order_id'] ?? 0);
    $status = (string) ($body['status'] ?? '');

    if ($orderId <= 0 || !in_array($status, ['delivery', 'done'], true)) {
        app_json_response(['ok' => false, 'message' => 'Mise a jour livreur invalide.'], 422);
    }

    $dashboard = app_delivery_dashboard(['phone' => $phone, 'code' => $code]);
    $allowedOrderIds = array_map(fn ($order) => (int) $order['id'], $dashboard['orders']);
    if (!in_array($orderId, $allowedOrderIds, true)) {
        app_json_response(['ok' => false, 'message' => 'Commande non assignee a ce livreur.'], 403);
    }

    $pdo = app_pdo();
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND delivery_staff_id = ?');
    $stmt->execute([$status, $orderId, (int) $dashboard['driver']['id']]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, note) VALUES (?, ?, NULL, ?)')
        ->execute([$orderId, $status, 'Mise a jour livreur']);

    if ($status === 'done') {
        $active = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_staff_id = ? AND status = 'delivery'");
        $active->execute([(int) $dashboard['driver']['id']]);
        if ((int) $active->fetchColumn() === 0) {
            $pdo->prepare("UPDATE delivery_staff SET status = 'available' WHERE id = ? AND status = 'busy'")
                ->execute([(int) $dashboard['driver']['id']]);
        }
    } else {
        $pdo->prepare("UPDATE delivery_staff SET status = 'busy' WHERE id = ? AND status = 'available'")
            ->execute([(int) $dashboard['driver']['id']]);
    }

    app_queue_notification($orderId, $status, 'whatsapp');

    return app_delivery_dashboard(['phone' => $phone, 'code' => $code]);
}

function app_new_order_number(): string
{
    $prefix = 'CK-' . date('Y') . '-';
    $stmt = app_pdo()->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(number, 9) AS UNSIGNED)), 0) FROM orders WHERE number LIKE ?');
    $stmt->execute([$prefix . '%']);
    $next = (int) $stmt->fetchColumn() + 1;

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function app_order_option_summary(int $menuItemId, array $optionIds): array
{
    $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
    if ($optionIds === []) {
        return ['names' => [], 'price_delta' => 0];
    }
    $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
    $stmt = app_pdo()->prepare("SELECT id, name, price_delta FROM menu_item_options WHERE active = 1 AND menu_item_id = ? AND id IN ({$placeholders})");
    $stmt->execute([$menuItemId, ...$optionIds]);
    $rows = $stmt->fetchAll();
    if (count($rows) !== count($optionIds)) {
        app_json_response(['ok' => false, 'message' => 'Une option du panier est indisponible.'], 422);
    }
    return [
        'names' => array_map(fn ($row) => (string) $row['name'], $rows),
        'price_delta' => array_sum(array_map(fn ($row) => (int) $row['price_delta'], $rows)),
    ];
}

function app_active_promo(string $code, int $subtotal): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = app_pdo()->prepare('SELECT code, type, value, minimum_order FROM promo_codes WHERE UPPER(code) = ? AND active = 1 LIMIT 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) {
        app_json_response(['ok' => false, 'message' => 'Code promo invalide.'], 422);
    }
    if ($subtotal < (int) $promo['minimum_order']) {
        app_json_response(['ok' => false, 'message' => 'Minimum de commande non atteint pour ce code promo.'], 422);
    }
    $discount = $promo['type'] === 'percent'
        ? (int) floor($subtotal * min(100, (int) $promo['value']) / 100)
        : min($subtotal, (int) $promo['value']);
    return ['code' => $promo['code'], 'discount' => max(0, $discount)];
}

function app_validate_scheduled_at(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false || $timestamp < time() + 30 * 60 || $timestamp > time() + 7 * 24 * 3600) {
        app_json_response(['ok' => false, 'message' => 'Choisissez un créneau entre 30 minutes et 7 jours.'], 422);
    }

    $weekday = (int) date('N', $timestamp);
    $stmt = app_pdo()->prepare('SELECT open_time, close_time, active FROM business_hours WHERE weekday = ?');
    $stmt->execute([$weekday]);
    $hours = $stmt->fetch();
    if (!$hours || !(bool) $hours['active']) {
        app_json_response(['ok' => false, 'message' => 'Le restaurant est fermé sur ce créneau.'], 422);
    }

    $time = date('H:i:s', $timestamp);
    $open = (string) $hours['open_time'];
    $close = (string) $hours['close_time'];
    $inside = $open <= $close ? ($time >= $open && $time <= $close) : ($time >= $open || $time <= $close);
    if (!$inside) {
        app_json_response(['ok' => false, 'message' => 'Le créneau choisi est hors horaires.'], 422);
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function app_create_order(array $body, ?array $user = null): array
{
    $items = $body['items'] ?? [];
    $customer = $body['customer'] ?? [];

    if (!is_array($items) || count($items) === 0) {
        app_json_response(['ok' => false, 'message' => 'Le panier est vide.'], 422);
    }
    if (count($items) > 50) {
        app_json_response(['ok' => false, 'message' => 'Maximum 50 articles par commande.'], 422);
    }

    foreach (['name', 'phone'] as $field) {
        if (trim((string) ($customer[$field] ?? '')) === '') {
            app_json_response(['ok' => false, 'message' => 'Informations client incomplètes.'], 422);
        }
    }

    $fulfillment = (string) ($body['fulfillment'] ?? 'delivery');
    if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
        app_json_response(['ok' => false, 'message' => 'Mode de retrait invalide.'], 422);
    }
<<<<<<< HEAD
=======
    $scheduledAt = app_validate_scheduled_at(isset($body['scheduled_at']) ? (string) $body['scheduled_at'] : null);
    $openState = app_is_open_now();
    if ($scheduledAt === null && !$openState['open']) {
        app_json_response(['ok' => false, 'message' => $openState['message']], 422);
    }
>>>>>>> 5f92b50 (mise a jour)
    if ($fulfillment === 'delivery' && trim((string) ($customer['address'] ?? '')) === '') {
        app_json_response(['ok' => false, 'message' => 'Adresse obligatoire pour une livraison.'], 422);
    }

    $pdo = app_pdo();
    $ids = array_map(fn ($item) => (int) ($item['id'] ?? 0), $items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, available, stock_enabled, stock_qty FROM menu_items WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $menu[(int) $row['id']] = $row;
    }

    $orderItems = [];
    $requestedStock = [];
    $subtotal = 0;
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $qty = min($qty, 99);
        if (!isset($menu[$id]) || !(bool) $menu[$id]['available']) {
            app_json_response(['ok' => false, 'message' => 'Un plat du panier est indisponible.'], 422);
        }
        if ((bool) ($menu[$id]['stock_enabled'] ?? false)) {
            $requestedStock[$id] = ($requestedStock[$id] ?? 0) + $qty;
        }
        $optionSummary = app_order_option_summary($id, is_array($item['options'] ?? null) ? $item['options'] : []);
        $unit = max(0, (int) $menu[$id]['price'] + (int) $optionSummary['price_delta']);
        $orderItems[] = ['menu_id' => $id, 'name' => $menu[$id]['name'], 'options_text' => implode(', ', $optionSummary['names']), 'qty' => $qty, 'unit_price' => $unit];
        $subtotal += $unit * $qty;
    }
    foreach ($requestedStock as $id => $qty) {
        if ((int) $menu[$id]['stock_qty'] < $qty) {
            app_json_response(['ok' => false, 'message' => $menu[$id]['name'] . ' est en stock insuffisant.'], 422);
        }
    }

    $deliveryZoneId = null;
    $deliveryFee = 0;
    if ($fulfillment === 'delivery') {
        $deliveryZoneId = (int) ($body['delivery_zone_id'] ?? 0);
        $zoneStmt = $pdo->prepare('SELECT id, fee, minimum_order FROM delivery_zones WHERE id = ? AND active = 1');
        $zoneStmt->execute([$deliveryZoneId]);
        $zone = $zoneStmt->fetch();
        if (!$zone) {
            app_json_response(['ok' => false, 'message' => 'Zone de livraison obligatoire.'], 422);
        }
        if ($subtotal < (int) $zone['minimum_order']) {
            app_json_response(['ok' => false, 'message' => 'Minimum de commande non atteint pour cette zone.'], 422);
        }
        $deliveryFee = (int) $zone['fee'];
    }
    $promo = app_active_promo((string) ($body['promo_code'] ?? ''), $subtotal);
    $discountTotal = (int) ($promo['discount'] ?? 0);
    $total = max(0, $subtotal + $deliveryFee - $discountTotal);

    $name = trim((string) $customer['name']);
    $phone = trim((string) $customer['phone']);
    $address = trim((string) ($customer['address'] ?? ''));
    if ($address === '') {
        $address = 'Retrait sur place';
    }

    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO customers (name, phone, default_address) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), default_address = VALUES(default_address)'
    )->execute([$name, $phone, $address]);
    $customerId = (int) $pdo->query('SELECT id FROM customers WHERE phone = ' . $pdo->quote($phone))->fetchColumn();

    $number = app_new_order_number();
    $isPosOrder = $user !== null && (string) ($body['source'] ?? '') === 'pos';
    $status = 'pending';
    if ($isPosOrder) {
        $requestedStatus = (string) ($body['status'] ?? 'prep');
        $status = in_array($requestedStatus, ['pending', 'prep', 'done'], true) ? $requestedStatus : 'prep';
    }
    $createdBy = $user !== null && isset($user['user_id']) ? (int) $user['user_id'] : null;

    $pdo->prepare(
        'INSERT INTO orders (number, customer_id, customer_name, phone, address, note, fulfillment, delivery_zone_id, scheduled_at, status, subtotal, delivery_fee, discount_total, promo_code, total, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $number,
        $customerId,
        $name,
        $phone,
        $address,
        trim((string) ($customer['note'] ?? '')),
        $fulfillment,
<<<<<<< HEAD
        'pending',
=======
        $deliveryZoneId,
        $scheduledAt,
        $status,
        $subtotal,
        $deliveryFee,
        $discountTotal,
        $promo['code'] ?? null,
>>>>>>> 5f92b50 (mise a jour)
        $total,
        $createdBy,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, options_text, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($orderItems as $item) {
        $itemStmt->execute([$orderId, $item['menu_id'], $item['name'], $item['options_text'], $item['qty'], $item['unit_price'], $item['unit_price'] * $item['qty']]);
        if ((bool) ($menu[$item['menu_id']]['stock_enabled'] ?? false)) {
            $pdo->prepare('UPDATE menu_items SET available = IF(stock_qty <= ?, 0, available), stock_qty = GREATEST(stock_qty - ?, 0) WHERE id = ?')
                ->execute([$item['qty'], $item['qty'], $item['menu_id']]);
        }
    }

    $paymentMethod = (string) ($body['payment_method'] ?? 'cash');
    $payment = app_prepare_payment($paymentMethod, $orderId, $number, $total, [
        'name' => $name,
        'phone' => $phone,
    ]);
    $paymentStatus = !empty($body['paid']) ? 'paid' : $payment['status'];
    $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;

    $pdo->prepare('INSERT INTO payments (order_id, method, provider, reference, checkout_url, amount, status, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $orderId,
            $paymentMethod,
            $payment['provider'],
            $payment['reference'],
            $payment['checkout_url'],
            $total,
            $paymentStatus,
            $paidAt,
        ]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)')
        ->execute([$orderId, $status, $isPosOrder ? 'Commande saisie en caisse' : 'Commande reçue']);
    $pdo->commit();

    $order = app_orders($number, 1)[0];
    if (!$isPosOrder) {
        app_queue_notification($orderId, 'confirmation', 'whatsapp');
    }

    return $order;
}

function app_create_contact_message(array $body): array
{
    $name = trim((string) ($body['name'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));

    if ($name === '' || $phone === '' || $message === '') {
        app_json_response(['ok' => false, 'message' => 'Nom, telephone et message sont obligatoires.'], 422);
    }

    $pdo = app_pdo();
    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, phone, message) VALUES (?, ?, ?)');
    $stmt->execute([$name, $phone, $message]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $name,
        'phone' => $phone,
        'message' => $message,
    ];
}

function app_base_url(): string
{
    return rtrim(app_env('APP_URL', 'http://localhost/boucherketchup'), '/');
}

function app_prepare_payment(string $method, int $orderId, string $number, int $amount, array $customer): array
{
    if ($method === 'cash') {
        return [
            'reference' => 'CASH-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
            'provider' => 'cash',
            'checkout_url' => null,
            'status' => 'pending',
        ];
    }

    if (!in_array($method, ['orange_money', 'wave'], true)) {
        app_json_response(['ok' => false, 'message' => 'Choisissez Cash, Orange Money ou Wave pour le paiement.'], 422);
    }

    $reference = strtoupper($method) . '-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);

    return [
        'reference' => $reference,
        'provider' => $method,
        'checkout_url' => null,
        'status' => 'pending',
    ];
}

function app_login(array $body): array
{
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    app_check_rate_limit('admin', $email);
    $stmt = app_pdo()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE LOWER(email) = ? AND active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        app_record_rate_limit('admin', $email, false);
        app_json_response(['ok' => false, 'message' => 'Identifiants invalides.'], 401);
    }
    if (app_is_production() && password_verify('ChangeMe123!', $user['password_hash'])) {
        app_record_rate_limit('admin', $email, false);
        app_json_response(['ok' => false, 'message' => 'Mot de passe de demonstration refuse en production.'], 403);
    }
    app_record_rate_limit('admin', $email, true);

    $token = bin2hex(random_bytes(24));
    $createdAt = date('c');
    $expiresAt = date('c', time() + app_session_ttl_seconds());
    $sessions = app_sessions();
    $sessions[$token] = [
        'user_id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'permissions' => app_role_permissions($user['role']),
        'created_at' => $createdAt,
        'expires_at' => $expiresAt,
    ];
    app_save_sessions($sessions);

    return ['token' => $token, 'user' => $sessions[$token]];
}

function app_admin_summary(?array $user = null): array
{
    $pdo = app_pdo();
    $today = date('Y-m-d');
    $summary = [
        'revenue_today' => (int) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = '{$today}'")->fetchColumn(),
        'orders_today' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '{$today}'")->fetchColumn(),
        'pending' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
        'delivery' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivery'")->fetchColumn(),
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'payments_pending' => (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
        'payments_paid_today' => (int) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND DATE(paid_at) = '{$today}'")->fetchColumn(),
    ];

<<<<<<< HEAD
    $response = ['summary' => $summary, 'orders' => app_orders('', 25), 'delivery_staff' => app_delivery_staff()];
=======
    $response = [
        'summary' => $summary,
        'orders' => app_orders('', 25),
        'delivery_staff' => app_delivery_staff(),
        'operations' => app_operations_config(),
        'kitchen_orders' => app_kitchen_orders(),
    ];
>>>>>>> 5f92b50 (mise a jour)
    if ($user !== null && !empty(app_role_permissions((string) ($user['role'] ?? ''))['can_view_stats'])) {
        $response['checks'] = app_system_checks();
    }

    return $response;
}

<<<<<<< HEAD
=======
function app_operations_config(): array
{
    return [
        'settings' => app_settings(),
        'hours' => app_business_hours(),
        'zones' => app_delivery_zones(false),
        'promos' => app_promo_codes(),
    ];
}

function app_save_settings(array $body): array
{
    $stmt = app_pdo()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach (['restaurant_name', 'restaurant_phone', 'restaurant_whatsapp', 'restaurant_address', 'pickup_eta_minutes', 'social_instagram', 'social_tiktok', 'social_facebook', 'social_youtube'] as $key) {
        if (array_key_exists($key, $body)) {
            $stmt->execute([$key, trim((string) $body[$key])]);
        }
    }
    if (array_key_exists('orders_paused', $body)) {
        $stmt->execute(['orders_paused', !empty($body['orders_paused']) ? '1' : '0']);
    }
    return app_operations_config();
}

function app_save_business_hours(array $hours): array
{
    $stmt = app_pdo()->prepare('INSERT INTO business_hours (weekday, open_time, close_time, active) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE open_time = VALUES(open_time), close_time = VALUES(close_time), active = VALUES(active)');
    foreach ($hours as $row) {
        $weekday = (int) ($row['weekday'] ?? 0);
        $open = trim((string) ($row['open_time'] ?? '08:00'));
        $close = trim((string) ($row['close_time'] ?? '23:00'));
        if ($weekday < 1 || $weekday > 7 || !preg_match('/^\d{2}:\d{2}$/', $open) || !preg_match('/^\d{2}:\d{2}$/', $close)) {
            app_json_response(['ok' => false, 'message' => 'Horaire invalide.'], 422);
        }
        $stmt->execute([$weekday, $open . ':00', $close . ':00', !empty($row['active']) ? 1 : 0]);
    }
    return app_operations_config();
}

function app_save_delivery_zone(array $body): array
{
    $id = (int) ($body['id'] ?? 0);
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        app_json_response(['ok' => false, 'message' => 'Nom de zone obligatoire.'], 422);
    }
    $data = [$name, max(0, (int) ($body['fee'] ?? 0)), max(0, (int) ($body['minimum_order'] ?? 0)), max(5, (int) ($body['estimated_minutes'] ?? 45)), !empty($body['active']) ? 1 : 0];
    if ($id > 0) {
        app_pdo()->prepare('UPDATE delivery_zones SET name = ?, fee = ?, minimum_order = ?, estimated_minutes = ?, active = ? WHERE id = ?')->execute([...$data, $id]);
    } else {
        $sort = (int) app_pdo()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM delivery_zones')->fetchColumn();
        app_pdo()->prepare('INSERT INTO delivery_zones (name, fee, minimum_order, estimated_minutes, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)')->execute([...$data, $sort]);
    }
    return app_operations_config();
}

function app_promo_codes(): array
{
    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['value'] = (int) $row['value'];
        $row['minimum_order'] = (int) $row['minimum_order'];
        $row['active'] = (bool) $row['active'];
        return $row;
    }, app_pdo()->query('SELECT id, code, label, type, value, minimum_order, active FROM promo_codes ORDER BY active DESC, code')->fetchAll());
}

function app_save_promo_code(array $body): array
{
    $id = (int) ($body['id'] ?? 0);
    $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($body['code'] ?? ''))));
    $label = trim((string) ($body['label'] ?? ''));
    $type = (string) ($body['type'] ?? 'fixed');
    $value = max(1, (int) ($body['value'] ?? 0));
    if ($code === '' || $label === '' || !in_array($type, ['fixed', 'percent'], true)) {
        app_json_response(['ok' => false, 'message' => 'Code promo invalide.'], 422);
    }
    $data = [$code, $label, $type, $value, max(0, (int) ($body['minimum_order'] ?? 0)), !empty($body['active']) ? 1 : 0];
    try {
        if ($id > 0) {
            app_pdo()->prepare('UPDATE promo_codes SET code = ?, label = ?, type = ?, value = ?, minimum_order = ?, active = ? WHERE id = ?')->execute([...$data, $id]);
        } else {
            app_pdo()->prepare('INSERT INTO promo_codes (code, label, type, value, minimum_order, active) VALUES (?, ?, ?, ?, ?, ?)')->execute($data);
        }
    } catch (PDOException $error) {
        app_json_response(['ok' => false, 'message' => 'Ce code promo existe deja.'], 422);
    }
    return app_operations_config();
}

function app_save_menu_options(int $menuItemId, array $options): array
{
    if ($menuItemId <= 0) {
        app_json_response(['ok' => false, 'message' => 'Plat invalide.'], 422);
    }
    $pdo = app_pdo();
    $pdo->prepare('DELETE FROM menu_item_options WHERE menu_item_id = ?')->execute([$menuItemId]);
    $stmt = $pdo->prepare('INSERT INTO menu_item_options (menu_item_id, name, price_delta, active, sort_order) VALUES (?, ?, ?, 1, ?)');
    foreach ($options as $index => $option) {
        $name = trim((string) ($option['name'] ?? ''));
        if ($name !== '') {
            $stmt->execute([$menuItemId, $name, (int) ($option['price_delta'] ?? 0), ($index + 1) * 10]);
        }
    }
    return app_menu_item($menuItemId);
}

function app_kitchen_orders(): array
{
    return array_values(array_filter(app_orders('', 50), fn ($order) => in_array($order['status'], ['pending', 'prep'], true)));
}

function app_create_review(array $body): array
{
    $stmt = app_pdo()->prepare('SELECT id FROM orders WHERE number = ? AND REPLACE(phone, " ", "") LIKE ? LIMIT 1');
    $stmt->execute([trim((string) ($body['order_number'] ?? '')), '%' . app_escape_like(str_replace(' ', '', (string) ($body['phone'] ?? ''))) . '%']);
    $orderId = (int) ($stmt->fetchColumn() ?: 0);
    $rating = (int) ($body['rating'] ?? 0);
    if ($orderId <= 0 || $rating < 1 || $rating > 5) {
        app_json_response(['ok' => false, 'message' => 'Avis invalide ou commande introuvable.'], 422);
    }
    app_pdo()->prepare('INSERT INTO customer_reviews (order_id, rating, comment) VALUES (?, ?, ?)')->execute([$orderId, $rating, trim((string) ($body['comment'] ?? ''))]);
    return ['rating' => $rating];
}

function app_reviews(): array
{
    $rows = app_pdo()->query('SELECT r.id, r.rating, r.comment, r.created_at, o.number, o.customer_name FROM customer_reviews r JOIN orders o ON o.id = r.order_id ORDER BY r.created_at DESC LIMIT 50')->fetchAll();
    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['rating'] = (int) $row['rating'];
        return $row;
    }, $rows);
}

>>>>>>> 5f92b50 (mise a jour)
function app_system_checks(): array
{
    $checks = [];
    $provider = app_notification_provider('whatsapp');
    $hasDefaultUserPasswords = app_has_default_user_passwords();
    $providerReady = match ($provider) {
        'webhook' => app_env('NOTIFICATION_WEBHOOK_URL') !== '',
        'twilio' => app_env('TWILIO_ACCOUNT_SID') !== '' && app_env('TWILIO_AUTH_TOKEN') !== '' && app_env('TWILIO_WHATSAPP_FROM') !== '',
        'meta' => app_env('META_WHATSAPP_PHONE_NUMBER_ID') !== '' && app_env('META_WHATSAPP_ACCESS_TOKEN') !== '',
        default => false,
    };

    $checks[] = [
        'key' => 'app_env',
        'label' => 'Mode application',
        'status' => app_env('APP_ENV', 'local') === 'production' ? 'ok' : 'warning',
        'message' => app_env('APP_ENV', 'local') === 'production' ? 'APP_ENV=production.' : 'APP_ENV est encore en mode local.',
    ];
    $checks[] = [
        'key' => 'notifications',
        'label' => 'Fournisseur notifications',
        'status' => $providerReady ? 'ok' : 'warning',
        'message' => $providerReady ? "Fournisseur {$provider} configure." : 'Notifications automatiques non configurees ou incompletes.',
    ];
    $checks[] = [
<<<<<<< HEAD
=======
        'key' => 'payment_callback_secret',
        'label' => 'Secret callback paiement',
        'status' => app_env('PAYMENT_CALLBACK_SECRET', '') !== '' ? 'ok' : 'warning',
        'message' => app_env('PAYMENT_CALLBACK_SECRET', '') !== ''
            ? 'Callback paiement protege par secret.'
            : 'PAYMENT_CALLBACK_SECRET est vide: a renseigner avant production.',
    ];
    $checks[] = [
>>>>>>> 5f92b50 (mise a jour)
        'key' => 'delivery_code',
        'label' => 'Code livreur par defaut',
        'status' => app_env('DELIVERY_DEFAULT_CODE', '1234') !== '1234' ? 'ok' : 'warning',
        'message' => app_env('DELIVERY_DEFAULT_CODE', '1234') !== '1234' ? 'Code livreur par defaut personnalise.' : 'DELIVERY_DEFAULT_CODE utilise encore 1234.',
    ];
    $checks[] = [
        'key' => 'default_passwords',
        'label' => 'Mots de passe demo',
        'status' => $hasDefaultUserPasswords ? 'warning' : 'ok',
        'message' => $hasDefaultUserPasswords
            ? (app_is_production() ? 'Bloquant: au moins un compte utilise encore ChangeMe123!.' : 'Au moins un compte utilise encore ChangeMe123!.')
            : 'Aucun mot de passe demo detecte.',
    ];
    $checks[] = [
        'key' => 'production_lock',
        'label' => 'Verrouillage production',
        'status' => app_is_production() ? 'ok' : 'warning',
        'message' => app_is_production()
            ? 'Les acces de demonstration sont refuses automatiquement.'
            : 'Le verrouillage strict sera actif quand APP_ENV=production.',
    ];
    $checks[] = [
        'key' => 'session_ttl',
        'label' => 'Expiration sessions',
        'status' => app_session_ttl_seconds() <= 12 * 3600 ? 'ok' : 'warning',
        'message' => 'Sessions admin valides pendant ' . (int) app_env('SESSION_TTL_HOURS', '12') . ' heure(s).',
    ];
    $checks[] = [
        'key' => 'rate_limit',
        'label' => 'Limitation connexions',
        'status' => (int) app_env('AUTH_MAX_ATTEMPTS', '5') <= 5 ? 'ok' : 'warning',
        'message' => 'Blocage apres ' . (int) app_env('AUTH_MAX_ATTEMPTS', '5') . ' echec(s) pendant ' . (int) app_env('AUTH_LOCK_MINUTES', '15') . ' minute(s).',
    ];
    $checks[] = [
        'key' => 'storage',
        'label' => 'Dossiers ecriture',
        'status' => app_writable_path(APP_ROOT . '/storage') && app_writable_path(UPLOAD_ROOT) ? 'ok' : 'warning',
        'message' => app_writable_path(APP_ROOT . '/storage') && app_writable_path(UPLOAD_ROOT) ? 'Storage et uploads accessibles.' : 'Verifier les droits de storage/ et uploads/menu.',
    ];

    return $checks;
}

function app_has_default_user_passwords(): bool
{
    $rows = app_pdo()->query('SELECT password_hash FROM users WHERE active = 1')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $hash) {
        if (password_verify('ChangeMe123!', (string) $hash)) {
            return true;
        }
    }
    return false;
}

function app_writable_path(string $path): bool
{
    return is_dir($path) ? is_writable($path) : is_writable(dirname($path));
}

function app_admin_users(): array
{
    $rows = app_pdo()->query('SELECT id, name, email, password_hash, role, active, created_at FROM users ORDER BY active DESC, role, name')->fetchAll();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['active'] = (bool) $row['active'];
        $row['uses_demo_password'] = password_verify('ChangeMe123!', (string) $row['password_hash']);
        unset($row['password_hash']);
        return $row;
    }, $rows);
}

function app_audit_log(?array $user, string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    $stmt = app_pdo()->prepare(
        'INSERT INTO audit_logs (user_id, user_name, action, entity, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        isset($user['user_id']) ? (int) $user['user_id'] : null,
        isset($user['name']) ? (string) $user['name'] : null,
        substr($action, 0, 80),
        substr($entity, 0, 80),
        $entityId,
        $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        app_client_ip(),
    ]);
}

function app_audit_logs(int $limit = 80): array
{
    $stmt = app_pdo()->prepare(
        'SELECT id, user_id, user_name, action, entity, entity_id, details, ip_address, created_at
         FROM audit_logs
         ORDER BY created_at DESC, id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = $row['user_id'] === null ? null : (int) $row['user_id'];
        $row['entity_id'] = $row['entity_id'] === null ? null : (int) $row['entity_id'];
        $decoded = json_decode((string) ($row['details'] ?? ''), true);
        $row['details'] = is_array($decoded) ? $decoded : [];
        return $row;
    }, $stmt->fetchAll());
}

function app_contact_messages(int $limit = 50): array
{
    $stmt = app_pdo()->prepare('SELECT id, name, phone, message, handled, created_at FROM contact_messages ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['handled'] = (bool) $row['handled'];
        return $row;
    }, $stmt->fetchAll());
}

function app_mark_contact_message(int $id, bool $handled): array
{
    if ($id <= 0) {
        app_json_response(['ok' => false, 'message' => 'Message invalide.'], 422);
    }

    $stmt = app_pdo()->prepare('UPDATE contact_messages SET handled = ? WHERE id = ?');
    $stmt->execute([$handled ? 1 : 0, $id]);

    if ($stmt->rowCount() === 0) {
        app_json_response(['ok' => false, 'message' => 'Message introuvable.'], 404);
    }

    return app_contact_messages();
}

function app_admin_customers(): array
{
    $rows = app_pdo()->query(
        "SELECT c.id, c.name, c.phone, c.default_address,
                COUNT(o.id) AS orders_count,
                COALESCE(SUM(o.total), 0) AS total_spent,
                MAX(o.created_at) AS last_order_at
         FROM customers c
         LEFT JOIN orders o ON o.customer_id = c.id
         GROUP BY c.id, c.name, c.phone, c.default_address
         ORDER BY last_order_at DESC, c.created_at DESC"
    )->fetchAll();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['orders_count'] = (int) $row['orders_count'];
        $row['total_spent'] = (int) $row['total_spent'];
        return $row;
    }, $rows);
}

function app_validate_user_payload(array $body, bool $creating = true): array
{
    $name = trim((string) ($body['name'] ?? ''));
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $role = (string) ($body['role'] ?? 'cashier');
    $password = (string) ($body['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['super_admin', 'manager', 'cashier'], true)) {
        app_json_response(['ok' => false, 'message' => 'Utilisateur invalide.'], 422);
    }

    if ($creating && strlen($password) < 8) {
        app_json_response(['ok' => false, 'message' => 'Mot de passe trop court. Minimum 8 caractères.'], 422);
    }

    return compact('name', 'email', 'role', 'password');
}

function app_create_user(array $body): array
{
    $data = app_validate_user_payload($body, true);
    $stmt = app_pdo()->prepare('INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], !empty($body['active']) ? 1 : 0]);
    } catch (PDOException $error) {
        app_json_response(['ok' => false, 'message' => 'Email déjà utilisé.'], 422);
    }

    return app_admin_users();
}

function app_update_user(int $id, array $body, int $currentUserId): array
{
    if ($id <= 0) {
        app_json_response(['ok' => false, 'message' => 'Utilisateur invalide.'], 422);
    }

    $data = app_validate_user_payload($body, false);
    $active = !empty($body['active']) ? 1 : 0;

    if ($id === $currentUserId && !$active) {
        app_json_response(['ok' => false, 'message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
    }

    if ($data['password'] !== '') {
        if (strlen($data['password']) < 8) {
            app_json_response(['ok' => false, 'message' => 'Mot de passe trop court. Minimum 8 caractères.'], 422);
        }
        $stmt = app_pdo()->prepare('UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, active = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $active, $id]);
    } else {
        $stmt = app_pdo()->prepare('UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?');
        $stmt->execute([$data['name'], $data['email'], $data['role'], $active, $id]);
    }

    return app_admin_users();
}

function app_delivery_staff(): array
{
    $stmt = app_pdo()->query(
        "SELECT d.id, d.name, d.phone, d.status, d.active,
                COUNT(CASE WHEN o.status = 'delivery' THEN 1 END) AS active_deliveries,
                COUNT(CASE WHEN DATE(o.created_at) = CURRENT_DATE THEN 1 END) AS deliveries_today
         FROM delivery_staff d
         LEFT JOIN orders o ON o.delivery_staff_id = d.id
         GROUP BY d.id, d.name, d.phone, d.status, d.active
         ORDER BY d.active DESC, d.status = 'available' DESC, d.name"
    );

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['active'] = (bool) $row['active'];
        $row['active_deliveries'] = (int) $row['active_deliveries'];
        $row['deliveries_today'] = (int) $row['deliveries_today'];
        return $row;
    }, $stmt->fetchAll());
}

function app_delivery_staff_one(int $id): array
{
<<<<<<< HEAD
    foreach (app_delivery_staff() as $driver) {
        if ((int) $driver['id'] === $id) {
            return $driver;
        }
    }

    app_json_response(['ok' => false, 'message' => 'Livreur introuvable.'], 404);
=======
    $stmt = app_pdo()->prepare('SELECT id, name, phone, status, active FROM delivery_staff WHERE id = ?');
    $stmt->execute([$id]);
    $driver = $stmt->fetch();
    if (!$driver) {
        app_json_response(['ok' => false, 'message' => 'Livreur introuvable.'], 404);
    }
    $driver['id'] = (int) $driver['id'];
    $driver['active'] = (bool) $driver['active'];
    return $driver;
>>>>>>> 5f92b50 (mise a jour)
}

function app_save_delivery_staff(array $body): array
{
    $id = (int) ($body['id'] ?? 0);
    $name = trim((string) ($body['name'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $accessCode = trim((string) ($body['access_code'] ?? ''));
    $status = (string) ($body['status'] ?? 'available');
    $active = !empty($body['active']) ? 1 : 0;

    if ($name === '' || $phone === '' || !in_array($status, ['available', 'busy', 'absent'], true)) {
        app_json_response(['ok' => false, 'message' => 'Livreur invalide.'], 422);
    }

    if ($id > 0) {
        if ($accessCode !== '') {
            if (strlen($accessCode) < 4) {
                app_json_response(['ok' => false, 'message' => 'Le code livreur doit contenir au moins 4 caracteres.'], 422);
            }
            $stmt = app_pdo()->prepare('UPDATE delivery_staff SET name = ?, phone = ?, access_code_hash = ?, status = ?, active = ? WHERE id = ?');
            $stmt->execute([$name, $phone, password_hash($accessCode, PASSWORD_DEFAULT), $status, $active, $id]);
        } else {
            $stmt = app_pdo()->prepare('UPDATE delivery_staff SET name = ?, phone = ?, status = ?, active = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $status, $active, $id]);
        }
        if ($stmt->rowCount() === 0) {
            $exists = app_pdo()->prepare('SELECT COUNT(*) FROM delivery_staff WHERE id = ?');
            $exists->execute([$id]);
            if ((int) $exists->fetchColumn() === 0) {
                app_json_response(['ok' => false, 'message' => 'Livreur introuvable.'], 404);
            }
        }
    } else {
        if ($accessCode === '') {
            $accessCode = app_env('DELIVERY_DEFAULT_CODE', '1234');
        }
        if (strlen($accessCode) < 4) {
            app_json_response(['ok' => false, 'message' => 'Le code livreur doit contenir au moins 4 caracteres.'], 422);
        }
        $stmt = app_pdo()->prepare('INSERT INTO delivery_staff (name, phone, access_code_hash, status, active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone, password_hash($accessCode, PASSWORD_DEFAULT), $status, $active]);
    }

    return app_delivery_staff();
}

function app_update_order_status(int $id, string $status, ?int $userId = null, string $reason = ''): array
{
    $allowed = ['pending', 'prep', 'delivery', 'done', 'cancelled'];
    if ($id <= 0 || !in_array($status, $allowed, true)) {
        app_json_response(['ok' => false, 'message' => 'Statut invalide.'], 422);
    }

    $reason = trim($reason);
    if ($status === 'cancelled' && $reason === '') {
        app_json_response(['ok' => false, 'message' => 'Motif d annulation obligatoire.'], 422);
    }

    $pdo = app_pdo();
    $stmt = $pdo->prepare('UPDATE orders SET status = ?, cancellation_reason = ? WHERE id = ?');
    $stmt->execute([$status, $status === 'cancelled' ? $reason : null, $id]);

    if ($stmt->rowCount() === 0) {
        app_json_response(['ok' => false, 'message' => 'Commande introuvable.'], 404);
    }

    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, note) VALUES (?, ?, ?, ?)')
        ->execute([$id, $status, $userId, $status === 'cancelled' ? $reason : null]);

    if (in_array($status, ['prep', 'delivery', 'done'], true)) {
        app_queue_notification($id, $status, 'whatsapp');
    }

    return app_orders('', 25);
}

function app_assign_delivery_staff(int $orderId, ?int $deliveryStaffId, ?int $userId = null): array
{
    if ($orderId <= 0) {
        app_json_response(['ok' => false, 'message' => 'Commande invalide.'], 422);
    }

    $pdo = app_pdo();
    $exists = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE id = ?');
    $exists->execute([$orderId]);
    if ((int) $exists->fetchColumn() === 0) {
        app_json_response(['ok' => false, 'message' => 'Commande introuvable.'], 404);
    }

    if ($deliveryStaffId !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM delivery_staff WHERE id = ? AND active = 1 AND status <> 'absent'");
        $stmt->execute([$deliveryStaffId]);
        if ((int) $stmt->fetchColumn() === 0) {
            app_json_response(['ok' => false, 'message' => 'Livreur indisponible.'], 422);
        }
    }

    $stmt = $pdo->prepare('UPDATE orders SET delivery_staff_id = ? WHERE id = ?');
    $stmt->execute([$deliveryStaffId, $orderId]);

    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, note) SELECT id, status, ?, ? FROM orders WHERE id = ?')
        ->execute([$userId, $deliveryStaffId ? 'Livreur assigné' : 'Livreur retiré', $orderId]);

    return app_orders('', 25);
}

function app_update_payment_status(int $orderId, string $status): array
{
    $allowed = ['pending', 'paid', 'failed', 'refunded'];
    if ($orderId <= 0 || !in_array($status, $allowed, true)) {
        app_json_response(['ok' => false, 'message' => 'Statut paiement invalide.'], 422);
    }

    $paidAtSql = $status === 'paid' ? ', paid_at = NOW()' : ', paid_at = NULL';
    $stmt = app_pdo()->prepare("UPDATE payments SET status = ? {$paidAtSql} WHERE order_id = ?");
    $stmt->execute([$status, $orderId]);

    if ($stmt->rowCount() === 0) {
        app_json_response(['ok' => false, 'message' => 'Paiement introuvable.'], 404);
    }

    if ($status === 'paid') {
        app_queue_notification($orderId, 'payment', 'whatsapp');
    }

    return app_orders('', 25);
}

function app_apply_payment_callback(array $body): array
{
    $secret = app_env('PAYMENT_CALLBACK_SECRET', '');
    if ($secret === '' && app_is_production()) {
        app_json_response(['ok' => false, 'message' => 'PAYMENT_CALLBACK_SECRET requis en production.'], 500);
    }
    if ($secret !== '' && !hash_equals($secret, (string) ($body['secret'] ?? ''))) {
        app_json_response(['ok' => false, 'message' => 'Signature paiement invalide.'], 403);
    }

    $reference = trim((string) ($body['reference'] ?? ''));
    $status = (string) ($body['status'] ?? 'paid');
    if ($reference === '' || !in_array($status, ['paid', 'failed', 'refunded'], true)) {
        app_json_response(['ok' => false, 'message' => 'Callback paiement invalide.'], 422);
    }

    $paidAtSql = $status === 'paid' ? ', paid_at = NOW()' : ', paid_at = NULL';
    $stmt = app_pdo()->prepare("UPDATE payments SET status = ? {$paidAtSql} WHERE reference = ?");
    $stmt->execute([$status, $reference]);
    if ($stmt->rowCount() === 0) {
        app_json_response(['ok' => false, 'message' => 'Reference paiement introuvable.'], 404);
    }

    $lookup = app_pdo()->prepare('SELECT order_id FROM payments WHERE reference = ?');
    $lookup->execute([$reference]);
    $orderId = (int) $lookup->fetchColumn();
    if ($status === 'paid' && $orderId > 0) {
        app_queue_notification($orderId, 'payment', 'whatsapp');
    }

    return $orderId > 0 ? app_order_by_id($orderId) : [];
}

function app_admin_stats(): array
{
    $pdo = app_pdo();
    $revenue = $pdo->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS revenue
         FROM orders
         WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 13 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day"
    )->fetchAll();

    $topItems = $pdo->query(
        "SELECT name, SUM(quantity) AS quantity, SUM(line_total) AS revenue
         FROM order_items
         GROUP BY name
         ORDER BY quantity DESC, revenue DESC
         LIMIT 10"
    )->fetchAll();

    $categorySales = $pdo->query(
        "SELECT c.name AS category, COALESCE(SUM(oi.quantity), 0) AS quantity, COALESCE(SUM(oi.line_total), 0) AS revenue
         FROM categories c
         LEFT JOIN menu_items m ON m.category_id = c.id
         LEFT JOIN order_items oi ON oi.menu_item_id = m.id
         GROUP BY c.id, c.name
         ORDER BY revenue DESC"
    )->fetchAll();

    $paymentBreakdown = $pdo->query(
        "SELECT method, status, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount
         FROM payments
         GROUP BY method, status
         ORDER BY method, status"
    )->fetchAll();

    return [
        'revenue_by_day' => app_numeric_rows($revenue),
        'top_items' => app_numeric_rows($topItems),
        'category_sales' => app_numeric_rows($categorySales),
        'payment_breakdown' => app_numeric_rows($paymentBreakdown),
    ];
}

function app_numeric_rows(array $rows): array
{
    foreach ($rows as &$row) {
        foreach ($row as $key => $value) {
            if (is_numeric($value)) {
                $row[$key] = (int) $value;
            }
        }
    }

    return $rows;
}

function app_export_csv(string $type): void
{
    $pdo = app_pdo();
    $date = date('Ymd');

    if ($type === 'orders') {
        $rows = $pdo->query(
            "SELECT o.number, o.created_at, o.customer_name, o.phone, o.address, o.fulfillment, o.status,
                    o.total, p.method, p.status AS payment_status, p.reference
             FROM orders o
             LEFT JOIN payments p ON p.order_id = o.id
             ORDER BY o.created_at DESC"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-orders-{$date}.csv", ['commande', 'date', 'client', 'telephone', 'adresse', 'mode', 'statut_commande', 'total', 'paiement', 'statut_paiement', 'reference'], $rows);
    }

    if ($type === 'clients') {
        $rows = $pdo->query(
            "SELECT c.name, c.phone, c.default_address, COUNT(o.id) AS orders_count, COALESCE(SUM(o.total), 0) AS total_spent
             FROM customers c
             LEFT JOIN orders o ON o.customer_id = c.id
             GROUP BY c.id, c.name, c.phone, c.default_address
             ORDER BY total_spent DESC"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-clients-{$date}.csv", ['client', 'telephone', 'adresse', 'commandes', 'total_depense'], $rows);
    }

    if ($type === 'payments') {
        $rows = $pdo->query(
            "SELECT o.number, p.method, p.provider, p.reference, p.amount, p.status, p.paid_at, p.created_at
             FROM payments p
             JOIN orders o ON o.id = p.order_id
             ORDER BY p.created_at DESC"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-payments-{$date}.csv", ['commande', 'methode', 'provider', 'reference', 'montant', 'statut', 'paye_le', 'cree_le'], $rows);
    }

    if ($type === 'menu') {
        $rows = $pdo->query(
            "SELECT m.name, c.name AS category, m.price, m.max_price, m.available, m.popular, m.daily_special, m.image_url
             FROM menu_items m
             JOIN categories c ON c.id = m.category_id
             WHERE m.active = 1
             ORDER BY c.sort_order, m.sort_order"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-menu-{$date}.csv", ['plat', 'categorie', 'prix', 'prix_max', 'disponible', 'incontournable', 'plat_du_jour', 'image'], $rows);
    }

    if ($type === 'notifications') {
        $rows = $pdo->query(
            "SELECT n.created_at, o.number, n.channel, n.recipient, n.status, n.provider, n.provider_reference, n.message
             FROM notifications n
             LEFT JOIN orders o ON o.id = n.order_id
             ORDER BY n.created_at DESC"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-notifications-{$date}.csv", ['date', 'commande', 'canal', 'destinataire', 'statut', 'provider', 'reference_provider', 'message'], $rows);
    }

    app_json_response(['ok' => false, 'message' => 'Export inconnu.'], 404);
}

function app_pdf_response(string $filename, string $title, array $summary, array $stats): void
{
    $commands = '';
    $commands .= app_pdf_rect(0, 0, 595, 842, '0.98 0.96 0.92');
    $commands .= app_pdf_rect(0, 735, 595, 107, '0.78 0.04 0.08');
    $commands .= app_pdf_rect(0, 720, 595, 16, '0.10 0.10 0.10');
    $commands .= app_pdf_text_at($title, 42, 785, 24, 'F2', '1 1 1');
    $commands .= app_pdf_text_at('Rapport de synthese - ' . date('d/m/Y H:i'), 42, 762, 10, 'F1', '1 1 1');
    $commands .= app_pdf_text_at(app_env('APP_URL', 'http://localhost/boucherketchup'), 42, 745, 9, 'F1', '1 1 1');

    $cards = [
        ['CA aujourd hui', app_pdf_money((int) $summary['revenue_today'])],
        ['Commandes jour', (string) $summary['orders_today']],
        ['En attente', (string) $summary['pending']],
        ['Paiements attente', (string) $summary['payments_pending']],
    ];
    $x = 42;
    foreach ($cards as $card) {
        $commands .= app_pdf_rect($x, 646, 118, 55, '1 1 1');
        $commands .= app_pdf_rect($x, 646, 118, 4, '0.93 0.63 0.08');
        $commands .= app_pdf_text_at($card[0], $x + 12, 681, 8, 'F1', '0.35 0.35 0.35');
        $commands .= app_pdf_text_at($card[1], $x + 12, 660, 15, 'F2', '0.10 0.10 0.10');
        $x += 128;
    }

    $commands .= app_pdf_text_at('Activite des 7 derniers jours', 42, 610, 14, 'F2', '0.10 0.10 0.10');
    $commands .= app_pdf_rect(42, 465, 230, 125, '1 1 1');
    $maxRevenue = app_pdf_max_value($stats['revenue_by_day'], 'revenue');
    $barX = 58;
    foreach (array_slice($stats['revenue_by_day'], -7) as $row) {
        $height = max(4, (int) round(((int) $row['revenue'] / $maxRevenue) * 82));
        $commands .= app_pdf_rect($barX, 485, 18, $height, '0.78 0.04 0.08');
        $commands .= app_pdf_text_at(date('d/m', strtotime((string) $row['day'])), $barX - 3, 474, 6, 'F1', '0.35 0.35 0.35');
        $barX += 29;
    }

    $commands .= app_pdf_text_at('Top plats', 310, 610, 14, 'F2', '0.10 0.10 0.10');
    $commands .= app_pdf_rect(310, 465, 243, 125, '1 1 1');
    $y = 568;
    foreach (array_slice($stats['top_items'], 0, 5) as $item) {
        $commands .= app_pdf_text_at(app_pdf_short_text((string) $item['name'], 28), 324, $y, 8, 'F2', '0.10 0.10 0.10');
        $commands .= app_pdf_text_at($item['quantity'] . ' ventes - ' . app_pdf_money((int) $item['revenue']), 470, $y, 8, 'F1', '0.35 0.35 0.35');
        $commands .= app_pdf_line(324, $y - 8, 538, $y - 8, '0.88 0.88 0.88');
        $y -= 21;
    }

    $commands .= app_pdf_text_at('Paiements', 42, 420, 14, 'F2', '0.10 0.10 0.10');
    $commands .= app_pdf_rect(42, 250, 511, 150, '1 1 1');
    $commands .= app_pdf_rect(42, 374, 511, 26, '0.10 0.10 0.10');
    $commands .= app_pdf_text_at('Methode', 58, 383, 8, 'F2', '1 1 1');
    $commands .= app_pdf_text_at('Statut', 210, 383, 8, 'F2', '1 1 1');
    $commands .= app_pdf_text_at('Nombre', 350, 383, 8, 'F2', '1 1 1');
    $commands .= app_pdf_text_at('Montant', 455, 383, 8, 'F2', '1 1 1');
    $y = 354;
    foreach (array_slice($stats['payment_breakdown'], 0, 6) as $payment) {
        $commands .= app_pdf_text_at((string) $payment['method'], 58, $y, 9, 'F1', '0.10 0.10 0.10');
        $commands .= app_pdf_text_at((string) $payment['status'], 210, $y, 9, 'F1', '0.10 0.10 0.10');
        $commands .= app_pdf_text_at((string) $payment['count'], 350, $y, 9, 'F1', '0.10 0.10 0.10');
        $commands .= app_pdf_text_at(app_pdf_money((int) $payment['amount']), 455, $y, 9, 'F1', '0.10 0.10 0.10');
        $commands .= app_pdf_line(58, $y - 9, 535, $y - 9, '0.88 0.88 0.88');
        $y -= 20;
    }

    $commands .= app_pdf_text_at('Categories', 42, 210, 14, 'F2', '0.10 0.10 0.10');
    $y = 188;
    $maxCategoryRevenue = app_pdf_max_value($stats['category_sales'], 'revenue');
    foreach (array_slice($stats['category_sales'], 0, 6) as $category) {
        $width = max(6, (int) round(((int) $category['revenue'] / $maxCategoryRevenue) * 260));
        $commands .= app_pdf_text_at(app_pdf_short_text((string) $category['category'], 22), 42, $y, 8, 'F1', '0.10 0.10 0.10');
        $commands .= app_pdf_rect(150, $y - 5, $width, 8, '0.93 0.63 0.08');
        $commands .= app_pdf_text_at(app_pdf_money((int) $category['revenue']), 425, $y, 8, 'F1', '0.35 0.35 0.35');
        $y -= 18;
    }

    $commands .= app_pdf_text_at('Chez Ketchup - Export genere depuis le back-office', 42, 42, 8, 'F1', '0.45 0.45 0.45');

    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>\nendobj\n",
        "4 0 obj\n<< /Length " . strlen($commands) . " >>\nstream\n{$commands}\nendstream\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    http_response_code(200);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}

function app_pdf_text(string $value): string
{
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $value);
}

function app_pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function app_pdf_text_at(string $text, int $x, int $y, int $size = 10, string $font = 'F1', string $color = '0 0 0'): string
{
    return "{$color} rg\nBT\n/{$font} {$size} Tf\n{$x} {$y} Td\n(" . app_pdf_escape(app_pdf_text($text)) . ") Tj\nET\n";
}

function app_pdf_short_text(string $text, int $max): string
{
    $plain = app_pdf_text($text);
    return strlen($plain) > $max ? substr($plain, 0, $max - 3) . '...' : $plain;
}

function app_pdf_max_value(array $rows, string $key): int
{
    $values = array_map(fn ($row) => (int) ($row[$key] ?? 0), $rows);
    return max(1, ...$values);
}

function app_pdf_rect(int $x, int $y, int $width, int $height, string $color): string
{
    return "{$color} rg\n{$x} {$y} {$width} {$height} re f\n";
}

function app_pdf_line(int $x1, int $y1, int $x2, int $y2, string $color): string
{
    return "{$color} RG\n0.5 w\n{$x1} {$y1} m\n{$x2} {$y2} l\nS\n";
}

function app_pdf_money(int $value): string
{
    return number_format($value, 0, ',', ' ') . ' CFA';
}

function app_export_pdf(string $type): void
{
    if ($type !== 'summary') {
        app_json_response(['ok' => false, 'message' => 'Rapport PDF inconnu.'], 404);
    }

    $stats = app_admin_stats();
    $summary = app_admin_summary()['summary'];

    app_pdf_response('chez-ketchup-rapport-' . date('Ymd') . '.pdf', 'Rapport Chez Ketchup', $summary, $stats);
}

function app_notification_templates(array $order): array
{
    $items = implode(', ', array_map(fn ($item) => $item['name'] . ' x' . $item['qty'], $order['items']));
    $driver = $order['delivery_name'] ? " Livreur: {$order['delivery_name']} ({$order['delivery_phone']})." : '';
    $payment = " Paiement: " . ($order['payment_method'] ?? 'cash') . ' / ' . ($order['payment_status'] ?? 'pending') . '.';

    return [
        'confirmation' => "Chez Ketchup: commande {$order['number']} reçue. Articles: {$items}. Total: {$order['total']} CFA.{$payment}",
        'prep' => "Chez Ketchup: votre commande {$order['number']} est en préparation. Merci pour votre patience.",
        'delivery' => "Chez Ketchup: votre commande {$order['number']} est en livraison.{$driver}",
        'done' => "Chez Ketchup: commande {$order['number']} livrée. Bon appétit et merci pour votre confiance.",
        'payment' => "Chez Ketchup: paiement de {$order['total']} CFA pour {$order['number']}. Référence: " . ($order['payment_reference'] ?? '-'),
    ];
}

function app_order_by_id(int $id): array
{
    $pdo = app_pdo();
    $sql = 'SELECT o.*, p.id AS payment_id, p.method AS payment_method, p.status AS payment_status,
                   p.reference AS payment_reference, p.provider AS payment_provider, p.checkout_url AS payment_checkout_url,
                   d.name AS delivery_name, d.phone AS delivery_phone, d.status AS delivery_staff_status,
                   z.name AS delivery_zone_name, z.estimated_minutes AS delivery_estimated_minutes
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            LEFT JOIN delivery_staff d ON d.id = o.delivery_staff_id
            LEFT JOIN delivery_zones z ON z.id = o.delivery_zone_id
            WHERE o.id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        app_json_response(['ok' => false, 'message' => 'Commande introuvable.'], 404);
    }
    $order['id'] = (int) $order['id'];
    $order['subtotal'] = (int) ($order['subtotal'] ?? 0);
    $order['delivery_fee'] = (int) ($order['delivery_fee'] ?? 0);
    $order['discount_total'] = (int) ($order['discount_total'] ?? 0);
    $order['total'] = (int) $order['total'];
    $order['payment_id'] = $order['payment_id'] === null ? null : (int) $order['payment_id'];
    $order['delivery_staff_id'] = $order['delivery_staff_id'] === null ? null : (int) $order['delivery_staff_id'];
    $order['delivery_zone_id'] = $order['delivery_zone_id'] === null ? null : (int) $order['delivery_zone_id'];
    $order['delivery_estimated_minutes'] = $order['delivery_estimated_minutes'] === null ? null : (int) $order['delivery_estimated_minutes'];
    $order['items'] = app_order_items([$id])[$id] ?? [];
    return $order;
}

function app_queue_notification(int $orderId, string $template, string $channel = 'whatsapp'): array
{
    $allowedChannels = ['whatsapp', 'sms'];
    if (!in_array($channel, $allowedChannels, true)) {
        app_json_response(['ok' => false, 'message' => 'Canal invalide.'], 422);
    }

    $order = app_order_by_id($orderId);
    $templates = app_notification_templates($order);
    if (!isset($templates[$template])) {
        app_json_response(['ok' => false, 'message' => 'Modèle de notification invalide.'], 422);
    }

    $pdo = app_pdo();
    $provider = app_notification_provider($channel);
    $stmt = $pdo->prepare('INSERT INTO notifications (order_id, channel, recipient, message, status, provider) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$orderId, $channel, $order['phone'], $templates[$template], 'queued', $provider]);
    $notificationId = (int) $pdo->lastInsertId();

    if ($provider !== 'manual') {
        app_send_notification($notificationId);
    }

    $notification = app_notification($notificationId);
    $notification['whatsapp_url'] = app_whatsapp_url($notification['recipient'], $notification['message']);

    return $notification;
}

function app_queue_test_notification(array $body): array
{
    $channel = (string) ($body['channel'] ?? 'whatsapp');
    $recipient = trim((string) ($body['recipient'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));

    if (!in_array($channel, ['whatsapp', 'sms'], true)) {
        app_json_response(['ok' => false, 'message' => 'Canal invalide.'], 422);
    }
    if ($recipient === '' || $message === '') {
        app_json_response(['ok' => false, 'message' => 'Destinataire et message de test requis.'], 422);
    }

    $provider = app_notification_provider($channel);
    $stmt = app_pdo()->prepare('INSERT INTO notifications (order_id, channel, recipient, message, status, provider) VALUES (NULL, ?, ?, ?, ?, ?)');
    $stmt->execute([$channel, $recipient, $message, 'queued', $provider]);
    $notificationId = (int) app_pdo()->lastInsertId();

    if ($provider !== 'manual') {
        app_send_notification($notificationId);
    }

    $notification = app_notification($notificationId);
    $notification['whatsapp_url'] = app_whatsapp_url($notification['recipient'], $notification['message']);

    return $notification;
}

function app_notification_provider(string $channel): string
{
    $specific = strtoupper($channel) . '_PROVIDER';
    $provider = strtolower(app_env($specific, app_env('NOTIFICATION_PROVIDER', 'manual')));
    return in_array($provider, ['manual', 'webhook', 'twilio', 'meta'], true) ? $provider : 'manual';
}

function app_send_notification(int $id): void
{
    $notification = app_notification($id);
    $provider = (string) ($notification['provider'] ?: app_notification_provider((string) $notification['channel']));

    try {
        $reference = match ($provider) {
            'webhook' => app_send_notification_webhook($notification),
            'twilio' => app_send_notification_twilio($notification),
            'meta' => app_send_notification_meta($notification),
            default => '',
        };
        if ($reference === '') {
            return;
        }
        app_update_notification_delivery($id, 'sent', $provider, $reference);
    } catch (Throwable $error) {
        app_update_notification_delivery($id, 'failed', $provider, 'ERR-' . substr(md5($error->getMessage()), 0, 12));
    }
}

function app_update_notification_delivery(int $id, string $status, string $provider, string $reference = ''): void
{
    $sentSql = $status === 'sent' ? ', sent_at = NOW()' : ', sent_at = NULL';
    $stmt = app_pdo()->prepare("UPDATE notifications SET status = ?, provider = ?, provider_reference = ? {$sentSql} WHERE id = ?");
    $stmt->execute([$status, $provider, $reference !== '' ? substr($reference, 0, 120) : null, $id]);
}

function app_send_notification_webhook(array $notification): string
{
    $url = app_env('NOTIFICATION_WEBHOOK_URL');
    if ($url === '') {
        throw new RuntimeException('NOTIFICATION_WEBHOOK_URL manquant.');
    }

    $payload = [
        'channel' => $notification['channel'],
        'recipient' => app_notification_phone((string) $notification['recipient']),
        'message' => $notification['message'],
        'order_number' => $notification['order_number'] ?? null,
    ];
    $response = app_http_request('POST', $url, ['Content-Type: application/json'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Webhook notification refusee: HTTP ' . $response['status']);
    }

    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? (string) ($decoded['id'] ?? $decoded['reference'] ?? 'webhook-' . date('YmdHis')) : 'webhook-' . date('YmdHis');
}

function app_send_notification_twilio(array $notification): string
{
    $sid = app_env('TWILIO_ACCOUNT_SID');
    $token = app_env('TWILIO_AUTH_TOKEN');
    $from = app_env($notification['channel'] === 'whatsapp' ? 'TWILIO_WHATSAPP_FROM' : 'TWILIO_SMS_FROM');
    if ($sid === '' || $token === '' || $from === '') {
        throw new RuntimeException('Configuration Twilio incomplete.');
    }

    $to = app_notification_phone((string) $notification['recipient']);
    if ($notification['channel'] === 'whatsapp' && !str_starts_with($to, 'whatsapp:')) {
        $to = 'whatsapp:' . $to;
    }

    $body = http_build_query(['From' => $from, 'To' => $to, 'Body' => $notification['message']]);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($sid . ':' . $token),
    ];
    $response = app_http_request('POST', "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $headers, $body);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Twilio notification refusee: HTTP ' . $response['status']);
    }

    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? (string) ($decoded['sid'] ?? 'twilio-' . date('YmdHis')) : 'twilio-' . date('YmdHis');
}

function app_send_notification_meta(array $notification): string
{
    if ($notification['channel'] !== 'whatsapp') {
        throw new RuntimeException('Meta WhatsApp ne supporte pas le canal SMS.');
    }

    $phoneNumberId = app_env('META_WHATSAPP_PHONE_NUMBER_ID');
    $token = app_env('META_WHATSAPP_ACCESS_TOKEN');
    $version = app_env('META_WHATSAPP_API_VERSION', 'v19.0');
    if ($phoneNumberId === '' || $token === '') {
        throw new RuntimeException('Configuration Meta WhatsApp incomplete.');
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => ltrim(app_notification_phone((string) $notification['recipient']), '+'),
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $notification['message']],
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ];
    $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    $response = app_http_request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Meta WhatsApp notification refusee: HTTP ' . $response['status']);
    }

    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? (string) ($decoded['messages'][0]['id'] ?? 'meta-' . date('YmdHis')) : 'meta-' . date('YmdHis');
}

function app_notification_phone(string $phone): string
{
    $trimmed = trim($phone);
    if (str_starts_with($trimmed, 'whatsapp:')) {
        return $trimmed;
    }
    $digits = preg_replace('/\D+/', '', $trimmed) ?: '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 9) {
        $digits = '221' . $digits;
    }
    return '+' . $digits;
}

function app_http_request(string $method, string $url, array $headers, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($responseBody === false) {
            throw new RuntimeException($error ?: 'Requete HTTP impossible.');
        }
        return ['status' => $status, 'body' => (string) $responseBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = file_get_contents($url, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException('Requete HTTP impossible.');
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return ['status' => $status, 'body' => $responseBody];
}

function app_notification(int $id): array
{
    $stmt = app_pdo()->prepare(
        'SELECT n.*, o.number AS order_number
         FROM notifications n
         LEFT JOIN orders o ON o.id = n.order_id
         WHERE n.id = ?'
    );
    $stmt->execute([$id]);
    $notification = $stmt->fetch();

    if (!$notification) {
        app_json_response(['ok' => false, 'message' => 'Notification introuvable.'], 404);
    }

    $notification['id'] = (int) $notification['id'];
    $notification['order_id'] = $notification['order_id'] === null ? null : (int) $notification['order_id'];
    return $notification;
}

function app_notifications(int $limit = 30): array
{
    $stmt = app_pdo()->prepare(
        'SELECT n.*, o.number AS order_number
         FROM notifications n
         LEFT JOIN orders o ON o.id = n.order_id
         ORDER BY n.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return array_map(function (array $notification): array {
        $notification['id'] = (int) $notification['id'];
        $notification['order_id'] = $notification['order_id'] === null ? null : (int) $notification['order_id'];
        $notification['whatsapp_url'] = app_whatsapp_url($notification['recipient'], $notification['message']);
        return $notification;
    }, $stmt->fetchAll());
}

function app_whatsapp_url(string $phone, string $message): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function app_mark_notification(int $id, string $status): array
{
    if (!in_array($status, ['queued', 'sent', 'failed'], true)) {
        app_json_response(['ok' => false, 'message' => 'Statut notification invalide.'], 422);
    }

    $sentSql = $status === 'sent' ? ', sent_at = NOW()' : ', sent_at = NULL';
    $stmt = app_pdo()->prepare("UPDATE notifications SET status = ? {$sentSql} WHERE id = ?");
    $stmt->execute([$status, $id]);

    return app_notification($id);
}

function app_update_menu_availability(int $id, bool $available): array
{
    $stmt = app_pdo()->prepare('UPDATE menu_items SET available = ? WHERE id = ?');
    $stmt->execute([$available ? 1 : 0, $id]);

    if ($stmt->rowCount() === 0) {
        app_json_response(['ok' => false, 'message' => 'Plat introuvable.'], 404);
    }

    $catalog = app_catalog();
    foreach ($catalog['items'] as $item) {
        if ((int) $item['id'] === $id) {
            return $item;
        }
    }

    app_json_response(['ok' => false, 'message' => 'Plat introuvable.'], 404);
}

function app_upload_menu_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        app_json_response(['ok' => false, 'message' => 'Image invalide ou absente.'], 422);
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        app_json_response(['ok' => false, 'message' => 'Image trop lourde. Maximum 3 Mo.'], 422);
    }

    $tmp = (string) $file['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        app_json_response(['ok' => false, 'message' => 'Format image non supporté. Utilisez JPG, PNG ou WebP.'], 422);
    }

    if (!is_dir(UPLOAD_ROOT)) {
        mkdir(UPLOAD_ROOT, 0775, true);
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $target = UPLOAD_ROOT . '/' . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        app_json_response(['ok' => false, 'message' => 'Impossible d’enregistrer l’image.'], 500);
    }

    return UPLOAD_PUBLIC_PATH . '/' . $filename;
}

function app_validate_menu_payload(array $body, bool $partial = false): array
{
    $required = ['name', 'category', 'price'];
    if (!$partial) {
        foreach ($required as $field) {
            if (!isset($body[$field]) || trim((string) $body[$field]) === '') {
                app_json_response(['ok' => false, 'message' => 'Nom, catégorie et prix sont obligatoires.'], 422);
            }
        }
    }

    $name = trim((string) ($body['name'] ?? ''));
    $category = trim((string) ($body['category'] ?? ''));
    $price = (int) ($body['price'] ?? 0);
    $maxPrice = isset($body['max_price']) && (int) $body['max_price'] > 0 ? (int) $body['max_price'] : null;
    $stockEnabled = !empty($body['stock_enabled']);
    $stockQty = max(0, (int) ($body['stock_qty'] ?? 0));
    $lowStockThreshold = max(0, (int) ($body['low_stock_threshold'] ?? 3));

    if ($name === '' || $category === '' || $price <= 0) {
        app_json_response(['ok' => false, 'message' => 'Nom, catégorie et prix sont obligatoires.'], 422);
    }

    $stmt = app_pdo()->prepare('SELECT COUNT(*) FROM categories WHERE id = ? AND active = 1');
    $stmt->execute([$category]);
    if ((int) $stmt->fetchColumn() === 0) {
        app_json_response(['ok' => false, 'message' => 'Catégorie invalide.'], 422);
    }

    if ($maxPrice !== null && $maxPrice < $price) {
        app_json_response(['ok' => false, 'message' => 'Le prix maximum doit être supérieur au prix de base.'], 422);
    }

    return [
        'name' => $name,
        'description' => trim((string) ($body['description'] ?? '')),
        'price' => $price,
        'max_price' => $maxPrice,
        'category' => $category,
        'emoji' => trim((string) ($body['emoji'] ?? '🍽️')),
        'image_url' => trim((string) ($body['image_url'] ?? '')),
        'available' => (bool) ($body['available'] ?? true),
        'popular' => (bool) ($body['popular'] ?? false),
        'daily_special' => (bool) ($body['daily_special'] ?? false),
        'stock_enabled' => $stockEnabled,
        'stock_qty' => $stockQty,
        'low_stock_threshold' => $lowStockThreshold,
    ];
}

function app_validate_daily_special_limit(PDO $pdo, bool $dailySpecial, int $currentId = 0): void
{
    if (!$dailySpecial) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM menu_items WHERE active = 1 AND daily_special = 1 AND id <> ?');
    $stmt->execute([$currentId]);
    if ((int) $stmt->fetchColumn() >= 3) {
        app_json_response(['ok' => false, 'message' => 'Vous pouvez afficher au maximum 3 plats du jour. Retirez-en un avant d’en ajouter un autre.'], 422);
    }
}

function app_create_menu_item(array $body): array
{
    $item = app_validate_menu_payload($body);
    $pdo = app_pdo();
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM menu_items')->fetchColumn();
    $pdo->beginTransaction();
    try {
        app_validate_daily_special_limit($pdo, $item['daily_special']);

        $stmt = $pdo->prepare(
            'INSERT INTO menu_items (category_id, name, description, price, max_price, image_url, emoji, available, active, popular, daily_special, stock_enabled, stock_qty, low_stock_threshold, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $item['category'],
            $item['name'],
            $item['description'],
            $item['price'],
            $item['max_price'],
            $item['image_url'],
            $item['emoji'],
            $item['available'] ? 1 : 0,
            $item['popular'] ? 1 : 0,
            $item['daily_special'] ? 1 : 0,
            $item['stock_enabled'] ? 1 : 0,
            $item['stock_qty'],
            $item['low_stock_threshold'],
            $sort,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return app_menu_item($id);
}

function app_update_menu_item(int $id, array $body): array
{
    if ($id <= 0) {
        app_json_response(['ok' => false, 'message' => 'Plat invalide.'], 422);
    }

    $item = app_validate_menu_payload($body);
    $pdo = app_pdo();
    $pdo->beginTransaction();
    try {
        app_validate_daily_special_limit($pdo, $item['daily_special'], $id);

        $stmt = $pdo->prepare(
            'UPDATE menu_items
             SET category_id = ?, name = ?, description = ?, price = ?, max_price = ?, image_url = ?, emoji = ?, available = ?, popular = ?, daily_special = ?, stock_enabled = ?, stock_qty = ?, low_stock_threshold = ?
             WHERE id = ? AND active = 1'
        );
        $stmt->execute([
            $item['category'],
            $item['name'],
            $item['description'],
            $item['price'],
            $item['max_price'],
            $item['image_url'],
            $item['emoji'],
            $item['available'] ? 1 : 0,
            $item['popular'] ? 1 : 0,
            $item['daily_special'] ? 1 : 0,
            $item['stock_enabled'] ? 1 : 0,
            $item['stock_qty'],
            $item['low_stock_threshold'],
            $id,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return app_menu_item($id);
}

function app_delete_menu_item(int $id): void
{
    if ($id <= 0) {
        app_json_response(['ok' => false, 'message' => 'Plat invalide.'], 422);
    }

    $pdo = app_pdo();
    $used = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE menu_item_id = ?');
    $used->execute([$id]);

    if ((int) $used->fetchColumn() > 0) {
        $stmt = $pdo->prepare('UPDATE menu_items SET active = 0, available = 0 WHERE id = ?');
        $stmt->execute([$id]);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM menu_items WHERE id = ?');
    $stmt->execute([$id]);
}

function app_menu_item(int $id): array
{
    $stmt = app_pdo()->prepare(
        'SELECT m.id, m.name, m.description, m.price, m.max_price, m.category_id AS category,
                m.image_url, m.emoji, m.available, m.popular, m.daily_special,
                m.stock_enabled, m.stock_qty, m.low_stock_threshold, c.name AS category_name
         FROM menu_items m
         JOIN categories c ON c.id = m.category_id
         WHERE m.id = ? AND m.active = 1'
    );
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        app_json_response(['ok' => false, 'message' => 'Plat introuvable.'], 404);
    }

    $item['id'] = (int) $item['id'];
    $item['price'] = (int) $item['price'];
    $item['max_price'] = $item['max_price'] === null ? null : (int) $item['max_price'];
    $item['available'] = (bool) $item['available'];
    $item['popular'] = (bool) $item['popular'];
    $item['daily_special'] = (bool) $item['daily_special'];
    $item['stock_enabled'] = (bool) ($item['stock_enabled'] ?? false);
    $item['stock_qty'] = (int) ($item['stock_qty'] ?? 0);
    $item['low_stock_threshold'] = (int) ($item['low_stock_threshold'] ?? 3);
    $options = app_menu_options([$item['id']]);
    $item['options'] = $options[$item['id']] ?? [];
    return $item;
}
