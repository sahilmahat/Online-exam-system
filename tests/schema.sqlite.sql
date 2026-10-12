-- SQLite twin of db/01_schema.sql, used only by tests/run.sh (no MySQL needed to run the tests).
CREATE TABLE admin_table (
  admin_id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_email_address TEXT NOT NULL UNIQUE,
  admin_password TEXT NOT NULL,
  admin_verfication_code TEXT NOT NULL DEFAULT '',
  admin_type TEXT NOT NULL DEFAULT 'sub_master',
  admin_created_on TEXT NOT NULL,
  email_verified TEXT NOT NULL DEFAULT 'no'
);
CREATE TABLE user_table (
  user_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_email_address TEXT NOT NULL UNIQUE,
  user_password TEXT NOT NULL,
  user_verfication_code TEXT NOT NULL,
  user_name TEXT NOT NULL,
  user_gender TEXT NOT NULL,
  user_address TEXT NOT NULL,
  user_mobile_no TEXT NOT NULL,
  user_image TEXT NOT NULL,
  user_created_on TEXT NOT NULL,
  user_email_verified TEXT NOT NULL DEFAULT 'no'
);
CREATE TABLE online_exam_table (
  online_exam_id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id INTEGER NOT NULL REFERENCES admin_table(admin_id) ON DELETE RESTRICT,
  online_exam_title TEXT NOT NULL,
  online_exam_datetime TEXT NOT NULL,
  online_exam_duration TEXT NOT NULL,
  total_question INTEGER NOT NULL,
  marks_per_right_answer TEXT NOT NULL,
  marks_per_wrong_answer TEXT NOT NULL,
  online_exam_created_on TEXT NOT NULL,
  online_exam_status TEXT NOT NULL DEFAULT 'Pending',
  online_exam_code TEXT NOT NULL UNIQUE
);
CREATE TABLE question_table (
  question_id INTEGER PRIMARY KEY AUTOINCREMENT,
  online_exam_id INTEGER NOT NULL REFERENCES online_exam_table(online_exam_id) ON DELETE CASCADE,
  question_title TEXT NOT NULL,
  answer_option TEXT NOT NULL
);
CREATE TABLE option_table (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL REFERENCES question_table(question_id) ON DELETE CASCADE,
  option_number INTEGER NOT NULL,
  option_title TEXT NOT NULL,
  UNIQUE (question_id, option_number)
);
CREATE TABLE user_exam_enroll_table (
  user_exam_enroll_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES user_table(user_id) ON DELETE CASCADE,
  exam_id INTEGER NOT NULL REFERENCES online_exam_table(online_exam_id) ON DELETE CASCADE,
  attendance_status TEXT NOT NULL DEFAULT 'Absent',
  UNIQUE (user_id, exam_id)
);
CREATE TABLE user_exam_question_answer (
  user_exam_question_answer_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES user_table(user_id) ON DELETE CASCADE,
  exam_id INTEGER NOT NULL REFERENCES online_exam_table(online_exam_id) ON DELETE CASCADE,
  question_id INTEGER NOT NULL REFERENCES question_table(question_id) ON DELETE CASCADE,
  user_answer_option TEXT NOT NULL DEFAULT '0',
  marks TEXT NOT NULL DEFAULT '0',
  UNIQUE (user_id, exam_id, question_id)
);
