-- =================================================
-- NUST Timetable Manager - Complete Database Schema
-- WAD621S Project - Course: Web Application Development
-- =================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS nust_timetable CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nust_timetable;

-- =================================================
-- USERS TABLE
-- =================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    program VARCHAR(50) NOT NULL,
    year_level INT DEFAULT 1,
    profile_picture VARCHAR(255) DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_logout TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_student_number (student_number),
    INDEX idx_status (status),
    INDEX idx_program (program)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================
-- COURSES TABLE
-- =================================================
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    credits INT NOT NULL,
    nqf_level INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    semester INT NOT NULL,
    year_level INT NOT NULL,
    lecturers JSON DEFAULT NULL,
    description TEXT,
    prerequisites JSON DEFAULT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    schedule_times JSON DEFAULT NULL,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_department (department),
    INDEX idx_semester (semester),
    INDEX idx_year_level (year_level),
    INDEX idx_status (status),
    FULLTEXT idx_search (code, name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================
-- SCHEDULES TABLE
-- =================================================
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    semester INT NOT NULL,
    year INT NOT NULL,
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    courses_data JSON DEFAULT NULL,
    share_token VARCHAR(100) DEFAULT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_semester_year (semester, year),
    INDEX idx_share_token (share_token),
    UNIQUE KEY unique_user_schedule (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================
-- SCHEDULE COURSES TABLE (Individual course entries)
-- =================================================
CREATE TABLE IF NOT EXISTS schedule_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    lecturer VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (course_code) REFERENCES courses(code) ON UPDATE CASCADE,
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_course_code (course_code),
    INDEX idx_day_time (day_of_week, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================
-- SCHEDULE SHARES TABLE (For sharing functionality)
-- =================================================
CREATE TABLE IF NOT EXISTS schedule_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    created_by INT NOT NULL,
    share_token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    allow_public BOOLEAN DEFAULT TRUE,
    access_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_share_token (share_token),
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================
-- SAMPLE DATA - USERS
-- =================================================
INSERT INTO users (student_number, full_name, email, password_hash, program, year_level) VALUES
('217090427', 'Nathan Duarte', 'nathan.duarte@nust.na', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '07BCSS-SD', 2),
('224041525', 'Ndilimeke Frans', 'ndilimeke.frans@nust.na', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '07BCSS-SD', 2),
('216097975', 'Enzo Jantjies', 'enzo.jantjies@nust.na', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '07BCSS-SD', 2),
('222067438', 'Eric Lubinda', 'eric.lubinda@nust.na', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '07BCSS-SD', 2);

-- =================================================
-- SAMPLE DATA - COURSES
-- =================================================
INSERT INTO courses (code, name, credits, nqf_level, department, semester, year_level, lecturers, description, venue) VALUES
('WAD621S', 'Web Application Development', 12, 6, 'Software Engineering', 2, 2, 
 '["Mrs. Josephina Muntuumo", "Mr. Wilfred Kongolo"]',
 'Full-stack web development using HTML5, CSS3, JavaScript, PHP, and MySQL. Students learn to build responsive, database-driven web applications.',
 'Software Labs'),

('DTA621S', 'Data Analytics', 12, 6, 'Informatics', 2, 2,
 '["Prof. Gloria Iyawa", "Eliazer Mbaeva"]',
 'Introduction to data analysis techniques, statistical methods, and visualization using modern tools and programming languages.',
 'Informatics Labs'),

('ITD621S', 'Interaction Design', 12, 6, 'Software Engineering', 2, 2,
 '["Dr. Gereon Koch Kapuire"]',
 'Human-computer interaction principles, user experience design, and interface prototyping.',
 'Design Studios'),

('DSA612S', 'Distributed Systems and Applications', 12, 6, 'Computer Science', 2, 2,
 '["Prof. Dharm Singh Jat"]',
 'Architecture and implementation of distributed computing systems, cloud computing, and scalable applications.',
 'Computer Labs'),

('SDN621S', 'Software Design', 12, 6, 'Software Engineering', 2, 2,
 '["Mrs. Shilumbe Chivuno-Kuria"]',
 'Software architecture patterns, design principles, and modeling techniques for complex systems.',
 'Software Labs'),

('EFC621S', 'Ethics for Computing', 10, 6, 'Cross-departmental', 2, 2,
 '["Cross-departmental"]',
 'Ethical considerations in computing, professional responsibility, privacy, security, and social impact of technology.',
 'Lecture Halls'),

('PRG510S', 'Programming 1', 12, 5, 'Computer Science', 1, 1,
 '["Dr. Memory Mukonda"]',
 'Introduction to programming concepts using Java. Object-oriented programming fundamentals.',
 'Computer Labs'),

('DST511S', 'Design Thinking', 10, 5, 'Software Engineering', 1, 1,
 '["Innovation Team"]',
 'Design thinking methodology, user-centered design, prototyping and innovation processes.',
 'Innovation Hub'),

('OOP611S', 'Object Oriented Programming', 12, 6, 'Computer Science', 1, 2,
 '["Dr. Memory Mukonda"]',
 'Advanced object-oriented programming concepts, design patterns, and software engineering principles.',
 'Computer Labs'),

('DPG621S', 'Database Programming', 12, 6, 'Informatics', 1, 2,
 '["Prof. Anicia Peters"]',
 'Database design, SQL programming, stored procedures, and database application development.',
 'Database Labs'),

('ASP611S', 'Applied Statistics and Probability', 12, 6, 'Mathematics', 1, 2,
 '["Dr. Statistics Team"]',
 'Statistical analysis, probability theory, and applications in computer science.',
 'Mathematics Labs'),

('CMN620S', 'Communication Networks', 12, 6, 'Computer Science', 1, 2,
 '["Network Team"]',
 'Network protocols, architecture, security, and administration.',
 'Network Labs'),

('ISS611S', 'Information Systems Security', 12, 6, 'Cyber Security', 1, 2,
 '["Cyber Security Team"]',
 'Information security principles, risk management, and security technologies.',
 'Security Labs'),

('EAP511S', 'English for Academic Purposes', 10, 5, 'Language Center', 1, 1,
 '["Language Team"]',
 'Academic writing, research skills, and communication for technical fields.',
 'Language Center');

-- =================================================
-- SAMPLE SCHEDULES (for testing)
-- =================================================
INSERT INTO schedules (user_id, name, description, semester, year, status, courses_data) VALUES
(1, 'Semester 2 - Final Schedule', 'My confirmed schedule for Semester 2, 2025', 2, 2025, 'active', 
 '[{"code": "WAD621S", "day": "Monday", "time": "14:00"}, {"code": "DTA621S", "day": "Tuesday", "time": "10:00"}]'),
(1, 'Semester 2 - Option B', 'Alternative schedule option', 2, 2025, 'draft',
 '[{"code": "WAD621S", "day": "Monday", "time": "14:00"}, {"code": "ITD621S", "day": "Wednesday", "time": "09:00"}]');

-- =================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =================================================

-- View for active courses with full details
CREATE OR REPLACE VIEW active_courses AS
SELECT 
    c.*,
    JSON_UNQUOTE(JSON_EXTRACT(c.lecturers, '$[0]')) as primary_lecturer
FROM courses c 
WHERE c.status = 'active';

-- View for user schedule summary
CREATE OR REPLACE VIEW user_schedule_summary AS
SELECT 
    u.id as user_id,
    u.full_name,
    s.id as schedule_id,
    s.name as schedule_name,
    s.status,
    s.semester,
    s.year,
    COUNT(sc.id) as course_count,
    s.updated_at
FROM users u
LEFT JOIN schedules s ON u.id = s.user_id
LEFT JOIN schedule_courses sc ON s.id = sc.schedule_id
GROUP BY u.id, s.id;

-- =================================================
-- STORED PROCEDURES
-- =================================================

DELIMITER //

-- Procedure to check for schedule conflicts
CREATE PROCEDURE CheckScheduleConflicts(IN p_schedule_id INT)
BEGIN
    SELECT 
        sc1.course_code as course1,
        sc2.course_code as course2,
        sc1.day_of_week,
        sc1.start_time,
        sc1.end_time,
        'Time Overlap' as conflict_type
    FROM schedule_courses sc1
    JOIN schedule_courses sc2 ON sc1.schedule_id = sc2.schedule_id
    WHERE sc1.schedule_id = p_schedule_id
      AND sc1.id != sc2.id
      AND sc1.day_of_week = sc2.day_of_week
      AND (
          (sc1.start_time <= sc2.start_time AND sc1.end_time > sc2.start_time) OR
          (sc2.start_time <= sc1.start_time AND sc2.end_time > sc1.start_time)
      );
END //

-- Procedure to get user dashboard statistics
CREATE PROCEDURE GetUserDashboardStats(IN p_user_id INT)
BEGIN
    SELECT 
        COUNT(*) as total_schedules,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_schedules,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_schedules,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_schedules
    FROM schedules 
    WHERE user_id = p_user_id;
END //

DELIMITER ;

-- =================================================
-- TRIGGERS
-- =================================================

DELIMITER //

-- Trigger to automatically set one active schedule per user
CREATE TRIGGER schedule_status_trigger
    BEFORE UPDATE ON schedules
    FOR EACH ROW
BEGIN
    IF NEW.status = 'active' AND OLD.status != 'active' THEN
        UPDATE schedules 
        SET status = 'draft' 
        WHERE user_id = NEW.user_id 
          AND id != NEW.id 
          AND status = 'active';
    END IF;
END //

-- Trigger to cleanup expired shares
CREATE TRIGGER cleanup_expired_shares
    BEFORE INSERT ON schedule_shares
    FOR EACH ROW
BEGIN
    DELETE FROM schedule_shares 
    WHERE expires_at < NOW();
END //

DELIMITER ;

-- =================================================
-- INDEXES FOR PERFORMANCE
-- =================================================

-- Composite indexes for common queries
CREATE INDEX idx_user_status_semester ON schedules(user_id, status, semester);
CREATE INDEX idx_course_dept_year ON courses(department, year_level, semester);
CREATE INDEX idx_schedule_course_lookup ON schedule_courses(schedule_id, day_of_week, start_time);

-- Full-text search index for courses
ALTER TABLE courses ADD FULLTEXT(code, name, description);

-- =================================================
-- INITIAL ADMIN DATA (Optional)
-- =================================================

-- Create an admin user (optional)
INSERT INTO users (student_number, full_name, email, password_hash, program, year_level, status) VALUES
('000000001', 'System Administrator', 'admin@nust.na', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administration', 1, 'active');

-- =================================================
-- GRANTS AND PERMISSIONS
-- =================================================

-- Create application user with limited permissions
CREATE USER IF NOT EXISTS 'nust_app'@'localhost' IDENTIFIED BY 'secure_app_password_2025';
GRANT SELECT, INSERT, UPDATE, DELETE ON nust_timetable.* TO 'nust_app'@'localhost';
GRANT EXECUTE ON nust_timetable.* TO 'nust_app'@'localhost';
FLUSH PRIVILEGES;

-- =================================================
-- DATABASE OPTIMIZATION SETTINGS
-- =================================================

-- Optimize for InnoDB
SET GLOBAL innodb_buffer_pool_size = 128M;
SET GLOBAL innodb_log_file_size = 64M;
SET GLOBAL innodb_flush_log_at_trx_commit = 2;

-- =================================================
-- MAINTENANCE PROCEDURES
-- =================================================

DELIMITER //

-- Procedure to cleanup old data
CREATE PROCEDURE MaintenanceCleanup()
BEGIN
    -- Remove expired shares
    DELETE FROM schedule_shares WHERE expires_at < NOW();
    
    -- Archive old schedules (older than 2 years)
    UPDATE schedules 
    SET status = 'archived' 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR) 
      AND status != 'archived';
    
    -- Log cleanup
    SELECT 'Maintenance cleanup completed' as message, NOW() as timestamp;
END //

DELIMITER ;

-- Schedule maintenance (if EVENT SCHEDULER is enabled)
-- CREATE EVENT IF NOT EXISTS daily_maintenance
-- ON SCHEDULE EVERY 1 DAY
-- STARTS TIMESTAMP(CURRENT_DATE, '02:00:00')
-- DO CALL MaintenanceCleanup();

-- =================================================
-- COMPLETION MESSAGE
-- =================================================
SELECT 'NUST Timetable Manager Database Setup Complete!' as message;