-- University Timetable Manager Database Schema
-- For NUST Faculty of Computing and Informatics

CREATE DATABASE IF NOT EXISTS nust_timetable;
USE nust_timetable;

-- Users table for student accounts
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    student_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    year_of_study INT DEFAULT 1,
    program VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Courses/Modules table
CREATE TABLE courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(10) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) DEFAULT 'Computing and Informatics',
    credits INT DEFAULT 3,
    year_level INT DEFAULT 1,
    semester INT DEFAULT 1,
    theory_lecturer VARCHAR(100),
    practical_lecturer VARCHAR(100),
    color_code VARCHAR(7) DEFAULT '#FFA500', -- Default to NUST orange
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Venues/Rooms table
CREATE TABLE venues (
    venue_id INT PRIMARY KEY AUTO_INCREMENT,
    venue_code VARCHAR(20) UNIQUE NOT NULL,
    venue_name VARCHAR(100),
    building VARCHAR(50),
    capacity INT,
    venue_type ENUM('Lecture Hall', 'Computer Lab', 'Tutorial Room') DEFAULT 'Lecture Hall'
);

-- Time slots table
CREATE TABLE time_slots (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_type ENUM('Regular', 'Part-time', 'Lunch') DEFAULT 'Regular',
    UNIQUE KEY unique_slot (day_of_week, start_time)
);

-- Schedules table (main timetables)
CREATE TABLE schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    schedule_name VARCHAR(100) NOT NULL,
    semester INT DEFAULT 1,
    year INT DEFAULT 2025,
    is_active BOOLEAN DEFAULT TRUE,
    share_token VARCHAR(32) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Schedule items (individual classes in a schedule)
CREATE TABLE schedule_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_id INT NOT NULL,
    course_id INT NOT NULL,
    slot_id INT NOT NULL,
    venue_id INT,
    class_type ENUM('Theory', 'Practical') NOT NULL,
    duration INT DEFAULT 1, -- Number of consecutive slots (1 for theory, 2 for practical)
    lecturer_name VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id),
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id),
    UNIQUE KEY unique_schedule_slot (schedule_id, slot_id)
);

-- Schedule versions (for tracking changes)
CREATE TABLE schedule_versions (
    version_id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_id INT NOT NULL,
    version_number INT NOT NULL,
    version_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE
);

-- Insert default time slots
INSERT INTO time_slots (day_of_week, start_time, end_time, slot_type) VALUES
-- Monday slots
('Monday', '07:30:00', '08:30:00', 'Regular'),
('Monday', '08:30:00', '09:30:00', 'Regular'),
('Monday', '09:30:00', '10:30:00', 'Regular'),
('Monday', '10:30:00', '11:30:00', 'Regular'),
('Monday', '11:30:00', '12:30:00', 'Regular'),
('Monday', '12:30:00', '13:30:00', 'Regular'),
('Monday', '13:30:00', '14:00:00', 'Lunch'),
('Monday', '14:00:00', '15:00:00', 'Regular'),
('Monday', '15:00:00', '16:00:00', 'Regular'),
('Monday', '17:15:00', '18:15:00', 'Part-time'),
('Monday', '18:40:00', '19:40:00', 'Part-time'),
('Monday', '20:00:00', '21:00:00', 'Part-time'),

-- Tuesday slots (repeat pattern)
('Tuesday', '07:30:00', '08:30:00', 'Regular'),
('Tuesday', '08:30:00', '09:30:00', 'Regular'),
('Tuesday', '09:30:00', '10:30:00', 'Regular'),
('Tuesday', '10:30:00', '11:30:00', 'Regular'),
('Tuesday', '11:30:00', '12:30:00', 'Regular'),
('Tuesday', '12:30:00', '13:30:00', 'Regular'),
('Tuesday', '13:30:00', '14:00:00', 'Lunch'),
('Tuesday', '14:00:00', '15:00:00', 'Regular'),
('Tuesday', '15:00:00', '16:00:00', 'Regular'),
('Tuesday', '17:15:00', '18:15:00', 'Part-time'),
('Tuesday', '18:40:00', '19:40:00', 'Part-time'),
('Tuesday', '20:00:00', '21:00:00', 'Part-time'),

-- Wednesday slots
('Wednesday', '07:30:00', '08:30:00', 'Regular'),
('Wednesday', '08:30:00', '09:30:00', 'Regular'),
('Wednesday', '09:30:00', '10:30:00', 'Regular'),
('Wednesday', '10:30:00', '11:30:00', 'Regular'),
('Wednesday', '11:30:00', '12:30:00', 'Regular'),
('Wednesday', '12:30:00', '13:30:00', 'Regular'),
('Wednesday', '13:30:00', '14:00:00', 'Lunch'),
('Wednesday', '14:00:00', '15:00:00', 'Regular'),
('Wednesday', '15:00:00', '16:00:00', 'Regular'),
('Wednesday', '17:15:00', '18:15:00', 'Part-time'),
('Wednesday', '18:40:00', '19:40:00', 'Part-time'),
('Wednesday', '20:00:00', '21:00:00', 'Part-time'),

