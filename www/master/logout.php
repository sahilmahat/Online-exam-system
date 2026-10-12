<?php

//master/logout.php
//Ends the admin session only; a user logged in on the same browser stays logged in.

include('Examination.php');

unset($_SESSION['admin_id'], $_SESSION['admin_type'], $_SESSION['admin_email']);

session_regenerate_id(true);

header('location:login.php');

?>
