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

    if ($resource === 'contact' && $method === 'POST') {
        app_json_response(['ok' => true, 'message' => app_create_contact_message(app_request_body())], 201);
    }

    if ($resource === 'auth/login' && $method === 'POST') {
        app_json_response(['ok' => true, ...app_login(app_request_body())]);
    }

    if ($resource === 'admin/summary' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, ...app_admin_summary()]);
    }

    if ($resource === 'admin/stats' && $method === 'GET') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, ...app_admin_stats()]);
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
        app_json_response(['ok' => true, 'users' => app_create_user(app_request_body())], 201);
    }

    if ($resource === 'admin/users' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin']);
        $body = app_request_body();
        app_json_response(['ok' => true, 'users' => app_update_user((int) ($body['id'] ?? 0), $body, (int) $user['user_id'])]);
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

    if ($resource === 'admin/notification-status' && $method === 'PATCH') {
        app_require_user();
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'notification' => app_mark_notification((int) ($body['id'] ?? 0), (string) ($body['status'] ?? 'queued')),
            'notifications' => app_notifications(),
        ]);
    }

    if ($resource === 'admin/order-status' && $method === 'PATCH') {
        $user = app_require_user();
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'orders' => app_update_order_status(
                (int) ($body['id'] ?? 0),
                (string) ($body['status'] ?? ''),
                (int) $user['user_id'],
                (string) ($body['reason'] ?? '')
            ),
        ]);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'GET') {
        app_require_user();
        app_json_response(['ok' => true, 'delivery_staff' => app_delivery_staff()]);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'POST') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'delivery_staff' => app_save_delivery_staff(app_request_body())], 201);
    }

    if ($resource === 'admin/delivery-staff' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        app_json_response(['ok' => true, 'delivery_staff' => app_save_delivery_staff(app_request_body())]);
    }

    if ($resource === 'admin/order-delivery' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        $deliveryStaffId = isset($body['delivery_staff_id']) && (int) $body['delivery_staff_id'] > 0
            ? (int) $body['delivery_staff_id']
            : null;
        app_json_response([
            'ok' => true,
            'orders' => app_assign_delivery_staff((int) ($body['id'] ?? 0), $deliveryStaffId, (int) $user['user_id']),
            'delivery_staff' => app_delivery_staff(),
        ]);
    }

    if ($resource === 'admin/payment-status' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'orders' => app_update_payment_status((int) ($body['id'] ?? 0), (string) ($body['status'] ?? '')),
        ]);
    }

    if ($resource === 'admin/menu-availability' && $method === 'PATCH') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        app_json_response([
            'ok' => true,
            'item' => app_update_menu_availability((int) ($body['id'] ?? 0), (bool) ($body['available'] ?? false)),
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
        app_json_response(['ok' => true, 'item' => app_create_menu_item(app_request_body())], 201);
    }

    if ($resource === 'admin/menu-items' && $method === 'PUT') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $body = app_request_body();
        app_json_response(['ok' => true, 'item' => app_update_menu_item((int) ($body['id'] ?? 0), $body)]);
    }

    if ($resource === 'admin/menu-items' && $method === 'DELETE') {
        $user = app_require_user();
        app_require_role($user, ['super_admin', 'manager']);
        $id = (int) ($_GET['id'] ?? 0);
        app_delete_menu_item($id);
        app_json_response(['ok' => true]);
    }

    app_json_response(['ok' => false, 'message' => 'Route introuvable.'], 404);
} catch (Throwable $error) {
    app_json_response([
        'ok' => false,
        'message' => 'Erreur serveur.',
        'detail' => app_env('APP_ENV', 'local') === 'local' ? $error->getMessage() : null,
    ], 500);
}
