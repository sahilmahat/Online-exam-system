<?php

//index.php

include('master/Examination.php');

$exam = new Examination;

include('header.php');

?>

	<div class="containter">
		<?php if(isset($_SESSION["user_id"])) { ?>
		<div class="text-center mb-4"><h3 class="mb-1">Welcome back</h3><p class="text-muted mb-0">Pick an exam to see its details and enroll.</p></div>
		<?php } else { ?>
		<div class="text-center mb-4 mt-5"><h2 class="mb-1">Online Examination System</h2><p class="text-muted">Register or log in to take your exams.</p></div>
		<?php } ?>
		<?php
		if(isset($_SESSION["user_id"]))
		{

		?>
		<div class="row">
			<div class="col-md-3"></div>
			<div class="col-md-6">
				<select name="exam_list" id="exam_list" class="form-control input-lg">
					<option value="">Select Exam</option>
					<?php

					echo $exam->Fill_exam_list();

					?>
				</select>
				<br />
				<span id="exam_details"></span>
			</div>
			<div class="col-md-3"></div>
		</div>
		<script>
		$(document).ready(function(){

			$('#exam_list').parsley();

			var exam_id = '';

			$('#exam_list').change(function(){

				$('#exam_list').attr('required', 'required');

				if($('#exam_list').parsley().validate())
				{
					exam_id = $('#exam_list').val();
					$.ajax({
						url:"user_ajax_action.php",
						method:"POST",
						data:{action:'fetch_exam', page:'index', exam_id:exam_id},
						success:function(data)
						{
							$('#exam_details').html(data);
						}
					});
				}
			});

			$(document).on('click', '#enroll_button', function(){
				exam_id = $('#enroll_button').data('exam_id');
				$.ajax({
					url:"user_ajax_action.php",
					method:"POST",
					data:{action:'enroll_exam', page:'index', exam_id:exam_id},
					beforeSend:function()
					{
						$('#enroll_button').attr('disabled', 'disabled');
						$('#enroll_button').text('please wait');
					},
					success:function()
					{
						$('#enroll_button').attr('disabled', false);
						$('#enroll_button').removeClass('btn-warning');
						$('#enroll_button').addClass('btn-success');
						$('#enroll_button').text('Enroll success');
					},
					error:function(xhr)
					{
						var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Enrollment failed';
						$('#enroll_button').attr('disabled', false);
						$('#enroll_button').text('Enroll it');
						alert(message);
					}
				});
			});

		});
		</script>
		<?php
		}
		else
		{
		?>
		<div align="center">
			<a href="login.php" class="btn btn-primary btn-lg mr-2">Login</a>
			<a href="register.php" class="btn btn-outline-primary btn-lg">Register</a>
		</div>
		<?php
		}
		?>
	</div>
</div>
</body>

</html>