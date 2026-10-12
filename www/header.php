<!DOCTYPE html>
<html lang="en">
<head>
  	<title>Online Examination System in PHP</title>
  	<meta charset="utf-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1">
  	<meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
  	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  	<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  	<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
  	<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
  	<link rel="stylesheet" href="style/style.css" />
    <link rel="stylesheet" href="style/TimeCircles.css" />
    <script src="style/TimeCircles.js"></script>
    <script>
      // Every AJAX request carries the CSRF token of the current session.
      $.ajaxSetup({headers: {'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')}});
    </script>
</head>
<body>
  <?php $user_logged = isset($_SESSION['user_id']); ?>
  <nav class="navbar navbar-expand-md app-nav">
    <a class="navbar-brand" href="<?php echo $user_logged ? 'index.php' : 'login.php'; ?>"><span class="brand-mark">&#10003;</span>Online Exam</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="collapsibleNavbar">
      <ul class="navbar-nav ml-auto">
        <?php if($user_logged) { ?>
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="enroll_exam.php">Enroll Exam</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="change_password.php">Change Password</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        <?php } else { ?>
        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
        <?php } ?>
      </ul>
    </div>
  </nav>

  <?php
  if($user_logged)
  {
  ?>
  <div class="container-fluid page-wrap">
  <?php
  }
  ?>
