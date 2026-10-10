<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = $_GET['resource'] ?? 'health';

try {
    if ($resource === 'health') {
        app_json_response(['ok' => true, 'service' => 'Chez Ketchup API', 'storage' => 'mysql', 'time' => date('c')]);
    }

    if ($resource === 'catalog' && $method === 'GET') {
        $catalog = app_catalog((string) ($_GET['category'] ?? 'all'));
        app_json_response(['ok' => true, ...$catalog]);
    }

    if ($resource === 'orders' && $method === 'GET') {
        app_json_response(['ok' => true, 'orders' => app_orders(trim((string) ($_GET['q'] ?? '')))]);
    }

    if ($resource === 'orders' && $method === 'POST') {
        app_json_response(['ok' => true, 'order' => app_create_order(app_request_body())], 201);
    }

<<<<<<< HEAD
=======
    if ($resource === 'payments/callback' && $method === 'POST') {
        app_json_response(['ok' => true, 'order' => app_apply_payment_callback(app_request_body())]);
    }

>>>>>>> 5f92b50 (mise a jour)
    if ($resource === 'driver/dashboard' && $method === 'POST') {
        app_json_response(['ok' => true, ...app_delivery_dashboard(app_request_body())]);
    }

    if ($resource === 'driver/order-status' && $method === 'PATCH') {
        $body = app_request_body();
        app_json_response(['ok' => true, ...app_update_delivery_order_status($body)]);
    }

    if ($resource === 'contact' && $method === 'POST') {
        app_json_response(['ok' => true, 'message' => app_create_contact_message(app_request_body())], 201);
    }

    if ($resource === 'reviews' && $method === 'POST') {
        app_json_response(['ok' => true, 'review' => app_create_review(app_request_body())], 201);
    }

    if ($resource === 'auth/login' && $method === 'POST') {
        app_json_response(['ok' => true, ...app_login(app_request_body())]);
    }

    if ($resource === 'auth/logout' && $method === 'POST') {
        app_logout();
        app_json_response(['ok' => true]);
    }

    if ($resource === 'admin/summary' && $method === 'GET') {
        $user = app_require_user();
        app_json_response(['ok' => true, ...app_admin_summary($user)]);
    }

    if ($resource === 'admin/system-check' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'checks' => app_system_checks()]);
    }

    if ($resource === 'admin/stats' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, ...app_admin_stats()]);
    }

    if ($resource === 'admin/operations' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'operations' => app_operations_config()]);
    }

    if ($resource === 'admin/settings' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'operations' => app_save_settings(app_request_body())]);
    }

    if ($resource === 'admin/business-hours' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        app_json_response(['ok' => true, 'operations' => app_save_business_hours(is_array($body['hours'] ?? null) ? $body['hours'] : [])]);
    }

    if ($resource === 'admin/delivery-zones' && in_array($method, ['POST', 'PUT'], true)) {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'operations' => app_save_delivery_zone(app_request_body())]);
    }

    if ($resource === 'admin/promo-codes' && in_array($method, ['POST', 'PUT'], true)) {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'operations' => app_save_promo_code(app_request_body())]);
    }

    if ($resource === 'admin/kitchen' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'orders' => app_kitchen_orders()]);
    }

    if ($resource === 'admin/pos-order' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager', 'cashier']);
        $body = app_request_body();
        $body['source'] = 'pos';
        $order = app_create_order($body, $user);
        app_audit_log($user, 'create', 'pos_order', (int) $order['id'], ['number' => $order['number'], 'total' => $order['total']]);
        app_json_response(['ok' => true, 'order' => $order, 'orders' => app_orders('', 25), 'customers' => app_admin_customers()], 201);
    }

    if ($resource === 'admin/reviews' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'reviews' => app_reviews()]);
    }

    if ($resource === 'admin/export' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_export_csv((string) ($_GET['type'] ?? 'orders'));
    }

    if ($resource === 'admin/export-pdf' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_export_pdf((string) ($_GET['type'] ?? 'summary'));
    }

    if ($resource === 'admin/contact-messages' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'messages' => app_contact_messages()]);
    }

    if ($resource === 'admin/customers' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'customers' => app_admin_customers()]);
    }

    if ($resource === 'admin/contact-message-status' && $method === 'PATCH') {
        app_require_user();
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'messages' => app_mark_contact_message((int) ($body['id'] ?? 0), (bool) ($body['handled'] ?? false)),
        ]);
    }

    if ($resource === 'admin/users' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin']);
        app_json_response(['ok' => true, 'users' => app_admin_users()]);
    }

    if ($resource === 'admin/users' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin']);
        $body = app_request_body();
        $users = app_create_user($body);
        app_audit_log($user, 'create', 'user', null, ['email' => $body['email'] ?? null, 'role' => $body['role'] ?? null]);
        app_json_response(['ok' => true, 'users' => $users], 201);
    }

    if ($resource === 'admin/users' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin']);
        $body = app_request_body();
        $users = app_update_user((int) ($body['id'] ?? 0), $body, (int) $user['user_id']);
        app_audit_log($user, 'update', 'user', (int) ($body['id'] ?? 0), ['email' => $body['email'] ?? null, 'role' => $body['role'] ?? null]);
        app_json_response(['ok' => true, 'users' => $users]);
    }

    if ($resource === 'admin/audit-log' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'logs' => app_audit_logs()]);
    }

    if ($resource === 'admin/notifications' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'notifications' => app_notifications()]);
    }

    if ($resource === 'admin/notifications' && $method === 'POST') {
        app_require_user();
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'notification' => app_queue_notification(
                (int) ($body['order_id'] ?? 0),
                (string) ($body['template'] ?? 'confirmation'),
                (string) ($body['channel'] ?? 'whatsapp')
            ),
            'notifications' => app_notifications(),
        ], 201);
    }

    if ($resource === 'admin/notification-test' && $method === 'POST') {
        $user = app_require_user();
        $body = app_request_body();
        $notification = app_queue_test_notification($body);
        app_audit_log($user, 'test', 'notification', (int) $notification['id'], ['channel' => $body['channel'] ?? 'whatsapp', 'recipient' => $body['recipient'] ?? null]);
        app_json_response([
            'ok' => true,
            'notification' => $notification,
            'notifications' => app_notifications(),
        ], 201);
    }

    if ($resource === 'admin/notification-status' && $method === 'PATCH') {
        $user = app_require_user();
        $body = app_request_body();
        $notification = app_mark_notification((int) ($body['id'] ?? 0), (string) ($body['status'] ?? 'queued'));
        app_audit_log($user, 'status', 'notification', (int) ($body['id'] ?? 0), ['status' => $body['status'] ?? 'queued']);
        app_json_response([
            'ok' => true,
            'notification' => $notification,
            'notifications' => app_notifications(),
        ]);
    }

    if ($resource === 'admin/order-status' && $method === 'PATCH') {
        $user = app_require_user();
        $body = app_request_body();
        $orders = app_update_order_status(
            (int) ($body['id'] ?? 0),
            (string) ($body['status'] ?? ''),
            (int) $user['user_id'],
            (string) ($body['reason'] ?? '')
        );
        app_audit_log($user, 'status', 'order', (int) ($body['id'] ?? 0), ['status' => $body['status'] ?? '', 'reason' => $body['reason'] ?? '']);
        app_json_response([
            'ok' => true,
            'orders' => $orders,
        ]);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'delivery_staff' => app_delivery_staff()]);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $deliveryStaff = app_save_delivery_staff($body);
        app_audit_log($user, 'create', 'delivery_staff', null, ['name' => $body['name'] ?? null, 'phone' => $body['phone'] ?? null]);
        app_json_response(['ok' => true, 'delivery_staff' => $deliveryStaff], 201);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $deliveryStaff = app_save_delivery_staff($body);
        app_audit_log($user, 'update', 'delivery_staff', (int) ($body['id'] ?? 0), ['name' => $body['name'] ?? null, 'status' => $body['status'] ?? null, 'active' => $body['active'] ?? null]);
        app_json_response(['ok' => true, 'delivery_staff' => $deliveryStaff]);
    }

    if ($resource === 'admin/order-delivery' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $deliveryStaffId = isset($body['delivery_staff_id']) && (int) $body['delivery_staff_id'] > 0
            ? (int) $body['delivery_staff_id']
            : null;
        $orders = app_assign_delivery_staff((int) ($body['id'] ?? 0), $deliveryStaffId, (int) $user['user_id']);
        app_audit_log($user, 'assign_delivery', 'order', (int) ($body['id'] ?? 0), ['delivery_staff_id' => $deliveryStaffId]);
        app_json_response([
            'ok' => true,
            'orders' => $orders,
            'delivery_staff' => app_delivery_staff(),
        ]);
    }

    if ($resource === 'admin/payment-status' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $orders = app_update_payment_status((int) ($body['id'] ?? 0), (string) ($body['status'] ?? ''));
        app_audit_log($user, 'status', 'payment', (int) ($body['id'] ?? 0), ['status' => $body['status'] ?? '']);
        app_json_response([
            'ok' => true,
            'orders' => $orders,
        ]);
    }

    if ($resource === 'admin/menu-availability' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $item = app_update_menu_availability((int) ($body['id'] ?? 0), (bool) ($body['available'] ?? false));
        app_audit_log($user, 'availability', 'menu_item', (int) ($body['id'] ?? 0), ['available' => $body['available'] ?? false]);
        app_json_response([
            'ok' => true,
            'item' => $item,
        ]);
    }

    if ($resource === 'admin/menu-upload' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'url' => app_upload_menu_image($_FILES['image'] ?? [])], 201);
    }

    if ($resource === 'admin/menu-items' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $item = app_create_menu_item($body);
<<<<<<< HEAD
        app_audit_log($user, 'create', 'menu_item', (int) $item['id'], ['name' => $body['name'] ?? null, 'category' => $body['category'] ?? null]);
=======
        app_audit_log($user, 'create', 'menu_item', (int) $item['id'], ['name' => $body['name'] ?? null, 'category' => $body['category'] ?? null, 'daily_special' => $body['daily_special'] ?? false]);
>>>>>>> 5f92b50 (mise a jour)
        app_json_response(['ok' => true, 'item' => $item], 201);
    }

    if ($resource === 'admin/menu-items' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $item = app_update_menu_item((int) ($body['id'] ?? 0), $body);
<<<<<<< HEAD
        app_audit_log($user, 'update', 'menu_item', (int) ($body['id'] ?? 0), ['name' => $body['name'] ?? null, 'category' => $body['category'] ?? null]);
=======
        app_audit_log($user, 'update', 'menu_item', (int) ($body['id'] ?? 0), ['name' => $body['name'] ?? null, 'category' => $body['category'] ?? null, 'daily_special' => $body['daily_special'] ?? false]);
>>>>>>> 5f92b50 (mise a jour)
        app_json_response(['ok' => true, 'item' => $item]);
    }

    if ($resource === 'admin/menu-items' && $method === 'DELETE') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $id = (int) ($_GET['id'] ?? 0);
        app_delete_menu_item($id);
        app_audit_log($user, 'delete', 'menu_item', $id);
        app_json_response(['ok' => true]);
    }

    if ($resource === 'admin/menu-options' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $item = app_save_menu_options((int) ($body['menu_item_id'] ?? 0), is_array($body['options'] ?? null) ? $body['options'] : []);
        app_audit_log($user, 'options', 'menu_item', (int) ($body['menu_item_id'] ?? 0));
        app_json_response(['ok' => true, 'item' => $item]);
    }

    app_json_response(['ok' => false, 'message' => 'Route introuvable.'], 404);
} catch (Throwable $error) {
    app_json_response([
        'ok' => false,
        'message' => 'Erreur serveur.',
        'detail' => app_env('APP_ENV', 'local') === 'local' ? $error->getMessage() : null,
    ], 500);
}
