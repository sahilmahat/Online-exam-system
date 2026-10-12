<?php

//master/exam_result.php  (results of one exam, with CSV export)

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

$enrolled = db_rows($exam, "
	SELECT u.user_id, u.user_name, u.user_email_address, e.attendance_status
	FROM user_exam_enroll_table e
	INNER JOIN user_table u ON u.user_id = e.user_id
	WHERE e.exam_id = :exam_id
	ORDER BY u.user_name ASC
", array(':exam_id' => $exam_id));

$answers = db_rows($exam, "
	SELECT a.user_id, a.user_answer_option, a.marks, q.answer_option
	FROM user_exam_question_answer a
	INNER JOIN question_table q ON q.question_id = a.question_id
	WHERE a.exam_id = :exam_id
", array(':exam_id' => $exam_id));

$stats = array();

foreach($answers as $answer)
{
	$uid = (int)$answer['user_id'];

	if(!isset($stats[$uid]))
	{
		$stats[$uid] = array('answered' => 0, 'right' => 0, 'wrong' => 0, 'total' => 0.0);
	}

	if($answer['user_answer_option'] != '0')
	{
		$stats[$uid]['answered']++;

		if($answer['user_answer_option'] == $answer['answer_option'])
		{
			$stats[$uid]['right']++;
		}
		else
		{
			$stats[$uid]['wrong']++;
		}
	}

	$stats[$uid]['total'] += (float)$answer['marks'];
}

$rows = array();

foreach($enrolled as $user)
{
	$uid = (int)$user['user_id'];

	$s = isset($stats[$uid]) ? $stats[$uid] : array('answered' => 0, 'right' => 0, 'wrong' => 0, 'total' => 0.0);

	$rows[] = array(
		'name'		=>	$user['user_name'],
		'email'		=>	$user['user_email_address'],
		'attendance'=>	$user['attendance_status'],
		'answered'	=>	$s['answered'],
		'right'		=>	$s['right'],
		'wrong'		=>	$s['wrong'],
		'total'		=>	$s['total']
	);
}

usort($rows, function($a, $b)
{
	if($a['total'] == $b['total'])
	{
		return strcmp($a['name'], $b['name']);
	}

	return ($a['total'] < $b['total']) ? 1 : -1;
});

//CSV export. Values starting with = + - @ are prefixed so spreadsheets do not run them as formulas.
if(isset($_GET['export']) && $_GET['export'] == 'csv')
{
	$safe = function($value)
	{
		$value = (string)$value;

		return (strlen($value) > 0 && strpos('=+-@', $value[0]) !== false) ? "'" . $value : $value;
	};

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="exam-results-' . preg_replace('/[^a-z0-9]/', '', strtolower(substr($code, 0, 12))) . '.csv"');

	$out = fopen('php://output', 'w');

	fputcsv($out, array('Rank', 'Name', 'Email', 'Attendance', 'Answered', 'Right', 'Wrong', 'Total marks'));

	$rank = 1;

	foreach($rows as $row)
	{
		fputcsv($out, array($rank++, $safe($row['name']), $safe($row['email']), $row['attendance'], $row['answered'], $row['right'], $row['wrong'], format_marks($row['total'])));
	}

	fclose($out);

	exit;
}

$badges = array('Pending' => 'warning', 'Created' => 'info', 'Started' => 'primary', 'Completed' => 'dark');

$page_title = 'Results';
$active = 'exam';

include('header.php');

?>

<div class="card mb-3">
	<div class="card-body">
		<div class="row">
			<div class="col-md-8">
				<h4><?php echo h_admin($exam_row['online_exam_title']); ?>
					<span class="badge badge-<?php echo h($badges[$exam_row['online_exam_status']]); ?>"><?php echo h($exam_row['online_exam_status']); ?></span></h4>
				<p class="mb-0">Starts <?php echo h($exam_row['online_exam_datetime']); ?> &middot; <?php echo h($exam_row['online_exam_duration']); ?> minutes &middot; <?php echo (int)$exam_row['total_question']; ?> questions &middot; +<?php echo h($exam_row['marks_per_right_answer']); ?> / -<?php echo h($exam_row['marks_per_wrong_answer']); ?></p>
				<?php if($exam_row['online_exam_status'] != 'Completed') { ?>
				<p class="text-muted mb-0">Final results are available once the exam is completed.</p>
				<?php } ?>
			</div>
			<div class="col-md-4 text-md-right mt-2 mt-md-0">
				<a href="exam.php" class="btn btn-secondary btn-sm">Back to exams</a>
				<a href="exam_result.php?code=<?php echo h(urlencode($code)); ?>&amp;export=csv" class="btn btn-success btn-sm">Download CSV</a>
			</div>
		</div>
	</div>
</div>

<div class="card">
	<div class="card-header">Results (<?php echo count($rows); ?> enrolled)</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-striped table-hover js-datatable">
				<thead>
					<tr><th>Rank</th><th>Name</th><th>Email</th><th>Attendance</th><th>Answered</th><th>Right</th><th>Wrong</th><th>Total marks</th></tr>
				</thead>
				<tbody>
				<?php $rank = 1; foreach($rows as $row) { ?>
					<tr>
						<td><?php echo $rank++; ?></td>
						<td><?php echo h($row['name']); ?></td>
						<td><?php echo h($row['email']); ?></td>
						<td><span class="badge badge-<?php echo $row['attendance'] == 'Present' ? 'success' : 'secondary'; ?>"><?php echo h($row['attendance']); ?></span></td>
						<td><?php echo (int)$row['answered']; ?></td>
						<td><?php echo (int)$row['right']; ?></td>
						<td><?php echo (int)$row['wrong']; ?></td>
						<td><strong><?php echo h(format_marks($row['total'])); ?></strong></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include('footer.php'); ?>
