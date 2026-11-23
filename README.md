# University Enrollment and Academic Management System

A comprehensive web-based University Enrollment and Academic Management System developed using **Native PHP 8, MySQL, PDO, Bootstrap 5, JavaScript, and Chart.js**.

The system is designed to streamline university operations, including student enrollment, academic records, class scheduling, attendance monitoring, grade management, tuition assessment, payment tracking, and administrative reporting.

The application supports three primary user roles:

- Administrator
- Teacher
- Student

**Development Environment:** XAMPP (Windows)

**Architecture:** Native PHP application with role-based access control.

**No Composer or PHP framework required.**

## 1. Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP 8 |
| Database | MySQL |
| Database Connection | PDO |
| Frontend | HTML5, CSS3, JavaScript |
| UI Framework | Bootstrap 5 |
| Icons | Bootstrap Icons |
| Data Visualization | Chart.js |
| Asynchronous Requests | Fetch API / AJAX |
| Web Server | Apache |
| Local Environment | XAMPP |
| Code Editor | Visual Studio Code |

## 2. System Modules and Features

### Administrator Module

- Dashboard with enrollment statistics and analytics.
- Student account management.
- Teacher and employee management.
- Department management.
- Academic program management.
- Course and subject management.
- Subject prerequisite management.
- Academic year and semester management.
- Enrollment period configuration.
- Enrollment approval and rejection.
- Student document verification.
- Class section management.
- Room and class scheduling.
- Attendance monitoring.
- Grade management.
- Tuition assessment and payment monitoring.
- Announcement management.
- Notification management.
- Reports and analytics.
- University settings.
- Administrator account management.
- Activity logs and system monitoring.

### Student Module

- Student dashboard.
- Personal profile management.
- Profile picture upload.
- Password management.
- Online enrollment.
- Subject selection.
- Automatic prerequisite validation.
- Maximum unit validation.
- Subject capacity checking.
- Schedule conflict detection.
- Enrollment status tracking.
- Enrollment history.
- Document submission.
- Document verification status.
- Class schedule viewing.
- Attendance monitoring.
- Grade viewing.
- Academic performance monitoring.
- Tuition assessment.
- Payment history and balance monitoring.
- Notifications and announcements.
- Printable enrollment form.

### Teacher Module

- Teacher dashboard.
- Teacher profile management.
- Assigned subject management.
- Class list management.
- Student profile viewing.
- Student search functionality.
- Class schedule viewing.
- Attendance recording.
- Grade encoding.
- Grade management.
- Student performance monitoring.
- Class announcements.
- Attendance reports.
- Grade reports.

Teachers are restricted to their assigned classes and authorized functions. They cannot access administrator modules.

## 3. Additional System Features

### Attendance Management
Teachers can record student attendance based on their assigned classes and scheduled sessions.

Attendance statuses include:
- Present
- Absent
- Late
- Excused

### Grade Management
Teachers can encode student grades, while administrators can manage academic grading periods and grade records.

Students can view their grades after they have been officially released.

### Payment Management
The system provides tuition assessment and payment monitoring.

Features include:
- Tuition fee assessment.
- Additional fee management.
- Payment recording.
- Outstanding balance calculation.
- Payment history.
- Receipt generation.
- Payment reports.

### Class Scheduling
Administrators can assign teachers, subjects, rooms, and schedules while checking for conflicts.

### Notification Management
The system provides notifications for enrollment approvals, rejected applications, document verification, schedule changes, grade releases, and payment updates.

### Analytics and Reporting
Administrators can monitor university performance through dashboard statistics, charts, and printable reports.

## 4. Enrollment Workflow

1. Student registers and logs into the system.
2. Student completes their personal information.
3. Student uploads the required enrollment documents.
4. Student selects available subjects.
5. The system validates prerequisites, subject capacity, schedule conflicts, duplicate subjects, program compatibility, and maximum units.
6. Student submits the enrollment application.
7. Application receives Pending status.
8. Administrator reviews the application and submitted documents.
9. Administrator approves or rejects the application with remarks.
10. Student receives an enrollment notification.
11. Approved enrollment receives the appropriate tuition assessment.
12. Student can view their enrollment confirmation and print the enrollment form.
13. Approved students appear in the assigned teachers' class lists.

Rejected or cancelled applications may be corrected and resubmitted according to the enrollment rules.

## 5. Database Structure

Database name: `university_enrollment_db`

Recommended database tables:

**User and Authentication**
- users
- roles
- user_sessions

**Student and Employee Records**
- students
- teachers
- departments
- student_documents

**Academic Management**
- programs
- courses
- subjects
- prerequisites
- academic_years
- semesters
- enrollment_periods

**Enrollment Management**
- enrollments
- enrollment_details

**Class Scheduling**
- class_sections
- class_schedules
- rooms

**Attendance Management**
- attendance_sessions
- attendance_records

