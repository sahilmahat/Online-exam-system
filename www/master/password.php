<?php

//master/password.php  (change the logged in admin's own password)

include('Examination.php');

$exam = new Examination;

$exam->admin_session_private();

$errors = array();

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('password.php');
	}

	$current = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
	$password = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
	$confirm = isset($_POST['confirm_new_password']) ? (string)$_POST['confirm_new_password'] : '';

	$valid = false;

	foreach(db_rows($exam, 'SELECT admin_password FROM admin_table WHERE admin_id = :id', array(':id' => $exam->admin_id())) as $row)
	{
		$valid = password_verify($current, $row['admin_password']);
	}

	if(!$valid)
	{
		$errors[] = 'Current password is wrong.';
	}

	if(strlen($password) < 8 || $password !== $confirm)
	{
		$errors[] = 'New password must be at least 8 characters and match the confirmation.';
	}

	if(count($errors) == 0)
	{
		db_exec($exam, 'UPDATE admin_table SET admin_password = :password WHERE admin_id = :id', array(':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $exam->admin_id()));

		session_regenerate_id(true);

		flash_set('success', 'Password changed.');

		redirect_to('password.php');
	}
}

$page_title = 'Change Password';
$active = 'password';

include('header.php');

?>

<?php if(count($errors) > 0) { ?>
<div class="alert alert-danger">
	<ul class="mb-0">
		<?php foreach($errors as $error) { ?><li><?php echo h($error); ?></li><?php } ?>
	</ul>
</div>
<?php } ?>

<div class="row">
	<div class="col-md-6">
		<div class="card">
			<div class="card-header">Change password</div>
			<div class="card-body">
				<form method="post" action="password.php">
					<?php echo csrf_field(); ?>
					<div class="form-group">
						<label for="current_password">Current password</label>
						<input type="password" name="current_password" id="current_password" class="form-control" required />
					</div>
					<div class="form-group">
						<label for="new_password">New password (min 8 characters)</label>
						<input type="password" name="new_password" id="new_password" class="form-control" minlength="8" required />
					</div>
					<div class="form-group">
						<label for="confirm_new_password">Confirm new password</label>
						<input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" minlength="8" required />
					</div>
					<input type="submit" class="btn btn-info" value="Change password" />
				</form>
			</div>
		</div>
	</div>
</div>

<?php include('footer.php'); ?>
