-- database.sql
-- Initializes the relational database infrastructure for the portfolio project.

CREATE DATABASE IF NOT EXISTS tu_portfolio_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tu_portfolio_db;

CREATE TABLE IF NOT EXISTS contact_logs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL,
    subject      VARCHAR(255)  NOT NULL,
    message      TEXT          NOT NULL,
    submitted_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_contact_logs_email (email),
    INDEX idx_contact_logs_submitted_at (submitted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
