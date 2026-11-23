-- =====================================================================
-- University Enrollment Management System
-- Database: university_enrollment_db      (import via phpMyAdmin)
-- WARNING: re-importing DROPS and recreates all tables (development reset).
--
-- Demo logins (all use password:  Admin@12345 )
--   admin   / teacher1  / student1     -> change these after first login!
--
-- Model notes
--   courses  = catalog entries (code, title, units, prerequisites)
--   subjects = a course OFFERED in a specific semester (teacher, section,
--              capacity). Students enroll in subjects.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS `university_enrollment_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `university_enrollment_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs, system_settings, student_documents, announcements,
  notifications, enrollment_details, enrollments, subject_schedules, subjects,
  course_prerequisites, courses, semesters, academic_years, students, teachers,
  programs, departments, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------- users
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','teacher','student') NOT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login    DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------- departments
CREATE TABLE departments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(20)  NOT NULL,
  name        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_departments_code (code)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- programs
CREATE TABLE programs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  code          VARCHAR(20)  NOT NULL,
  name          VARCHAR(150) NOT NULL,
  duration_years TINYINT UNSIGNED NOT NULL DEFAULT 4,   -- number of year levels
  description   TEXT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_programs_code (code),
  KEY idx_programs_department (department_id),
  CONSTRAINT fk_programs_department FOREIGN KEY (department_id)
    REFERENCES departments(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -------------------------------------------------------------- teachers
CREATE TABLE teachers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  employee_no     VARCHAR(30)  NOT NULL,
  first_name      VARCHAR(60)  NOT NULL,
  middle_name     VARCHAR(60)  NULL,
  last_name       VARCHAR(60)  NOT NULL,
  gender          ENUM('male','female','other') NULL,
  birth_date      DATE NULL,
  phone           VARCHAR(30)  NULL,
  address         VARCHAR(255) NULL,
  department_id   INT UNSIGNED NULL,
  profile_picture VARCHAR(255) NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teachers_user (user_id),
  UNIQUE KEY uq_teachers_empno (employee_no),
  KEY idx_teachers_name (last_name, first_name),
  KEY idx_teachers_department (department_id),
  CONSTRAINT fk_teachers_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_teachers_department FOREIGN KEY (department_id)
    REFERENCES departments(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------- students
CREATE TABLE students (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  student_no      VARCHAR(20)  NOT NULL,              -- e.g. 2026-00001
  first_name      VARCHAR(60)  NOT NULL,
  middle_name     VARCHAR(60)  NULL,
  last_name       VARCHAR(60)  NOT NULL,
  gender          ENUM('male','female','other') NULL,
  birth_date      DATE NULL,
  phone           VARCHAR(30)  NULL,
  address         VARCHAR(255) NULL,
  program_id      INT UNSIGNED NOT NULL,
  year_level      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  profile_picture VARCHAR(255) NULL,
  status          ENUM('active','inactive','graduated') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_user (user_id),
  UNIQUE KEY uq_students_no (student_no),
  KEY idx_students_name (last_name, first_name),
  KEY idx_students_program_year (program_id, year_level),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_students_program FOREIGN KEY (program_id)
    REFERENCES programs(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------- academic_years
CREATE TABLE academic_years (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(20) NOT NULL,                    -- e.g. 2026-2027
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_years_name (name),
  CONSTRAINT chk_ay_dates CHECK (end_date > start_date)
) ENGINE=InnoDB;

-- ------------------------------------------------------------ semesters
CREATE TABLE semesters (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id  INT UNSIGNED NOT NULL,
  semester_no       TINYINT UNSIGNED NOT NULL,        -- 1, 2, 3 (summer)
  name              VARCHAR(50) NOT NULL,
  start_date        DATE NOT NULL,
  end_date          DATE NOT NULL,
  enrollment_start  DATE NULL,
  enrollment_end    DATE NULL,
  max_units         TINYINT UNSIGNED NOT NULL DEFAULT 24,
  is_active         TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_semesters_ay_no (academic_year_id, semester_no),
  KEY idx_semesters_active (is_active),
  CONSTRAINT fk_semesters_ay FOREIGN KEY (academic_year_id)
    REFERENCES academic_years(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------- courses
CREATE TABLE courses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  program_id  INT UNSIGNED NULL,                      -- NULL = general/shared course
  code        VARCHAR(20)  NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NULL,
  units       TINYINT UNSIGNED NOT NULL DEFAULT 3,
  year_level  TINYINT UNSIGNED NULL,
  status      ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_courses_code (code),
  KEY idx_courses_program (program_id),
  CONSTRAINT fk_courses_program FOREIGN KEY (program_id)
    REFERENCES programs(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------- course_prerequisites
CREATE TABLE course_prerequisites (
  course_id        INT UNSIGNED NOT NULL,
  prerequisite_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (course_id, prerequisite_id),
  CONSTRAINT fk_prereq_course FOREIGN KEY (course_id)
    REFERENCES courses(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_prereq_required FOREIGN KEY (prerequisite_id)
    REFERENCES courses(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- subjects
CREATE TABLE subjects (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id    INT UNSIGNED NOT NULL,
  semester_id  INT UNSIGNED NOT NULL,
  teacher_id   INT UNSIGNED NULL,
  section      VARCHAR(20) NOT NULL DEFAULT 'A',
  max_students SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  status       ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subjects_offering (course_id, semester_id, section),
  KEY idx_subjects_semester (semester_id),
  KEY idx_subjects_teacher (teacher_id),
  CONSTRAINT fk_subjects_course FOREIGN KEY (course_id)
    REFERENCES courses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id)
    REFERENCES semesters(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id)
    REFERENCES teachers(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------- subject_schedules
CREATE TABLE subject_schedules (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id  INT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  room        VARCHAR(50) NULL,
  KEY idx_schedules_subject (subject_id),
  KEY idx_schedules_day_time (day_of_week, start_time, end_time),
  CONSTRAINT fk_schedules_subject FOREIGN KEY (subject_id)
    REFERENCES subjects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_schedule_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------- enrollments
-- One enrollment application per student per semester.
CREATE TABLE enrollments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id   INT UNSIGNED NOT NULL,
  semester_id  INT UNSIGNED NOT NULL,
  status       ENUM('draft','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
  total_units  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  reviewed_by  INT UNSIGNED NULL,                      -- users.id of the admin
  reviewed_at  DATETIME NULL,
  remarks      TEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enrollments_student_sem (student_id, semester_id),
  KEY idx_enrollments_status (status),
  KEY idx_enrollments_semester (semester_id),
  CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id)
    REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_semester FOREIGN KEY (semester_id)
    REFERENCES semesters(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_enrollments_reviewer FOREIGN KEY (reviewed_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------- enrollment_details
CREATE TABLE enrollment_details (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED NOT NULL,
  units         TINYINT UNSIGNED NOT NULL,             -- copied from course at selection time
  result        ENUM('enrolled','passed','failed','dropped') NOT NULL DEFAULT 'enrolled',
  grade         DECIMAL(4,2) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_details_enrollment_subject (enrollment_id, subject_id),
  KEY idx_details_subject (subject_id),
  CONSTRAINT fk_details_enrollment FOREIGN KEY (enrollment_id)
    REFERENCES enrollments(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_details_subject FOREIGN KEY (subject_id)
    REFERENCES subjects(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -------------------------------------------------------- notifications
CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  title      VARCHAR(150) NOT NULL,
  message    TEXT NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_read (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------- announcements
CREATE TABLE announcements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id  INT UNSIGNED NOT NULL,                    -- users.id
  subject_id INT UNSIGNED NULL,                        -- NULL = system-wide
  audience   ENUM('all','students','teachers') NOT NULL DEFAULT 'all',
  title      VARCHAR(150) NOT NULL,
  body       TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_announcements_subject (subject_id),
  KEY idx_announcements_created (created_at),
  CONSTRAINT fk_announcements_author FOREIGN KEY (author_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_announcements_subject FOREIGN KEY (subject_id)
    REFERENCES subjects(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------ student_documents
CREATE TABLE student_documents (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    INT UNSIGNED NOT NULL,
  document_type VARCHAR(60)  NOT NULL,                 -- e.g. Birth Certificate
  original_name VARCHAR(255) NOT NULL,
  file_path     VARCHAR(255) NOT NULL,                 -- random stored filename
  status        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  remarks       VARCHAR(255) NULL,
  verified_by   INT UNSIGNED NULL,
  verified_at   DATETIME NULL,
  uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_documents_student (student_id),
  KEY idx_documents_status (status),
  CONSTRAINT fk_documents_student FOREIGN KEY (student_id)
    REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_documents_verifier FOREIGN KEY (verified_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------- activity_logs
CREATE TABLE activity_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,                   -- e.g. login, enrollment_approved
  description VARCHAR(255) NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_logs_user (user_id),
  KEY idx_logs_action (action),
  KEY idx_logs_created (created_at),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------ system_settings
CREATE TABLE system_settings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  description   VARCHAR(255) NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================
SET @pw = '$2b$12$N.XjzcMXCsjF0.q9d/pPHefwMSSkdiveGkB2jrBNIvY09u8iB8gKm';   -- bcrypt of "Admin@12345"

INSERT INTO users (id, username, email, password_hash, role) VALUES
 (1, 'admin',    'admin@university.local',    @pw, 'admin'),
 (2, 'teacher1', 'teacher1@university.local', @pw, 'teacher'),
 (3, 'student1', 'student1@university.local', @pw, 'student');

INSERT INTO departments (id, code, name, description) VALUES
 (1, 'CCS', 'College of Computer Studies', 'Computing and information technology programs'),
 (2, 'CAS', 'College of Arts and Sciences', 'General education and sciences');

INSERT INTO programs (id, department_id, code, name, duration_years) VALUES
 (1, 1, 'BSIT', 'Bachelor of Science in Information Technology', 4),
 (2, 1, 'BSCS', 'Bachelor of Science in Computer Science', 4);

INSERT INTO teachers (id, user_id, employee_no, first_name, last_name, gender, department_id, phone) VALUES
 (1, 2, 'EMP-0001', 'Maria', 'Santos', 'female', 1, '09170000001');

INSERT INTO students (id, user_id, student_no, first_name, last_name, gender, birth_date, program_id, year_level, phone) VALUES
 (1, 3, '2026-00001', 'Juan', 'Dela Cruz', 'male', '2007-05-14', 1, 1, '09170000002');

INSERT INTO academic_years (id, name, start_date, end_date, is_active) VALUES
 (1, '2026-2027', '2026-08-01', '2027-07-31', 1);

INSERT INTO semesters (id, academic_year_id, semester_no, name, start_date, end_date, enrollment_start, enrollment_end, max_units, is_active) VALUES
 (1, 1, 1, 'First Semester',  '2026-08-03', '2026-12-18', '2026-07-01', '2026-08-14', 24, 0),
 (2, 1, 2, 'Second Semester', '2027-01-11', '2027-05-28', '2026-11-01', '2027-02-28', 24, 1);

INSERT INTO courses (id, program_id, code, title, description, units, year_level) VALUES
 (1, 1,    'IT101',   'Introduction to Computing',  'Foundations of computing and IT.', 3, 1),
 (2, 1,    'IT102',   'Computer Programming 1',     'Programming fundamentals.',        3, 1),
 (3, 1,    'IT103',   'Computer Programming 2',     'Intermediate programming.',        3, 1),
 (4, NULL, 'MATH101', 'College Algebra',            'Algebra for college students.',    3, 1),
 (5, NULL, 'ENG101',  'Communication Skills',       'Academic reading and writing.',    3, 1),
 (6, 1,    'IT201',   'Data Structures',            'Lists, stacks, queues, trees.',    3, 2);

INSERT INTO course_prerequisites (course_id, prerequisite_id) VALUES (3, 2), (6, 3);

INSERT INTO subjects (id, course_id, semester_id, teacher_id, section, max_students) VALUES
 (1, 1, 1, 1, 'A', 40),   -- Sem 1: IT101
 (2, 2, 1, 1, 'A', 40),   -- Sem 1: IT102
 (3, 3, 2, 1, 'A', 40),   -- Sem 2: IT103 (prereq IT102)
 (4, 4, 2, 1, 'A', 40),   -- Sem 2: MATH101  Tue/Thu 10:00-11:30
 (5, 5, 2, 1, 'A', 30),   -- Sem 2: ENG101   Tue/Thu 11:00-12:30  (overlaps MATH101 on purpose)
 (6, 6, 2, 1, 'A', 35);   -- Sem 2: IT201 (prereq IT103, demo student lacks it)

INSERT INTO subject_schedules (subject_id, day_of_week, start_time, end_time, room) VALUES
 (1, 'Monday',    '08:00:00', '09:30:00', 'Room 101'),
 (1, 'Wednesday', '08:00:00', '09:30:00', 'Room 101'),
 (2, 'Tuesday',   '13:00:00', '14:30:00', 'Lab 1'),
 (2, 'Thursday',  '13:00:00', '14:30:00', 'Lab 1'),
 (3, 'Monday',    '08:00:00', '09:30:00', 'Lab 1'),
 (3, 'Wednesday', '08:00:00', '09:30:00', 'Lab 1'),
 (4, 'Tuesday',   '10:00:00', '11:30:00', 'Room 202'),
 (4, 'Thursday',  '10:00:00', '11:30:00', 'Room 202'),
 (5, 'Tuesday',   '11:00:00', '12:30:00', 'Room 203'),
 (5, 'Thursday',  '11:00:00', '12:30:00', 'Room 203'),
 (6, 'Friday',    '13:00:00', '16:00:00', 'Lab 2');

-- Demo student already passed Semester 1 (IT101 + IT102) -> tests prerequisites & history
INSERT INTO enrollments (id, student_id, semester_id, status, total_units, submitted_at, reviewed_by, reviewed_at, remarks) VALUES
 (1, 1, 1, 'approved', 6, '2026-07-20 09:00:00', 1, '2026-07-21 10:00:00', 'Approved. Welcome!');
INSERT INTO enrollment_details (enrollment_id, subject_id, units, result, grade) VALUES
 (1, 1, 3, 'passed', 1.75),
 (1, 2, 3, 'passed', 2.00);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
 ('university_name',    'Sample State University',      'Displayed in headers and reports'),
 ('university_address', 'Balamban, Cebu, Philippines',  'Displayed on enrollment forms'),
 ('university_email',   'registrar@university.local',   'Contact email'),
 ('university_phone',   '(032) 000-0000',               'Contact phone'),
 ('default_max_units',  '24',                           'Fallback maximum units per semester'),
 ('student_no_prefix',  '2026',                         'Year prefix for generated student numbers');

INSERT INTO notifications (user_id, type, title, message) VALUES
 (3, 'info', 'Welcome!', 'Your student account is ready. Enrollment for the Second Semester is now open.');

INSERT INTO announcements (author_id, audience, title, body) VALUES
 (1, 'all', 'Second Semester Enrollment Open', 'Enrollment for the Second Semester 2026-2027 runs until February 28, 2027.');

INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
 (1, 'system_install', 'Database installed with seed data', '127.0.0.1');
