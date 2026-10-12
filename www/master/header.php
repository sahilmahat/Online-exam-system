<?php

//master/header.php
//Shared admin layout (top). Set $page_title and $active before including.

if(!isset($page_title))
{
	$page_title = 'Admin';
}

if(!isset($active))
{
	$active = '';
}

$admin_logged_in = isset($_SESSION['admin_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
	<title><?php echo h($page_title); ?> - Online Examination Admin</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
	<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
	<link rel="stylesheet" href="../style/style.css" />
</head>
<body>
	<nav class="navbar navbar-expand-md app-nav">
		<a class="navbar-brand" href="<?php echo $admin_logged_in ? 'index.php' : 'login.php'; ?>"><span class="brand-mark">&#10003;</span>Online Exam <span class="badge badge-light brand-badge">Admin</span></a>
		<?php if($admin_logged_in) { ?>
		<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="adminNav">
			<ul class="navbar-nav mr-auto">
				<li class="nav-item<?php echo $active == 'dashboard' ? ' active' : ''; ?>"><a class="nav-link" href="index.php">Dashboard</a></li>
				<li class="nav-item<?php echo $active == 'exam' ? ' active' : ''; ?>"><a class="nav-link" href="exam.php">Exams</a></li>
				<?php if(isset($_SESSION['admin_type']) && $_SESSION['admin_type'] == 'master') { ?>
				<li class="nav-item<?php echo $active == 'user' ? ' active' : ''; ?>"><a class="nav-link" href="user.php">Users</a></li>
				<li class="nav-item<?php echo $active == 'admins' ? ' active' : ''; ?>"><a class="nav-link" href="admins.php">Admins</a></li>
				<?php } ?>
				<li class="nav-item<?php echo $active == 'password' ? ' active' : ''; ?>"><a class="nav-link" href="password.php">Change Password</a></li>
			</ul>
			<span class="navbar-text mr-3 small"><?php echo h(isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : ''); ?> (<?php echo h(isset($_SESSION['admin_type']) ? $_SESSION['admin_type'] : ''); ?>)</span>
			<a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
		</div>
		<?php } ?>
	</nav>

	<div class="container-fluid page-wrap">
		<?php echo flash_render(); ?>
