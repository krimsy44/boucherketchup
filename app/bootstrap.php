<?php

declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const SESSION_FILE = APP_ROOT . '/storage/sessions.json';
const UPLOAD_ROOT = APP_ROOT . '/uploads/menu';
const UPLOAD_PUBLIC_PATH = 'uploads/menu';

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
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
    app_install_schema($pdo);
    app_seed_database($pdo);

    return $pdo;
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
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    app_ensure_column($pdo, 'menu_items', 'active', 'ALTER TABLE menu_items ADD active BOOLEAN NOT NULL DEFAULT TRUE AFTER available');
    app_ensure_column($pdo, 'payments', 'checkout_url', 'ALTER TABLE payments ADD checkout_url VARCHAR(255) NULL AFTER reference');
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
            ['name' => 'Super Admin', 'email' => 'admin@chezketchup.com', 'password' => 'ChangeMe123!', 'role' => 'super_admin'],
            ['name' => 'Gérant', 'email' => 'gerant@chezketchup.com', 'password' => 'ChangeMe123!', 'role' => 'manager'],
            ['name' => 'Caisse', 'email' => 'caisse@chezketchup.com', 'password' => 'ChangeMe123!', 'role' => 'cashier'],
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
        'INSERT INTO menu_items (category_id, name, description, price, max_price, emoji, available, popular, sort_order)
         VALUES (:category_id, :name, :description, :price, :max_price, :emoji, 1, :popular, :sort_order)'
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

    $stmt = $pdo->prepare('INSERT INTO delivery_staff (name, phone, status) VALUES (?, ?, ?)');
    foreach ([
        ['Moussa Diop', '+221 77 400 10 10', 'available'],
        ['Cheikh Fall', '+221 78 401 20 20', 'available'],
        ['Abdou Sarr', '+221 76 402 30 30', 'absent'],
    ] as $driver) {
        $stmt->execute($driver);
    }
}

