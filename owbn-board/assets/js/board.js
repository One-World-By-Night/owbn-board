/* OWBN Board — client-side behaviors: tile state, notebook autosave, admin layout save. */

(function ($) {
	'use strict';

	if (typeof OWBN_BOARD === 'undefined') {
		return;
	}

	$(function () {
		initTileActions();
		initNotebookAutosave();
		initAdminLayoutPage();
	});

	// ---------- Tile state (collapse, pin, snooze, dismiss) ----------

	function initTileActions() {
		$('.owbn-board').on('click', '.owbn-board-tile__collapse', function () {
			var $tile = $(this).closest('.owbn-board-tile');
			$tile.toggleClass('owbn-board-tile--state-collapsed');
			var state = $tile.hasClass('owbn-board-tile--state-collapsed') ? 'collapsed' : 'default';
			saveTileState($tile.data('tile-id'), state);
		});
	}

	function saveTileState(tileId, state, snoozeUntil) {
		$.post(OWBN_BOARD.ajax_url, {
			action: 'owbn_board_tile_state',
			nonce: OWBN_BOARD.nonce,
			tile_id: tileId,
			state: state,
			snooze_until: snoozeUntil || ''
		});
	}

	// ---------- Notebook autosave ----------

	function initNotebookAutosave() {
		var debounceTimers = {};

		$('.owbn-board-notebook').each(function () {
			var $notebook = $(this);
			var notebookId = $notebook.data('notebook-id');
			var editorId = 'owbn_board_notebook_' + notebookId;

			// Listen for TinyMCE input events once it's ready
			var checkTinyMCE = setInterval(function () {
				if (typeof tinymce === 'undefined') {
					return;
				}
				var editor = tinymce.get(editorId);
				if (!editor) {
					return;
				}
				clearInterval(checkTinyMCE);

				editor.on('change keyup blur', function () {
					clearTimeout(debounceTimers[notebookId]);
					debounceTimers[notebookId] = setTimeout(function () {
						saveNotebook($notebook, notebookId, editor.getContent());
					}, 3000);
				});

				editor.on('blur', function () {
					clearTimeout(debounceTimers[notebookId]);
					saveNotebook($notebook, notebookId, editor.getContent());
				});
			}, 500);
		});
	}

	function saveNotebook($notebook, notebookId, content) {
		var $status = $notebook.find('.owbn-board-notebook__status');
		$status.removeClass('is-saved is-error').addClass('is-saving').text(OWBN_BOARD.i18n.saving);

		$.post(OWBN_BOARD.ajax_url, {
			action: 'owbn_board_notebook_save',
			nonce: OWBN_BOARD.nonce,
			notebook_id: notebookId,
			content: content
		})
			.done(function (response) {
				if (response && response.success) {
					$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
				} else {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				}
			})
			.fail(function () {
				$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
			});
	}

	// ---------- Admin layout page ----------

	function initAdminLayoutPage() {
		var $table = $('#owbn-board-tiles-body');
		if (!$table.length) {
			return;
		}

		var $status = $('.owbn-board-admin__status');
		var debounceTimer;

		function collectLayout() {
			var tiles = {};
			$table.find('tr[data-tile-id]').each(function (i) {
				var $row = $(this);
				var tileId = $row.data('tile-id');
				tiles[tileId] = {
					enabled: $row.find('.owbn-board-admin__enable').is(':checked'),
					size: $row.find('.owbn-board-admin__size').val(),
					priority: parseInt($row.find('.owbn-board-admin__priority').val(), 10) || 10
				};
			});
			return { tiles: tiles };
		}

		function saveLayout() {
			clearTimeout(debounceTimer);
			$status.removeClass('is-saved is-error').addClass('is-saving').text(OWBN_BOARD.i18n.saving);

			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_layout_save',
				nonce: OWBN_BOARD.nonce,
				layout: JSON.stringify(collectLayout())
			})
				.done(function (response) {
					if (response && response.success) {
						$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
					} else {
						$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
					}
				})
				.fail(function () {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				});
		}

		$table.on('change input', '.owbn-board-admin__enable, .owbn-board-admin__size, .owbn-board-admin__priority', function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(saveLayout, 500);
		});

		// Drag-and-drop reorder via jQuery UI Sortable
		if ($.fn.sortable) {
			$table.sortable({
				handle: 'td:nth-child(2)',
				update: function () {
					// Reassign priorities based on new order
					$table.find('tr[data-tile-id]').each(function (i) {
						$(this).find('.owbn-board-admin__priority').val((i + 1) * 10);
					});
					saveLayout();
				}
			});
		}
	}
})(jQuery);
