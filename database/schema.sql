-- Conference booking schema (MySQL 5.7+ / MariaDB 10.3+), utf8mb4

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mobile CHAR(11) NOT NULL,
  first_name VARCHAR(60) NULL,
  last_name VARCHAR(80) NULL,
  company VARCHAR(120) NULL,
  job_title VARCHAR(120) NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_codes (
  mobile CHAR(11) NOT NULL PRIMARY KEY,
  code_hash CHAR(64) NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sent_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL,
  reset_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  role ENUM('admin','operator','checkin') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price tiers / seat zones
CREATE TABLE IF NOT EXISTS categories (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#3b82f6',
  sort SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Physical hall layout (one hall)
CREATE TABLE IF NOT EXISTS seats (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  row_no TINYINT UNSIGNED NOT NULL,
  seat_no SMALLINT UNSIGNED NOT NULL,
  section CHAR(1) NOT NULL,            -- R=right, C=center, L=left (from audience view of the plan)
  x DECIMAL(8,2) NOT NULL,
  y DECIMAL(8,2) NOT NULL,
  rot DECIMAL(6,2) NOT NULL DEFAULT 0,
  category_id SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_row_seat (row_no, seat_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A session (day / show) with its own inventory
CREATE TABLE IF NOT EXISTS sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(255) NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  sale_open TINYINT(1) NOT NULL DEFAULT 1,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  max_per_order TINYINT UNSIGNED NOT NULL DEFAULT 10,
  sort SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_prices (
  session_id INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,          -- Toman
  PRIMARY KEY (session_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: 0 free, 1 held (until hold_until), 2 sold, 3 blocked by admin
CREATE TABLE IF NOT EXISTS session_seats (
  session_id INT UNSIGNED NOT NULL,
  seat_id SMALLINT UNSIGNED NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 0,
  order_id INT UNSIGNED NULL,
  hold_until INT UNSIGNED NULL,
  PRIMARY KEY (session_id, seat_id),
  KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value INT UNSIGNED NOT NULL,
  max_uses INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  min_seats TINYINT UNSIGNED NOT NULL DEFAULT 1,
  session_id INT UNSIGNED NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending, paid, cancelled, expired, failed, refunded
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  seats_count TINYINT UNSIGNED NOT NULL,
  subtotal INT UNSIGNED NOT NULL,
  discount INT UNSIGNED NOT NULL DEFAULT 0,
  total INT UNSIGNED NOT NULL,
  coupon_id INT UNSIGNED NULL,
  method VARCHAR(16) NULL,              -- zarinpal, zibal, fake, free, manual
  authority VARCHAR(64) NULL,
  ref_id VARCHAR(64) NULL,
  card_pan VARCHAR(32) NULL,
  note VARCHAR(255) NULL,
  issued_by INT UNSIGNED NULL,          -- admin id for manual issue
  hold_until INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  KEY idx_user (user_id),
  KEY idx_session_status (session_id, status),
  KEY idx_authority (authority),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  order_id INT UNSIGNED NOT NULL,
  seat_id SMALLINT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NULL,
  price INT UNSIGNED NOT NULL,
  PRIMARY KEY (order_id, seat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: valid, cancelled
CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code CHAR(10) NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  seat_id SMALLINT UNSIGNED NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'valid',
  guest_name VARCHAR(120) NULL,
  guest_mobile CHAR(11) NULL,
  checked_in_at DATETIME NULL,
  checked_in_by INT UNSIGNED NULL,
  checkin_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_code (code),
  KEY idx_order (order_id),
  KEY idx_user (user_id),
  KEY idx_session_checkin (session_id, checked_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkin_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NULL,
  admin_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NULL,
  result VARCHAR(16) NOT NULL,          -- ok, duplicate, invalid, wrong_session, cancelled
  raw VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ticket (ticket_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS speakers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  title VARCHAR(255) NULL,
  bio TEXT NULL,
  photo VARCHAR(255) NULL,
  sort SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  mobile CHAR(11) NOT NULL,
  kind VARCHAR(16) NOT NULL,
  ok TINYINT(1) NOT NULL,
  response VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
