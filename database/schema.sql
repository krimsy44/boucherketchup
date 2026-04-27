CREATE DATABASE IF NOT EXISTS chez_ketchup
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chez_ketchup;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id VARCHAR(60) PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menu_items (
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
);

CREATE TABLE customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL UNIQUE,
  default_address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE delivery_staff (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  status ENUM('available', 'busy', 'absent') NOT NULL DEFAULT 'available',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
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
);

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  menu_item_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  line_total INT UNSIGNED NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

CREATE TABLE payments (
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
);

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending', 'prep', 'delivery', 'done', 'cancelled') NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
);

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  handled BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
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
);

CREATE INDEX idx_orders_status_created ON orders(status, created_at);
CREATE INDEX idx_orders_phone ON orders(phone);
CREATE INDEX idx_payments_status ON payments(status);
