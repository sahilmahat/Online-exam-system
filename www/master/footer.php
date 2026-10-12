	</div>

	<script>
	$(document).ready(function(){

		$.ajaxSetup({headers: {'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')}});

		$('.js-datatable').DataTable({"order": [], "pageLength": 25});

		$('form[data-confirm]').on('submit', function(){
			return confirm($(this).data('confirm'));
		});

	});
	</script>
</body>
</html>
