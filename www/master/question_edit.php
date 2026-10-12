<?php

//master/question_edit.php  (edit one question and its options)

include('Examination.php');

$exam = new Examination;

$exam->admin_session_private();

$question_id = isset($_GET['id']) ? (string)$_GET['id'] : '';

if(!ctype_digit($question_id))
{
	flash_set('danger', 'Question not found.');

	redirect_to('exam.php');
}

$rows = db_rows($exam, "
	SELECT question_table.*, online_exam_table.online_exam_code
	FROM question_table
	INNER JOIN online_exam_table ON online_exam_table.online_exam_id = question_table.online_exam_id
	WHERE question_table.question_id = :question_id
", array(':question_id' => (int)$question_id));

$question = count($rows) ? $rows[0] : false;

//Ownership check goes through the exam.
$exam_row = $question ? $exam->get_admin_exam($question['online_exam_code']) : false;

if($question === false || $exam_row === false)
{
	flash_set('danger', 'Question not found.');

	redirect_to('exam.php');
}

$back = 'question.php?code=' . urlencode($exam_row['online_exam_code']);

if($exam_row['online_exam_status'] != 'Pending' && $exam_row['online_exam_status'] != 'Created')
{
	flash_set('danger', 'Questions of a started exam cannot be edited.');

	redirect_to($back);
}

$old = array('question_title' => $question['question_title'], 'answer_option' => $question['answer_option']);

foreach(db_rows($exam, 'SELECT * FROM option_table WHERE question_id = :id', array(':id' => $question['question_id'])) as $option)
{
	$old['option_' . $option['option_number']] = $option['option_title'];
}

$errors = array();

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('question_edit.php?id=' . (int)$question_id);
	}

	$clean = $exam->validate_question_input($_POST, $errors);

	foreach($old as $key => $value)
	{
		if(isset($_POST[$key]))
		{
			$old[$key] = (string)$_POST[$key];
		}
	}

	if(count($errors) == 0)
	{
		$exam->update_question((int)$question_id, $clean);

		flash_set('success', 'Question updated.');

		redirect_to($back);
	}
}

$page_title = 'Edit Question';
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
	<div class="card-header">Edit question - <?php echo h_admin($exam_row['online_exam_title']); ?></div>
	<div class="card-body">
		<form method="post" action="question_edit.php?id=<?php echo (int)$question_id; ?>">
			<?php echo csrf_field(); ?>
			<div class="form-group">
				<label for="question_title">Question</label>
				<textarea name="question_title" id="question_title" class="form-control" rows="2" maxlength="2000" required><?php echo h_admin($old['question_title']); ?></textarea>
			</div>
			<div class="form-row">
				<?php for($i = 1; $i <= 4; $i++) { ?>
				<div class="form-group col-md-6">
					<label for="option_<?php echo $i; ?>">Option <?php echo $i; ?></label>
					<input type="text" name="option_<?php echo $i; ?>" id="option_<?php echo $i; ?>" class="form-control" maxlength="250" value="<?php echo h_admin(isset($old['option_' . $i]) ? $old['option_' . $i] : ''); ?>" required />
				</div>
				<?php } ?>
			</div>
			<div class="form-group">
				<label for="answer_option">Correct option</label>
				<select name="answer_option" id="answer_option" class="form-control" style="max-width:200px" required>
					<?php for($i = 1; $i <= 4; $i++) { ?>
					<option value="<?php echo $i; ?>"<?php echo (string)$old['answer_option'] === (string)$i ? ' selected' : ''; ?>>Option <?php echo $i; ?></option>
					<?php } ?>
				</select>
			</div>
			<input type="submit" class="btn btn-info" value="Save question" />
			<a href="<?php echo h($back); ?>" class="btn btn-secondary">Cancel</a>
		</form>
	</div>
</div>

<?php include('footer.php'); ?>
