#!/usr/bin/env bash
# End-to-end tests: starts the real app (PHP built-in server) against a throwaway SQLite database
# and drives it over HTTP like a browser would (admin builds an exam, users take it, admin sees results).
# Needs: php (with pdo_sqlite, gd), curl.   Usage: bash tests/run.sh
set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$HERE")"
T="$HERE/tmp"
PORT="${TEST_PORT:-8099}"
U="http://127.0.0.1:$PORT"

rm -rf "$T" && mkdir -p "$T"
cp -r "$ROOT/www" "$T/www"                    # run on a copy so uploads/logs do not touch the project
rm -f "$T/www/upload/"* "$T/www/logs/"*

# --- database: schema + the same seeded admin as db/02_seed.sql
HASH=$(grep -o '\$2y\$[^'"'"']*' "$ROOT/db/02_seed.sql" | head -1)
php -r '
$d = new PDO("sqlite:" . $argv[1]);
$d->exec(file_get_contents($argv[2]));
$s = $d->prepare("INSERT INTO admin_table (admin_email_address, admin_password, admin_type, admin_created_on, email_verified) VALUES (?,?,?,?,?)");
$s->execute(array("admin@example.com", $argv[3], "master", date("Y-m-d H:i:s"), "yes"));
' "$T/test.db" "$HERE/schema.sqlite.sql" "$HASH"

php -r 'imagepng(imagecreatetruecolor(8,8), $argv[1]);' "$T/ok.png"
echo "<?php echo 'not an image';" > "$T/evil.png"

# --- start server (env makes the app use SQLite, log mails to a file and run on this port)
DB_DRIVER=sqlite DB_SQLITE_PATH="$T/test.db" MAIL_DRIVER=log APP_URL="$U" APP_TIMEZONE=UTC \
  php -S "127.0.0.1:$PORT" -t "$T/www" > "$T/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "$U/login.php" && break; sleep 0.2; done

PASS=0; FAIL=0
sql(){ php -r '$d=new PDO("sqlite:"."'"$T"'/test.db"); $d->exec("PRAGMA foreign_keys=ON"); $d->exec($argv[1]);' "$1"; }
q1(){ php -r '$d=new PDO("sqlite:"."'"$T"'/test.db"); echo $d->query($argv[1])->fetchColumn();' "$1"; }
FUTURE(){ date -u -d "+$1 minutes" '+%Y-%m-%dT%H:%M'; }

