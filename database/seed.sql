USE chez_ketchup;

INSERT INTO categories (id, name, sort_order) VALUES
('fast-food', 'Fast Food', 10),
('gastro', 'Gastro', 20),
('pizzeria', 'Pizzeria', 30),
('petit-dej', 'Petit Déj', 40),
('entrees', 'Entrées', 50),
('desserts', 'Desserts', 60),
('tapas', 'Tapas', 70),
('repas', 'Repas', 80);

INSERT INTO users (name, email, password_hash, role) VALUES
('Super Admin', 'admin@chezketchup.com', '$2y$10$qC/rMBBCaCA2CmslEwDI9enGojnVII0N/gPutM1l8yfSOyGePWZVC', 'super_admin'),
('Gérant', 'gerant@chezketchup.com', '$2y$10$qC/rMBBCaCA2CmslEwDI9enGojnVII0N/gPutM1l8yfSOyGePWZVC', 'manager'),
('Caisse', 'caisse@chezketchup.com', '$2y$10$qC/rMBBCaCA2CmslEwDI9enGojnVII0N/gPutM1l8yfSOyGePWZVC', 'cashier');

INSERT INTO delivery_staff (name, phone, access_code_hash, status) VALUES
('Moussa Diop', '+221 77 400 10 10', '$2y$10$HLlW/8DVlaDduVURHxNJjeuOGMrNc0aO4ai.d9SENlItn/nZIQiWu', 'available'),
('Cheikh Fall', '+221 78 401 20 20', '$2y$10$HLlW/8DVlaDduVURHxNJjeuOGMrNc0aO4ai.d9SENlItn/nZIQiWu', 'available'),
('Abdou Sarr', '+221 76 402 30 30', '$2y$10$HLlW/8DVlaDduVURHxNJjeuOGMrNc0aO4ai.d9SENlItn/nZIQiWu', 'absent');
