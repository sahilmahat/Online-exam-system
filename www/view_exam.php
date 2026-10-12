<?php

//view_exam.php

include('master/Examination.php');

require_once('helpers.php');

$exam = new Examination;

$exam->user_session_private();

$user_id = (int)$_SESSION['user_id'];

$code = isset($_GET['code']) ? $_GET['code'] : '';

//Redirects must happen before header.php prints any HTML.
if(!preg_match('/^[A-Za-z0-9]+$/', $code))
{
	header('location:enroll_exam.php');
	exit;
}

$exam_id = $exam->Get_exam_id($code);

//The user must be enrolled in this exam.
$result = db_rows($exam, "
	SELECT online_exam_table.online_exam_status, online_exam_table.online_exam_datetime, online_exam_table.online_exam_duration
	FROM online_exam_table
	INNER JOIN user_exam_enroll_table
	ON user_exam_enroll_table.exam_id = online_exam_table.online_exam_id
	WHERE online_exam_table.online_exam_id = :exam_id
	AND user_exam_enroll_table.user_id = :user_id
", array(':exam_id' => $exam_id, ':user_id' => $user_id));

if(count($result) == 0)
{
	header('location:enroll_exam.php');
	exit;
}

$exam_status = '';
$remaining_seconds = 0;

foreach($result as $row)
{
	$exam_status = $row['online_exam_status'];

	$exam_end_time = strtotime($row['online_exam_datetime']) + ((int)$row['online_exam_duration'] * 60);

	$remaining_seconds = max(0, $exam_end_time - time());
}

if($exam_status != 'Started' && $exam_status != 'Completed')
{
	header('location:enroll_exam.php');
	exit;
}

include('header.php');

?>

<br />
<?php
if($exam_status == 'Started')
{
	db_exec($exam, "
		UPDATE user_exam_enroll_table
		SET attendance_status = 'Present'
		WHERE user_id = :user_id
		AND exam_id = :exam_id
	", array(':user_id' => $user_id, ':exam_id' => $exam_id));

?>
<div class="row">
	<div class="col-md-8">
		<div class="card">
			<div class="card-header">Online Exam</div>
			<div class="card-body">
				<div id="single_question_area"></div>
			</div>
		</div>
		<br />
		<div id="question_navigation_area"></div>
	</div>
	<div class="col-md-4">
		<br />
		<div align="center">
			<div id="exam_timer" data-timer="<?php echo (int)$remaining_seconds; ?>" style="max-width:400px; width: 100%; height: 200px;"></div>
		</div>
		<br />
		<div id="user_details_area"></div>
	</div>
</div>

<script>

$(document).ready(function(){
	var exam_id = <?php echo (int)$exam_id; ?>;

	load_question();
	question_navigation();

	function load_question(question_id = '')
	{
		$.ajax({
			url:"user_ajax_action.php",
			method:"POST",
			data:{exam_id:exam_id, question_id:question_id, page:'view_exam', action:'load_question'},
			success:function(data)
			{
				$('#single_question_area').html(data);
			}
		})
	}

	$(document).on('click', '.next', function(){
		var question_id = $(this).attr('id');
		load_question(question_id);
	});

	$(document).on('click', '.previous', function(){
		var question_id = $(this).attr('id');
		load_question(question_id);
	});

	function question_navigation()
	{
		$.ajax({
			url:"user_ajax_action.php",
			method:"POST",
			data:{exam_id:exam_id, page:'view_exam', action:'question_navigation'},
			success:function(data)
			{
				$('#question_navigation_area').html(data);
			}
		})
	}

	$(document).on('click', '.question_navigation', function(){
		var question_id = $(this).data('question_id');
		load_question(question_id);
	});

	function load_user_details()
	{
		$.ajax({
			url:"user_ajax_action.php",
			method:"POST",
			data:{page:'view_exam', action:'user_detail'},
			success:function(data)
			{
				$('#user_details_area').html(data);
			}
		})
	}

	load_user_details();

	$("#exam_timer").TimeCircles({
		time:{
			Days:{
				show: false
			},
			Hours:{
				show: false
			}
		}
	});

	var exam_over = false;

	setInterval(function(){
		var remaining_second = $("#exam_timer").TimeCircles().getTime();
		if(remaining_second < 1 && !exam_over)
		{
			exam_over = true;
			alert('Exam time over');
			location.reload();
		}
	}, 1000);

	$(document).on('click', '.answer_option', function(){
		var question_id = $(this).data('question_id');

		var answer_option = $(this).data('id');

		$.ajax({
			url:"user_ajax_action.php",
			method:"POST",
			data:{question_id:question_id, answer_option:answer_option, exam_id:exam_id, page:'view_exam', action:'answer'},
			error:function()
			{
				alert('Your answer could not be saved. The exam may have ended.');
			}
		})
	});

});
</script>
<?php
}
if($exam_status == 'Completed')
{
	$result = db_rows($exam, "
		SELECT * FROM question_table
		INNER JOIN user_exam_question_answer
		ON user_exam_question_answer.question_id = question_table.question_id
		WHERE question_table.online_exam_id = :exam_id
		AND user_exam_question_answer.user_id = :user_id
	", array(':exam_id' => $exam_id, ':user_id' => $user_id));
?>
	<div class="card">
		<div class="card-header">
			<div class="row">
				<div class="col-md-8">Online Exam Result</div>
				<div class="col-md-4" align="right">
					<a href="pdf_exam_result.php?code=<?php echo h(urlencode($code)); ?>" class="btn btn-danger btn-sm" target="_blank">PDF</a>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-bordered table-hover">
					<tr>
						<th>Question</th>
						<th>Option 1</th>
						<th>Option 2</th>
						<th>Option 3</th>
						<th>Option 4</th>
						<th>Your Answer</th>
						<th>Answer</th>
						<th>Result</th>
						<th>Marks</th>
					</tr>
					<?php

					foreach($result as $row)
					{
						$sub_result = db_rows($exam, "
							SELECT *
							FROM option_table
							WHERE question_id = :question_id
							ORDER BY option_number ASC
						", array(':question_id' => $row["question_id"]));

						$user_answer = '';
						$orignal_answer = '';
						$question_result = '';

						if($row['user_answer_option'] == '0')
						{
							$question_result = '<h4 class="badge badge-dark">Not Attend</h4>';
						}
						elseif($row['user_answer_option'] == $row['answer_option'])
						{
							$question_result = '<h4 class="badge badge-success">Right</h4>';
						}
						else
						{
							$question_result = '<h4 class="badge badge-danger">Wrong</h4>';
						}

						echo '
						<tr>
							<td>'.h_admin($row['question_title']).'</td>
						';

						foreach($sub_result as $sub_row)
						{
							echo '<td>'.h_admin($sub_row["option_title"]).'</td>';

							if($sub_row["option_number"] == $row['user_answer_option'])
							{
								$user_answer = $sub_row['option_title'];
							}

							if($sub_row['option_number'] == $row['answer_option'])
							{
								$orignal_answer = $sub_row['option_title'];
							}
						}
						echo '
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
					?>
					<tr>
						<td colspan="8" align="right">Total Marks</td>
						<td align="right"><?php echo h($row["total_mark"]); ?></td>
					</tr>
					<?php
					}

					?>
				</table>
			</div>
		</div>
	</div>
<?php
}

?>

</div>
</body>
</html>
