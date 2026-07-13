-- ═══════════════════════════════════════════════════════════
-- KARAN OLI PORTFOLIO — database.sql
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS portfolio_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portfolio_db;

-- 1. Contacts Table (Stores your messages)
CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(200),
  message TEXT NOT NULL,
  ip_address VARCHAR(45),
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Visitors Table (Unique view counter)
CREATE TABLE IF NOT EXISTS visitors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45),
  visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Projects Table (Optional: Manage projects via DB)
CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  tags VARCHAR(255),
  icon VARCHAR(10) DEFAULT '🚀',
  sort_order INT DEFAULT 0
) ENGINE=InnoDB;

-- Insert Sample Projects
INSERT INTO projects (title, description, tags, icon, sort_order) VALUES
('E-Commerce App', 'Full-stack shopping site with PHP/MySQL.', 'php,mysql,javascript', '🛒', 1),
('Weather Dash', 'Real-time API weather dashboard.', 'javascript,css', '🌤️', 2),
('Task Manager', 'Kanban style task management.', 'javascript,css', '✅', 3);