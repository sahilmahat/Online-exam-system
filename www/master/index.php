<?php

//master/index.php  (dashboard)

include('Examination.php');

$exam = new Examination;

$exam->admin_session_private();

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('index.php');
	}

	if(isset($_POST['action']) && $_POST['action'] == 'demo_exam')
	{
		$minutes = isset($_POST['minutes']) ? (string)$_POST['minutes'] : '';

		if(!ctype_digit($minutes) || (int)$minutes < 2 || (int)$minutes > 180)
		{
			flash_set('danger', 'Start time must be between 2 and 180 minutes from now.');

			redirect_to('index.php');
		}

		$exam->create_demo_exam($exam->admin_id(), (int)$minutes);

		flash_set('success', 'Demo exam created. It starts in ' . (int)$minutes . ' minutes: register a user, verify the e-mail and enroll before then.');

		redirect_to('exam.php');
	}

	redirect_to('index.php');
}

$scope = '';
$params = array();

if(!$exam->is_master())
{
	$scope = ' WHERE admin_id = :admin_id';
	$params[':admin_id'] = $exam->admin_id();
}

$status_counts = array('Pending' => 0, 'Created' => 0, 'Started' => 0, 'Completed' => 0);

foreach(db_rows($exam, 'SELECT online_exam_status, COUNT(*) AS total FROM online_exam_table' . $scope . ' GROUP BY online_exam_status', $params) as $row)
{
	$status_counts[$row['online_exam_status']] = (int)$row['total'];
}

$total_exams = array_sum($status_counts);

if($exam->is_master())
{
	$enrollments = db_rows($exam, 'SELECT COUNT(*) AS total FROM user_exam_enroll_table');
}
else
{
	$enrollments = db_rows($exam, 'SELECT COUNT(*) AS total FROM user_exam_enroll_table INNER JOIN online_exam_table ON online_exam_table.online_exam_id = user_exam_enroll_table.exam_id WHERE online_exam_table.admin_id = :admin_id', $params);
}

$total_enrollments = (int)$enrollments[0]['total'];

$total_users = 0;

if($exam->is_master())
{
	$user_rows = db_rows($exam, 'SELECT COUNT(*) AS total FROM user_table');

	$total_users = (int)$user_rows[0]['total'];
}

$recent = db_rows($exam, 'SELECT * FROM online_exam_table' . $scope . ' ORDER BY online_exam_id DESC LIMIT 5', $params);

$badges = array('Pending' => 'warning', 'Created' => 'info', 'Started' => 'primary', 'Completed' => 'dark');

$page_title = 'Dashboard';
$active = 'dashboard';

include('header.php');

?>

<div class="row">
	<div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h2><?php echo $total_exams; ?></h2>Exams</div></div></div>
	<div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h2><?php echo $status_counts['Started']; ?></h2>Running now</div></div></div>
	<div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h2><?php echo $total_enrollments; ?></h2>Enrollments</div></div></div>
	<div class="col-md-3 mb-3"><div class="card text-center"><div class="card-body"><h2><?php echo $exam->is_master() ? $total_users : '-'; ?></h2>Registered users</div></div></div>
</div>

<div class="row">
	<div class="col-md-8 mb-3">
		<div class="card">
			<div class="card-header">Recent exams</div>
			<div class="card-body">
				<?php if(count($recent) == 0) { ?>
					<p class="text-muted mb-0">No exams yet. <a href="exam.php">Create one</a> or add the demo exam.</p>
				<?php } else { ?>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Title</th><th>Starts</th><th>Status</th><th></th></tr></thead>
						<tbody>
						<?php foreach($recent as $row) { ?>
							<tr>
								<td><?php echo h_admin($row['online_exam_title']); ?></td>
								<td><?php echo h($row['online_exam_datetime']); ?></td>
								<td><span class="badge badge-<?php echo h($badges[$row['online_exam_status']]); ?>"><?php echo h($row['online_exam_status']); ?></span></td>
								<td><a href="question.php?code=<?php echo h(urlencode($row['online_exam_code'])); ?>">Questions</a> | <a href="exam_result.php?code=<?php echo h(urlencode($row['online_exam_code'])); ?>">Results</a></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
	<div class="col-md-4 mb-3">
		<div class="card">
			<div class="card-header">Try it out</div>
			<div class="card-body">
				<p>Create a ready-made 5 question exam that starts a few minutes from now.</p>
				<form method="post" action="index.php">
					<?php echo csrf_field(); ?>
					<input type="hidden" name="action" value="demo_exam" />
					<div class="form-group">
						<label for="minutes">Starts in (minutes)</label>
						<input type="number" name="minutes" id="minutes" class="form-control" value="10" min="2" max="180" required />
					</div>
					<input type="submit" class="btn btn-warning" value="Create demo exam" />
				</form>
			</div>
		</div>
	</div>
</div>

<?php include('footer.php'); ?>
