# 🎓 CBT System — Setup Guide (Step by Step for Beginners)

## What You Get

| File/Folder | Purpose |
|---|---|
| `cbt_database.sql` | Run this in phpMyAdmin to create all tables |
| `includes/db.php` | Database connection config (edit your credentials here) |
| `admin/index.html` | Admin dashboard (runs in browser) |
| `admin/api.php` | Admin back-end (PHP, handles all admin actions) |
| `student/index.html` | Student portal (runs in browser) |
| `student/api.php` | Student back-end (PHP, handles quiz, results, auth) |

---

## STEP 1 — Install XAMPP (if you haven't)

1. Download XAMPP from https://www.apachefriends.org
2. Install it (all defaults are fine)
3. Open **XAMPP Control Panel**
4. Click **Start** next to **Apache** and **MySQL**
5. Both should turn green ✅

---

## STEP 2 — Copy Project Files

1. Open your XAMPP folder. On Windows it is usually: `C:\xampp\htdocs\`
2. Create a new folder called **cbt_system** inside htdocs
3. Copy ALL files into it, keeping the folder structure:

```
C:\xampp\htdocs\cbt_system\
├── cbt_database.sql
├── includes\
│   └── db.php
├── admin\
│   ├── index.html
│   └── api.php
├── student\
│   ├── index.html
│   └── api.php
└── uploads\          (leave empty, used internally)
```

---

## STEP 3 — Create the Database in phpMyAdmin

1. Open your browser and go to: http://localhost/phpmyadmin
2. Click **Import** tab at the top
3. Click **Choose File** and select `cbt_database.sql`
4. Scroll down and click **Go**
5. You should see: *Import has been successfully finished*
6. You will now see a database called **cbt_system** in the left panel ✅

---

## STEP 4 — Edit Database Credentials (if needed)

Open `includes/db.php` in Notepad and check these lines:

```php
define('DB_HOST', 'localhost');   // Usually fine as-is
define('DB_USER', 'root');        // Default XAMPP username
define('DB_PASS', '');            // Default XAMPP password (empty)
define('DB_NAME', 'cbt_system');  // Must match the database you imported
```

If your MySQL username or password is different, update them here.

---

## STEP 5 — Open the Admin Dashboard

Go to: **http://localhost/cbt_system/admin/index.html**

Login with:
- **Email:** baba.aminu@udusok.edu.ng
- **Password:** admin

---

## STEP 6 — Open the Student Portal

Go to: **http://localhost/cbt_system/student/index.html**

Students register here with their Admission Number, Name, Email and Password.

---

## HOW TO USE THE ADMIN DASHBOARD

### ➕ Add Subjects
1. Click **Subjects** in the sidebar
2. Enter name (e.g. Mathematics), code (e.g. MTH101), duration (e.g. 30 minutes)
3. Click **Add Subject**

Suggested subjects:
| Name | Code |
|---|---|
| Mathematics | MTH101 |
| Physics | PHY101 |
| Chemistry | CHM101 |
| Biology | BIO101 |
| English | ENG101 |

---

### 📤 Upload Questions (via CSV)

Create a CSV file in Excel with these **exact column headers** (Row 1):

```
Question,Opt1,Opt2,Opt3,Opt4,correct,mark
```

Example rows:
```
What is 2+2?,1,2,3,4,4,1
What is H2O?,Water,Fire,Soil,Air,1,2
```

- **correct** = the number of the correct option (1, 2, 3, or 4)
- **mark** = how many marks the question is worth

**In Excel:**
1. Create a new spreadsheet
2. Row 1: Question | Opt1 | Opt2 | Opt3 | Opt4 | correct | mark
3. Fill in your questions
4. Save as **CSV (Comma delimited)**
5. In Admin → Questions → Upload, choose the subject, then upload the file

The system will:
- ✅ Validate each row (empty fields, correct option range, mark value)
- ✅ Show how many questions were successfully uploaded
- ⚠️ List any rows that had errors

---

### 👥 Batch Register Students

Create a CSV file:
```
Adm no,Name
UDU/SCI/19/001,Aisha Mohammed
UDU/SCI/19/002,Emeka Okafor
```

In Admin → Students: pick the subject, upload the CSV.
Students will be registered AND enrolled into that subject automatically.

> ⚠️ Students must also register themselves on the Student Portal to set a password before they can log in to take quizzes.

---

### ✏️ Edit / Delete Questions

1. Go to Admin → Questions
2. Select the subject from the dropdown
3. Click ✏ to edit a question or 🗑 to delete it

---

### 📋 View & Export Results

1. Go to Admin → Results
2. Filter by subject and/or student
3. Click **⬇ Export CSV** to download results

---

## HOW THE STUDENT PORTAL WORKS

### First-Time Students
1. Go to http://localhost/cbt_system/student/index.html
2. Click **Register**
3. Enter Admission Number (must match what admin uploaded), Name, Email, Password
4. Click **Create Account**
5. Log in with Admission Number + Password

### Taking a Quiz
1. Log in
2. On the Dashboard, enrolled subjects appear as cards
3. Click **▶ Start Quiz** on any subject
4. Questions are shown in **random order**, with **options randomized** too
5. The **timer** counts down at the top
6. When time runs out, quiz is **auto-submitted**
7. Only **one attempt** per subject is allowed
8. After submission: Score, Percentage, and Grade (A–F) are shown

### Viewing Results
- Click **My Results** tab on the Dashboard
- Filter by subject or view all
- Results show: Subject, Score, Total, Percentage, Grade, Date

---

## GRADE SCALE

| Grade | Percentage |
|---|---|
| A | 70% and above |
| B | 60% – 69% |
| C | 50% – 59% |
| D | 45% – 49% |
| E | 40% – 44% |
| F | Below 40% |

---

## ADJUSTING THE QUIZ TIMER

In Admin → Subjects, click **⏱ Timer** next to any subject to change its duration.
The change applies to all future quiz attempts for that subject.

---

## TROUBLESHOOTING

| Problem | Fix |
|---|---|
| Blank page or 404 error | Make sure Apache and MySQL are running in XAMPP |
| "Database connection failed" | Check `includes/db.php` credentials |
| Login not working | Make sure you imported `cbt_database.sql` correctly |
| Questions not uploading | Check your CSV headers exactly match: Question,Opt1,Opt2,Opt3,Opt4,correct,mark |
| Student can't log in | Make sure they registered on the student portal (the batch upload doesn't set a password) |
| PHP errors showing | Check your PHP version in XAMPP (PHP 7.4+ required) |

---

## SECURITY NOTES (for production use)

If you deploy this online (not just locally), you should:
1. Change the admin password — update the hash in the `admins` table in phpMyAdmin
   - Generate a new hash: use PHP's `password_hash('yourpassword', PASSWORD_DEFAULT)`
2. Add HTTPS to your server
3. Change MySQL `root` password
4. Consider adding CSRF protection to all forms

---

*Built for UDUSOK Computer-Based Testing System*
