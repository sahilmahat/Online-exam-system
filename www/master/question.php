<?php

//master/question.php  (list, add and delete the questions of one exam)

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

$exam_id = (int)$exam_row['online_exam_id'];

$enrolled = $exam->exam_enrollment_count($exam_id);

$question_count = $exam->exam_question_count($exam_id);

//Questions can only be added or removed before the exam starts and before anyone has enrolled
//(enrolled users already have answer rows for the existing questions).
$locked_reason = '';

if($exam_row['online_exam_status'] == 'Started' || $exam_row['online_exam_status'] == 'Completed')
{
	$locked_reason = 'This exam has ' . strtolower($exam_row['online_exam_status']) . ', so its questions can no longer be changed.';
}
elseif($enrolled > 0)
{
	$locked_reason = $enrolled . ' user(s) have enrolled, so questions can no longer be added or removed (you can still edit their text).';
}

$errors = array();

$old = array('question_title' => '', 'option_1' => '', 'option_2' => '', 'option_3' => '', 'option_4' => '', 'answer_option' => '');

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('question.php?code=' . urlencode($code));
	}

	$action = isset($_POST['action']) ? $_POST['action'] : '';

	if($locked_reason != '')
	{
		flash_set('danger', $locked_reason);

		redirect_to('question.php?code=' . urlencode($code));
	}

	if($action == 'add')
	{
		$clean = $exam->validate_question_input($_POST, $errors);

		foreach($old as $key => $value)
		{
			if(isset($_POST[$key]))
			{
				$old[$key] = (string)$_POST[$key];
			}
		}

		if($question_count >= (int)$exam_row['total_question'])
		{
			$errors[] = 'This exam already has all ' . (int)$exam_row['total_question'] . ' questions. Increase the total in the exam settings to add more.';
		}

		if(count($errors) == 0)
		{
			$exam->add_question($exam_id, $clean);

			$exam->sync_exam_ready($exam_id);

			flash_set('success', 'Question added.');

			redirect_to('question.php?code=' . urlencode($code));
		}
	}

	if($action == 'delete')
	{
		$question_id = isset($_POST['question_id']) ? (string)$_POST['question_id'] : '';

		if(ctype_digit($question_id))
		{
			db_exec($exam, 'DELETE FROM question_table WHERE question_id = :question_id AND online_exam_id = :exam_id', array(':question_id' => (int)$question_id, ':exam_id' => $exam_id));

			$exam->sync_exam_ready($exam_id);

			flash_set('success', 'Question deleted.');
		}

		redirect_to('question.php?code=' . urlencode($code));
	}
}

$questions = db_rows($exam, 'SELECT * FROM question_table WHERE online_exam_id = :exam_id ORDER BY question_id ASC', array(':exam_id' => $exam_id));

$badges = array('Pending' => 'warning', 'Created' => 'info', 'Started' => 'primary', 'Completed' => 'dark');

//Re-read the status: adding the last question flips Pending -> Created.
$exam_row = $exam->get_admin_exam($code);

$page_title = 'Questions';
$active = 'exam';

include('header.php');

?>

<div class="card mb-4">
	<div class="card-body">
		<div class="row">
			<div class="col-md-8">
				<h4><?php echo h_admin($exam_row['online_exam_title']); ?>
					<span class="badge badge-<?php echo h($badges[$exam_row['online_exam_status']]); ?>"><?php echo h($exam_row['online_exam_status']); ?></span></h4>
				<p class="mb-0">
					Starts <?php echo h($exam_row['online_exam_datetime']); ?> &middot; <?php echo h($exam_row['online_exam_duration']); ?> minutes &middot;
					+<?php echo h($exam_row['marks_per_right_answer']); ?> / -<?php echo h($exam_row['marks_per_wrong_answer']); ?> marks &middot;
					<strong><?php echo count($questions); ?> of <?php echo (int)$exam_row['total_question']; ?> questions added</strong>
				</p>
				<?php if($exam_row['online_exam_status'] == 'Pending') { ?>
				<p class="text-muted mb-0">Users can enroll as soon as all questions are added.</p>
				<?php if(strtotime($exam_row['online_exam_datetime']) <= time()) { ?>
				<p class="text-danger mb-0">The start time has already passed. <a href="exam_edit.php?code=<?php echo h(urlencode($code)); ?>">Edit the exam</a> to set a new time.</p>
				<?php } } ?>
			</div>
			<div class="col-md-4 text-md-right mt-2 mt-md-0">
				<a href="exam.php" class="btn btn-secondary btn-sm">Back to exams</a>
				<a href="exam_result.php?code=<?php echo h(urlencode($code)); ?>" class="btn btn-success btn-sm">Results</a>
			</div>
		</div>
	</div>
