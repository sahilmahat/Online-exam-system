<?php

//master/Examination.php
//Database connection, sessions, mail, uploads and the exam business rules shared by the user side and the admin panel.

date_default_timezone_set(getenv('APP_TIMEZONE') ? getenv('APP_TIMEZONE') : 'UTC');

if(session_status() === PHP_SESSION_NONE)
{
	session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'));
	session_start();
}

require_once(dirname(__DIR__) . '/helpers.php');

//Composer autoloader (PHPMailer, Dompdf) when installed.
require_once(dirname(__DIR__) . '/class/class.phpmailer.php');

class Examination
{
	public $connect;
	public $query = '';
	public $data = array();
	public $filedata;
	public $home_page;

	private static $status_synced = false;

	static function env($key, $default = '')
	{
		$value = getenv($key);

		return ($value === false || $value === '') ? $default : $value;
	}

	function __construct()
	{
		$this->home_page = rtrim(self::env('APP_URL', 'http://localhost:8080'), '/') . '/';

		$options = array(
			PDO::ATTR_ERRMODE				=>	PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE	=>	PDO::FETCH_ASSOC
		);

		if(self::env('DB_DRIVER', 'mysql') == 'sqlite')
		{
			//Used by the automated tests only.
			$this->connect = new PDO('sqlite:' . self::env('DB_SQLITE_PATH', dirname(__DIR__) . '/test.db'), null, null, $options);

			$this->connect->exec('PRAGMA foreign_keys = ON');
		}
		else
		{
			$dsn = 'mysql:host=' . self::env('DB_HOST', 'db') . ';port=' . self::env('DB_PORT', '3306') . ';dbname=' . self::env('DB_NAME', 'online_examination') . ';charset=utf8mb4';

			$options[PDO::ATTR_EMULATE_PREPARES] = false;

			$this->connect = new PDO($dsn, self::env('DB_USER', 'exam_user'), self::env('DB_PASS', 'exam_pass'), $options);
		}

		//Keep exam statuses (Created -> Started -> Completed) current, once per request.
		if(!self::$status_synced)
		{
			self::$status_synced = true;

			$this->Change_exam_status();
		}
	}

	//-----------------------------------------------------------------
	// Query execution. $this->data is always bound as named parameters.
	//-----------------------------------------------------------------

	function execute_query()
	{
		$statement = $this->connect->prepare($this->query);

		$statement->execute($this->data);
	}

	function query_result()
	{
		$statement = $this->connect->prepare($this->query);

		$statement->execute($this->data);

		return $statement->fetchAll();
	}

	function total_row()
	{
		$statement = $this->connect->prepare($this->query);

		$statement->execute($this->data);

		return count($statement->fetchAll());
	}

	function last_id()
	{
		return (int)$this->connect->lastInsertId();
	}

	function begin()
	{
		$this->connect->beginTransaction();
	}

	function commit()
	{
		$this->connect->commit();
	}

	function rollback()
	{
		if($this->connect->inTransaction())
		{
			$this->connect->rollBack();
		}
	}

	//-----------------------------------------------------------------
	// Sessions
	//-----------------------------------------------------------------

	//True only while the account of the logged in user still exists (a deleted user's open session stops working).
	function user_logged_in()
	{
		if(!isset($_SESSION['user_id']))
		{
			return false;
		}

		if(db_count($this, 'SELECT user_id FROM user_table WHERE user_id = :id', array(':id' => (int)$_SESSION['user_id'])) == 0)
		{
			unset($_SESSION['user_id']);

			return false;
		}

		return true;
	}

	function admin_logged_in()
	{
		if(!isset($_SESSION['admin_id']))
		{
			return false;
		}

		if(db_count($this, 'SELECT admin_id FROM admin_table WHERE admin_id = :id', array(':id' => (int)$_SESSION['admin_id'])) == 0)
		{
			unset($_SESSION['admin_id'], $_SESSION['admin_type'], $_SESSION['admin_email']);

			return false;
		}

		return true;
	}

	function user_session_private()
	{
		if(!$this->user_logged_in())
		{
			header('location:login.php');
			exit;
		}
	}

