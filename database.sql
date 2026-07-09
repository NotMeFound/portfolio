-- ═══════════════════════════════════════════════════════════
--  KARAN OLI PORTFOLIO — database.sql
--  Complete database schema with security optimizations
-- ═══════════════════════════════════════════════════════════

-- Create database with proper charset
CREATE DATABASE IF NOT EXISTS portfolio_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

-- ─────────────────────────────────────────────────────────────
-- 1. CONTACTS TABLE — Stores form submissions
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contacts (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)    NOT NULL,
  email        VARCHAR(150)    NOT NULL,
  subject      VARCHAR(200)    DEFAULT '',
  message      TEXT            NOT NULL,
  ip_address   VARCHAR(45)     DEFAULT NULL,
  user_agent   VARCHAR(255)    DEFAULT NULL,
  is_read      TINYINT(1)      NOT NULL DEFAULT 0,
  submitted_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_email       (email),
  INDEX idx_submitted   (submitted_at),
  INDEX idx_ip_time     (ip_address, submitted_at),
  INDEX idx_read_status (is_read, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. VISITORS TABLE — Unique daily visitor tracking
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS visitors (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  ip_address   VARCHAR(45)     DEFAULT NULL,
  user_agent   VARCHAR(255)    DEFAULT NULL,
  visitor_id   VARCHAR(64)     NOT NULL,
  visited_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_visitor_date (visitor_id, visited_at),
  INDEX idx_date (visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. PROJECTS TABLE — Dynamic project management
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
  id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  title        VARCHAR(150)    NOT NULL,
  description  TEXT,
  tags         VARCHAR(255)    DEFAULT '',
  github_url   VARCHAR(300)    DEFAULT '',
  demo_url     VARCHAR(300)    DEFAULT '',
  icon         VARCHAR(10)     DEFAULT '🛠️',
  sort_order   INT             NOT NULL DEFAULT 0,
  is_visible   TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_visible_sort (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. SAMPLE DATA — Insert initial projects
-- ─────────────────────────────────────────────────────────────
INSERT INTO projects (title, description, tags, github_url, demo_url, icon, sort_order) VALUES
('E-Commerce Web App',
 'Full-stack shopping site with cart, auth, and normalized MySQL schema.',
 'PHP,SQL,JavaScript,CSS',
 'https://github.com/karanoli',
 '#', '🛒', 1),

('Student Result System',
 'Grade management with GPA calculation and role-based access.',
 'PHP,SQL,JavaScript',
 'https://github.com/karanoli',
 '#', '📋', 2),

('Weather Dashboard',
 'Real-time weather app using OpenWeatherMap API with localStorage.',
 'JavaScript,CSS',
 'https://github.com/karanoli',
 '#', '🌤️', 3),

('Blog CMS',
 'Lightweight PHP/MySQL CMS with markdown and comment moderation.',
 'PHP,SQL,CSS',
 'https://github.com/karanoli',
 '#', '📰', 4),

('Task Manager',
 'Drag-and-drop Kanban board with vanilla JS — zero dependencies.',
 'JavaScript,CSS',
 'https://github.com/karanoli',
 '#', '✅', 5),

('Real-Time Guestbook',
 'AJAX-powered guestbook with spam filtering and admin panel.',
 'PHP,SQL,JavaScript,CSS',
 'https://github.com/karanoli',
 NULL, '💬', 6);

-- ─────────────────────────────────────────────────────────────
-- 5. VERIFY TABLES
-- ─────────────────────────────────────────────────────────────
SHOW TABLES;

-- Show table structures
DESCRIBE contacts;
DESCRIBE visitors;
DESCRIBE projects;

-- Show sample data
SELECT * FROM projects ORDER BY sort_order;