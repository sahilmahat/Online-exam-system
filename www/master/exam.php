<?php

//master/exam.php  (list, create, delete exams)

include('Examination.php');

$exam = new Examination;

$exam->admin_session_private();

$errors = array();

$old = array(
	'online_exam_title'			=>	'',
	'online_exam_datetime'		=>	date('Y-m-d\TH:i', time() + 3600),
	'online_exam_duration'		=>	'30',
	'total_question'			=>	'10',
	'marks_per_right_answer'	=>	'1',
	'marks_per_wrong_answer'	=>	'0.25'
);

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('exam.php');
	}

	$action = isset($_POST['action']) ? $_POST['action'] : '';

	if($action == 'create')
	{
		$clean = $exam->validate_exam_input($_POST, $errors);

		foreach($old as $key => $value)
		{
			if(isset($_POST[$key]))
			{
				$old[$key] = (string)$_POST[$key];
			}
		}

		if(count($errors) == 0)
		{
			$code = bin2hex(random_bytes(16));

			db_exec($exam, "
				INSERT INTO online_exam_table
				(admin_id, online_exam_title, online_exam_datetime, online_exam_duration, total_question, marks_per_right_answer, marks_per_wrong_answer, online_exam_created_on, online_exam_status, online_exam_code)
				VALUES
				(:admin_id, :title, :datetime, :duration, :total, :right, :wrong, :created, 'Pending', :code)
			", array(
				':admin_id'		=>	$exam->admin_id(),
				':title'		=>	$clean['title'],
				':datetime'		=>	$clean['datetime'],
				':duration'		=>	$clean['duration'],
				':total'		=>	(int)$clean['total'],
				':right'		=>	$clean['right'],
				':wrong'		=>	$clean['wrong'],
				':created'		=>	date('Y-m-d H:i:s'),
				':code'			=>	$code
			));

			flash_set('success', 'Exam created. Now add its ' . (int)$clean['total'] . ' questions.');

			redirect_to('question.php?code=' . urlencode($code));
		}
	}

	if($action == 'delete')
	{
		$exam_row = $exam->get_admin_exam(isset($_POST['code']) ? $_POST['code'] : '');

		if($exam_row === false)
		{
			flash_set('danger', 'Exam not found.');
		}
		elseif($exam_row['online_exam_status'] == 'Started')
		{
			flash_set('danger', 'A running exam cannot be deleted.');
		}
		else
		{
			db_exec($exam, 'DELETE FROM online_exam_table WHERE online_exam_id = :id', array(':id' => $exam_row['online_exam_id']));

			flash_set('success', 'Exam deleted.');
		}

		redirect_to('exam.php');
	}
}

$scope = '';
$params = array();

if(!$exam->is_master())
{
	$scope = ' WHERE e.admin_id = :admin_id';
	$params[':admin_id'] = $exam->admin_id();
}

$exams = db_rows($exam, "
	SELECT e.*, a.admin_email_address,
		(SELECT COUNT(*) FROM question_table q WHERE q.online_exam_id = e.online_exam_id) AS question_count,
		(SELECT COUNT(*) FROM user_exam_enroll_table u WHERE u.exam_id = e.online_exam_id) AS enrolled_count
	FROM online_exam_table e
	INNER JOIN admin_table a ON a.admin_id = e.admin_id
	" . $scope . "
	ORDER BY e.online_exam_datetime DESC
", $params);

$badges = array('Pending' => 'warning', 'Created' => 'info', 'Started' => 'primary', 'Completed' => 'dark');

$page_title = 'Exams';
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

<div class="card mb-4">
	<div class="card-header">Create exam</div>
	<div class="card-body">
		<form method="post" action="exam.php">
			<?php echo csrf_field(); ?>
			<input type="hidden" name="action" value="create" />
			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="online_exam_title">Exam title</label>
					<input type="text" name="online_exam_title" id="online_exam_title" class="form-control" maxlength="250" value="<?php echo h($old['online_exam_title']); ?>" required />
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
			<input type="submit" class="btn btn-info" value="Create exam" />
			<small class="text-muted ml-2">The exam becomes available to users once all its questions have been added.</small>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-header">All exams</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-striped table-hover js-datatable">
				<thead>
					<tr>
						<th>Title</th>
						<th>Starts</th>
						<th>Duration</th>
						<th>Questions</th>
						<th>Marks (right / wrong)</th>
						<th>Enrolled</th>
						<th>Status</th>
						<?php if($exam->is_master()) { ?><th>Created by</th><?php } ?>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach($exams as $row) { $code = urlencode($row['online_exam_code']); ?>
					<tr>
						<td><?php echo h_admin($row['online_exam_title']); ?></td>
						<td><?php echo h($row['online_exam_datetime']); ?></td>
						<td><?php echo h($row['online_exam_duration']); ?> min</td>
						<td><?php echo (int)$row['question_count']; ?> / <?php echo (int)$row['total_question']; ?></td>
						<td>+<?php echo h($row['marks_per_right_answer']); ?> / -<?php echo h($row['marks_per_wrong_answer']); ?></td>
						<td><?php echo (int)$row['enrolled_count']; ?></td>
						<td><span class="badge badge-<?php echo h($badges[$row['online_exam_status']]); ?>"><?php echo h($row['online_exam_status']); ?></span></td>
						<?php if($exam->is_master()) { ?><td><?php echo h($row['admin_email_address']); ?></td><?php } ?>
						<td class="text-nowrap">
							<a class="btn btn-sm btn-outline-primary" href="question.php?code=<?php echo h($code); ?>">Questions</a>
							<a class="btn btn-sm btn-outline-success" href="exam_result.php?code=<?php echo h($code); ?>">Results</a>
							<?php if($row['online_exam_status'] == 'Pending' || $row['online_exam_status'] == 'Created') { ?>
							<a class="btn btn-sm btn-outline-secondary" href="exam_edit.php?code=<?php echo h($code); ?>">Edit</a>
							<?php } ?>
							<?php if($row['online_exam_status'] != 'Started') { ?>
							<form method="post" action="exam.php" class="d-inline" data-confirm="Delete this exam with all its questions, enrollments and results?">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="action" value="delete" />
								<input type="hidden" name="code" value="<?php echo h($row['online_exam_code']); ?>" />
								<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
							</form>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include('footer.php'); ?>
