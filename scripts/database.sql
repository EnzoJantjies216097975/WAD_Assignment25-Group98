-- Create the database
CREATE DATABASE IF NOT EXISTS nust_portal;
USE nust_portal;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    program VARCHAR(50),
    year_level INT DEFAULT 1,
    profile_picture VARCHAR(255) DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    INDEX idx_email (email),
    INDEX idx_student_number (student_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO courses (code, name, credits, nqf_level, department, semester, year_level, lecturers, description) VALUES
('WAD621S', 'Web Application Development', 12, 6, 'Software Engineering', 2, 2, 
 '["Dr. Simon Muchinenyika", "Ndinelago Nashandi"]',
 'Full-stack web development using HTML5, CSS3, JavaScript, PHP, and MySQL. Students learn to build responsive, database-driven web applications.'),

('DTA621S', 'Data Analytics', 12, 6, 'Informatics', 2, 2,
 '["Prof. Gloria Iyawa", "Eliazer Mbaeva"]',
 'Introduction to data analysis techniques, statistical methods, and visualization using modern tools and programming languages.'),

('ITD621S', 'Interaction Design', 12, 6, 'Software Engineering', 2, 2,
 '["Dr. Gereon Koch Kapuire"]',
 'Human-computer interaction principles, user experience design, and interface prototyping.'),

('DSA612S', 'Distributed Systems and Applications', 12, 6, 'Computer Science', 2, 2,
 '["Prof. Dharm Singh Jat"]',
 'Architecture and implementation of distributed computing systems, cloud computing, and scalable applications.'),

('SDN621S', 'Software Design', 12, 6, 'Software Engineering', 2, 2,
 '["Mrs. Shilumbe Chivuno-Kuria"]',
 'Software architecture patterns, design principles, and modeling techniques for complex systems.'),

('EFC621S', 'Ethics for Computing', 10, 6, 'Cross-departmental', 2, 2,
 '["Cross-departmental"]',
 'Ethical considerations in computing, professional responsibility, privacy, security, and social impact of technology.');