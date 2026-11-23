# Test checklist (Phase 9)

Work through these in order. Log in with the demo accounts (password `Admin@12345`).

## Setup
- [ ] `setup-check.php` shows every row green (18 tables, 3 demo accounts).

## Authentication & access control
- [ ] Wrong password shows a generic error; 5 failures lock the IP for 15 minutes.
- [ ] Register a new student on the login page → gets the next student number.
- [ ] Logged in as `student1`, open `/admin/dashboard.php` → 403. Same for `teacher1`.
- [ ] Log out, press Back → redirected to login.
- [ ] Deactivate a student (Admin → Students) while they are logged in → their next click logs them out.

## Admin
- [ ] Dashboard shows live counts and both charts.
- [ ] Students / Teachers / Courses / Subjects: add, edit, search, filter, paginate, deactivate, delete (and see the protective blocks).
- [ ] Courses: circular prerequisite is rejected. Subjects: teacher/room double-booking is rejected.
- [ ] Academic: add a year and semester, set one active, set enrollment dates.
- [ ] Settings: change university name → shows on reports and the enrollment form.

## Enrollment workflow (the important one)
1. [ ] As `student1`: Enrollment → IT103 can be added (prerequisite IT102 passed).
2. [ ] IT201 is refused (needs IT103). MATH101 then ENG101 → second is refused (schedule conflict).
3. [ ] Add up to the unit limit; one more is refused. Remove a subject. Submit → status *Pending*.
4. [ ] As `admin`: bell shows a new application → Enrollments → Review → Approve.
5. [ ] As `student1`: notification received; status *Approved*; Enrollment form prints.
6. [ ] As `teacher1`: Students → the student appears in the class list; Export CSV works.
7. [ ] Repeat with a rejection (reason required) → student sees the remarks and can resubmit.
8. [ ] Set a subject's capacity to 1, approve two applications → the second approval is blocked ("no slots left").
9. [ ] Close the enrollment period (Academic) → student cannot add or submit.

## Documents, notifications, reports
- [ ] Student uploads a PDF → Admin → Documents verifies it → student is notified. Direct URL to the file under `assets/uploads/documents/` is forbidden.
- [ ] Teacher posts a class announcement → enrolled students are notified. Admin posts one to everyone.
- [ ] Reports: all four types generate, Print works, CSV opens in Excel.
- [ ] Activity logs show the actions above and can be filtered and exported.

## Responsive
- [ ] Narrow the window to phone width: sidebar becomes a slide-in menu, tables scroll sideways.
