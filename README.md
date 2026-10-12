# Online Examination System

A complete online exam website written in plain PHP. Admins create exams and questions; students register, enroll, take the exam against a live countdown timer, and see their marks (with a PDF result).

No Docker, no MySQL, no database setup. Data is kept in one small file.

## How to run it (3 steps)

You need a computer with **Ubuntu / Debian Linux** (or macOS, or Windows with WSL) and an internet connection.

**1. Open a terminal in the project folder.**
If you received a `.zip` file, unzip it first, then go into the folder:

```bash
unzip online-exam-system.zip
cd online-exam-system
```

(Run `ls` - you should see `start-local.sh`, `www`, `db` and `README.md`. If you do not, you are in the wrong folder.)

**2. Start it:**

```bash
bash start-local.sh
```

The first time, it installs PHP for you if it is missing. Your computer password may be asked once (nothing shows while you type - that is normal). Then it creates the database and prints the addresses.

**3. Open it in your browser:**

| What | Address |
|---|---|
| Student site | <http://localhost:8000> |
| Admin panel | <http://localhost:8000/master/login.php> |

Leave the terminal open while you use the site. **Stop** it with `Ctrl + C`. Start it again with the same command - your data is kept.

> The site loads its styling from the internet (Bootstrap, jQuery, DataTables), so the browser needs internet access. Without it the pages look plain and some buttons will not work.

### Other systems

- **macOS:** run `brew install php` once (Homebrew: <https://brew.sh>), then the steps above.
- **Windows:** install WSL (`wsl --install` in PowerShell, then open "Ubuntu"), go to the project folder inside Ubuntu (Windows drives are under `/mnt/c/...`) and follow the same steps.

### Handy options

```bash
PORT=9000 bash start-local.sh                    # use another port if 8000 is busy
APP_TIMEZONE=Asia/Kolkata bash start-local.sh    # exam times in your own time zone
rm data/exam.db                                  # (while stopped) wipe everything and start fresh
```

## Login details

| Who | Where | E-mail | Password |
|---|---|---|---|
| Master admin (created automatically) | `/master/login.php` | `admin@example.com` | `Admin@12345` |
| Students | `/login.php` | whatever they register with | whatever they choose |

**Change the admin password at first login** (Admin panel -> *Change Password*).

## Try it end to end (5 minutes)

Use two browser windows (or one normal and one private window) so admin and student stay logged in separately.

1. **Admin:** open the admin panel and log in. On the dashboard click **Create demo exam**. It makes a ready-made 5-question exam that starts in a few minutes (you can type a smaller number, minimum 2).
2. **Student:** open the student site, click **Register**, fill in the form (a profile picture is required) and submit.
3. **Verify the student.** There is no real mail server, so: Admin panel -> **Users** -> click **Verify** next to the student. (The verification link is also saved in `www/logs/mail.log`.)
4. **Student:** log in, choose the demo exam on the home page and click **Enroll it** *before* it starts.
5. When the start time arrives: **Enroll Exam** -> **View Exam**. Answer the questions (each click is saved); use *Next / Previous* or the numbered buttons.
6. When the timer ends the exam completes by itself. The student sees marks per question and can open the **PDF**; the admin sees the ranked list under *Exams -> Results* and can export it as CSV.

**Making your own exam:** Admin -> *Exams* -> fill in the form -> *Create exam* -> **Questions** -> add every question. The exam becomes available to students as soon as the last question is added.

## What it can do

**Students:** register with e-mail verification, log in, edit profile, change password, enroll in exams, take the exam one question at a time with a live countdown (answers save the moment you click), then see Right / Wrong / Not attended per question, total marks and a PDF result.

**Admins:** dashboard with counts and a demo exam, create/edit/delete exams, add/edit/delete questions (4 options each), see enrollments, ranked results per exam, CSV export. **Master admin** also manages *Users* and *Admins*; a **sub admin** only sees their own exams.

## Exam rules

- **Status changes by itself:** `Pending` (questions still missing) -> `Created` (all questions added, students can enroll) -> `Started` (start time reached) -> `Completed` (duration over).
- **Marking:** right answer `+X`, wrong answer `-Y` (Y can be 0), not answered `0`. Students may change an answer until time is up; the last choice counts.
- **Enrollment** is only possible while the exam is `Created`.
- **Questions are locked** once anyone has enrolled. Their text can still be edited until the exam starts.
- Exam times are entered and checked in the configured time zone (`APP_TIMEZONE`).

## Troubleshooting

| Problem | Fix |
|---|---|
| `No such file` for `start-local.sh` | You are in the wrong folder. `cd` into the project folder (run `ls` to check). |
| `php: command not found` on macOS | `brew install php`, then run the script again. |
| Asks for a password | Normal on first run (it installs PHP). Type your computer password; nothing is shown. |
| "Address already in use" | Use another port: `PORT=9000 bash start-local.sh` and open `http://localhost:9000`. |
| Pages look unstyled, buttons do nothing | The browser cannot reach the internet / CDNs. Check the connection and any script blockers. |
| Registered but cannot log in | E-mail not verified. Admin -> Users -> **Verify**. |
| No verification e-mail arrives | Expected - no mail server locally. Use **Verify** as above, or the link in `www/logs/mail.log`. |
| Exam start time off by some hours | Set your time zone: `APP_TIMEZONE=Asia/Kolkata bash start-local.sh`. |
| Cannot enroll in an exam | Only exams in status `Created` accept enrollment (all questions added, not yet started). |
| "Invalid security token" | The page was open too long or cookies are blocked. Reload and retry. |
| Image upload rejected | Must be a real JPG/PNG/GIF up to 2 MB. |
| `Permission denied` on `www/upload` / `www/logs` | `chmod -R u+rwX www/upload www/logs` |
| Want a clean start | Stop the site, `rm data/exam.db`, start again. |

## Tests (optional)

```bash
bash tests/run.sh
```

Starts the real site with a throwaway database and drives it over HTTP (admin builds an exam, students register/enroll/take it, results appear, plus security checks). The last line must read `RESULT: 269 passed, 0 failed`. Needs PHP and curl.

## Project layout

```
start-local.sh   one-command start
db/02_seed.sql   the first admin account
tests/           automated tests
composer.json    optional extras (real PDF files and real SMTP e-mail)
www/             the website itself
  index.php, login.php, register.php, profile.php, enroll_exam.php,
  view_exam.php, change_password.php, pdf_exam_result.php   student pages
  user_ajax_action.php   student AJAX calls
  helpers.php            escaping, query helpers, CSRF, messages
  master/                admin panel + Examination.php (database, sessions, mail, exam rules)
  style/                 stylesheet + countdown plugin (MIT)
  upload/, logs/         profile pictures, mail.log (created on use)
data/            the database file (created on first start)
```

## Notes before using it for real

- This start method uses PHP's built-in server, which is meant for local use, demos and classrooms. For a public site, put the `www/` folder behind a real web server (Apache/Nginx + PHP) over HTTPS.
- Change the default admin password.
- For real e-mail (instead of the `mail.log` file) install the optional packages with `composer install` and set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM` and `MAIL_DRIVER=smtp` before starting.
- Back up `data/exam.db` and `www/upload/`.
- Built in: bound-parameter queries, escaped output, CSRF tokens on every change, hashed passwords, verified image uploads, sessions of deleted accounts stop working.
