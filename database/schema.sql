CREATE DATABASE IF NOT EXISTS service_desk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE service_desk;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'done', 'rejected') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

INSERT IGNORE INTO users (name, email, password, role) VALUES
    ('Admin', 'admin@example.com', '$2y$10$zFWSdfpdj4u.YXoS598J0uBN0KmA.60n/anTsIZaH4VMDdGoKdbLW', 'admin'),
    ('Ivan', 'ivan@example.com', '$2y$10$zFWSdfpdj4u.YXoS598J0uBN0KmA.60n/anTsIZaH4VMDdGoKdbLW', 'user'),
    ('Anna', 'anna@example.com', '$2y$10$zFWSdfpdj4u.YXoS598J0uBN0KmA.60n/anTsIZaH4VMDdGoKdbLW', 'user');

INSERT IGNORE INTO categories (name) VALUES
    ('Почта'),
    ('Авторизация'),
    ('Оборудование'),
    ('Приложение'),
    ('Файлы');

INSERT INTO tickets (user_id, category_id, subject, description, status)
SELECT
    (SELECT id FROM users WHERE email = 'ivan@example.com' LIMIT 1),
    (SELECT id FROM categories WHERE name = 'Почта' LIMIT 1),
    'Не открывается почта',
    'При входе появляется ошибка авторизации.',
    'new'
WHERE NOT EXISTS (
    SELECT 1 FROM tickets WHERE subject = 'Не открывается почта'
);

INSERT INTO tickets (user_id, category_id, subject, description, status)
SELECT
    (SELECT id FROM users WHERE email = 'anna@example.com' LIMIT 1),
    (SELECT id FROM categories WHERE name = 'Оборудование' LIMIT 1),
    'Не работает принтер',
    'Принтер не печатает документы из офиса.',
    'in_progress'
WHERE NOT EXISTS (
    SELECT 1 FROM tickets WHERE subject = 'Не работает принтер'
);

DELETE t1 FROM tickets t1
INNER JOIN tickets t2
    ON t1.subject = t2.subject
    AND t1.id > t2.id
WHERE t1.subject IN ('Не открывается почта', 'Не работает принтер');

UPDATE tickets
SET user_id = (SELECT id FROM users WHERE email = 'ivan@example.com' LIMIT 1),
    category_id = (SELECT id FROM categories WHERE name = 'Почта' LIMIT 1)
WHERE subject = 'Не открывается почта';

UPDATE tickets
SET user_id = (SELECT id FROM users WHERE email = 'anna@example.com' LIMIT 1),
    category_id = (SELECT id FROM categories WHERE name = 'Оборудование' LIMIT 1)
WHERE subject = 'Не работает принтер';

CREATE TABLE IF NOT EXISTS ticket_comments (
	 id INT AUTO_INCREMENT PRIMARY KEY,
     ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

DELETE c1
FROM categories c1
INNER JOIN categories c2
    ON c1.name = c2.name
    AND c1.id > c2.id;
    
ALTER TABLE categories
ADD CONSTRAINT uq_categories_name UNIQUE (name);