-- Thursday slots
('Thursday', '07:30:00', '08:30:00', 'Regular'),
('Thursday', '08:30:00', '09:30:00', 'Regular'),
('Thursday', '09:30:00', '10:30:00', 'Regular'),
('Thursday', '10:30:00', '11:30:00', 'Regular'),
('Thursday', '11:30:00', '12:30:00', 'Regular'),
('Thursday', '12:30:00', '13:30:00', 'Regular'),
('Thursday', '13:30:00', '14:00:00', 'Lunch'),
('Thursday', '14:00:00', '15:00:00', 'Regular'),
('Thursday', '15:00:00', '16:00:00', 'Regular'),
('Thursday', '17:15:00', '18:15:00', 'Part-time'),
('Thursday', '18:40:00', '19:40:00', 'Part-time'),
('Thursday', '20:00:00', '21:00:00', 'Part-time'),

-- Friday slots
('Friday', '07:30:00', '08:30:00', 'Regular'),
('Friday', '08:30:00', '09:30:00', 'Regular'),
('Friday', '09:30:00', '10:30:00', 'Regular'),
('Friday', '10:30:00', '11:30:00', 'Regular'),
('Friday', '11:30:00', '12:30:00', 'Regular'),
('Friday', '12:30:00', '13:30:00', 'Regular'),
('Friday', '13:30:00', '14:00:00', 'Lunch'),
('Friday', '14:00:00', '15:00:00', 'Regular'),
('Friday', '15:00:00', '16:00:00', 'Regular'),
('Friday', '17:15:00', '18:15:00', 'Part-time'),
('Friday', '18:40:00', '19:40:00', 'Part-time'),
('Friday', '20:00:00', '21:00:00', 'Part-time');

-- Insert sample NUST Computing courses
INSERT INTO courses (course_code, course_name, year_level, semester, theory_lecturer, practical_lecturer, color_code) VALUES
('WAD621S', 'Web Application Development', 3, 2, 'Mrs. Josephina Muntuumo', 'Mr. Wilfred Kongolo', '#FF6B35'),
('DSA521S', 'Data Structures and Algorithms', 2, 1, 'Dr. John Smith', 'Mr. Peter Jones', '#4A90E2'),
('OOP621S', 'Object Oriented Programming', 2, 2, 'Mr. James Wilson', 'Ms. Sarah Brown', '#7B68EE'),
('DBS521S', 'Database Systems', 2, 1, 'Dr. Mary Johnson', 'Dr. Mary Johnson', '#2ECC71'),
('NET621S', 'Computer Networks', 3, 2, 'Mr. Robert Davis', 'Ms. Linda Martinez', '#E74C3C'),
('SWE721S', 'Software Engineering', 3, 1, 'Dr. Michael Chen', 'Mr. David Lee', '#F39C12'),
('AIT521S', 'Artificial Intelligence', 3, 1, 'Dr. Emma Watson', 'Dr. Emma Watson', '#9B59B6'),
('CYS621S', 'Cybersecurity', 3, 2, 'Mr. Alex Thompson', 'Ms. Jessica Taylor', '#1ABC9C'),
('MOB721S', 'Mobile Application Development', 4, 1, 'Mr. Daniel White', 'Mr. Daniel White', '#34495E'),
('CMP411S', 'Compiler Construction', 4, 1, 'Dr. Susan Clark', 'Dr. Susan Clark', '#E67E22');

-- Insert sample venues
INSERT INTO venues (venue_code, venue_name, building, capacity, venue_type) VALUES
('LH1', 'Lecture Hall 1', 'Main Building', 150, 'Lecture Hall'),
('LH2', 'Lecture Hall 2', 'Main Building', 120, 'Lecture Hall'),
('CL1', 'Computer Lab 1', 'IT Building', 40, 'Computer Lab'),
('CL2', 'Computer Lab 2', 'IT Building', 40, 'Computer Lab'),
('CL3', 'Computer Lab 3', 'IT Building', 35, 'Computer Lab'),
('TR1', 'Tutorial Room 1', 'Science Building', 30, 'Tutorial Room'),
('TR2', 'Tutorial Room 2', 'Science Building', 30, 'Tutorial Room'),
('AUD', 'Auditorium', 'Main Building', 300, 'Lecture Hall'),
('CL4', 'Computer Lab 4', 'IT Building', 45, 'Computer Lab'),
('CL5', 'Computer Lab 5', 'IT Building', 50, 'Computer Lab');

-- Create indexes for better performance
CREATE INDEX idx_schedule_user ON schedules(user_id);
CREATE INDEX idx_schedule_items_schedule ON schedule_items(schedule_id);
CREATE INDEX idx_schedule_items_course ON schedule_items(course_id);
CREATE INDEX idx_time_slots_day ON time_slots(day_of_week);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_courses_code ON courses(course_code);