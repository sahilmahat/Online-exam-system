<?php

//class/class.phpmailer.php
//Loads Composer's autoloader so PHPMailer and Dompdf are available. If Composer packages are not
//installed the app still works: mail is written to logs/mail.log and PDFs fall back to a printable page.

$autoloaders = array(
	dirname(__DIR__, 2) . '/vendor/autoload.php',	//optional Composer install
	dirname(__DIR__) . '/vendor/autoload.php'		//composer install inside www/
);

foreach($autoloaders as $autoloader)
{
	if(is_file($autoloader))
	{
		require_once($autoloader);

		break;
	}
}

?>
