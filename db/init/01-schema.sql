CREATE DATABASE IF NOT EXISTS devfest_vuln
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS devfest_secure
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'devfest'@'%' IDENTIFIED BY 'devfest';
GRANT ALL PRIVILEGES ON devfest_vuln.*   TO 'devfest'@'%';
GRANT ALL PRIVILEGES ON devfest_secure.* TO 'devfest'@'%';
FLUSH PRIVILEGES;

USE devfest_vuln;

DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    full_name    VARCHAR(100) DEFAULT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    role         ENUM('user','admin') NOT NULL DEFAULT 'user',
    bio          TEXT,
    private_note TEXT,
    avatar       VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (username, password, full_name, email, role, bio, private_note) VALUES
('admin', 'Admin123!',    'Platform Admin', 'admin@pulse.local', 'admin',
 'Platform owner. I keep the lights on.',
 'Deployment checklist: rotate credentials quarterly. Admin console lives at /admin.php.'),
('alice', 'Password123!', 'Alice Mwangi',   'alice@pulse.local', 'user',
 'Frontend developer. CSS wrangler. Coffee-powered.',
 'Draft blog post: "Why I finally fell in love with container queries."'),
('bob',   'Password123!', 'Bob Otieno',     'bob@pulse.local',   'user',
 'Backend engineer. I speak fluent SQL and occasional PHP.',
 'Backup email: bob.private@example.com. Phone: +254 712 345 678. Please keep this between us.'),
('carol', 'Password123!', 'Carol Njeri',    'carol@pulse.local', 'user',
 'Product designer. Prototypes, pixels and post-it notes.',
 'Portfolio review moved to Friday at 10:00. Do not share outside the team.');

INSERT INTO feedback (user_id, message) VALUES
(2, 'Loving the new profile features!'),
(3, 'The dark theme is on point.'),
(4, 'Would love a dark mode toggle next year.');

USE devfest_secure;

DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    full_name    VARCHAR(100) DEFAULT NULL,
    email        VARCHAR(150) DEFAULT NULL,
    role         ENUM('user','admin') NOT NULL DEFAULT 'user',
    bio          TEXT,
    private_note TEXT,
    avatar       VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (username, password, full_name, email, role, bio, private_note) VALUES
('admin', '$2y$12$7EEfM/dgFvuLdTBvDPbaWuyOgGgdBv4NN9VT3a8d1XkxnXVagDDPi', 'Platform Admin', 'admin@pulse.local', 'admin',
 'Platform owner. I keep the lights on.',
 'Deployment checklist: rotate credentials quarterly. Admin console lives at /admin.php.'),
('alice', '$2y$12$40Ab/HUKANLDINEvO7w4JuLEnGgbPNsg6YPbYI1UkEuZO0F3INW7W', 'Alice Mwangi',   'alice@pulse.local', 'user',
 'Frontend developer. CSS wrangler. Coffee-powered.',
 'Draft blog post: "Why I finally fell in love with container queries."'),
('bob',   '$2y$12$fh4AzYF6JO9FRX5qlgTJZe7C9MH.b5h2Rx5qhWkEA5OOrUMTZRb4C', 'Bob Otieno',     'bob@pulse.local',   'user',
 'Backend engineer. I speak fluent SQL and occasional PHP.',
 'Backup email: bob.private@example.com. Phone: +254 712 345 678. Please keep this between us.'),
('carol', '$2y$12$dZfI2Qt.MfANxx7PC6PrYuDmq/Jz9gOwZBXX4di4.cic0b4akyXiq', 'Carol Njeri',    'carol@pulse.local', 'user',
 'Product designer. Prototypes, pixels and post-it notes.',
 'Portfolio review moved to Friday at 10:00. Do not share outside the team.');

INSERT INTO feedback (user_id, message) VALUES
(2, 'Loving the new profile features!'),
(3, 'The dark theme is on point.'),
(4, 'Would love a dark mode toggle next year.');
