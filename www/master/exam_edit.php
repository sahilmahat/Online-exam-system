<?php

//master/exam_edit.php  (edit an exam that has not started yet)

include('Examination.php');

$exam = new Examination;

$exam->admin_session_private();

$code = isset($_GET['code']) ? $_GET['code'] : '';

$exam_row = $exam->get_admin_exam($code);

if($exam_row === false)
{
	flash_set('danger', 'Exam not found.');

	redirect_to('exam.php');
}

if($exam_row['online_exam_status'] != 'Pending' && $exam_row['online_exam_status'] != 'Created')
{
	flash_set('danger', 'Only exams that have not started can be edited.');

	redirect_to('exam.php');
}

$errors = array();

$old = array(
	'online_exam_title'			=>	$exam_row['online_exam_title'],
	'online_exam_datetime'		=>	date('Y-m-d\TH:i', strtotime($exam_row['online_exam_datetime'])),
	'online_exam_duration'		=>	$exam_row['online_exam_duration'],
	'total_question'			=>	$exam_row['total_question'],
	'marks_per_right_answer'	=>	$exam_row['marks_per_right_answer'],
	'marks_per_wrong_answer'	=>	$exam_row['marks_per_wrong_answer']
);

$enrolled = $exam->exam_enrollment_count($exam_row['online_exam_id']);

$question_count = $exam->exam_question_count($exam_row['online_exam_id']);

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('exam_edit.php?code=' . urlencode($code));
	}

	$clean = $exam->validate_exam_input($_POST, $errors);

	foreach($old as $key => $value)
	{
		if(isset($_POST[$key]))
		{
			$old[$key] = (string)$_POST[$key];
		}
	}

	if((int)$clean['total'] < $question_count)
	{
		$errors[] = 'Total questions cannot be lower than the ' . $question_count . ' questions already added.';
	}

	if($enrolled > 0 && (int)$clean['total'] != (int)$exam_row['total_question'])
	{
		$errors[] = 'The number of questions cannot change once users have enrolled.';
	}

	if(count($errors) == 0)
	{
		db_exec($exam, "
			UPDATE online_exam_table
			SET online_exam_title = :title, online_exam_datetime = :datetime, online_exam_duration = :duration,
				total_question = :total, marks_per_right_answer = :right, marks_per_wrong_answer = :wrong
			WHERE online_exam_id = :id
		", array(
			':title'	=>	$clean['title'],
			':datetime'	=>	$clean['datetime'],
			':duration'	=>	$clean['duration'],
			':total'	=>	(int)$clean['total'],
			':right'	=>	$clean['right'],
			':wrong'	=>	$clean['wrong'],
			':id'		=>	$exam_row['online_exam_id']
		));

		$exam->sync_exam_ready($exam_row['online_exam_id']);

		flash_set('success', 'Exam updated.');

		redirect_to('exam.php');
	}
}

$page_title = 'Edit Exam';
$active = 'exam';

include('header.php');

?>

<?php if(count($errors) > 0) { ?>
<div class="alert alert-danger">
	<ul class="mb-0">
		<?php foreach($errors as $error) { ?><li><?php echo h($error); ?></li><?php } ?>
	</ul>
</div>
<?php } ?>

<div class="card">
	<div class="card-header">Edit exam</div>
	<div class="card-body">
		<?php if($enrolled > 0) { ?>
		<div class="alert alert-info"><?php echo (int)$enrolled; ?> user(s) have enrolled, so the number of questions is locked.</div>
		<?php } ?>
		<form method="post" action="exam_edit.php?code=<?php echo h(urlencode($code)); ?>">
			<?php echo csrf_field(); ?>
			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="online_exam_title">Exam title</label>
					<input type="text" name="online_exam_title" id="online_exam_title" class="form-control" maxlength="250" value="<?php echo h_admin($old['online_exam_title']); ?>" required />
				</div>
				<div class="form-group col-md-3">
					<label for="online_exam_datetime">Starts at (<?php echo h(date_default_timezone_get()); ?>)</label>
					<input type="datetime-local" name="online_exam_datetime" id="online_exam_datetime" class="form-control" value="<?php echo h($old['online_exam_datetime']); ?>" required />
				</div>
				<div class="form-group col-md-3">
					<label for="online_exam_duration">Duration (minutes)</label>
					<input type="number" name="online_exam_duration" id="online_exam_duration" class="form-control" min="1" max="600" value="<?php echo h($old['online_exam_duration']); ?>" required />
				</div>
			</div>
			<div class="form-row">
				<div class="form-group col-md-4">
					<label for="total_question">Total questions</label>
					<input type="number" name="total_question" id="total_question" class="form-control" min="1" max="200" value="<?php echo h($old['total_question']); ?>" required />
				</div>
				<div class="form-group col-md-4">
					<label for="marks_per_right_answer">Marks per right answer</label>
					<input type="number" step="0.01" name="marks_per_right_answer" id="marks_per_right_answer" class="form-control" min="0.01" max="100" value="<?php echo h($old['marks_per_right_answer']); ?>" required />
				</div>
				<div class="form-group col-md-4">
					<label for="marks_per_wrong_answer">Marks deducted per wrong answer</label>
					<input type="number" step="0.01" name="marks_per_wrong_answer" id="marks_per_wrong_answer" class="form-control" min="0" max="100" value="<?php echo h($old['marks_per_wrong_answer']); ?>" required />
				</div>
			</div>
			<input type="submit" class="btn btn-info" value="Save changes" />
			<a href="exam.php" class="btn btn-secondary">Cancel</a>
		</form>
	</div>
</div>

<?php include('footer.php'); ?>
