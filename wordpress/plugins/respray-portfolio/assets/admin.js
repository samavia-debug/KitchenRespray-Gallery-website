/* Respray Portfolio: photo pickers on the editing screens. */
(function ($) {
	'use strict';

	function syncList($box) {
		var ids = $box.find('.rp-photo-list li').map(function () {
			return $(this).data('id');
		}).get();
		$box.find('input[type=hidden]').val(ids.join(','));
	}

	function syncAll() {
		$('[data-rp-photos]').each(function () {
			syncList($(this));
		});
	}

	function allIds() {
		return $('[data-rp-photos] .rp-photo-list li').map(function () {
			return String($(this).data('id'));
		}).get();
	}

	$(function () {
		// Multi-photo boxes (before / after / more photos).
		$('.rp-photo-list').sortable({
			connectWith: '.rp-photo-list',
			placeholder: 'rp-placeholder',
			tolerance: 'pointer',
			update: syncAll
		});

		$(document).on('click', '[data-rp-add]', function (e) {
			e.preventDefault();
			var $box = $(this).closest('[data-rp-photos]');
			var frame = wp.media({
				title: $box.data('title'),
				button: { text: 'Add to project' },
				library: { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				var existing = allIds();
				frame.state().get('selection').each(function (item) {
					var a = item.toJSON();
					if (existing.indexOf(String(a.id)) !== -1) {
						return; // Already in this project.
					}
					var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					$('<li>').attr('data-id', a.id)
						.append($('<img>').attr({ src: src, alt: '' }))
						.append('<button type="button" class="rp-remove" aria-label="Remove photo">&times;</button>')
						.appendTo($box.find('.rp-photo-list'));
				});
				syncList($box);
			});
			frame.open();
		});

		$(document).on('click', '.rp-remove', function (e) {
			e.preventDefault();
			var $box = $(this).closest('[data-rp-photos]');
			$(this).closest('li').remove();
			syncList($box);
		});

		// "Generate Project Description with AI": the text is placed in the
		// description box for staff to read and correct; nothing is published.
		$(document).on('click', '#rp-ai-describe', function (e) {
			e.preventDefault();
			var $button = $(this);
			var $status = $('#rp-ai-describe-status');
			var editor = window.tinymce ? window.tinymce.get('content') : null;
			var visual = editor && !editor.isHidden();
			var current = visual ? editor.getContent({ format: 'text' }) : $('#content').val();
			if ($.trim(current) && !window.confirm('Replace the description that is already there?')) {
				return;
			}
			var ticked = function (id) {
				return $('#' + id + ' input:checked').map(function () {
					return $.trim($(this).parent().text());
				}).get().join(', ');
			};
			var primary = $('#rp-primary-colour').val() !== '0' ? $('#rp-primary-colour option:selected').text() : '';
			var others = $('input[name="rp_other_colours[]"]:checked').map(function () {
				return $.trim($(this).parent().text());
			}).get().join(', ');

			$button.prop('disabled', true);
			$status.text('Writing… this can take up to half a minute.');
			$.post(window.ajaxurl, {
				action: 'rp_ai_describe',
				_wpnonce: window.rpAdmin ? window.rpAdmin.describeNonce : '',
				fields: {
					title: $('#title').val(),
					service: ticked('roomchecklist'),
					colour: primary,
					other_colours: others,
					new_colour: $('#rp-new-colour').val(),
					original_colour: $('#rp-original-colour').val(),
					finish: ticked('finishchecklist'),
					style: ticked('stylechecklist'),
					material: ticked('materialchecklist'),
					brand: ticked('brandchecklist'),
					location: $('#rp-location').val(),
					notes: $('#rp-notes').val()
				}
			}).done(function (res) {
				if (!res || !res.success) {
					$status.text((res && res.data) || 'Something went wrong. Please write the description by hand.');
					return;
				}
				if (visual) {
					editor.setContent($('<p>').text(res.data.text).prop('outerHTML'));
				} else {
					$('#content').val(res.data.text);
				}
				$status.text('Done. Please read it and correct anything that is not right before publishing.');
			}).fail(function () {
				$status.text('Could not reach the AI service. Please write the description by hand.');
			}).always(function () {
				$button.prop('disabled', false);
			});
		});

		// Single image or file fields (settings page, brochure file).
		$(document).on('click', '[data-rp-pick]', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('[data-rp-single-image],[data-rp-file]');
			var isFile = $wrap.is('[data-rp-file]');
			var frame = wp.media({
				title: isFile ? 'Choose file' : 'Choose image',
				button: { text: 'Use this' },
				library: isFile ? {} : { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				$wrap.find('input[type=hidden]').val(a.id);
				if (isFile) {
					$wrap.find('[data-rp-filename]').text(a.filename);
				} else {
					var src = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
					$wrap.find('img').attr('src', src).show();
				}
			});
			frame.open();
		});

		$(document).on('click', '[data-rp-clear]', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('[data-rp-single-image],[data-rp-file]');
			$wrap.find('input[type=hidden]').val('');
			$wrap.find('img').hide();
			$wrap.find('[data-rp-filename]').text('No file chosen');
		});
	});
})(jQuery);
