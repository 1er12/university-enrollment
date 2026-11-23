# University Enrollment Management System

A complete enrollment system built with **native PHP 8 (PDO), MySQL, Bootstrap 5 and Chart.js**.
Runs locally on **XAMPP**. No Composer, no framework, no GD extension required.

## 1. Installation (XAMPP, Windows)

1. Install XAMPP (PHP 8.0 or newer). Open the XAMPP Control Panel and **Start Apache and MySQL**.
2. Copy the `university-enrollment` folder into `C:\xampp\htdocs\`.
3. Open **http://localhost/phpmyadmin** → **Import** → choose `database/university_enrollment_db.sql` → **Go**.
   (This creates `university_enrollment_db` with all 18 tables and demo data. Importing again resets everything.)
4. If your MySQL root user has a password, edit `config/config.php` (`DB_USER`, `DB_PASS`).
5. Open **http://localhost/university-enrollment/setup-check.php**. Every row should be green.
6. Open **http://localhost/university-enrollment/** and log in.

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `Admin@12345` |
| Teacher | `teacher1` | `Admin@12345` |
| Student | `student1` | `Admin@12345` |

**Change these passwords** (Settings → Admin accounts, Teachers, Students, or each user's own profile page).

## 2. What is included

**Admin:** live dashboard with charts · students, teachers, courses (with prerequisites), subjects (offerings, schedules,
teacher/room conflict checks, capacity) · enrollment review queue (approve/reject with remarks, capacity re-check, notifications) ·
document verification · academic years, semesters, enrollment periods, departments, programs · announcements ·
reports (print, PDF via browser, Excel/CSV) · university settings · administrator accounts · activity logs.

**Student:** dashboard · profile + picture + password · online enrollment (validates prerequisites, capacity, schedule conflicts,
duplicates, program match, already-passed courses and the maximum units, on add and again on submit) · status tracking with admin remarks ·
history with grades · notifications and announcements · document upload with verification status · printable enrollment form.

**Teacher:** dashboard · profile · assigned subjects · class lists with search, student profile view and export · weekly schedules ·
class announcements (students are notified). Teachers cannot reach any admin page.

## 3. Enrollment workflow

Student selects subjects → system validates → **Submit** (status *pending*, admins are notified) → admin **Approve** or **Reject**
(rejection needs a reason) → student is notified → approved students appear in teachers' class lists and can print their form.
A rejected or cancelled application can be edited and resubmitted.

## 4. Security notes

Passwords use `password_hash()` / `password_verify()` · PDO prepared statements everywhere · CSRF token on every POST ·
output escaping with `e()` · session ID regenerated on login · 30-minute idle timeout · account status re-checked from the database on
every request · role checks run on the server on every protected page · login lock-out after 5 failed attempts per IP (15 minutes) ·
uploads are validated by real MIME type, size-limited, renamed randomly; student documents live in a folder that Apache blocks and are
only served through `download.php` after a permission check · activity log of important actions.

**Before using it for real:** set `APP_DEBUG` to `false` in `config/config.php`, delete `setup-check.php`, change all demo passwords,
and set a MySQL root password.

## 5. Project structure

```
admin/        dashboard, students, teachers, courses, subjects, enrollments, documents, reports, settings
student/      dashboard, profile, enrollment, enrollment-history, notifications, documents, enrollment-form
teacher/      dashboard, profile, my-subjects, students, schedules, announcements
auth/         login, logout, register
config/       config.php (settings), database.php (PDO), bootstrap.php (loaded by every page)
includes/     auth_check, functions, enrollment (business rules), upload, csv, reports, header/sidebar/footer, profile_page
assets/       css, js, images, uploads (profiles/, documents/)
database/     university_enrollment_db.sql
download.php  permission-checked document downloads
```

## 6. Troubleshooting

| Problem | Fix |
|---|---|
| Blank page or "Database connection failed" | MySQL not running, or wrong credentials in `config/config.php`. |
| Pages look unstyled | Bootstrap loads from a CDN, so an internet connection is needed. |
| Links go to wrong URL | Keep the folder name `university-enrollment` directly under `htdocs`; the base URL is auto-detected. |
| Locked out after failed logins | In phpMyAdmin run: `DELETE FROM activity_logs WHERE action='login_failed';` |
| Upload fails | Files must be JPG/PNG (or PDF for documents) under 2 MB; `assets/uploads` must be writable. |
| Students see "enrollment closed" | Admin → Academic: make sure a semester is **Active** and today is inside its enrollment period. |

## 7. Notes

* **PDF:** reports and the enrollment form use *Print → Save as PDF* in the browser (no PDF library needed).
* **Excel:** exports are UTF-8 CSV files, which Excel opens directly.
* Student numbers (`2026-00001`) and employee numbers (`EMP-0001`) are generated automatically.
