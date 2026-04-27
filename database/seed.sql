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
('Super Admin', 'admin@chezketchup.com', '$2y$10$pwlt9JaM33YLDji7ztUfO.RFDShg9TRujZFQdPoN9C1PIoIV8jEWG', 'super_admin'),
('Gérant', 'gerant@chezketchup.com', '$2y$10$pwlt9JaM33YLDji7ztUfO.RFDShg9TRujZFQdPoN9C1PIoIV8jEWG', 'manager'),
('Caisse', 'caisse@chezketchup.com', '$2y$10$pwlt9JaM33YLDji7ztUfO.RFDShg9TRujZFQdPoN9C1PIoIV8jEWG', 'cashier');

INSERT INTO delivery_staff (name, phone, status) VALUES
('Moussa Diop', '+221 77 400 10 10', 'available'),
('Cheikh Fall', '+221 78 401 20 20', 'available'),
('Abdou Sarr', '+221 76 402 30 30', 'absent');
