<?php

//user_ajax_action.php

include('master/Examination.php');

require_once('class/class.phpmailer.php');

require_once('helpers.php');

$exam = new Examination;

$current_datetime = date('Y-m-d H:i:s');

//---------------------------------------------------------------------
// Local helpers
//---------------------------------------------------------------------

//Fetch an exam row only if the logged in user is enrolled in it.
function get_enrolled_exam($exam, $exam_id, $user_id)
{
	$rows = db_rows($exam, "
		SELECT online_exam_table.* FROM online_exam_table
		INNER JOIN user_exam_enroll_table
		ON user_exam_enroll_table.exam_id = online_exam_table.online_exam_id
		WHERE user_exam_enroll_table.user_id = :user_id
		AND online_exam_table.online_exam_id = :exam_id
	", array(':user_id' => $user_id, ':exam_id' => $exam_id));

	foreach($rows as $row)
	{
		return $row;
	}

	return false;
}

//True while the exam is Started and its time window has not ended.
function exam_in_progress($exam_row)
{
	if($exam_row['online_exam_status'] != 'Started')
	{
		return false;
	}

	$end = strtotime($exam_row['online_exam_datetime']) + ((int)$exam_row['online_exam_duration'] * 60);

	return time() <= $end;
}

//True if the question belongs to the exam.
function question_in_exam($exam, $question_id, $exam_id)
{
	return db_count($exam, "
		SELECT question_id FROM question_table
		WHERE question_id = :question_id AND online_exam_id = :exam_id
	", array(':question_id' => $question_id, ':exam_id' => $exam_id)) > 0;
}

//---------------------------------------------------------------------
// Request handling
//---------------------------------------------------------------------

if(!isset($_POST['page'], $_POST['action']))
{
	json_out(array('error' => 'Bad request'), 400);
}

//Every state-changing request must carry the session's CSRF token (see the X-CSRF-Token header set in header.php).
if(!csrf_valid())
{
	json_out(array('error' => 'Invalid security token. Please reload the page.'), 403);
}

$page = $_POST['page'];
$action = $_POST['action'];

$public_actions = array('register' => array('check_email', 'register'), 'login' => array('login'));

$is_public = isset($public_actions[$page]) && in_array($action, $public_actions[$page], true);

if(!$is_public && !$exam->user_logged_in())
{
	json_out(array('error' => 'Please login first'), 401);
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

//------------------------------ register -------------------------------
if($page == 'register' && $action == 'check_email')
{
	$email = trim(isset($_POST['email']) ? $_POST['email'] : '');

	$total_row = db_count($exam, "
		SELECT user_id FROM user_table
		WHERE user_email_address = :email
	", array(':email' => $email));

	if($total_row == 0)
	{
		json_out(array('success' => true));
	}

	//Non-2xx makes Parsley treat the e-mail as already taken.
	json_out(array('success' => false, 'error' => 'Email Address already Exists'), 409);
}

if($page == 'register' && $action == 'register')
{
	$receiver_email = trim(isset($_POST['user_email_address']) ? $_POST['user_email_address'] : '');
	$password = isset($_POST['user_password']) ? $_POST['user_password'] : '';
	$confirm = isset($_POST['confirm_user_password']) ? $_POST['confirm_user_password'] : '';
	$name = trim(isset($_POST['user_name']) ? $_POST['user_name'] : '');
	$gender = isset($_POST['user_gender']) ? $_POST['user_gender'] : '';
	$address = trim(isset($_POST['user_address']) ? $_POST['user_address'] : '');
	$mobile = trim(isset($_POST['user_mobile_no']) ? $_POST['user_mobile_no'] : '');

	if(!filter_var($receiver_email, FILTER_VALIDATE_EMAIL))
	{
		json_out(array('success' => false, 'error' => 'Invalid email address'), 422);
	}

	if(strlen($password) < 8 || $password !== $confirm)
	{
		json_out(array('success' => false, 'error' => 'Password must be at least 8 characters and match the confirmation'), 422);
	}

	if(!preg_match('/^[a-zA-Z ]+$/', $name) || $address === '' || !preg_match('/^[0-9]+$/', $mobile) || !in_array($gender, array('Male', 'Female'), true))
	{
		json_out(array('success' => false, 'error' => 'Please fill in all fields correctly'), 422);
	}

	if(db_count($exam, "SELECT user_id FROM user_table WHERE user_email_address = :email", array(':email' => $receiver_email)) > 0)
	{
		json_out(array('success' => false, 'error' => 'Email Address already Exists'), 409);
	}

	$image_error = check_uploaded_image(isset($_FILES['user_image']) ? $_FILES['user_image'] : null);

	if($image_error != '')
	{
		json_out(array('success' => false, 'error' => $image_error), 422);
	}

	$user_verfication_code = bin2hex(random_bytes(16));

	$exam->filedata = $_FILES['user_image'];

	$user_image = $exam->Upload_file();

	db_exec($exam, "
		INSERT INTO user_table
		(user_email_address, user_password, user_verfication_code, user_name, user_gender, user_address, user_mobile_no, user_image, user_created_on, user_email_verified)
		VALUES
		(:user_email_address, :user_password, :user_verfication_code, :user_name, :user_gender, :user_address, :user_mobile_no, :user_image, :user_created_on, 'no')
	", array(
		':user_email_address'	=>	$receiver_email,
		':user_password'		=>	password_hash($password, PASSWORD_DEFAULT),
		':user_verfication_code'=>	$user_verfication_code,
		':user_name'			=>	$name,
		':user_gender'			=>	$gender,
		':user_address'			=>	$address,
		':user_mobile_no'		=>	$mobile,
		':user_image'			=>	$user_image,
		':user_created_on'		=>	$current_datetime
	));

	$subject = 'Online Examination Registration Verification';

	$body = '
	<p>Thank you for registering.</p>
	<p>This is a verification eMail, please click the link to verify your eMail address by clicking this <a href="'.$exam->home_page.'verify_email.php?type=user&code='.$user_verfication_code.'" target="_blank"><b>link</b></a>.</p>
	<p>In case if you have any difficulty please eMail us.</p>
	<p>Thank you,</p>
	<p>Online Examination System</p>
	';

	$exam->send_email($receiver_email, $subject, $body);

	json_out(array('success' => true));
}

//------------------------------- login ---------------------------------
if($page == 'login' && $action == 'login')
{
	$email = trim(isset($_POST['user_email_address']) ? $_POST['user_email_address'] : '');
	$password = isset($_POST['user_password']) ? $_POST['user_password'] : '';

	$result = db_rows($exam, "
		SELECT * FROM user_table
		WHERE user_email_address = :email
	", array(':email' => $email));

	$output = array('error' => 'Wrong email address or password');

	foreach($result as $row)
	{
		if(password_verify($password, $row['user_password']))
		{
			if($row['user_email_verified'] == 'yes')
			{
				session_regenerate_id(true);

				$_SESSION['user_id'] = $row['user_id'];

				$output = array('success' => true);
			}
			else
			{
				$output = array('error' => 'Your Email is not verify');
			}
		}
	}

	json_out($output);
}

//------------------------------ profile --------------------------------
if($page == 'profile' && $action == 'profile')
{
	$name = trim(isset($_POST['user_name']) ? $_POST['user_name'] : '');
	$gender = isset($_POST['user_gender']) ? $_POST['user_gender'] : '';
	$address = trim(isset($_POST['user_address']) ? $_POST['user_address'] : '');
	$mobile = trim(isset($_POST['user_mobile_no']) ? $_POST['user_mobile_no'] : '');

	if(!preg_match('/^[a-zA-Z ]+$/', $name) || $address === '' || !preg_match('/^[0-9]+$/', $mobile) || !in_array($gender, array('Male', 'Female'), true))
	{
		json_out(array('error' => 'Please fill in all fields correctly'), 422);
	}

	//Take the current image from the database, never from a hidden form field.
	$user_image = '';

	foreach(db_rows($exam, "SELECT user_image FROM user_table WHERE user_id = :user_id", array(':user_id' => $user_id)) as $row)
	{
		$user_image = $row['user_image'];
	}

	if(isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '')
	{
		$image_error = check_uploaded_image($_FILES['user_image']);

		if($image_error != '')
		{
			json_out(array('error' => $image_error), 422);
		}

		$exam->filedata = $_FILES['user_image'];

		$user_image = $exam->Upload_file();
	}

	db_exec($exam, "
		UPDATE user_table
		SET user_name = :user_name, user_gender = :user_gender, user_address = :user_address, user_mobile_no = :user_mobile_no, user_image = :user_image
		WHERE user_id = :user_id
	", array(
		':user_name'		=>	$name,
		':user_gender'		=>	$gender,
		':user_address'		=>	$address,
		':user_mobile_no'	=>	$mobile,
		':user_image'		=>	$user_image,
		':user_id'			=>	$user_id
	));

	json_out(array('success' => true));
}

//--------------------------- change password ---------------------------
if($page == 'change_password' && $action == 'change_password')
{
	$current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
	$password = isset($_POST['user_password']) ? $_POST['user_password'] : '';
	$confirm = isset($_POST['confirm_user_password']) ? $_POST['confirm_user_password'] : '';

	if(strlen($password) < 8 || $password !== $confirm)
	{
		json_out(array('error' => 'Password must be at least 8 characters and match the confirmation'), 422);
	}

	$valid = false;

	foreach(db_rows($exam, "SELECT user_password FROM user_table WHERE user_id = :user_id", array(':user_id' => $user_id)) as $row)
	{
		$valid = password_verify($current, $row['user_password']);
	}

	if(!$valid)
	{
		json_out(array('error' => 'Current password is wrong'), 403);
	}

	db_exec($exam, "
		UPDATE user_table SET user_password = :user_password WHERE user_id = :user_id
	", array(
		':user_password'	=>	password_hash($password, PASSWORD_DEFAULT),
		':user_id'			=>	$user_id
	));

	session_destroy();

	json_out(array('success' => 'Password has been change'));
}

//------------------------- index (exam details) ------------------------
if($page == 'index' && $action == 'fetch_exam')
{
	$exam_id = (int)(isset($_POST['exam_id']) ? $_POST['exam_id'] : 0);

	$result = db_rows($exam, "
		SELECT * FROM online_exam_table WHERE online_exam_id = :exam_id
	", array(':exam_id' => $exam_id));

	$output = '
	<div class="card">
		<div class="card-header">Exam Details</div>
		<div class="card-body">
			<table class="table table-striped table-hover table-bordered">
	';

	foreach($result as $row)
	{
		$output .= '
		<tr>
			<td><b>Exam Title</b></td>
			<td>'.h_admin($row["online_exam_title"]).'</td>
		</tr>
		<tr>
			<td><b>Exam Date & Time</b></td>
			<td>'.h($row["online_exam_datetime"]).'</td>
		</tr>
		<tr>
			<td><b>Exam Duration</b></td>
			<td>'.h($row["online_exam_duration"]).' Minute</td>
		</tr>
		<tr>
			<td><b>Exam Total Question</b></td>
			<td>'.h($row["total_question"]).' </td>
		</tr>
		<tr>
			<td><b>Marks Per Right Answer</b></td>
			<td>'.h($row["marks_per_right_answer"]).' Mark</td>
		</tr>
		<tr>
			<td><b>Marks Per Wrong Answer</b></td>
			<td>-'.h($row["marks_per_wrong_answer"]).' Mark</td>
		</tr>
		';

		if($exam->If_user_already_enroll_exam($row['online_exam_id'], $user_id))
		{
			$output .= '
			<tr>
				<td colspan="2" align="center">
					<button type="button" name="enroll_button" class="btn btn-info">You Already Enroll it</button>
				</td>
			</tr>
			';
		}
		else
		{
			$output .= '
			<tr>
				<td colspan="2" align="center">
					<button type="button" name="enroll_button" id="enroll_button" class="btn btn-warning" data-exam_id="'.(int)$row['online_exam_id'].'">Enroll it</button>
				</td>
			</tr>
			';
		}
	}

	$output .= '</table></div></div>';

	echo $output;
	exit;
}

if($page == 'index' && $action == 'enroll_exam')
{
	$exam_id = (int)(isset($_POST['exam_id']) ? $_POST['exam_id'] : 0);

	$exam_rows = db_rows($exam, "
		SELECT online_exam_id FROM online_exam_table
		WHERE online_exam_id = :exam_id AND online_exam_status = 'Created'
	", array(':exam_id' => $exam_id));

	if(count($exam_rows) == 0)
	{
		json_out(array('error' => 'This exam is not open for enrollment'), 422);
	}

	if($exam->If_user_already_enroll_exam($exam_id, $user_id))
	{
		json_out(array('error' => 'You are already enrolled in this exam'), 409);
	}

	db_exec($exam, "
		INSERT INTO user_exam_enroll_table (user_id, exam_id, attendance_status)
		VALUES (:user_id, :exam_id, 'Absent')
	", array(':user_id' => $user_id, ':exam_id' => $exam_id));

	$questions = db_rows($exam, "
		SELECT question_id FROM question_table WHERE online_exam_id = :exam_id
	", array(':exam_id' => $exam_id));

	foreach($questions as $row)
	{
		db_exec($exam, "
			INSERT INTO user_exam_question_answer
			(user_id, exam_id, question_id, user_answer_option, marks)
			VALUES (:user_id, :exam_id, :question_id, '0', '0')
		", array(
			':user_id'		=>	$user_id,
			':exam_id'		=>	$exam_id,
			':question_id'	=>	$row['question_id']
		));
	}

	json_out(array('success' => true));
}

//--------------------- enroll_exam (DataTables list) -------------------
if($page == 'enroll_exam' && $action == 'fetch')
{
	//DataTables column index => SQL expression. Never trust the index as raw SQL.
	$order_columns = array(
		0 => 'online_exam_table.online_exam_title',
		1 => 'online_exam_table.online_exam_datetime',
		2 => 'CAST(online_exam_table.online_exam_duration AS UNSIGNED)',
		3 => 'online_exam_table.total_question',
		4 => 'CAST(online_exam_table.marks_per_right_answer AS DECIMAL(10,2))',
		5 => 'CAST(online_exam_table.marks_per_wrong_answer AS DECIMAL(10,2))',
		6 => 'online_exam_table.online_exam_status'
	);

	$base = "
		FROM user_exam_enroll_table
		INNER JOIN online_exam_table
		ON online_exam_table.online_exam_id = user_exam_enroll_table.exam_id
		WHERE user_exam_enroll_table.user_id = :user_id
	";

	$params = array(':user_id' => $user_id);

	$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

	$where = '';

	if($search !== '')
	{
		$like = '%'.$search.'%';

		$where = " AND (
			online_exam_table.online_exam_title LIKE :s1
			OR online_exam_table.online_exam_datetime LIKE :s2
			OR online_exam_table.online_exam_duration LIKE :s3
			OR online_exam_table.total_question LIKE :s4
			OR online_exam_table.marks_per_right_answer LIKE :s5
			OR online_exam_table.marks_per_wrong_answer LIKE :s6
			OR online_exam_table.online_exam_status LIKE :s7
		)";

		for($i = 1; $i <= 7; $i++)
		{
			$params[':s'.$i] = $like;
		}
	}

	$order_by = ' ORDER BY online_exam_table.online_exam_id DESC';

	if(isset($_POST['order'][0]['column']))
	{
		$column = (int)$_POST['order'][0]['column'];

		if(isset($order_columns[$column]))
		{
			$dir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) == 'asc') ? 'ASC' : 'DESC';

			$order_by = ' ORDER BY '.$order_columns[$column].' '.$dir;
		}
	}

	$limit = '';

	if(isset($_POST['length']) && (int)$_POST['length'] != -1)
	{
		$limit = ' LIMIT '.max(0, (int)(isset($_POST['start']) ? $_POST['start'] : 0)).', '.max(1, (int)$_POST['length']);
	}

	$filtered_rows = db_count($exam, 'SELECT online_exam_table.online_exam_id '.$base.$where, $params);

	$result = db_rows($exam, 'SELECT online_exam_table.* '.$base.$where.$order_by.$limit, $params);

	$total_rows = db_count($exam, 'SELECT online_exam_table.online_exam_id '.$base, array(':user_id' => $user_id));

	$data = array();

	foreach($result as $row)
	{
		$sub_array = array();
		$sub_array[] = h_admin($row["online_exam_title"]);
		$sub_array[] = h($row["online_exam_datetime"]);
		$sub_array[] = h($row["online_exam_duration"]) . ' Minute';
		$sub_array[] = h($row["total_question"]) . ' Question';
		$sub_array[] = h($row["marks_per_right_answer"]) . ' Mark';
		$sub_array[] = '-' . h($row["marks_per_wrong_answer"]) . ' Mark';

		$status = '';
		$view_exam = '';

		if($row['online_exam_status'] == 'Created')
		{
			$status = '<span class="badge badge-success">Created</span>';
		}

		if($row['online_exam_status'] == 'Started')
		{
			$status = '<span class="badge badge-primary">Started</span>';
		}

		if($row['online_exam_status'] == 'Completed')
		{
			$status = '<span class="badge badge-dark">Completed</span>';
		}

		$sub_array[] = $status;

		if($row["online_exam_status"] == 'Started' || $row["online_exam_status"] == 'Completed')
		{
			$view_exam = '<a href="view_exam.php?code='.urlencode($row["online_exam_code"]).'" class="btn btn-info btn-sm">View Exam</a>';
		}

		$sub_array[] = $view_exam;

		$data[] = $sub_array;
	}

	json_out(array(
		"draw"				=>	intval(isset($_POST["draw"]) ? $_POST["draw"] : 0),
		"recordsTotal"		=>	$total_rows,
		"recordsFiltered"	=>	$filtered_rows,
		"data"				=>	$data
	));
}

//----------------------------- view_exam -------------------------------
if($page == 'view_exam')
{
	if($action == 'user_detail')
	{
		$result = db_rows($exam, "SELECT * FROM user_table WHERE user_id = :user_id", array(':user_id' => $user_id));

		$output = '
		<div class="card">
			<div class="card-header">User Details</div>
			<div class="card-body">
				<div class="row">
		';

		foreach($result as $row)
		{
			$output .= '
			<div class="col-md-3">
				<img src="upload/'.h($row["user_image"]).'" class="img-fluid" />
			</div>
			<div class="col-md-9">
				<table class="table table-bordered">
					<tr>
						<th>Name</th>
						<td>'.h($row["user_name"]).'</td>
					</tr>
					<tr>
						<th>Email ID</th>
						<td>'.h($row["user_email_address"]).'</td>
					</tr>
					<tr>
						<th>Gender</th>
						<td>'.h($row["user_gender"]).'</td>
					</tr>
				</table>
			</div>
			';
		}

		$output .= '</div></div></div>';

		echo $output;
		exit;
	}

	//Everything below needs an exam the user is enrolled in, and that is currently running.
	$exam_id = (int)(isset($_POST['exam_id']) ? $_POST['exam_id'] : 0);

	$exam_row = get_enrolled_exam($exam, $exam_id, $user_id);

	if($exam_row === false || !exam_in_progress($exam_row))
	{
		json_out(array('error' => 'Exam is not available'), 403);
	}

	if($action == 'load_question')
	{
		$question_id = (int)(isset($_POST['question_id']) ? $_POST['question_id'] : 0);

		if($question_id == 0)
		{
			$result = db_rows($exam, "
				SELECT * FROM question_table
				WHERE online_exam_id = :exam_id
				ORDER BY question_id ASC LIMIT 1
			", array(':exam_id' => $exam_id));
		}
		else
		{
			$result = db_rows($exam, "
				SELECT * FROM question_table
				WHERE question_id = :question_id AND online_exam_id = :exam_id
			", array(':question_id' => $question_id, ':exam_id' => $exam_id));
		}

		$output = '';

		foreach($result as $row)
		{
			$output .= '
			<h1 class="question-title">'.h_admin($row["question_title"]).'</h1>
			<hr />
			<br />
			<div class="row">
			';

			//Currently saved answer for this question, so it stays selected when navigating.
			$saved_answer = '0';

			foreach(db_rows($exam, "
				SELECT user_answer_option FROM user_exam_question_answer
				WHERE user_id = :user_id AND exam_id = :exam_id AND question_id = :question_id
			", array(':user_id' => $user_id, ':exam_id' => $exam_id, ':question_id' => $row['question_id'])) as $saved_row)
			{
				$saved_answer = $saved_row['user_answer_option'];
			}

			$sub_result = db_rows($exam, "
				SELECT * FROM option_table
				WHERE question_id = :question_id
				ORDER BY option_number ASC
			", array(':question_id' => $row['question_id']));

			foreach($sub_result as $sub_row)
			{
				$checked = ($saved_answer == $sub_row["option_number"]) ? ' checked' : '';

				$output .= '
				<div class="col-md-6" style="margin-bottom:32px;">
					<div class="radio">
						<label><h4><input type="radio" name="option_'.(int)$row["question_id"].'" class="answer_option" data-question_id="'.(int)$row["question_id"].'" data-id="'.(int)$sub_row["option_number"].'"'.$checked.' />&nbsp;'.h_admin($sub_row["option_title"]).'</h4></label>
					</div>
				</div>
				';
			}

			$output .= '
			</div>
			';

			$previous_id = '';
			$next_id = '';

			foreach(db_rows($exam, "
				SELECT question_id FROM question_table
				WHERE question_id < :question_id AND online_exam_id = :exam_id
				ORDER BY question_id DESC LIMIT 1
			", array(':question_id' => $row['question_id'], ':exam_id' => $exam_id)) as $previous_row)
			{
				$previous_id = $previous_row['question_id'];
			}

			foreach(db_rows($exam, "
				SELECT question_id FROM question_table
				WHERE question_id > :question_id AND online_exam_id = :exam_id
				ORDER BY question_id ASC LIMIT 1
			", array(':question_id' => $row['question_id'], ':exam_id' => $exam_id)) as $next_row)
			{
				$next_id = $next_row['question_id'];
			}

			$if_previous_disable = ($previous_id == '') ? 'disabled' : '';
			$if_next_disable = ($next_id == '') ? 'disabled' : '';

			$output .= '
				<br /><br />
				<div align="center">
					<button type="button" name="previous" class="btn btn-info btn-lg previous" id="'.(int)$previous_id.'" '.$if_previous_disable.'>Previous</button>
					<button type="button" name="next" class="btn btn-warning btn-lg next" id="'.(int)$next_id.'" '.$if_next_disable.'>Next</button>
				</div>
				<br /><br />';
		}

		echo $output;
		exit;
	}

	if($action == 'question_navigation')
	{
		$result = db_rows($exam, "
			SELECT question_id FROM question_table
			WHERE online_exam_id = :exam_id
			ORDER BY question_id ASC
		", array(':exam_id' => $exam_id));

		$output = '
		<div class="card">
			<div class="card-header">Question Navigation</div>
			<div class="card-body">
				<div class="row">
		';

		$count = 1;

		foreach($result as $row)
		{
			$output .= '
			<div class="col-md-2" style="margin-bottom:24px;">
				<button type="button" class="btn btn-primary btn-lg question_navigation" data-question_id="'.(int)$row["question_id"].'">'.$count.'</button>
			</div>
			';
			$count++;
		}

		$output .= '
				</div>
			</div>
		</div>
		';

		echo $output;
		exit;
	}

	if($action == 'answer')
	{
		$question_id = (int)(isset($_POST['question_id']) ? $_POST['question_id'] : 0);
		$answer_option = isset($_POST['answer_option']) ? $_POST['answer_option'] : '';

		if(!in_array($answer_option, array('1', '2', '3', '4'), true) || !question_in_exam($exam, $question_id, $exam_id))
		{
			json_out(array('error' => 'Invalid answer'), 422);
		}

		$original_answer = $exam->Get_question_answer_option($question_id);

		if($original_answer == $answer_option)
		{
			$marks = '+' . $exam_row['marks_per_right_answer'];
		}
		else
		{
			$marks = '-' . $exam_row['marks_per_wrong_answer'];
		}

		db_exec($exam, "
			UPDATE user_exam_question_answer
			SET user_answer_option = :user_answer_option, marks = :marks
			WHERE user_id = :user_id
			AND exam_id = :exam_id
			AND question_id = :question_id
		", array(
			':user_answer_option'	=>	$answer_option,
			':marks'				=>	$marks,
			':user_id'				=>	$user_id,
			':exam_id'				=>	$exam_id,
			':question_id'			=>	$question_id
		));

		json_out(array('success' => true));
	}
}

json_out(array('error' => 'Unknown action'), 400);

?>