**Grade Management**
- grading_periods
- grades

**Payment Management**
- fee_structures
- student_assessments
- payments

**Communication and System Management**
- announcements
- notifications
- activity_logs
- university_settings

All tables should use appropriate primary keys, foreign keys, indexes, and constraints to maintain data integrity.

## 6. Installation Guide (XAMPP, Windows)

1. Download and install XAMPP with PHP 8.0 or newer.
2. Open the XAMPP Control Panel.
3. Start Apache and MySQL.
4. Copy the project folder into:

   `C:\xampp\htdocs\university-enrollment`

5. Open phpMyAdmin:

   `http://localhost/phpmyadmin`

6. Create the database named:

   `university_enrollment_db`

7. Import the updated SQL file located at:

   `database/university_enrollment_db.sql`

8. Open `config/config.php` and configure the database credentials.

9. Verify that the upload and storage directories are writable and protected against direct unauthorized access.

10. Open the application:

    `http://localhost/university-enrollment/`

11. Log in using the administrator account created during database setup.

Change all demo credentials immediately after installation.

## 7. Project Structure

```text
university-enrollment/
├── admin/
├── student/
├── teacher/
├── auth/
├── config/
│   ├── config.php
│   ├── database.php
│   └── bootstrap.php
├── includes/
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
├── storage/
│   ├── documents/
│   └── logs/
├── database/
│   └── university_enrollment_db.sql
├── download.php
├── index.php
├── setup-check.php
└── README.md
```

Each module contains its own pages and supporting files.

## 8. Security Implementation

- Password hashing using `password_hash()` and `password_verify()`.
- PDO prepared statements to prevent SQL injection.
- CSRF token validation for POST requests.
- Output escaping to prevent cross-site scripting.
- Session ID regeneration after successful login.
- Automatic logout after inactivity.
- Server-side role-based access control.
- Account status verification.
- Login attempt restrictions.
- Secure file upload validation.
- Randomized filenames for uploaded documents.
- Protected document storage.
- Permission-checked document downloads.
- Database transactions for critical operations.
- Activity logging for important user actions.
- Restricted access to administrative functions.

## 9. UI/UX Design

The application uses a modern, responsive university dashboard design.

Design specifications:
- Professional university-themed interface.
- Dark blue, white, and accent color palette.
- Responsive sidebar navigation.
- Dashboard statistics cards.
- Interactive charts.
- Modern data tables.
- Search and filtering tools.
- Responsive forms.
- Modal confirmation dialogs.
- Toast notifications.
- User profile management.
- Mobile-friendly layouts.

## 10. Reports and Export

The system supports:
- Enrollment reports.
- Student records.
- Teacher assignments.
- Attendance reports.
- Grade reports.
- Tuition and payment reports.
- Subject and class reports.
- Academic performance reports.

Export formats:
- Browser printing.
- PDF through Print to PDF.
- CSV files compatible with Microsoft Excel.

## 11. Development Phases

1. Database design and authentication.
2. Role-based dashboard implementation.
3. Student and teacher management.
4. Academic structure and subject management.
5. Enrollment processing.
6. Class scheduling.
7. Attendance monitoring.
8. Grade management.
9. Tuition and payment management.
10. Notifications and announcements.
11. Reports and analytics.
12. Security testing and system deployment.

## 12. Troubleshooting

| Problem | Solution |
|---|---|
| Database connection failed | Ensure MySQL is running and check database credentials. |
| Blank page | Check PHP errors and application logs. |
| Pages are not styled | Check the Bootstrap CDN connection. |
| Incorrect URLs | Ensure the project folder is inside `htdocs`. |
| Login problems | Verify account credentials and account status. |
| Upload errors | Check file type, file size, and folder permissions. |
| Enrollment closed | Verify the active semester and enrollment dates. |
| Scheduling conflicts | Review teacher, room, and class schedule assignments. |
| Access denied | Verify the user's role and assigned permissions. |

## 13. Important Notes Before Deployment

- Set `APP_DEBUG` to false.
- Remove or disable `setup-check.php`.
- Change all default account passwords.
- Use secure database credentials.
- Disable public access to sensitive storage directories.
- Configure HTTPS on production hosting.
- Back up the database regularly.
- Test all role permissions.
- Verify enrollment, attendance, grading, and payment calculations.
- Never use demo accounts in production.

## 14. Future Enhancements

Possible future improvements include:
- Email notifications.
- Online payment gateway integration.
- QR code-based attendance.
- Student ID card generation.
- Mobile application integration.
- Advanced academic analytics.
- Automated enrollment recommendations.
- Document verification improvements.

---

**Project:** University Enrollment and Academic Management System

**Backend:** Native PHP 8

**Database:** MySQL

**Frontend:** Bootstrap 5, HTML, CSS, JavaScript

**Development Environment:** XAMPP and Visual Studio Code

**Primary Users:** Administrator, Teacher, and Student