function app_sessions(): array
{
    if (!file_exists(SESSION_FILE)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents(SESSION_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function app_save_sessions(array $sessions): void
{
    if (!is_dir(dirname(SESSION_FILE))) {
        mkdir(dirname(SESSION_FILE), 0775, true);
    }

    file_put_contents(SESSION_FILE, json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
        'can_export' => in_array($role, ['super_admin', 'manager'], true),
    ];
}

function app_categories(): array
{
    return app_pdo()->query('SELECT id, name, sort_order AS sort FROM categories WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
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
                m.image_url, m.emoji, m.available, m.popular, c.name AS category_name
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
    }

    return ['categories' => app_categories(), 'items' => $items];
}

function app_order_items(array $orderIds): array
{
    if ($orderIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = app_pdo()->prepare("SELECT order_id, menu_item_id, name, quantity, unit_price FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id");
    $stmt->execute($orderIds);
    $grouped = [];

    foreach ($stmt->fetchAll() as $row) {
        $grouped[(int) $row['order_id']][] = [
            'menu_id' => $row['menu_item_id'] === null ? null : (int) $row['menu_item_id'],
            'name' => $row['name'],
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
                   d.name AS delivery_name, d.phone AS delivery_phone, d.status AS delivery_staff_status
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            LEFT JOIN delivery_staff d ON d.id = o.delivery_staff_id';
    $params = [];

    if ($query !== '') {
        $sql .= ' WHERE UPPER(o.number) LIKE :query OR REPLACE(o.phone, " ", "") LIKE :phone';
        $params['query'] = '%' . strtoupper($query) . '%';
        $params['phone'] = '%' . str_replace(' ', '', $query) . '%';
    }

    $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . max(1, $limit);
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    $items = app_order_items(array_map(fn ($order) => (int) $order['id'], $orders));

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['total'] = (int) $order['total'];
        $order['payment_id'] = $order['payment_id'] === null ? null : (int) $order['payment_id'];
        $order['delivery_staff_id'] = $order['delivery_staff_id'] === null ? null : (int) $order['delivery_staff_id'];
        $order['items'] = $items[$order['id']] ?? [];
    }

    return $orders;
}

function app_new_order_number(): string
{
    $prefix = 'CK-' . date('Y') . '-';
    $stmt = app_pdo()->prepare('SELECT number FROM orders WHERE number LIKE ? ORDER BY number DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = preg_match('/(\d{4})$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function app_create_order(array $body): array
{
    $items = $body['items'] ?? [];
    $customer = $body['customer'] ?? [];

    if (!is_array($items) || count($items) === 0) {
        app_json_response(['ok' => false, 'message' => 'Le panier est vide.'], 422);
    }

    foreach (['name', 'phone', 'address'] as $field) {
        if (trim((string) ($customer[$field] ?? '')) === '') {
            app_json_response(['ok' => false, 'message' => 'Informations client incomplètes.'], 422);
        }
    }

    $pdo = app_pdo();
    $ids = array_map(fn ($item) => (int) ($item['id'] ?? 0), $items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, available FROM menu_items WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    $menu = [];
    foreach ($stmt->fetchAll() as $row) {
        $menu[(int) $row['id']] = $row;
    }

    $orderItems = [];
    $total = 0;
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        $qty = max(1, (int) ($item['qty'] ?? 1));
        if (!isset($menu[$id]) || !(bool) $menu[$id]['available']) {
            app_json_response(['ok' => false, 'message' => 'Un plat du panier est indisponible.'], 422);
        }
        $unit = (int) $menu[$id]['price'];
        $orderItems[] = ['menu_id' => $id, 'name' => $menu[$id]['name'], 'qty' => $qty, 'unit_price' => $unit];
        $total += $unit * $qty;
    }

    $name = trim((string) $customer['name']);
    $phone = trim((string) $customer['phone']);
    $address = trim((string) $customer['address']);

    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO customers (name, phone, default_address) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), default_address = VALUES(default_address)'
    )->execute([$name, $phone, $address]);
    $customerId = (int) $pdo->query('SELECT id FROM customers WHERE phone = ' . $pdo->quote($phone))->fetchColumn();

    $number = app_new_order_number();
    $pdo->prepare(
        'INSERT INTO orders (number, customer_id, customer_name, phone, address, note, fulfillment, status, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $number,
        $customerId,
        $name,
        $phone,
        $address,
        trim((string) ($customer['note'] ?? '')),
        $body['fulfillment'] ?? 'delivery',
        'pending',
        $total,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($orderItems as $item) {
        $itemStmt->execute([$orderId, $item['menu_id'], $item['name'], $item['qty'], $item['unit_price'], $item['unit_price'] * $item['qty']]);
    }

    $paymentMethod = (string) ($body['payment_method'] ?? 'cash');
    $payment = app_prepare_payment($paymentMethod, $orderId, $number, $total, [
        'name' => $name,
        'phone' => $phone,
    ]);

    $pdo->prepare('INSERT INTO payments (order_id, method, provider, reference, checkout_url, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $orderId,
            $paymentMethod,
            $payment['provider'],
            $payment['reference'],
            $payment['checkout_url'],
            $total,
            $payment['status'],
        ]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)')
        ->execute([$orderId, 'pending', 'Commande reçue']);
    $pdo->commit();

    $order = app_orders($number, 1)[0];
    app_queue_notification($orderId, 'confirmation', 'whatsapp');

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
    if (!in_array($method, ['orange_money', 'wave'], true)) {
        app_json_response(['ok' => false, 'message' => 'Choisissez Orange Money ou Wave pour le paiement.'], 422);
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
    $stmt = app_pdo()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE LOWER(email) = ? AND active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        app_json_response(['ok' => false, 'message' => 'Identifiants invalides.'], 401);
    }

    $token = bin2hex(random_bytes(24));
    $sessions = app_sessions();
    $sessions[$token] = [
        'user_id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'permissions' => app_role_permissions($user['role']),
        'created_at' => date('c'),
    ];
    app_save_sessions($sessions);

    return ['token' => $token, 'user' => $sessions[$token]];
}

function app_admin_summary(): array
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

    return ['summary' => $summary, 'orders' => app_orders('', 25), 'delivery_staff' => app_delivery_staff()];
}

function app_admin_users(): array
{
    $rows = app_pdo()->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY active DESC, role, name')->fetchAll();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['active'] = (bool) $row['active'];
        return $row;
    }, $rows);
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

function app_save_delivery_staff(array $body): array
{
    $id = (int) ($body['id'] ?? 0);
    $name = trim((string) ($body['name'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $status = (string) ($body['status'] ?? 'available');
    $active = !empty($body['active']) ? 1 : 0;

    if ($name === '' || $phone === '' || !in_array($status, ['available', 'busy', 'absent'], true)) {
        app_json_response(['ok' => false, 'message' => 'Livreur invalide.'], 422);
    }

    if ($id > 0) {
        $stmt = app_pdo()->prepare('UPDATE delivery_staff SET name = ?, phone = ?, status = ?, active = ? WHERE id = ?');
        $stmt->execute([$name, $phone, $status, $active, $id]);
        if ($stmt->rowCount() === 0) {
            app_json_response(['ok' => false, 'message' => 'Livreur introuvable.'], 404);
        }
    } else {
        $stmt = app_pdo()->prepare('INSERT INTO delivery_staff (name, phone, status, active) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $phone, $status, $active]);
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
            "SELECT m.name, c.name AS category, m.price, m.max_price, m.available, m.popular, m.image_url
             FROM menu_items m
             JOIN categories c ON c.id = m.category_id
             WHERE m.active = 1
             ORDER BY c.sort_order, m.sort_order"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-menu-{$date}.csv", ['plat', 'categorie', 'prix', 'prix_max', 'disponible', 'incontournable', 'image'], $rows);
    }

    if ($type === 'notifications') {
        $rows = $pdo->query(
            "SELECT n.created_at, o.number, n.channel, n.recipient, n.status, n.message
             FROM notifications n
             LEFT JOIN orders o ON o.id = n.order_id
             ORDER BY n.created_at DESC"
        )->fetchAll(PDO::FETCH_NUM);
        app_csv_response("chez-ketchup-notifications-{$date}.csv", ['date', 'commande', 'canal', 'destinataire', 'statut', 'message'], $rows);
    }

    app_json_response(['ok' => false, 'message' => 'Export inconnu.'], 404);
}

function app_pdf_response(string $filename, string $title, array $lines): void
{
    $text = app_pdf_text($title);
    $commands = "BT\n/F1 16 Tf\n50 800 Td\n(" . app_pdf_escape($text) . ") Tj\n";
    $commands .= "/F1 10 Tf\n";
    $y = 770;

    foreach ($lines as $line) {
        if ($y < 60) {
            break;
        }
        $commands .= "50 {$y} Td\n(" . app_pdf_escape(app_pdf_text((string) $line)) . ") Tj\n-50 -" . $y . " Td\n";
        $y -= 16;
    }
    $commands .= "ET";

    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
        "4 0 obj\n<< /Length " . strlen($commands) . " >>\nstream\n{$commands}\nendstream\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
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

function app_export_pdf(string $type): void
{
    if ($type !== 'summary') {
        app_json_response(['ok' => false, 'message' => 'Rapport PDF inconnu.'], 404);
    }

    $stats = app_admin_stats();
    $summary = app_admin_summary()['summary'];
    $lines = [
        'Date: ' . date('d/m/Y H:i'),
        'CA aujourd hui: ' . $summary['revenue_today'] . ' CFA',
        'Commandes aujourd hui: ' . $summary['orders_today'],
        'Commandes en attente: ' . $summary['pending'],
        'Paiements en attente: ' . $summary['payments_pending'],
        '',
        'Top plats',
    ];

    foreach (array_slice($stats['top_items'], 0, 10) as $item) {
        $lines[] = $item['name'] . ' - ' . $item['quantity'] . ' ventes - ' . $item['revenue'] . ' CFA';
    }

    $lines[] = '';
    $lines[] = 'Paiements';
    foreach ($stats['payment_breakdown'] as $payment) {
        $lines[] = $payment['method'] . ' / ' . $payment['status'] . ' - ' . $payment['count'] . ' - ' . $payment['amount'] . ' CFA';
    }

    app_pdf_response('chez-ketchup-rapport-' . date('Ymd') . '.pdf', 'Rapport Chez Ketchup', $lines);
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
    foreach (app_orders('', 200) as $order) {
        if ((int) $order['id'] === $id) {
            return $order;
        }
    }

    app_json_response(['ok' => false, 'message' => 'Commande introuvable.'], 404);
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
    $stmt = $pdo->prepare('INSERT INTO notifications (order_id, channel, recipient, message, status, provider) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$orderId, $channel, $order['phone'], $templates[$template], 'queued', 'manual']);
    $notification = app_notification((int) $pdo->lastInsertId());
    $notification['whatsapp_url'] = app_whatsapp_url($notification['recipient'], $notification['message']);

    return $notification;
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
    ];
}

function app_create_menu_item(array $body): array
{
    $item = app_validate_menu_payload($body);
    $pdo = app_pdo();
    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM menu_items')->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO menu_items (category_id, name, description, price, max_price, image_url, emoji, available, active, popular, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
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
        $sort,
    ]);

    return app_menu_item((int) $pdo->lastInsertId());
}

function app_update_menu_item(int $id, array $body): array
{
    if ($id <= 0) {
        app_json_response(['ok' => false, 'message' => 'Plat invalide.'], 422);
    }

    $item = app_validate_menu_payload($body);
    $stmt = app_pdo()->prepare(
        'UPDATE menu_items
         SET category_id = ?, name = ?, description = ?, price = ?, max_price = ?, image_url = ?, emoji = ?, available = ?, popular = ?
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
        $id,
    ]);

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
                m.image_url, m.emoji, m.available, m.popular, c.name AS category_name
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
    return $item;
}
