<?php

//pdf_exam_result.php

include('master/Examination.php');

require_once('class/pdf.php');

require_once('helpers.php');

$exam = new Examination;

$exam->user_session_private();

$user_id = (int)$_SESSION['user_id'];

$code = isset($_GET['code']) ? $_GET['code'] : '';

if(!preg_match('/^[A-Za-z0-9]+$/', $code))
{
	header('location:enroll_exam.php');
	exit;
}

$exam_id = $exam->Get_exam_id($code);

//Only enrolled users may see results, and only once the exam is completed.
$allowed = db_count($exam, "
	SELECT online_exam_table.online_exam_id FROM online_exam_table
	INNER JOIN user_exam_enroll_table
	ON user_exam_enroll_table.exam_id = online_exam_table.online_exam_id
	WHERE online_exam_table.online_exam_id = :exam_id
	AND online_exam_table.online_exam_status = 'Completed'
	AND user_exam_enroll_table.user_id = :user_id
", array(':exam_id' => $exam_id, ':user_id' => $user_id));

if($allowed == 0)
{
	header('location:enroll_exam.php');
	exit;
}

$result = db_rows($exam, "
	SELECT * FROM question_table
	INNER JOIN user_exam_question_answer
	ON user_exam_question_answer.question_id = question_table.question_id
	WHERE question_table.online_exam_id = :exam_id
	AND user_exam_question_answer.user_id = :user_id
", array(':exam_id' => $exam_id, ':user_id' => $user_id));

$output = '
<h3 align="center">Exam Result</h3>
<table width="100%" border="1" cellpadding="5" cellspacing="0">
	<tr>
		<th>Question</th>
		<th>Your Answer</th>
		<th>Answer</th>
		<th>Result</th>
		<th>Marks</th>
	</tr>
';

foreach($result as $row)
{
	$sub_result = db_rows($exam, "
		SELECT * FROM option_table
		WHERE question_id = :question_id
	", array(':question_id' => $row["question_id"]));

	$user_answer = '';
	$orignal_answer = '';
	$question_result = '';

	if($row['user_answer_option'] == '0')
	{
		$question_result = 'Not Attend';
	}
	elseif($row['user_answer_option'] == $row['answer_option'])
	{
		$question_result = 'Right';
	}
	else
	{
		$question_result = 'Wrong';
	}

	foreach($sub_result as $sub_row)
	{
		if($sub_row["option_number"] == $row["user_answer_option"])
		{
			$user_answer = $sub_row["option_title"];
		}

		if($sub_row["option_number"] == $row["answer_option"])
		{
			$orignal_answer = $sub_row["option_title"];
		}
	}

	$output .= '
	<tr>
		<td>'.h_admin($row["question_title"]).'</td>
		<td>'.h_admin($user_answer).'</td>
		<td>'.h_admin($orignal_answer).'</td>
		<td>'.$question_result.'</td>
		<td>'.h($row["marks"]).'</td>
	</tr>
	';
}

$marks_result = db_rows($exam, "
	SELECT SUM(marks) as total_mark FROM user_exam_question_answer
	WHERE user_id = :user_id
	AND exam_id = :exam_id
", array(':user_id' => $user_id, ':exam_id' => $exam_id));

foreach($marks_result as $row)
{
	$output .= '
	<tr>
		<td colspan="4" align="right">Total Marks</td>
		<td align="right">'.h($row["total_mark"]).'</td>
	</tr>
	';
}

$output .= '</table>';

$pdf = new Pdf();

$pdf->set_paper('letter','landscape');

$file_name = 'Exam Result.pdf';

$pdf->loadHtml($output);

$pdf->render();

$pdf->stream($file_name, array("Attachment" => false));

exit(0);

?>
