CREATE TABLE users (
  user_id INT PRIMARY KEY,
  role_id INT NULL,
  created_at DATETIME NULL
);

CREATE TABLE reservations (
  reservation_id INT PRIMARY KEY,
  status VARCHAR(32) NOT NULL,
  deposit_status VARCHAR(32) NULL,
  start_time DATETIME NULL,
  created_at DATETIME NULL
);
