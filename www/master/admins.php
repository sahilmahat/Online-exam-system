<?php

//master/admins.php  (create and remove sub admins, master admin only)

include('Examination.php');

$exam = new Examination;

$exam->require_master();

$errors = array();

$old_email = '';

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('admins.php');
	}

	$action = isset($_POST['action']) ? $_POST['action'] : '';

	if($action == 'create')
	{
		$email = trim(isset($_POST['admin_email_address']) ? (string)$_POST['admin_email_address'] : '');
		$password = isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '';
		$confirm = isset($_POST['confirm_admin_password']) ? (string)$_POST['confirm_admin_password'] : '';

		$old_email = $email;

		if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)
		{
			$errors[] = 'Please enter a valid e-mail address.';
		}

		if(strlen($password) < 8 || $password !== $confirm)
		{
			$errors[] = 'Password must be at least 8 characters and match the confirmation.';
		}

		if(count($errors) == 0 && db_count($exam, 'SELECT admin_id FROM admin_table WHERE admin_email_address = :email', array(':email' => $email)) > 0)
		{
			$errors[] = 'An admin with that e-mail address already exists.';
		}

		if(count($errors) == 0)
		{
			db_exec($exam, "
				INSERT INTO admin_table (admin_email_address, admin_password, admin_verfication_code, admin_type, admin_created_on, email_verified)
				VALUES (:email, :password, '', 'sub_master', :created, 'yes')
			", array(':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT), ':created' => date('Y-m-d H:i:s')));

			flash_set('success', 'Sub admin created. They can only manage their own exams.');

			redirect_to('admins.php');
		}
	}

	if($action == 'delete')
	{
		$admin_id = isset($_POST['admin_id']) ? (string)$_POST['admin_id'] : '';

		if(!ctype_digit($admin_id))
		{
			redirect_to('admins.php');
		}

		$target = db_rows($exam, 'SELECT admin_id, admin_type FROM admin_table WHERE admin_id = :id', array(':id' => (int)$admin_id));

		if(count($target) == 0 || $target[0]['admin_type'] != 'sub_master')
		{
			flash_set('danger', 'Only sub admins can be deleted.');
		}
		elseif(db_count($exam, 'SELECT online_exam_id FROM online_exam_table WHERE admin_id = :id', array(':id' => (int)$admin_id)) > 0)
		{
			flash_set('danger', 'This admin still owns exams. Delete those exams first.');
		}
		else
		{
			db_exec($exam, 'DELETE FROM admin_table WHERE admin_id = :id', array(':id' => (int)$admin_id));

			flash_set('success', 'Sub admin deleted.');
		}

		redirect_to('admins.php');
	}
}

$admins = db_rows($exam, "
	SELECT a.admin_id, a.admin_email_address, a.admin_type, a.admin_created_on,
		(SELECT COUNT(*) FROM online_exam_table e WHERE e.admin_id = a.admin_id) AS exam_count
	FROM admin_table a
	ORDER BY a.admin_id ASC
");

$page_title = 'Admins';
$active = 'admins';

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
	<div class="card-header">Add sub admin</div>
	<div class="card-body">
		<form method="post" action="admins.php">
			<?php echo csrf_field(); ?>
			<input type="hidden" name="action" value="create" />
			<div class="form-row">
				<div class="form-group col-md-4">
					<label for="admin_email_address">Email address</label>
					<input type="email" name="admin_email_address" id="admin_email_address" class="form-control" value="<?php echo h($old_email); ?>" required />
				</div>
				<div class="form-group col-md-4">
					<label for="admin_password">Password (min 8 characters)</label>
					<input type="password" name="admin_password" id="admin_password" class="form-control" minlength="8" required />
				</div>
				<div class="form-group col-md-4">
					<label for="confirm_admin_password">Confirm password</label>
					<input type="password" name="confirm_admin_password" id="confirm_admin_password" class="form-control" minlength="8" required />
				</div>
			</div>
			<input type="submit" class="btn btn-info" value="Create sub admin" />
		</form>
	</div>
</div>

<div class="card">
	<div class="card-header">Admins</div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>Email</th><th>Type</th><th>Created</th><th>Exams</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach($admins as $row) { ?>
					<tr>
						<td><?php echo h($row['admin_email_address']); ?></td>
						<td><?php echo h($row['admin_type']); ?></td>
						<td><?php echo h($row['admin_created_on']); ?></td>
						<td><?php echo (int)$row['exam_count']; ?></td>
						<td>
							<?php if($row['admin_type'] == 'sub_master') { ?>
							<form method="post" action="admins.php" class="d-inline" data-confirm="Delete this sub admin?">
								<?php echo csrf_field(); ?>
								<input type="hidden" name="action" value="delete" />
								<input type="hidden" name="admin_id" value="<?php echo (int)$row['admin_id']; ?>" />
								<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
							</form>
							<?php } else { ?>
							<span class="text-muted">-</span>
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
