<?php

//class/pdf.php
//Thin wrapper over Dompdf with the method names the result page uses.
//Without Dompdf installed it streams the same HTML so the result can still be printed from the browser.

require_once(__DIR__ . '/class.phpmailer.php');

class Pdf
{
	private $dompdf = null;
	private $html = '';

	function __construct()
	{
		if(class_exists('\Dompdf\Dompdf'))
		{
			$this->dompdf = new \Dompdf\Dompdf(array('isRemoteEnabled' => false));
		}
	}

	function set_paper($size, $orientation = 'portrait')
	{
		if($this->dompdf)
		{
			$this->dompdf->setPaper($size, $orientation);
		}
	}

	function loadHtml($html)
	{
		$this->html = $html;

		if($this->dompdf)
		{
			$this->dompdf->loadHtml($html);
		}
	}

	function render()
	{
		if($this->dompdf)
		{
			$this->dompdf->render();
		}
	}

	function stream($file_name, $options = array())
	{
		if($this->dompdf)
		{
			$this->dompdf->stream($file_name, $options);

			return;
		}

		header('Content-Type: text/html; charset=utf-8');

		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Exam Result</title></head><body>'
			. '<p><em>PDF library not installed (run <code>composer install</code>). Use your browser\'s Print &rarr; Save as PDF.</em></p>'
			. $this->html . '</body></html>';
	}
}

?>
