CREATE DATABASE IF NOT EXISTS quizer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quizer;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    zone VARCHAR(100) NOT NULL,
    quiz_type ENUM('Adult', 'YAYA') NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_type ENUM('Adult', 'YAYA') NOT NULL UNIQUE,
    question_count INT NOT NULL DEFAULT 50,
    duration_minutes INT NOT NULL DEFAULT 30,
    total_marks INT NOT NULL DEFAULT 50,
    max_attempts INT NOT NULL DEFAULT 1,
    live_at DATETIME NULL,
    expires_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(120) NOT NULL,
    quiz_type ENUM('Adult', 'YAYA') NOT NULL,
    question_count INT NOT NULL DEFAULT 50,
    duration_minutes INT NOT NULL DEFAULT 30,
    total_marks INT NOT NULL DEFAULT 50,
    max_attempts INT NOT NULL DEFAULT 1,
    live_at DATETIME NULL,
    expires_at DATETIME NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_session_id INT NULL,
    quiz_type ENUM('Adult', 'YAYA') NOT NULL,
    question_type ENUM('multiple_choice', 'fill_gap') NOT NULL DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    correct_text VARCHAR(255) NULL,
    points INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_session_id INT NULL,
    user_id INT NOT NULL,
    quiz_type ENUM('Adult', 'YAYA') NOT NULL,
    started_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    status ENUM('in_progress', 'submitted', 'expired') NOT NULL DEFAULT 'in_progress',
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    total_marks INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option ENUM('A', 'B', 'C', 'D') NULL,
    answer_text VARCHAR(255) NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attempt_question (attempt_id, question_id),
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

INSERT INTO settings (setting_key, setting_value) VALUES
('quiz_duration_minutes', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO quiz_configs (quiz_type, question_count, duration_minutes, total_marks, max_attempts) VALUES
('Adult', 50, 30, 50, 1),
('YAYA', 50, 30, 50, 1)
ON DUPLICATE KEY UPDATE quiz_type = VALUES(quiz_type);

INSERT INTO admins (name, email, password_hash) VALUES
('QUIZER Admin', 'admin@quizer.local', '$2y$10$dAWjlNGz1mpDx8mEsay29OUsE5kio6Q5vcjXH5W5VzbCqk5jlpBl2X')
ON DUPLICATE KEY UPDATE email = email;
