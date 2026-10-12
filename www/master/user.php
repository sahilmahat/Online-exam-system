<?php

//master/user.php  (registered users, master admin only)

include('Examination.php');

$exam = new Examination;

$exam->require_master();

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('user.php');
	}

	$action = isset($_POST['action']) ? $_POST['action'] : '';

	$user_id = isset($_POST['user_id']) ? (string)$_POST['user_id'] : '';

	if(ctype_digit($user_id))
	{
		if($action == 'verify' || $action == 'unverify')
		{
			db_exec($exam, 'UPDATE user_table SET user_email_verified = :state WHERE user_id = :id', array(':state' => $action == 'verify' ? 'yes' : 'no', ':id' => (int)$user_id));

			flash_set('success', $action == 'verify' ? 'User e-mail marked as verified.' : 'User e-mail marked as not verified.');
		}

		if($action == 'delete')
		{
			foreach(db_rows($exam, 'SELECT user_image FROM user_table WHERE user_id = :id', array(':id' => (int)$user_id)) as $row)
			{
				$image = basename((string)$row['user_image']);

				if($image !== '' && is_file(dirname(__DIR__) . '/upload/' . $image))
				{
					@unlink(dirname(__DIR__) . '/upload/' . $image);
				}
			}

			db_exec($exam, 'DELETE FROM user_table WHERE user_id = :id', array(':id' => (int)$user_id));

			flash_set('success', 'User deleted together with their enrollments and results.');
		}
	}

	redirect_to('user.php');
}

$users = db_rows($exam, "
	SELECT u.user_id, u.user_name, u.user_email_address, u.user_mobile_no, u.user_email_verified, u.user_created_on,
		(SELECT COUNT(*) FROM user_exam_enroll_table e WHERE e.user_id = u.user_id) AS enrolled_count
	FROM user_table u
	ORDER BY u.user_id DESC
");

$page_title = 'Users';
$active = 'user';

include('header.php');

?>

<div class="card">
	<div class="card-header">Registered users</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-striped table-hover js-datatable">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Mobile</th>
						<th>Registered</th>
						<th>Email verified</th>
						<th>Exams</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach($users as $row) { ?>
					<tr>
						<td><?php echo h($row['user_name']); ?></td>
						<td><?php echo h($row['user_email_address']); ?></td>
						<td><?php echo h($row['user_mobile_no']); ?></td>
						<td><?php echo h($row['user_created_on']); ?></td>
						<td><span class="badge badge-<?php echo $row['user_email_verified'] == 'yes' ? 'success' : 'secondary'; ?>"><?php echo h($row['user_email_verified']); ?></span></td>
						<td><?php echo (int)$row['enrolled_count']; ?></td>
						<td class="text-nowrap">
							<form method="post" action="user.php" class="d-inline">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>" />
								<input type="hidden" name="action" value="<?php echo $row['user_email_verified'] == 'yes' ? 'unverify' : 'verify'; ?>" />
								<button type="submit" class="btn btn-sm btn-outline-primary"><?php echo $row['user_email_verified'] == 'yes' ? 'Unverify' : 'Verify'; ?></button>
							</form>
							<form method="post" action="user.php" class="d-inline" data-confirm="Delete this user with all enrollments and results?">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>" />
								<input type="hidden" name="action" value="delete" />
								<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
							</form>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php include('footer.php'); ?>
