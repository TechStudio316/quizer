USE quizer;

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

INSERT INTO quiz_configs (quiz_type, question_count, duration_minutes, total_marks, max_attempts) VALUES
('Adult', 50, 30, 50, 1),
('YAYA', 50, 30, 50, 1)
ON DUPLICATE KEY UPDATE quiz_type = VALUES(quiz_type);

-- If your MySQL version supports it, run this once for existing installations.
ALTER TABLE attempts ADD COLUMN total_marks INT NOT NULL DEFAULT 0 AFTER total_questions;

ALTER TABLE questions ADD COLUMN quiz_session_id INT NULL AFTER id;
ALTER TABLE attempts ADD COLUMN quiz_session_id INT NULL AFTER id;
ALTER TABLE questions ADD COLUMN question_type ENUM('multiple_choice', 'fill_gap') NOT NULL DEFAULT 'multiple_choice' AFTER quiz_type;
ALTER TABLE questions ADD COLUMN correct_text VARCHAR(255) NULL AFTER correct_option;
ALTER TABLE answers ADD COLUMN answer_text VARCHAR(255) NULL AFTER selected_option;
