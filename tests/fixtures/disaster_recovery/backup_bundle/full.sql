CREATE TABLE users (
  user_id INT PRIMARY KEY,
  role_id INT NULL,
  created_at DATETIME NULL
);

INSERT INTO users (user_id, role_id, created_at) VALUES (1, 10, '2026-04-05 09:00:00');

CREATE TABLE restaurant_tables (
  table_id INT PRIMARY KEY,
  branch_id INT NOT NULL,
  zone_id INT NULL,
  capacity INT NOT NULL
);

INSERT INTO restaurant_tables (table_id, branch_id, zone_id, capacity) VALUES (1, 1, 1, 4);