	function user_session_public()
	{
		if($this->user_logged_in())
		{
			header('location:index.php');
			exit;
		}
	}

	//Admin pages live in master/, so these redirects are relative to that folder.
	function admin_session_private()
	{
		if(!$this->admin_logged_in())
		{
			header('location:login.php');
			exit;
		}
	}

	function admin_session_public()
	{
		if($this->admin_logged_in())
		{
			header('location:index.php');
			exit;
		}
	}

	function admin_id()
	{
		return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
	}

	function is_master()
	{
		return isset($_SESSION['admin_type']) && $_SESSION['admin_type'] == 'master';
	}

	//Master only pages.
	function require_master()
	{
		$this->admin_session_private();

		if(!$this->is_master())
		{
			flash_set('danger', 'Only the master admin can open that page.');

			header('location:index.php');
			exit;
		}
	}

	function redirect($url)
	{
		header('location:' . $url);
		exit;
	}

	function clean_data($data)
	{
		return trim($data);
	}

	//-----------------------------------------------------------------
	// Mail and uploads
	//-----------------------------------------------------------------

	function send_email($to, $subject, $body)
	{
		if(self::env('MAIL_DRIVER', 'smtp') == 'smtp' && class_exists('\PHPMailer\PHPMailer\PHPMailer'))
		{
			try
			{
				$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

				$mail->isSMTP();
				$mail->Host = self::env('SMTP_HOST', 'localhost');
				$mail->Port = (int)self::env('SMTP_PORT', '1025');

				if(self::env('SMTP_USER') !== '')
				{
					$mail->SMTPAuth = true;
					$mail->Username = self::env('SMTP_USER');
					$mail->Password = self::env('SMTP_PASS');
				}

				if(self::env('SMTP_SECURE') !== '')
				{
					$mail->SMTPSecure = self::env('SMTP_SECURE');
				}
				else
				{
					$mail->SMTPSecure = '';
					$mail->SMTPAutoTLS = false;
				}

				$mail->CharSet = 'UTF-8';
				$mail->setFrom(self::env('MAIL_FROM', 'no-reply@example.com'), 'Online Examination System');
				$mail->addAddress($to);
				$mail->isHTML(true);
				$mail->Subject = $subject;
				$mail->Body = $body;
				$mail->AltBody = trim(strip_tags($body));

				$mail->send();

				return true;
			}
			catch(\Exception $e)
			{
				$this->log_mail('SEND FAILED (' . $e->getMessage() . ')', $to, $subject, $body);

				return false;
			}
		}

		//MAIL_DRIVER=log (or PHPMailer not installed): write the mail to logs/mail.log instead.
		$this->log_mail('LOGGED', $to, $subject, $body);

		return true;
	}