# CSRF token of a jar (fetched from any page that prints the meta tag)
token(){ curl -s -b "$T/$1" -c "$T/$1" "$U/$2" | grep -o 'name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }
setjar(){ rm -f "$T/$1"; TOKEN[$1]=$(token "$1" "$2"); }
declare -A TOKEN

report(){ if [ "$CODE" = "$3" ]; then PASS=$((PASS+1)); echo "PASS [$CODE] $2"; else FAIL=$((FAIL+1)); echo "FAIL [$CODE != $3] $2 :: ${B:0:160}"; fi; }
# raw JAR LABEL EXPECT curl-args...        (no token added)
raw(){ jar=$1; label=$2; exp=$3; shift 3; CODE=$(curl -s -b "$T/$jar" -c "$T/$jar" -o "$T/body.txt" -w "%{http_code}" "$@"); B=$(tr -d '\0' < "$T/body.txt"); report "$jar" "$label" "$exp"; }
# form JAR LABEL EXPECT URL data...        (HTML form POST with _csrf field)
form(){ jar=$1; label=$2; exp=$3; url=$4; shift 4; args=(); for d in "$@"; do args+=(--data-urlencode "$d"); done
  CODE=$(curl -s -b "$T/$jar" -c "$T/$jar" -o "$T/body.txt" -w "%{http_code}" --data-urlencode "_csrf=${TOKEN[$jar]}" "${args[@]}" "$url"); B=$(tr -d '\0' < "$T/body.txt"); report "$jar" "$label" "$exp"; }
# ajax JAR LABEL EXPECT URL data...        (AJAX POST with X-CSRF-Token header)
ajax(){ jar=$1; label=$2; exp=$3; url=$4; shift 4; args=(); for d in "$@"; do args+=(--data-urlencode "$d"); done
  CODE=$(curl -s -b "$T/$jar" -c "$T/$jar" -o "$T/body.txt" -w "%{http_code}" -H "X-CSRF-Token: ${TOKEN[$jar]}" "${args[@]}" "$url"); B=$(tr -d '\0' < "$T/body.txt"); report "$jar" "$label" "$exp"; }
# multipart upload with token header: upl JAR LABEL EXPECT URL -F ...
upl(){ jar=$1; label=$2; exp=$3; url=$4; shift 4
  CODE=$(curl -s -b "$T/$jar" -c "$T/$jar" -o "$T/body.txt" -w "%{http_code}" -H "X-CSRF-Token: ${TOKEN[$jar]}" "$@" "$url"); B=$(tr -d '\0' < "$T/body.txt"); report "$jar" "$label" "$exp"; }
has(){ if grep -qF -- "$2" "$T/body.txt"; then PASS=$((PASS+1)); echo "  PASS $1 contains: $2"; else FAIL=$((FAIL+1)); echo "  FAIL $1 missing: $2 :: $(head -c 200 "$T/body.txt")"; fi; }
hasnt(){ if grep -qF -- "$2" "$T/body.txt"; then FAIL=$((FAIL+1)); echo "  FAIL $1 should NOT contain: $2"; else PASS=$((PASS+1)); echo "  PASS $1 free of: $2"; fi; }
eq(){ if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "  PASS $1 ($2)"; else FAIL=$((FAIL+1)); echo "  FAIL $1: got '$2' want '$3'"; fi; }
A="$U/user_ajax_action.php"; M="$U/master"

echo "=================== STATIC ASSETS"
raw x "logo.png" 200 "$M/logo.png"
raw x "style.css" 200 "$U/style/style.css"
raw x "TimeCircles.js" 200 "$U/style/TimeCircles.js"
raw x "TimeCircles.css" 200 "$U/style/TimeCircles.css"

echo "=================== ADMIN: access control"
setjar admin master/login.php
for p in index.php exam.php exam_edit.php question.php question_edit.php user.php exam_result.php admins.php password.php; do raw x "anon $p -> login" 302 "$M/$p"; done
raw admin "login page renders" 200 "$M/login.php"; has login 'name="admin_email_address"'
CODE=$(curl -s -b "$T/admin" -c "$T/admin" -o "$T/body.txt" -w "%{http_code}" --data-urlencode "admin_email_address=admin@example.com" --data-urlencode "admin_password=Admin@12345" "$M/login.php"); B=""; report admin "login with NO token -> redirect, no session" 302
raw admin "still logged out" 302 "$M/exam.php"
form admin "login wrong password" 302 "$M/login.php" "admin_email_address=admin@example.com" "admin_password=wrong"
raw admin "still logged out after wrong password" 302 "$M/exam.php"
form admin "login SQLi in email" 302 "$M/login.php" "admin_email_address=' OR '1'='1" "admin_password=x"
raw admin "still logged out after SQLi" 302 "$M/exam.php"
form admin "login ok" 302 "$M/login.php" "admin_email_address=admin@example.com" "admin_password=Admin@12345"
raw admin "dashboard" 200 "$M/index.php"; has dash "Dashboard"; has dash "Create demo exam"
raw admin "login page redirects when logged in" 302 "$M/login.php"

echo "=================== ADMIN: demo exam"
form admin "demo exam invalid minutes" 302 "$M/index.php" "action=demo_exam" "minutes=1"
eq "no exam created by invalid request" "$(q1 'select count(*) from online_exam_table')" 0
form admin "demo exam" 302 "$M/index.php" "action=demo_exam" "minutes=30"
eq "demo exam row" "$(q1 "select count(*) from online_exam_table where online_exam_title like 'Demo Exam%'")" 1
eq "demo exam has 5 questions" "$(q1 'select count(*) from question_table')" 5
eq "demo exam has 20 options" "$(q1 'select count(*) from option_table')" 20
eq "demo exam auto-status Created (all questions present)" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=1')" Created
raw admin "exam list shows demo" 200 "$M/exam.php"; has list "Demo Exam - Web Basics"

echo "=================== ADMIN: create exam, validation"
form admin "create: start in the past" 200 "$M/exam.php" "action=create" "online_exam_title=Past" "online_exam_datetime=2020-01-01T10:00" "online_exam_duration=30" "total_question=3" "marks_per_right_answer=2" "marks_per_wrong_answer=1"; has err "must start in the future"
form admin "create: duration 0 / marks 0 / no title" 200 "$M/exam.php" "action=create" "online_exam_title=" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=0" "total_question=3" "marks_per_right_answer=0" "marks_per_wrong_answer=1"; has err "Exam title is required"; has err "Duration must be"; has err "Marks per right answer"
form admin "create: negative penalty" 200 "$M/exam.php" "action=create" "online_exam_title=Neg" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=30" "total_question=3" "marks_per_right_answer=1" "marks_per_wrong_answer=-1"; has err "Marks deducted"
eq "invalid creates inserted nothing" "$(q1 'select count(*) from online_exam_table')" 1
form admin "create: XSS title" 302 "$M/exam.php" "action=create" "online_exam_title=Chem <script>alert(1)</script> & Bio" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=30" "total_question=3" "marks_per_right_answer=2" "marks_per_wrong_answer=1"
EXAM2=$(q1 "select online_exam_code from online_exam_table where online_exam_id=2")
eq "exam 2 starts Pending" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Pending
raw admin "exam list escapes title" 200 "$M/exam.php"; has list "Chem &lt;script&gt;alert(1)&lt;/script&gt; &amp; Bio"; hasnt list "<script>alert(1)</script>"

echo "=================== ADMIN: questions"
raw admin "question page" 200 "$M/question.php?code=$EXAM2"; has q "0 of 3 questions added"
form admin "add question: missing option" 200 "$M/question.php?code=$EXAM2" "action=add" "question_title=What?" "option_1=a" "option_2=" "option_3=c" "option_4=d" "answer_option=1"; has err "Option 2 is required"
form admin "add question: bad correct option" 200 "$M/question.php?code=$EXAM2" "action=add" "question_title=What?" "option_1=a" "option_2=b" "option_3=c" "option_4=d" "answer_option=9"; has err "choose the correct option"
eq "no question created by invalid input" "$(q1 'select count(*) from question_table where online_exam_id=2')" 0
CODE=$(curl -s -b "$T/admin" -c "$T/admin" -o "$T/body.txt" -w "%{http_code}" --data-urlencode "action=add" --data-urlencode "question_title=No token" --data-urlencode "option_1=a" --data-urlencode "option_2=b" --data-urlencode "option_3=c" --data-urlencode "option_4=d" --data-urlencode "answer_option=1" "$M/question.php?code=$EXAM2"); B=""; report admin "add question without CSRF token" 302
eq "CSRF-less request changed nothing" "$(q1 'select count(*) from question_table where online_exam_id=2')" 0
form admin "add Q1" 302 "$M/question.php?code=$EXAM2" "action=add" "question_title=2 + 2 = ? <b>bold</b>" "option_1=3" "option_2=4" "option_3=5" "option_4=6" "answer_option=2"
form admin "add Q2" 302 "$M/question.php?code=$EXAM2" "action=add" "question_title=Capital of France?" "option_1=Rome" "option_2=Berlin" "option_3=Paris" "option_4=Madrid" "answer_option=3"
eq "still Pending with 2 of 3 questions" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Pending
form admin "add Q3" 302 "$M/question.php?code=$EXAM2" "action=add" "question_title=PHP is a ...?" "option_1=server-side language" "option_2=database" "option_3=browser" "option_4=OS" "answer_option=1"
eq "Created once all 3 questions are in" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Created
form admin "add Q4 beyond total" 200 "$M/question.php?code=$EXAM2" "action=add" "question_title=Extra" "option_1=a" "option_2=b" "option_3=c" "option_4=d" "answer_option=1"; has err "already has all 3 questions"
raw admin "question list escapes html" 200 "$M/question.php?code=$EXAM2"; has q "&lt;b&gt;bold&lt;/b&gt;"; hasnt q "<b>bold</b>"; has q "3 of 3 questions added"; has q "correct"
QID=$(q1 "select question_id from question_table where online_exam_id=2 order by question_id limit 1")
raw admin "edit question page" 200 "$M/question_edit.php?id=$QID"; has qe "Save question"
form admin "edit question" 302 "$M/question_edit.php?id=$QID" "question_title=What is 2 + 2?" "option_1=3" "option_2=4" "option_3=5" "option_4=22" "answer_option=2"
eq "question text updated" "$(q1 "select question_title from question_table where question_id=$QID")" "What is 2 + 2?"
eq "option 4 updated" "$(q1 "select option_title from option_table where question_id=$QID and option_number=4")" "22"
form admin "delete a question -> exam drops back to Pending" 302 "$M/question.php?code=$EXAM2" "action=delete" "question_id=$(q1 "select question_id from question_table where online_exam_id=2 order by question_id desc limit 1")"
eq "Pending again with 2 questions" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Pending
form admin "re-add the question" 302 "$M/question.php?code=$EXAM2" "action=add" "question_title=PHP is a ...?" "option_1=server-side language" "option_2=database" "option_3=browser" "option_4=OS" "answer_option=1"
eq "Created again" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Created
raw admin "question page SQLi code" 302 "$M/question.php?code=x'%20OR%20'1'='1"
raw admin "question_edit non-numeric id" 302 "$M/question_edit.php?id=1%20OR%201=1"

echo "=================== ADMIN: edit exam"
raw admin "exam_edit page" 200 "$M/exam_edit.php?code=$EXAM2"; has ee "Save changes"
form admin "edit: total below question count" 200 "$M/exam_edit.php?code=$EXAM2" "online_exam_title=Chemistry" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=30" "total_question=2" "marks_per_right_answer=2" "marks_per_wrong_answer=1"; has err "cannot be lower than the 3 questions"
form admin "edit: past date" 200 "$M/exam_edit.php?code=$EXAM2" "online_exam_title=Chemistry" "online_exam_datetime=2020-01-01T10:00" "online_exam_duration=30" "total_question=3" "marks_per_right_answer=2" "marks_per_wrong_answer=1"; has err "must start in the future"
form admin "edit ok" 302 "$M/exam_edit.php?code=$EXAM2" "online_exam_title=Chemistry Quiz" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=20" "total_question=3" "marks_per_right_answer=2" "marks_per_wrong_answer=1"
eq "title updated" "$(q1 'select online_exam_title from online_exam_table where online_exam_id=2')" "Chemistry Quiz"
eq "still Created" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Created
eq "marks stored 2 / 1" "$(q1 'select marks_per_right_answer from online_exam_table where online_exam_id=2')/$(q1 'select marks_per_wrong_answer from online_exam_table where online_exam_id=2')" "2/1"

echo "=================== ADMIN: sub admin + ownership"
form admin "create sub admin: short password" 200 "$M/admins.php" "action=create" "admin_email_address=sub@example.com" "admin_password=short" "confirm_admin_password=short"; has err "at least 8 characters"
form admin "create sub admin" 302 "$M/admins.php" "action=create" "admin_email_address=sub@example.com" "admin_password=subpassword1" "confirm_admin_password=subpassword1"
form admin "duplicate sub admin" 200 "$M/admins.php" "action=create" "admin_email_address=sub@example.com" "admin_password=subpassword1" "confirm_admin_password=subpassword1"; has err "already exists"
setjar sub master/login.php
form sub "sub admin login" 302 "$M/login.php" "admin_email_address=sub@example.com" "admin_password=subpassword1"
raw sub "sub dashboard" 200 "$M/index.php"
raw sub "sub cannot open Users" 302 "$M/user.php"
raw sub "sub cannot open Admins" 302 "$M/admins.php"
raw sub "sub list hides master's exams" 200 "$M/exam.php"; hasnt sublist "Chemistry Quiz"; hasnt sublist "Demo Exam"
raw sub "sub cannot open master's questions" 302 "$M/question.php?code=$EXAM2"
raw sub "sub cannot open master's results" 302 "$M/exam_result.php?code=$EXAM2&export=csv"
raw sub "sub cannot edit master's exam" 302 "$M/exam_edit.php?code=$EXAM2"
raw sub "sub cannot open master's question edit" 302 "$M/question_edit.php?id=$QID"
form sub "sub cannot delete master's exam" 302 "$M/exam.php" "action=delete" "code=$EXAM2"
eq "master's exam still exists" "$(q1 'select count(*) from online_exam_table where online_exam_id=2')" 1
form sub "sub creates own exam" 302 "$M/exam.php" "action=create" "online_exam_title=Sub's exam" "online_exam_datetime=$(FUTURE 180)" "online_exam_duration=10" "total_question=1" "marks_per_right_answer=1" "marks_per_wrong_answer=0"
SUBCODE=$(q1 "select online_exam_code from online_exam_table where online_exam_title like 'Sub%'")
raw sub "sub list shows own exam only" 200 "$M/exam.php"; has sublist "Sub&#039;s exam"; hasnt sublist "Chemistry Quiz"
raw admin "master sees sub's exam" 200 "$M/exam.php"; has list "Sub&#039;s exam"
SUBID=$(q1 "select admin_id from admin_table where admin_email_address='sub@example.com'")
form admin "cannot delete sub admin who owns exams" 302 "$M/admins.php" "action=delete" "admin_id=$SUBID"
eq "sub admin still there" "$(q1 "select count(*) from admin_table where admin_id=$SUBID")" 1
form admin "master deletes sub's exam" 302 "$M/exam.php" "action=delete" "code=$SUBCODE"
form admin "delete sub admin" 302 "$M/admins.php" "action=delete" "admin_id=$SUBID"
eq "sub admin gone" "$(q1 "select count(*) from admin_table where admin_id=$SUBID")" 0
form admin "master admin cannot be deleted" 302 "$M/admins.php" "action=delete" "admin_id=1"
eq "master still there" "$(q1 "select count(*) from admin_table where admin_id=1")" 1
raw sub "deleted sub admin's open session is rejected" 302 "$M/index.php"

echo "=================== USER: register / verify / login"
setjar jarA login.php
setjar jarB login.php
setjar jarX login.php
CODE=$(curl -s -o "$T/body.txt" -w "%{http_code}" -d "page=index&action=fetch_exam&exam_id=1" "$A"); B=""; report x "no-cookie, no-token ajax" 403
ajax jarX "anon ajax with valid token but not logged in" 401 "$A" "page=index" "action=fetch_exam" "exam_id=1"
ajax jarX "check_email new" 200 "$A" "page=register" "action=check_email" "email=a@test.com"
upl jarA "register: short password" 422 "$A" -F page=register -F action=register -F user_email_address=a@test.com -F user_password=short -F confirm_user_password=short -F user_name="Alice A" -F user_gender=Male -F user_address=Street -F user_mobile_no=12345 -F user_image=@$T/ok.png
upl jarA "register: non-image upload" 422 "$A" -F page=register -F action=register -F user_email_address=a@test.com -F user_password=password1 -F confirm_user_password=password1 -F user_name="Alice A" -F user_gender=Male -F user_address=Street -F user_mobile_no=12345 -F "user_image=@$T/evil.png;type=image/png"
eq "failed registrations created no user" "$(q1 'select count(*) from user_table')" 0
upl jarA "register A" 200 "$A" -F page=register -F action=register -F user_email_address=a@test.com -F user_password=password1 -F confirm_user_password=password1 -F user_name="Alice A" -F user_gender=Male --form-string "user_address=<script>alert(1)</script> Street" -F user_mobile_no=12345 -F user_image=@$T/ok.png
upl jarB "register B" 200 "$A" -F page=register -F action=register -F user_email_address=b@test.com -F user_password=password2 -F confirm_user_password=password2 -F user_name="Bob B" -F user_gender=Male -F user_address=Road -F user_mobile_no=999 -F user_image=@$T/ok.png
upl jarX "register duplicate" 409 "$A" -F page=register -F action=register -F user_email_address=a@test.com -F user_password=password1 -F confirm_user_password=password1 -F user_name="Alice A" -F user_gender=Male -F user_address=S -F user_mobile_no=1 -F user_image=@$T/ok.png
ajax jarX "check_email existing" 409 "$A" "page=register" "action=check_email" "email=a@test.com"
eq "two users stored, unverified" "$(q1 "select count(*) from user_table where user_email_verified='no'")" 2
eq "verification e-mails logged" "$(grep -c 'LOGGED to=' "$T/www/logs/mail.log")" 2
ajax jarA "login before verification" 200 "$A" "page=login" "action=login" "user_email_address=a@test.com" "user_password=password1"; has login "Your Email is not verify"
CODEA=$(grep -o 'code=[a-f0-9]*' "$T/www/logs/mail.log" | sed -n 1p | cut -d= -f2)
raw x "verify with SQLi code" 302 "$U/verify_email.php?type=user&code=x'%20OR%20'1'='1"
eq "SQLi verification verified nobody" "$(q1 "select count(*) from user_table where user_email_verified='yes'")" 0
raw x "verify A via e-mail link" 302 "$U/verify_email.php?type=user&code=$CODEA"
eq "A verified" "$(q1 "select user_email_verified from user_table where user_email_address='a@test.com'")" yes
ajax jarA "login wrong password" 200 "$A" "page=login" "action=login" "user_email_address=a@test.com" "user_password=nope"; has login "Wrong email address or password"
ajax jarA "login A" 200 "$A" "page=login" "action=login" "user_email_address=a@test.com" "user_password=password1"; has login '"success":true'
echo "-- B is verified by the admin from the Users page instead of the e-mail link"
raw admin "users page" 200 "$M/user.php"; has users "Bob B"; has users "Alice A"
BID=$(q1 "select user_id from user_table where user_email_address='b@test.com'")
form admin "admin verifies B" 302 "$M/user.php" "action=verify" "user_id=$BID"
ajax jarB "login B (verified by admin)" 200 "$A" "page=login" "action=login" "user_email_address=b@test.com" "user_password=password2"; has login '"success":true'
CODE=$(curl -s -b "$T/jarA" -c "$T/jarA" -o "$T/body.txt" -w "%{http_code}" -d "page=index&action=fetch_exam&exam_id=2" "$A"); B=""; report jarA "same request with NO token header" 403

echo "=================== USER: enrollment"
raw jarA "index lists enrollable exams" 200 "$U/index.php"; has idx "Chemistry Quiz"; has idx "Demo Exam - Web Basics"
ajax jarA "fetch_exam" 200 "$A" "page=index" "action=fetch_exam" "exam_id=2"; has fx "Chemistry Quiz"; has fx "Enroll it"
ajax jarA "enroll: nonexistent exam" 422 "$A" "page=index" "action=enroll_exam" "exam_id=99"
ajax jarA "enroll Chemistry Quiz" 200 "$A" "page=index" "action=enroll_exam" "exam_id=2"
ajax jarA "enroll again" 409 "$A" "page=index" "action=enroll_exam" "exam_id=2"
ajax jarB "B enrolls in Chemistry Quiz" 200 "$A" "page=index" "action=enroll_exam" "exam_id=2"
eq "answer rows for A (3 questions)" "$(q1 'select count(*) from user_exam_question_answer where user_id=1')" 3
ajax jarA "datatable lists only A's enrolments" 200 "$A" "page=enroll_exam" "action=fetch" "draw=1" "start=0" "length=10" "search[value]="; has dt '"recordsTotal":1'
ajax jarA "datatable search SQLi" 200 "$A" "page=enroll_exam" "action=fetch" "draw=1" "start=0" "length=10" "search[value]=%' OR 1=1 --"; has dt '"recordsFiltered":0'
ajax jarA "datatable order SQLi" 200 "$A" "page=enroll_exam" "action=fetch" "draw=1" "start=0" "length=10" "order[0][column]=1; DROP TABLE user_table" "order[0][dir]=asc"
eq "user_table intact" "$(q1 "select count(*) from sqlite_master where name='user_table'")" 1
raw jarA "enroll_exam page" 200 "$U/enroll_exam.php"

echo "=================== ADMIN: enrollment locks questions"
raw admin "question page shows lock" 200 "$M/question.php?code=$EXAM2"; has lock "have enrolled"; hasnt lock 'value="Add question"'
form admin "delete question blocked while enrolled" 302 "$M/question.php?code=$EXAM2" "action=delete" "question_id=$QID"
eq "question still there" "$(q1 "select count(*) from question_table where question_id=$QID")" 1
form admin "edit question text still allowed" 302 "$M/question_edit.php?id=$QID" "question_title=What is 2 plus 2?" "option_1=3" "option_2=4" "option_3=5" "option_4=22" "answer_option=2"
form admin "exam_edit cannot change number of questions" 200 "$M/exam_edit.php?code=$EXAM2" "online_exam_title=Chemistry Quiz" "online_exam_datetime=$(FUTURE 120)" "online_exam_duration=20" "total_question=4" "marks_per_right_answer=2" "marks_per_wrong_answer=1"; has err "cannot change once users have enrolled"

echo "=================== EXAM STARTS AUTOMATICALLY"
raw jarA "view_exam before start -> redirect" 302 "$U/view_exam.php?code=$EXAM2"
ajax jarA "load_question before start" 403 "$A" "page=view_exam" "action=load_question" "exam_id=2" "question_id="
NOW=$(date -u '+%Y-%m-%d %H:%M:%S')
sql "update online_exam_table set online_exam_datetime='$NOW' where online_exam_id=2"
raw jarA "view_exam once start time reached" 200 "$U/view_exam.php?code=$EXAM2"; has view "exam_timer"
eq "status switched to Started by itself" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Started
eq "attendance Present" "$(q1 'select attendance_status from user_exam_enroll_table where user_id=1 and exam_id=2')" Present
eq "B still Absent" "$(q1 'select attendance_status from user_exam_enroll_table where user_id=2 and exam_id=2')" Absent
raw admin "exam list while running" 200 "$M/exam.php"; has list "Started"
raw admin "question page while running is locked" 200 "$M/question.php?code=$EXAM2"; has lock "has started"
raw admin "exam_edit blocked while running" 302 "$M/exam_edit.php?code=$EXAM2"
form admin "cannot delete a running exam" 302 "$M/exam.php" "action=delete" "code=$EXAM2"
eq "running exam still exists" "$(q1 'select count(*) from online_exam_table where online_exam_id=2')" 1

echo "=================== USER: taking the exam"
ajax jarA "first question" 200 "$A" "page=view_exam" "action=load_question" "exam_id=2" "question_id="; has q 'data-id="2"'; has q "What is 2 plus 2?"; hasnt q "id-data"
ajax jarA "navigation" 200 "$A" "page=view_exam" "action=question_navigation" "exam_id=2"; has nav "question_navigation"
ajax jarA "user details" 200 "$A" "page=view_exam" "action=user_detail"; has ud "Alice A"
Q1=$QID; Q2=$(q1 "select question_id from question_table where online_exam_id=2 order by question_id limit 1 offset 1"); Q3=$(q1 "select question_id from question_table where online_exam_id=2 order by question_id limit 1 offset 2")
ajax jarA "answer Q1 right" 200 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q1" "answer_option=2"
ajax jarA "answer Q2 wrong" 200 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q2" "answer_option=1"
eq "Q1 marks" "$(q1 "select marks from user_exam_question_answer where user_id=1 and question_id=$Q1")" "+2"
eq "Q2 marks" "$(q1 "select marks from user_exam_question_answer where user_id=1 and question_id=$Q2")" "-1"
ajax jarA "invalid option" 422 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q3" "answer_option=9"
ajax jarA "question of another exam" 422 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=1" "answer_option=1"
ajax jarA "reload Q1 shows saved answer" 200 "$A" "page=view_exam" "action=load_question" "exam_id=2" "question_id=$Q1"; has q 'data-id="2" checked'
echo "-- B answers one question right, leaves the rest"
ajax jarB "B answers Q3 right" 200 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q3" "answer_option=1"
echo "-- a user who did not enroll cannot get in"
upl jarX "register C" 200 "$A" -F page=register -F action=register -F user_email_address=c@test.com -F user_password=password3 -F confirm_user_password=password3 -F user_name="Carl C" -F user_gender=Male -F user_address=Lane -F user_mobile_no=111 -F user_image=@$T/ok.png
sql "update user_table set user_email_verified='yes' where user_email_address='c@test.com'"
setjar jarC login.php
ajax jarC "login C" 200 "$A" "page=login" "action=login" "user_email_address=c@test.com" "user_password=password3"
ajax jarC "C (not enrolled) load_question" 403 "$A" "page=view_exam" "action=load_question" "exam_id=2" "question_id="
ajax jarC "C (not enrolled) answer" 403 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q1" "answer_option=2"
raw jarC "C (not enrolled) view_exam" 302 "$U/view_exam.php?code=$EXAM2"

echo "=================== EXAM ENDS AUTOMATICALLY"
sql "update online_exam_table set online_exam_datetime='2020-01-01 00:00:00' where online_exam_id=2"
ajax jarA "answer after the time is over" 403 "$A" "page=view_exam" "action=answer" "exam_id=2" "question_id=$Q3" "answer_option=1"
eq "status switched to Completed by itself" "$(q1 'select online_exam_status from online_exam_table where online_exam_id=2')" Completed
eq "unanswered Q3 stays at 0" "$(q1 "select marks from user_exam_question_answer where user_id=1 and question_id=$Q3")" 0

echo "=================== USER: results"
raw jarA "A results page" 200 "$U/view_exam.php?code=$EXAM2"; has res "Online Exam Result"; has res "Total Marks"; has res ">1<"; has res "badge-success"; has res "badge-danger"; has res "badge-dark"
raw jarA "A result PDF (fallback page without Dompdf)" 200 "$U/pdf_exam_result.php?code=$EXAM2"; has pdf "Exam Result"; has pdf "Right"; has pdf "Wrong"; has pdf "Not Attend"
raw jarC "C cannot get A's result PDF" 302 "$U/pdf_exam_result.php?code=$EXAM2"
raw jarX "anon cannot get result PDF" 302 "$U/pdf_exam_result.php?code=$EXAM2"

echo "=================== ADMIN: results"
raw admin "results page" 200 "$M/exam_result.php?code=$EXAM2"; has ar "Alice A"; has ar "Bob B"; has ar "Completed"
raw admin "results CSV" 200 "$M/exam_result.php?code=$EXAM2&export=csv"
has csv "Rank,Name,Email,Attendance,Answered,Right,Wrong,\"Total marks\""
has csv "1,\"Bob B\",b@test.com,Absent,1,1,0,2"
has csv "2,\"Alice A\",a@test.com,Present,2,1,1,1"
raw admin "results of demo exam (nobody enrolled)" 200 "$M/exam_result.php?code=$(q1 'select online_exam_code from online_exam_table where online_exam_id=1')"; has ar "0 enrolled"
raw admin "results SQLi code" 302 "$M/exam_result.php?code=x'--"
raw admin "dashboard counts" 200 "$M/index.php"; has dash "Enrollments"

echo "=================== USER: profile + password"
raw jarA "profile page escapes" 200 "$U/profile.php"
upl jarA "profile bad gender" 422 "$A" -F page=profile -F action=profile -F user_name="Alice A" -F user_gender=Alien -F user_address=X -F user_mobile_no=123
upl jarA "profile update" 200 "$A" -F page=profile -F action=profile -F user_name="Alice Updated" -F user_gender=Female -F user_address="New addr" -F user_mobile_no=123
eq "name updated" "$(q1 "select user_name from user_table where user_id=1")" "Alice Updated"
ajax jarA "change password: wrong current" 403 "$A" "page=change_password" "action=change_password" "current_password=wrong" "user_password=newpassword1" "confirm_user_password=newpassword1"
ajax jarA "change password" 200 "$A" "page=change_password" "action=change_password" "current_password=password1" "user_password=newpassword1" "confirm_user_password=newpassword1"
setjar jarA login.php
ajax jarA "login with new password" 200 "$A" "page=login" "action=login" "user_email_address=a@test.com" "user_password=newpassword1"; has login '"success":true'

echo "=================== ADMIN: users page, password, logout"
form admin "admin deletes user C" 302 "$M/user.php" "action=delete" "user_id=$(q1 "select user_id from user_table where user_email_address='c@test.com'")"
eq "C deleted" "$(q1 "select count(*) from user_table where user_email_address='c@test.com'")" 0
form admin "deleting user B cascades enrollments and answers" 302 "$M/user.php" "action=delete" "user_id=$BID"
eq "B's enrolment gone" "$(q1 "select count(*) from user_exam_enroll_table where user_id=$BID")" 0
eq "B's answers gone" "$(q1 "select count(*) from user_exam_question_answer where user_id=$BID")" 0
ajax jarB "deleted user's open session is rejected" 401 "$A" "page=enroll_exam" "action=fetch" "draw=1" "start=0" "length=10"
form admin "admin password: wrong current" 200 "$M/password.php" "current_password=nope" "new_password=newadmin123" "confirm_new_password=newadmin123"; has err "Current password is wrong"
form admin "admin password change" 302 "$M/password.php" "current_password=Admin@12345" "new_password=newadmin123" "confirm_new_password=newadmin123"
raw admin "logout" 302 "$M/logout.php"
raw admin "logged out" 302 "$M/exam.php"
setjar admin2 master/login.php
form admin2 "old admin password rejected" 302 "$M/login.php" "admin_email_address=admin@example.com" "admin_password=Admin@12345"
raw admin2 "still logged out" 302 "$M/exam.php"
form admin2 "new admin password works" 302 "$M/login.php" "admin_email_address=admin@example.com" "admin_password=newadmin123"
raw admin2 "dashboard after re-login" 200 "$M/index.php"
form admin2 "delete a completed exam" 302 "$M/exam.php" "action=delete" "code=$EXAM2"
eq "exam and its questions gone (cascade)" "$(q1 'select count(*) from question_table where online_exam_id=2')" 0

echo
echo "RESULT: $PASS passed, $FAIL failed"
echo "PHP warnings/errors in server log:"; grep -iE 'warning|fatal|notice|deprecated|exception|error' "$T/server.log" | grep -v 'Accepted\|Closing\|\[200\]\|\[30[0-9]\]\|\[40[0-9]\]' | sort | uniq -c | head -20
[ "$FAIL" = "0" ]
