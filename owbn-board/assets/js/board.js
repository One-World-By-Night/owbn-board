/* OWBN Board — client-side behaviors: tile state, notebook autosave, admin layout save. */

(function ($) {
	'use strict';

	if (typeof OWBN_BOARD === 'undefined') {
		return;
	}

	$(function () {
		initTileActions();
		initNotebookAutosave();
		initMessageTile();
		initSearchTile();
		initPinnedLinksTile();
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

	// ---------- Message tile ----------

	function initMessageTile() {
		$('.owbn-board').on('submit', '.owbn-board-message__form', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $tile = $form.closest('.owbn-board-message');
			var $input = $form.find('.owbn-board-message__input');
			var content = $input.val().trim();
			if (!content) {
				return;
			}
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_message_post',
				nonce: OWBN_BOARD.nonce,
				role_path: $tile.data('role-path'),
				content: content
			}).done(function (response) {
				if (response && response.success) {
					$input.val('');
					// Reload the tile body would be ideal — for now, just prepend a placeholder
					location.reload();
				}
			});
		});

		$('.owbn-board').on('click', '.owbn-board-message__delete', function () {
			var $item = $(this).closest('.owbn-board-message__item');
			var messageId = $item.data('message-id');
			if (!confirm('Delete this message?')) {
				return;
			}
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_message_delete',
				nonce: OWBN_BOARD.nonce,
				message_id: messageId
			}).done(function (response) {
				if (response && response.success) {
					$item.remove();
				}
			});
		});
	}

	// ---------- Search tile ----------

	function initSearchTile() {
		var $search = $('.owbn-board-search__input');
		if (!$search.length) {
			return;
		}
		var $results = $('.owbn-board-search__results');
		var debounceTimer;

		$search.on('input', function () {
			var query = $(this).val().trim();
			clearTimeout(debounceTimer);
			if (query.length < 2) {
				$results.empty();
				return;
			}
			debounceTimer = setTimeout(function () {
				$.post(OWBN_BOARD.ajax_url, {
					action: 'owbn_board_search',
					nonce: OWBN_BOARD.nonce,
					q: query
				}).done(function (response) {
					renderSearchResults(response);
				});
			}, 300);
		});

		// Cmd+K / Ctrl+K global shortcut
		$(document).on('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
				// Don't intercept when focus is in an input/textarea/contenteditable
				var tag = document.activeElement ? document.activeElement.tagName : '';
				if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable) {
					return;
				}
				e.preventDefault();
				$search.focus();
			}
		});

		function renderSearchResults(response) {
			$results.empty();
			if (!response || !response.success || !response.data || !response.data.results) {
				return;
			}
			var groups = response.data.results;
			if (groups.length === 0) {
				$results.html('<p><em>No results.</em></p>');
				return;
			}
			groups.forEach(function (group) {
				var $group = $('<div class="owbn-board-search__group"></div>');
				$group.append('<div class="owbn-board-search__group-label">' + escapeHtml(group.label) + '</div>');
				group.results.forEach(function (r) {
					var $result = $('<a class="owbn-board-search__result"></a>').attr('href', r.url || '#');
					$result.append('<div class="owbn-board-search__result-title">' + escapeHtml(r.title || '') + '</div>');
					if (r.snippet) {
						$result.append('<div class="owbn-board-search__result-snippet">' + escapeHtml(r.snippet) + '</div>');
					}
					$group.append($result);
				});
				$results.append($group);
			});
		}

		function escapeHtml(str) {
			return String(str).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}
	}

	// ---------- Pinned links tile ----------

	function initPinnedLinksTile() {
		$('.owbn-board').on('submit', '.owbn-board-pins__form', function (e) {
			e.preventDefault();
			var $form = $(this);
			var label = $form.find('.owbn-board-pins__label').val().trim();
			var url = $form.find('.owbn-board-pins__url').val().trim();
			if (!label || !url) {
				return;
			}
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_pin_add',
				nonce: OWBN_BOARD.nonce,
				label: label,
				url: url
			}).done(function (response) {
				if (response && response.success) {
					location.reload();
				}
			});
		});

		$('.owbn-board').on('click', '.owbn-board-pins__remove', function () {
			var $item = $(this).closest('.owbn-board-pins__item');
			var pinId = $item.data('pin-id');
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_pin_remove',
				nonce: OWBN_BOARD.nonce,
				pin_id: pinId
			}).done(function (response) {
				if (response && response.success) {
					$item.remove();
				}
			});
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
