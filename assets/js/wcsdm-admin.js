(function ($) {
	$(document).ready(function () {
		$(document).on('change', '#rates-list-table tbody .select-item', function (e) {
			var $tr = $(this).closest('tr');
			if (this.checked) {
				$(this).closest('tr').addClass('selected');
				if ($('#rates-list-table tbody tr.selected').length == $('#rates-list-table tbody tr').length) {
					$('#rates-list-table thead .select-item').prop({
						checked: true
					});
				}
			} else {
				$(this).closest('tr').removeClass('selected');
				$('#rates-list-table thead .select-item').prop({
					checked: false
				});
			}
		});

		$(document).on('change', '#rates-list-table thead .select-item', function (e) {
			if (this.checked) {
				$('#rates-list-table tbody .select-item').each(function (index, el) {
					$(el).prop('checked', true).closest('tr').addClass('selected');
				});
			} else {
				$('#rates-list-table tbody .select-item').each(function (index, el) {
					$(el).prop('checked', false).closest('tr').removeClass('selected');
				});
			}
		});

		$(document).on('change', '#rates-list-table tbody .select-item', function (e) {
			var $tr = $(this).closest('tr');
			if (this.checked) {
				$(this).closest('tr').addClass('selected');
				if ($('#rates-list-table tbody tr.selected').length == $('#rates-list-table tbody tr').length) {
					$('#rates-list-table thead .select-item').prop({
						checked: true
					});
				}
			} else {
				$(this).closest('tr').removeClass('selected');
				$('#rates-list-table thead .select-item').prop({
					checked: false
				});
			}
		});

		$(document).on('click', '#rates-list-table a.add', function (e) {
			e.preventDefault();

			var self = $(this);

			var template = wp.template('rates-list-input-table-row'); // uses script tag ID minus "tmpl-"

			// Let's fake some data (maybe this is data from an API request?)
			var tmplData = {
				field_key: self.data('key')
			};

			var row = template(tmplData);

			$('#rates-list-table tbody').append(row);
		});

		$(document).on('click', '#rates-list-table a.remove_rows', function (e) {
			e.preventDefault();
			$('#rates-list-table tbody tr.selected').remove();
			if (!$('#rates-list-table tbody tr.selected').length) {
				$('#rates-list-table thead .select-item').prop({
					checked: false
				});
			}
		});
	});
})(jQuery);