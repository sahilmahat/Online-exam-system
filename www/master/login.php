<?php

//master/login.php

include('Examination.php');

$exam = new Examination;

$exam->admin_session_public();

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
	if(!csrf_valid())
	{
		flash_set('danger', 'Invalid security token. Please try again.');

		redirect_to('login.php');
	}

	$email = trim(isset($_POST['admin_email_address']) ? (string)$_POST['admin_email_address'] : '');

	$password = isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '';

	$logged_in = false;

	foreach(db_rows($exam, 'SELECT * FROM admin_table WHERE admin_email_address = :email', array(':email' => $email)) as $row)
	{
		if(password_verify($password, $row['admin_password']) && $row['email_verified'] == 'yes')
		{
			session_regenerate_id(true);

			$_SESSION['admin_id'] = (int)$row['admin_id'];
			$_SESSION['admin_type'] = $row['admin_type'];
			$_SESSION['admin_email'] = $row['admin_email_address'];

			$logged_in = true;
		}
	}

	if($logged_in)
	{
		redirect_to('index.php');
	}

	flash_set('danger', 'Wrong email address or password');

	redirect_to('login.php');
}

$page_title = 'Admin Login';

include('header.php');

?>

<div class="row">
	<div class="col-md-3"></div>
	<div class="col-md-6" style="margin-top:30px;">
		<div class="card">
			<div class="card-header">Admin Login</div>
			<div class="card-body">
				<form method="post" action="login.php">
					<?php echo csrf_field(); ?>
					<div class="form-group">
						<label for="admin_email_address">Email Address</label>
						<input type="email" name="admin_email_address" id="admin_email_address" class="form-control" required autofocus />
					</div>
					<div class="form-group">
						<label for="admin_password">Password</label>
						<input type="password" name="admin_password" id="admin_password" class="form-control" required />
					</div>
					<input type="submit" class="btn btn-info" value="Login" />
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-3"></div>
</div>

<?php include('footer.php'); ?>