</div>

<?php if($locked_reason != '') { ?>
<div class="alert alert-warning"><?php echo h($locked_reason); ?></div>
<?php } ?>

<?php if(count($errors) > 0) { ?>
<div class="alert alert-danger">
	<ul class="mb-0">
		<?php foreach($errors as $error) { ?><li><?php echo h($error); ?></li><?php } ?>
	</ul>
</div>
<?php } ?>

<?php if($locked_reason == '') { ?>
<div class="card mb-4">
	<div class="card-header">Add question</div>
	<div class="card-body">
		<form method="post" action="question.php?code=<?php echo h(urlencode($code)); ?>">
			<?php echo csrf_field(); ?>
			<input type="hidden" name="action" value="add" />
			<div class="form-group">
				<label for="question_title">Question</label>
				<textarea name="question_title" id="question_title" class="form-control" rows="2" maxlength="2000" required><?php echo h($old['question_title']); ?></textarea>
			</div>
			<div class="form-row">
				<?php for($i = 1; $i <= 4; $i++) { ?>
				<div class="form-group col-md-6">
					<label for="option_<?php echo $i; ?>">Option <?php echo $i; ?></label>
					<input type="text" name="option_<?php echo $i; ?>" id="option_<?php echo $i; ?>" class="form-control" maxlength="250" value="<?php echo h($old['option_' . $i]); ?>" required />
				</div>
				<?php } ?>
			</div>
			<div class="form-group">
				<label for="answer_option">Correct option</label>
				<select name="answer_option" id="answer_option" class="form-control" style="max-width:200px" required>
					<option value="">Select</option>
					<?php for($i = 1; $i <= 4; $i++) { ?>
					<option value="<?php echo $i; ?>"<?php echo $old['answer_option'] === (string)$i ? ' selected' : ''; ?>>Option <?php echo $i; ?></option>
					<?php } ?>
				</select>
			</div>
			<input type="submit" class="btn btn-info" value="Add question" />
		</form>
	</div>
</div>
<?php } ?>

<div class="card">
	<div class="card-header">Questions</div>
	<div class="card-body">
		<?php if(count($questions) == 0) { ?>
			<p class="text-muted mb-0">No questions yet.</p>
		<?php } ?>
		<?php $number = 1; foreach($questions as $question) {
			$options = db_rows($exam, 'SELECT * FROM option_table WHERE question_id = :question_id ORDER BY option_number ASC', array(':question_id' => $question['question_id'])); ?>
		<div class="border rounded p-3 mb-3">
			<div class="d-flex justify-content-between">
				<h6>Q<?php echo $number; ?>. <?php echo h_admin($question['question_title']); ?></h6>
				<div class="text-nowrap">
					<?php if($exam_row['online_exam_status'] == 'Pending' || $exam_row['online_exam_status'] == 'Created') { ?>
					<a class="btn btn-sm btn-outline-secondary" href="question_edit.php?id=<?php echo (int)$question['question_id']; ?>">Edit</a>
					<?php } ?>
					<?php if($locked_reason == '') { ?>
					<form method="post" action="question.php?code=<?php echo h(urlencode($code)); ?>" class="d-inline" data-confirm="Delete this question?">
						<?php echo csrf_field(); ?>
						<input type="hidden" name="action" value="delete" />
						<input type="hidden" name="question_id" value="<?php echo (int)$question['question_id']; ?>" />
						<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
					</form>
					<?php } ?>
				</div>
			</div>
			<ol class="mb-0">
				<?php foreach($options as $option) { ?>
				<li<?php echo $option['option_number'] == $question['answer_option'] ? ' class="text-success font-weight-bold"' : ''; ?>>
					<?php echo h_admin($option['option_title']); ?>
					<?php echo $option['option_number'] == $question['answer_option'] ? ' &#10003; correct' : ''; ?>
				</li>
				<?php } ?>
			</ol>
		</div>
		<?php $number++; } ?>
	</div>
</div>

<?php include('footer.php'); ?>
