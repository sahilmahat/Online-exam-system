<?php

//verify_email.php

include('master/Examination.php');

require_once('helpers.php');

$exam = new Examination;

if(isset($_GET['type'], $_GET['code']) && preg_match('/^[A-Za-z0-9]{1,100}$/', $_GET['code']))
{
	if($_GET['type'] == 'master')
	{
		db_exec($exam, "
			UPDATE admin_table
			SET email_verified = 'yes'
			WHERE admin_verfication_code = :code
		", array(':code' => $_GET['code']));

		$exam->redirect('master/login.php?verified=success');
	}

	if($_GET['type'] == 'user')
	{
		db_exec($exam, "
			UPDATE user_table
			SET user_email_verified = 'yes'
			WHERE user_verfication_code = :code
		", array(':code' => $_GET['code']));

		$exam->redirect('login.php?verified=success');
	}
}

header('location:login.php');
exit;

?>
