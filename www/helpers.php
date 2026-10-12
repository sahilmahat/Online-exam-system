<?php

//helpers.php
//Shared helpers: output escaping, prepared-query wrappers, JSON responses, CSRF, flash messages.

//Escape user supplied text for HTML output.
function h($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

//Escape text that may already be HTML-encoded (exam titles, questions, options).
//Decoding first avoids double encoding (&amp;amp;) while still being safe to print.
function h_admin($value)
{
	return h(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
}

//Run a SELECT with bound parameters and return all rows.
function db_rows($exam, $sql, $params = array())
{
	$exam->query = $sql;
	$exam->data = $params;
	return $exam->query_result();
}

//Run a SELECT with bound parameters and return the row count.
function db_count($exam, $sql, $params = array())
{
	$exam->query = $sql;
	$exam->data = $params;
	return $exam->total_row();
}

//Run INSERT / UPDATE / DELETE with bound parameters.
function db_exec($exam, $sql, $params = array())
{
	$exam->query = $sql;
	$exam->data = $params;
	$exam->execute_query();
}

//Send a JSON response and stop.
function json_out($payload, $status = 200)
{
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($payload);
	exit;
}

function redirect_to($url)
{
	header('location:' . $url);
	exit;
}

//Validate an uploaded image (error code, size, real image type). Returns an error string or ''.
function check_uploaded_image($file)
{
	if(!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK)
	{
		return 'Please select a valid image';
	}

	if($file['size'] > 2 * 1024 * 1024)
	{
		return 'Image must be smaller than 2 MB';
	}

	$info = @getimagesize($file['tmp_name']);

	if($info === false || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF), true))
	{
		return 'Only JPG, PNG or GIF images are allowed';
	}

	$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

	if(!in_array($extension, array('jpg', 'jpeg', 'png', 'gif'), true))
	{
		return 'Only JPG, PNG or GIF images are allowed';
	}

	return '';
}

//Exam codes are random hex strings; reject anything else before it reaches a query.
function valid_exam_code($code)
{
	return is_string($code) && preg_match('/^[A-Za-z0-9]{1,100}$/', $code) === 1;
}

//Print marks without useless zeros: 2.00 -> 2, 0.50 -> 0.5
function format_marks($value)
{
	return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
}

//---------------------------------------------------------------------
// CSRF
//---------------------------------------------------------------------

function csrf_token()
{
	if(session_status() === PHP_SESSION_NONE)
	{
		session_start();
	}

	if(empty($_SESSION['csrf_token']))
	{
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function csrf_field()
{
	return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '" />';
}

//Token may come from the X-CSRF-Token header (AJAX) or the _csrf form field.
function csrf_valid()
{
	$sent = '';

	if(isset($_SERVER['HTTP_X_CSRF_TOKEN']))
	{
		$sent = $_SERVER['HTTP_X_CSRF_TOKEN'];
	}
	elseif(isset($_POST['_csrf']))
	{
		$sent = $_POST['_csrf'];
	}

	return is_string($sent) && $sent !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

//---------------------------------------------------------------------
// Flash messages (shown once after a redirect)
//---------------------------------------------------------------------

function flash_set($type, $message)
{
	$_SESSION['flash'][] = array($type, $message);
}

function flash_render()
{
	$output = '';

	if(!empty($_SESSION['flash']))
	{
		foreach($_SESSION['flash'] as $item)
		{
			$output .= '<div class="alert alert-' . h($item[0]) . ' alert-dismissible fade show" role="alert">' . h($item[1]) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
		}

		unset($_SESSION['flash']);
	}

	return $output;
}

?>