	private function log_mail($state, $to, $subject, $body)
	{
		$dir = dirname(__DIR__) . '/logs';

		if(!is_dir($dir))
		{
			@mkdir($dir, 0775, true);
		}

		@file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . '] ' . $state . ' to=' . $to . ' subject=' . $subject . "\n" . $body . "\n----\n", FILE_APPEND);
	}

	//Moves the already validated upload in $this->filedata into upload/ under a random name.
	function Upload_file()
	{
		$extension = strtolower(pathinfo($this->filedata['name'], PATHINFO_EXTENSION));

		if(!in_array($extension, array('jpg', 'jpeg', 'png', 'gif'), true))
		{
			throw new RuntimeException('Invalid image type');
		}

		$dir = dirname(__DIR__) . '/upload/';

		if(!is_dir($dir))
		{
			mkdir($dir, 0775, true);
		}

		$name = bin2hex(random_bytes(12)) . '.' . $extension;

		if(!move_uploaded_file($this->filedata['tmp_name'], $dir . $name))
		{
			throw new RuntimeException('Upload failed');
		}

		return $name;
	}

	//-----------------------------------------------------------------
	// Lookups used by the user side
	//-----------------------------------------------------------------

	function Get_exam_id($code)
	{
		if(!valid_exam_code($code))
		{
			return 0;
		}

		foreach(db_rows($this, 'SELECT online_exam_id FROM online_exam_table WHERE online_exam_code = :code', array(':code' => $code)) as $row)
		{
			return (int)$row['online_exam_id'];
		}

		return 0;
	}

	function If_user_already_enroll_exam($exam_id, $user_id)
	{
		return db_count($this, 'SELECT user_exam_enroll_id FROM user_exam_enroll_table WHERE exam_id = :exam_id AND user_id = :user_id', array(':exam_id' => $exam_id, ':user_id' => $user_id)) > 0;
	}

	function Get_question_answer_option($question_id)
	{
		foreach(db_rows($this, 'SELECT answer_option FROM question_table WHERE question_id = :question_id', array(':question_id' => $question_id)) as $row)
		{
			return $row['answer_option'];
		}

		return '';
	}

	function Get_question_right_answer_mark($exam_id)
	{
		foreach(db_rows($this, 'SELECT marks_per_right_answer FROM online_exam_table WHERE online_exam_id = :exam_id', array(':exam_id' => $exam_id)) as $row)
		{
			return $row['marks_per_right_answer'];
		}

		return 0;
	}

	function Get_question_wrong_answer_mark($exam_id)
	{
		foreach(db_rows($this, 'SELECT marks_per_wrong_answer FROM online_exam_table WHERE online_exam_id = :exam_id', array(':exam_id' => $exam_id)) as $row)
		{
			return $row['marks_per_wrong_answer'];
		}

		return 0;
	}

	//<option> list of exams users can still enroll in.
	function Fill_exam_list()
	{
		$output = '';

		$result = db_rows($this, "
			SELECT online_exam_id, online_exam_title, online_exam_datetime
			FROM online_exam_table
			WHERE online_exam_status = 'Created'
			ORDER BY online_exam_datetime ASC
		");

		foreach($result as $row)
		{
			$output .= '<option value="' . (int)$row['online_exam_id'] . '">' . h_admin($row['online_exam_title']) . ' (' . h($row['online_exam_datetime']) . ')</option>';
		}

		return $output;
	}

	//Created -> Started when the start time is reached, -> Completed when the duration is over.
	function Change_exam_status($user_id = null)
	{
		$now = time();

		$rows = db_rows($this, "
			SELECT online_exam_id, online_exam_status, online_exam_datetime, online_exam_duration
			FROM online_exam_table
			WHERE online_exam_status IN ('Created', 'Started')
		");

		foreach($rows as $row)
		{
			$start = strtotime($row['online_exam_datetime']);

			$end = $start + ((int)$row['online_exam_duration'] * 60);

			$new_status = $row['online_exam_status'];

			if($now >= $end)
			{
				$new_status = 'Completed';
			}
			elseif($row['online_exam_status'] == 'Created' && $now >= $start)
			{
				$new_status = 'Started';
			}

			if($new_status != $row['online_exam_status'])
			{
				db_exec($this, 'UPDATE online_exam_table SET online_exam_status = :status WHERE online_exam_id = :id', array(':status' => $new_status, ':id' => $row['online_exam_id']));
			}
		}
	}

	//-----------------------------------------------------------------
	// Admin side: exams
	//-----------------------------------------------------------------

	//Returns the exam row if the logged in admin may manage it (master: all, sub admin: own), else false.
	function get_admin_exam($code)
	{
		if(!valid_exam_code($code))
		{
			return false;
		}

		foreach(db_rows($this, 'SELECT * FROM online_exam_table WHERE online_exam_code = :code', array(':code' => $code)) as $row)
		{
			if($this->is_master() || (int)$row['admin_id'] === $this->admin_id())
			{
				return $row;
			}
		}

		return false;
	}

	function exam_enrollment_count($exam_id)
	{
		return db_count($this, 'SELECT user_exam_enroll_id FROM user_exam_enroll_table WHERE exam_id = :exam_id', array(':exam_id' => $exam_id));
	}

	function exam_question_count($exam_id)
	{
		return db_count($this, 'SELECT question_id FROM question_table WHERE online_exam_id = :exam_id', array(':exam_id' => $exam_id));
	}

	//Validates the create/edit exam form. Returns cleaned values, fills $errors.
	function validate_exam_input($post, &$errors)
	{
		$title = trim(isset($post['online_exam_title']) ? (string)$post['online_exam_title'] : '');

		if($title === '' || mb_strlen($title) > 250)
		{
			$errors[] = 'Exam title is required (max 250 characters).';
		}

		$raw = trim(isset($post['online_exam_datetime']) ? (string)$post['online_exam_datetime'] : '');

		$date = DateTime::createFromFormat('Y-m-d\TH:i', $raw);

		if($date === false)
		{
			$date = DateTime::createFromFormat('Y-m-d\TH:i:s', $raw);
		}

		$datetime = '';

		if($date === false)
		{
			$errors[] = 'Please enter a valid exam date and time.';
		}
		else
		{
			$datetime = $date->format('Y-m-d H:i:s');

			if($date->getTimestamp() <= time())
			{
				$errors[] = 'The exam must start in the future.';
			}
		}

		$duration = isset($post['online_exam_duration']) ? (string)$post['online_exam_duration'] : '';

		if(!ctype_digit($duration) || (int)$duration < 1 || (int)$duration > 600)
		{
			$errors[] = 'Duration must be between 1 and 600 minutes.';
		}

		$total = isset($post['total_question']) ? (string)$post['total_question'] : '';

		if(!ctype_digit($total) || (int)$total < 1 || (int)$total > 200)
		{
			$errors[] = 'Total questions must be between 1 and 200.';
		}

		$right = isset($post['marks_per_right_answer']) ? trim((string)$post['marks_per_right_answer']) : '';

		if(!is_numeric($right) || (float)$right <= 0 || (float)$right > 100)
		{
			$errors[] = 'Marks per right answer must be a number above 0 (max 100).';
		}

		$wrong = isset($post['marks_per_wrong_answer']) ? trim((string)$post['marks_per_wrong_answer']) : '';

		if(!is_numeric($wrong) || (float)$wrong < 0 || (float)$wrong > 100)
		{
			$errors[] = 'Marks deducted per wrong answer must be 0 or more (max 100).';
		}

		return array(
			'title'		=>	$title,
			'datetime'	=>	$datetime,
			'duration'	=>	(string)(int)$duration,
			'total'		=>	(string)(int)$total,
			'right'		=>	is_numeric($right) ? format_marks($right) : '',
			'wrong'		=>	is_numeric($wrong) ? format_marks($wrong) : ''
		);
	}

	//Pending <-> Created depending on whether all questions have been added.
	function sync_exam_ready($exam_id)
	{
		foreach(db_rows($this, 'SELECT online_exam_status, total_question FROM online_exam_table WHERE online_exam_id = :id', array(':id' => $exam_id)) as $row)
		{
			$count = $this->exam_question_count($exam_id);

			if($row['online_exam_status'] == 'Pending' && $count >= (int)$row['total_question'])
			{
				db_exec($this, "UPDATE online_exam_table SET online_exam_status = 'Created' WHERE online_exam_id = :id", array(':id' => $exam_id));
			}
			elseif($row['online_exam_status'] == 'Created' && $count < (int)$row['total_question'])
			{
				db_exec($this, "UPDATE online_exam_table SET online_exam_status = 'Pending' WHERE online_exam_id = :id", array(':id' => $exam_id));
			}
		}
	}

	//-----------------------------------------------------------------
	// Admin side: questions
	//-----------------------------------------------------------------

	function validate_question_input($post, &$errors)
	{
		$title = trim(isset($post['question_title']) ? (string)$post['question_title'] : '');

		if($title === '' || mb_strlen($title) > 2000)
		{
			$errors[] = 'Question text is required (max 2000 characters).';
		}

		$options = array();

		for($i = 1; $i <= 4; $i++)
		{
			$value = trim(isset($post['option_' . $i]) ? (string)$post['option_' . $i] : '');

			if($value === '' || mb_strlen($value) > 250)
			{
				$errors[] = 'Option ' . $i . ' is required (max 250 characters).';
			}

			$options[$i] = $value;
		}

		$answer = isset($post['answer_option']) ? (string)$post['answer_option'] : '';

		if(!in_array($answer, array('1', '2', '3', '4'), true))
		{
			$errors[] = 'Please choose the correct option.';
		}

		return array('title' => $title, 'options' => $options, 'answer' => $answer);
	}

	function add_question($exam_id, $clean)
	{
		$this->begin();

		try
		{
			db_exec($this, 'INSERT INTO question_table (online_exam_id, question_title, answer_option) VALUES (:exam_id, :title, :answer)', array(
				':exam_id'	=>	$exam_id,
				':title'	=>	$clean['title'],
				':answer'	=>	$clean['answer']
			));

			$question_id = $this->last_id();

			foreach($clean['options'] as $number => $title)
			{
				db_exec($this, 'INSERT INTO option_table (question_id, option_number, option_title) VALUES (:question_id, :number, :title)', array(
					':question_id'	=>	$question_id,
					':number'		=>	$number,
					':title'		=>	$title
				));
			}

			$this->commit();
		}
		catch(Exception $e)
		{
			$this->rollback();

			throw $e;
		}

		return $question_id;
	}

	function update_question($question_id, $clean)
	{
		$this->begin();

		try
		{
			db_exec($this, 'UPDATE question_table SET question_title = :title, answer_option = :answer WHERE question_id = :id', array(
				':title'	=>	$clean['title'],
				':answer'	=>	$clean['answer'],
				':id'		=>	$question_id
			));

			foreach($clean['options'] as $number => $title)
			{
				db_exec($this, 'UPDATE option_table SET option_title = :title WHERE question_id = :question_id AND option_number = :number', array(
					':title'		=>	$title,
					':question_id'	=>	$question_id,
					':number'		=>	$number
				));
			}

			$this->commit();
		}
		catch(Exception $e)
		{
			$this->rollback();

			throw $e;
		}
	}

	//-----------------------------------------------------------------
	// Demo exam (dashboard button) so a fresh install can be tried end to end.
	//-----------------------------------------------------------------

	function create_demo_exam($admin_id, $starts_in_minutes)
	{
		$start = date('Y-m-d H:i:s', time() + ($starts_in_minutes * 60));

		db_exec($this, "
			INSERT INTO online_exam_table
			(admin_id, online_exam_title, online_exam_datetime, online_exam_duration, total_question, marks_per_right_answer, marks_per_wrong_answer, online_exam_created_on, online_exam_status, online_exam_code)
			VALUES
			(:admin_id, :title, :datetime, '15', 5, '2', '0.5', :created, 'Pending', :code)
		", array(
			':admin_id'	=>	$admin_id,
			':title'	=>	'Demo Exam - Web Basics',
			':datetime'	=>	$start,
			':created'	=>	date('Y-m-d H:i:s'),
			':code'		=>	bin2hex(random_bytes(16))
		));

		$exam_id = $this->last_id();

		$questions = array(
			array('What does PHP stand for?', array('Personal Home Page Tools', 'PHP: Hypertext Preprocessor', 'Private Hypertext Processor', 'Programmed Hyper Pages'), '2'),
			array('Which HTTP status code means "Not Found"?', array('200', '301', '404', '500'), '3'),
			array('Which SQL statement reads rows from a table?', array('INSERT', 'SELECT', 'UPDATE', 'DELETE'), '2'),
			array('Which language runs natively in web browsers?', array('JavaScript', 'PHP', 'Python', 'SQL'), '1'),
			array('What does CSS control?', array('Database access', 'Server routing', 'Page styling and layout', 'Email delivery'), '3')
		);

		foreach($questions as $item)
		{
			$this->add_question($exam_id, array('title' => $item[0], 'options' => array(1 => $item[1][0], 2 => $item[1][1], 3 => $item[1][2], 4 => $item[1][3]), 'answer' => $item[2]));
		}

		$this->sync_exam_ready($exam_id);

		return $exam_id;
	}
}

?>
