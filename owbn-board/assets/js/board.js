/* OWBN Board — client-side behaviors: tile state, notebook autosave, admin layout save. */

(function ($) {
	'use strict';

	if (typeof OWBN_BOARD === 'undefined') {
		return;
	}

	$(function () {
		initBoardTabs();
		initTileActions();
		initTileMenu();
		initRearrangeMode();
		initScopeSwitcher();
		initTilePolling();
		initNotebookAutosave();
		initNotebookGroupSwitcher();
		initMessageTile();
		initSearchTile();
		initPinnedLinksTile();
		initCalendarTile();
		initVisitorsTile();
		initEventsTile();
		initErrataTile();
		initBallotTile();
		initAdminLayoutPage();
		initTileAccessPage();
	});

	// ---------- Tab switching (lazy panel load) ----------
	function initBoardTabs() {
		var $board = $('.owbn-board');
		if (!$board.length) return;

		$board.on('click', '.owbn-board-tab', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var target = String($btn.data('owbn-tab') || '');
			if (!target) return;

			$board.find('.owbn-board-tab').each(function () {
				var $b = $(this);
				var active = String($b.data('owbn-tab') || '') === target;
				$b.toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false');
			});

			var $targetPanel = null;
			$board.find('.owbn-board-tab-panel').each(function () {
				var $p = $(this);
				var active = String($p.data('owbn-panel') || '') === target;
				$p.toggleClass('is-active', active);
				if (active) {
					$p.removeAttr('hidden');
					$targetPanel = $p;
				} else {
					$p.attr('hidden', 'hidden');
				}
			});

			if (!$targetPanel || $targetPanel.attr('data-owbn-loaded') === '1') {
				return;
			}
			loadTabPanel($targetPanel, target);
		});
	}

	function loadTabPanel($panel, tabKey) {
		$panel.attr('data-owbn-loaded', 'loading');
		$.ajax({
			url:  OWBN_BOARD.ajax_url,
			type: 'POST',
			data: {
				action: 'owbn_board_load_tab',
				nonce:  OWBN_BOARD.nonce,
				tab:    tabKey
			}
		}).done(function (resp) {
			if (resp && resp.success && resp.data && typeof resp.data.html === 'string') {
				$panel.html(resp.data.html);
				$panel.attr('data-owbn-loaded', '1');
			} else {
				$panel.html('<div class="owbn-board-tab-error">Failed to load tab.</div>');
				$panel.attr('data-owbn-loaded', '0');
			}
		}).fail(function () {
			$panel.html('<div class="owbn-board-tab-error">Failed to load tab.</div>');
			$panel.attr('data-owbn-loaded', '0');
		});
	}

	// ---------- Tile polling (adaptive backoff) ----------
	// Each tile starts at MIN_INTERVAL. If a refresh returns identical content,
	// the interval doubles up to MAX_INTERVAL. If content changed (new message,
	// new entry), interval resets to MIN. User interaction also resets to MIN.
	var POLL_MIN_INTERVAL = 3000;
	var POLL_MAX_INTERVAL = 30000;
	var pollTimers = {};

	function initTilePolling() {
		var $tiles = $('.owbn-board-tile[data-poll-interval-ms]');
		if (!$tiles.length) {
			return;
		}
		$tiles.each(function () {
			var $tile = $(this);
			var tileId = String($tile.data('tile-id') || '');
			if (!tileId) {
				return;
			}
			pollTimers[tileId] = {
				interval: POLL_MIN_INTERVAL,
				lastHash: null,
				handle: null
			};
			schedulePoll(tileId);
		});

		// Reset all polling to fast cadence on user interaction with any tile
		$('.owbn-board').on('input change click', function () {
			Object.keys(pollTimers).forEach(function (tileId) {
				resetPollInterval(tileId);
			});
		});

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				Object.keys(pollTimers).forEach(function (tileId) {
					if (pollTimers[tileId].handle) {
						clearTimeout(pollTimers[tileId].handle);
					}
				});
			} else {
				Object.keys(pollTimers).forEach(function (tileId) {
					resetPollInterval(tileId);
				});
			}
		});
	}

	function schedulePoll(tileId) {
		var t = pollTimers[tileId];
		if (!t) return;
		if (t.handle) {
			clearTimeout(t.handle);
		}
		t.handle = setTimeout(function () {
			refreshTile(tileId);
		}, t.interval);
	}

	function resetPollInterval(tileId) {
		var t = pollTimers[tileId];
		if (!t) return;
		t.interval = POLL_MIN_INTERVAL;
		schedulePoll(tileId);
	}

	function backoffPollInterval(tileId) {
		var t = pollTimers[tileId];
		if (!t) return;
		t.interval = Math.min( t.interval * 2, POLL_MAX_INTERVAL );
	}

	// FNV-1a 32-bit hash so we can cheaply detect content changes between polls
	function hashString(s) {
		var h = 0x811c9dc5;
		for (var i = 0; i < s.length; i++) {
			h ^= s.charCodeAt(i);
			h = (h + ((h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24))) >>> 0;
		}
		return h;
	}

	function refreshTile(tileId) {
		if (document.hidden) {
			return;
		}
		var $tile = $('.owbn-board-tile[data-tile-id="' + tileId.replace(/"/g, '\\"') + '"]').first();
		if (!$tile.length) {
			return;
		}

		// Skip if user is typing
		var $tileBody = $tile.find('.owbn-board-tile__body').first();
		var $focused = $tileBody.find(':focus');
		if ($focused.length && ($focused.is('textarea') || $focused.is('input'))) {
			schedulePoll(tileId);
			return;
		}
		var hasUnsavedText = false;
		$tileBody.find('textarea, input[type="text"]').each(function () {
			if ($(this).val() && $(this).val().length > 0) {
				hasUnsavedText = true;
			}
		});
		if (hasUnsavedText) {
			schedulePoll(tileId);
			return;
		}

		var $scopeContainer = $tileBody.find('[data-active-scope]').first();
		var activeScope = $scopeContainer.length ? $scopeContainer.attr('data-active-scope') : '';

		$.post(OWBN_BOARD.ajax_url, {
			action: 'owbn_board_tile_refresh',
			nonce: OWBN_BOARD.nonce,
			tile_id: tileId
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}
			var newHash = hashString(response.data.html);
			var t = pollTimers[tileId];
			if (t.lastHash !== null && t.lastHash === newHash) {
				// No change — back off
				backoffPollInterval(tileId);
			} else {
				// Content changed (or first poll) — only re-render if hash changed
				if (t.lastHash !== null) {
					$tileBody.html(response.data.html);
					if (activeScope) {
						var $newContainer = $tileBody.find('[data-active-scope]').first();
						if ($newContainer.length) {
							$newContainer.attr('data-active-scope', activeScope);
							var $panels = $newContainer.find('[data-scope]');
							$panels.removeClass('is-active');
							$panels.filter('[data-scope="' + activeScope.replace(/"/g, '\\"') + '"]').addClass('is-active');
							var $select = $newContainer.find('.owbn-board-scope-switcher');
							if ($select.length) {
								$select.val(activeScope);
							}
						}
					}
				}
				resetPollInterval(tileId);
				t.lastHash = newHash;
				return;
			}
			t.lastHash = newHash;
		}).always(function () {
			schedulePoll(tileId);
		});
	}

	// Generic scope switcher: tiles that support multiple groups have a select with
	// class .owbn-board-scope-switcher in the tile body. Sibling .{module}__panel
	// elements with matching data-scope attributes get .is-active toggled.
	function initScopeSwitcher() {
		$('.owbn-board').on('change', '.owbn-board-scope-switcher', function () {
			var $select = $(this);
			var scope = $select.val();
			// Find the nearest tile body and update its data-active-scope.
			var $tileBody = $select.closest('[data-active-scope]');
			if ($tileBody.length) {
				$tileBody.attr('data-active-scope', scope);
			}
			// Toggle is-active on sibling panels matching this scope.
			var $panels = $tileBody.find('[data-scope]');
			$panels.removeClass('is-active');
			$panels.filter('[data-scope="' + scope.replace(/"/g, '\\"') + '"]').addClass('is-active');
		});
	}

	function escapeHtml(str) {
		return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// ---------- Tile state (collapse, pin, snooze, dismiss) ----------

	function initTileActions() {
		$('.owbn-board').on('click', '.owbn-board-tile__collapse', function () {
			var $tile = $(this).closest('.owbn-board-tile');
			$tile.toggleClass('owbn-board-tile--state-collapsed');
			var state = $tile.hasClass('owbn-board-tile--state-collapsed') ? 'collapsed' : 'default';
			saveTileState($tile.data('tile-id'), state);
		});

		$('.owbn-board').on('change', '.owbn-board-tile__size-picker', function () {
			var $tile = $(this).closest('.owbn-board-tile');
			var tileId = $tile.data('tile-id');
			var oldSize = String($tile.data('size'));
			var newSize = $(this).val();
			if (newSize === oldSize) {
				return;
			}
			var oldClass = 'owbn-board-tile--size-' + oldSize;
			var newClass = 'owbn-board-tile--size-' + newSize;
			var parts = newSize.split('x');
			var cols = parseInt(parts[0], 10) || 1;
			var rows = parseInt(parts[1], 10) || 1;
			$tile.removeClass(oldClass).addClass(newClass)
				.attr('data-size', newSize).data('size', newSize)
				.css({
					'grid-column': 'span ' + cols,
					'grid-row': 'span ' + rows
				});
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_tile_size',
				nonce: OWBN_BOARD.nonce,
				tile_id: tileId,
				size: newSize
			});
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

	// ---------- Tile context menu (Move / Snooze / Hide) ----------

	function closeAllTileMenus() {
		$('.owbn-board-tile__menu-popup').attr('hidden', true);
		$('.owbn-board-tile__menu').attr('aria-expanded', 'false');
	}

	function initTileMenu() {
		// Toggle popup on kebab click
		$('.owbn-board').on('click', '.owbn-board-tile__menu', function (e) {
			e.stopPropagation();
			var $btn = $(this);
			var $popup = $btn.siblings('.owbn-board-tile__menu-popup');
			var isOpen = !$popup.attr('hidden');
			closeAllTileMenus();
			if (!isOpen) {
				$popup.removeAttr('hidden');
				$btn.attr('aria-expanded', 'true');
			}
		});

		// Click outside closes
		$(document).on('click', function () {
			closeAllTileMenus();
		});

		// Snooze 24h
		$('.owbn-board').on('click', '.owbn-board-tile__menu-item[data-action="snooze"]', function (e) {
			e.stopPropagation();
			var $tile = $(this).closest('.owbn-board-tile');
			var snoozeUntil = new Date(Date.now() + 24 * 60 * 60 * 1000)
				.toISOString().slice(0, 19).replace('T', ' ');
			saveTileState($tile.data('tile-id'), 'snoozed', snoozeUntil);
			$tile.fadeOut(200);
			closeAllTileMenus();
		});

		// Hide (dismiss)
		$('.owbn-board').on('click', '.owbn-board-tile__menu-item[data-action="hide"]', function (e) {
			e.stopPropagation();
			var $tile = $(this).closest('.owbn-board-tile');
			if (!window.confirm(OWBN_BOARD.i18n.confirm_hide || 'Hide this tile? You can restore it from the OWBN Board admin.')) {
				closeAllTileMenus();
				return;
			}
			saveTileState($tile.data('tile-id'), 'dismissed');
			$tile.fadeOut(200);
			closeAllTileMenus();
		});

		// Move → enter rearrange mode
		$('.owbn-board').on('click', '.owbn-board-tile__menu-item[data-action="move"]', function (e) {
			e.stopPropagation();
			closeAllTileMenus();
			enterRearrangeMode();
		});
	}

	// ---------- Rearrange mode (drag-to-reorder) ----------

	var rearrangeKeyHandler = null;

	function initRearrangeMode() {
		// Floating "Done" button (created on demand, removed on exit)
	}

	function enterRearrangeMode() {
		var $board = $('.owbn-board').first();
		if ($board.hasClass('owbn-board--rearranging')) {
			return;
		}
		$board.addClass('owbn-board--rearranging');

		// Floating done button
		var $done = $('<button type="button" class="owbn-board__rearrange-done">' +
			(OWBN_BOARD.i18n.done_rearranging || 'Done rearranging') + '</button>');
		$('body').append($done);
		$done.on('click', exitRearrangeMode);

		// Initialize sortable on the grid
		var $grid = $board.find('.owbn-board-grid').first();
		if ($.fn.sortable && $grid.length) {
			$grid.sortable({
				items: '.owbn-board-tile',
				tolerance: 'pointer',
				placeholder: 'owbn-board-tile owbn-board-tile--placeholder',
				forcePlaceholderSize: true,
				update: function () {
					var ids = $grid.find('.owbn-board-tile').map(function () {
						return $(this).data('tile-id');
					}).get();
					$.post(OWBN_BOARD.ajax_url, {
						action: 'owbn_board_tile_order',
						nonce: OWBN_BOARD.nonce,
						'tile_ids[]': ids
					});
				}
			});
		}

		// Escape key exits
		rearrangeKeyHandler = function (e) {
			if (e.key === 'Escape') {
				exitRearrangeMode();
			}
		};
		$(document).on('keydown', rearrangeKeyHandler);
	}

	function exitRearrangeMode() {
		var $board = $('.owbn-board').first();
		$board.removeClass('owbn-board--rearranging');

		var $grid = $board.find('.owbn-board-grid').first();
		if ($.fn.sortable && $grid.length && $grid.hasClass('ui-sortable')) {
			$grid.sortable('destroy');
		}

		$('.owbn-board__rearrange-done').remove();

		if (rearrangeKeyHandler) {
			$(document).off('keydown', rearrangeKeyHandler);
			rearrangeKeyHandler = null;
		}
	}

	// ---------- Notebook autosave + group switcher ----------
	// Shared state: switcher flushes pending saves for the old id before
	// setContent; autosave skips POSTs when editor content equals last-saved
	// (state right after a group switch).

	var notebookDebounceTimers = {};
	var notebookLastSaved = {};

	function flushPendingNotebookSave(notebookId) {
		if (!notebookId) {
			return;
		}
		if (notebookDebounceTimers[notebookId]) {
			clearTimeout(notebookDebounceTimers[notebookId]);
			delete notebookDebounceTimers[notebookId];
		}
	}

	function initNotebookAutosave() {
		$('.owbn-board-notebook').each(function () {
			var $notebook = $(this);
			var initialId = $notebook.data('notebook-id');

			// Empty-state notebook (no DB row yet, no editor) — skip.
			if (!initialId) {
				return;
			}

			// Editor DOM id is fixed at render time to the initial notebook;
			// saves always read the current data-notebook-id so group switches
			// route writes to the right notebook.
			var editorDomId = 'owbn_board_notebook_' + initialId;

			var checkTinyMCE = setInterval(function () {
				if (typeof tinymce === 'undefined') {
					return;
				}
				var editor = tinymce.get(editorDomId);
				if (!editor) {
					return;
				}
				clearInterval(checkTinyMCE);

				// Seed last-saved so TinyMCE's settling change event doesn't queue a no-op save.
				notebookLastSaved[initialId] = editor.getContent();

				editor.on('change keyup', function () {
					var currentId = $notebook.data('notebook-id');
					if (!currentId) {
						return;
					}
					var currentContent = editor.getContent();
					if (notebookLastSaved[currentId] === currentContent) {
						return;
					}
					clearTimeout(notebookDebounceTimers[currentId]);
					notebookDebounceTimers[currentId] = setTimeout(function () {
						saveNotebook($notebook, currentId, editor.getContent());
					}, 3000);
				});

				editor.on('blur', function () {
					var currentId = $notebook.data('notebook-id');
					if (!currentId) {
						return;
					}
					var currentContent = editor.getContent();
					if (notebookLastSaved[currentId] === currentContent) {
						return;
					}
					clearTimeout(notebookDebounceTimers[currentId]);
					saveNotebook($notebook, currentId, currentContent);
				});
			}, 500);
		});
	}

	function saveNotebook($notebook, notebookId, content) {
		if (!notebookId) {
			return;
		}
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
					notebookLastSaved[notebookId] = content;
					$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
				} else {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				}
			})
			.fail(function () {
				$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
			});
	}

	function initNotebookGroupSwitcher() {
		$('.owbn-board').on('change', '.owbn-board-notebook__group-select', function () {
			var $select = $(this);
			var $notebook = $select.closest('.owbn-board-notebook');
			var group = $select.val();
			var $status = $notebook.find('.owbn-board-notebook__status');
			var oldId = $notebook.data('notebook-id');

			// Cancel pending debounced save for the old id so it can't fire
			// against swapped content; then flush any unsaved edits synchronously.
			flushPendingNotebookSave(oldId);

			if (oldId && typeof tinymce !== 'undefined') {
				var pendingEditor = null;
				var pendingEditors = tinymce.editors || [];
				for (var p = 0; p < pendingEditors.length; p++) {
					if ($notebook[0].contains(pendingEditors[p].getElement())) {
						pendingEditor = pendingEditors[p];
						break;
					}
				}
				if (pendingEditor) {
					var pendingContent = pendingEditor.getContent();
					if (notebookLastSaved[oldId] !== pendingContent) {
						saveNotebook($notebook, oldId, pendingContent);
					}
				}
			}

			$status.removeClass('is-saved is-error').addClass('is-saving').text(OWBN_BOARD.i18n.saving);

			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_notebook_load',
				nonce: OWBN_BOARD.nonce,
				group: group
			})
				.done(function (response) {
					if (!response || !response.success || !response.data) {
						$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
						return;
					}
					var data = response.data;
					var newId = data.notebook_id;
					var newContent = data.content || '';

					$notebook.attr('data-notebook-id', newId).data('notebook-id', newId);
					$notebook.attr('data-role-path', data.role_path).data('role-path', data.role_path);
					$notebook.find('.owbn-board-notebook__scope').text(data.role_path);

					// Record last-saved so the upcoming setContent doesn't queue a no-op.
					if (newId) {
						notebookLastSaved[newId] = newContent;
					}

					if (typeof tinymce !== 'undefined') {
						var editors = tinymce.editors || [];
						var editor = null;
						for (var i = 0; i < editors.length; i++) {
							if ($notebook[0].contains(editors[i].getElement())) {
								editor = editors[i];
								break;
							}
						}
						if (editor) {
							editor.setContent(newContent);
						} else {
							$notebook.find('.owbn-board-notebook__readonly').html(newContent);
						}
					} else {
						$notebook.find('.owbn-board-notebook__readonly').html(newContent);
					}

					$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
				})
				.fail(function () {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				});
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
			var activeScope = $tile.attr('data-active-scope') || $tile.data('role-path');
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_message_post',
				nonce: OWBN_BOARD.nonce,
				role_path: activeScope,
				content: content
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				$input.val('');
				var data = response.data;
				// Prepend to the active panel's feed (or the tile's feed if no panels).
				var $activePanel = $tile.find('.owbn-board-message__panel.is-active').first();
				var $feed = $activePanel.length
					? $activePanel.find('.owbn-board-message__feed')
					: $tile.find('.owbn-board-message__feed').first();
				$feed.find('.owbn-board-message__empty').remove();
				var html =
					'<div class="owbn-board-message__item" data-message-id="' + escapeHtml(data.id) + '">' +
						'<div class="owbn-board-message__meta">' +
							'<strong class="owbn-board-message__author">' + escapeHtml(data.display_name) + '</strong>' +
							'<span class="owbn-board-message__time">' + escapeHtml(data.time_label) + '</span>' +
							(data.can_delete ? '<button type="button" class="owbn-board-message__delete" aria-label="Delete message">&times;</button>' : '') +
						'</div>' +
						'<div class="owbn-board-message__body">' + escapeHtml(data.content) + '</div>' +
					'</div>';
				$feed.prepend(html);
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
			var $tile = $form.closest('.owbn-board-pins');
			var $labelInput = $form.find('.owbn-board-pins__label');
			var $urlInput = $form.find('.owbn-board-pins__url');
			var label = $labelInput.val().trim();
			var url = $urlInput.val().trim();
			if (!label || !url) {
				return;
			}
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_pin_add',
				nonce: OWBN_BOARD.nonce,
				label: label,
				url: url
			}).done(function (response) {
				if (!response || !response.success || !response.data || !response.data.links) {
					return;
				}
				var links = response.data.links;
				var newest = links[links.length - 1];
				if (!newest) {
					return;
				}
				var $list = $tile.find('.owbn-board-pins__list');
				$list.find('.owbn-board-pins__empty').remove();
				var html =
					'<li class="owbn-board-pins__item" data-pin-id="' + escapeHtml(newest.id) + '">' +
						'<a href="' + escapeHtml(newest.url) + '" class="owbn-board-pins__link">' + escapeHtml(newest.label) + '</a>' +
						'<button type="button" class="owbn-board-pins__remove" aria-label="Remove pin">&times;</button>' +
					'</li>';
				$list.append(html);
				$labelInput.val('');
				$urlInput.val('');
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

	// ---------- Calendar tile ----------

	function initCalendarTile() {
		// Convert UTC timestamps in data-start to browser-local date/time labels
		$('.owbn-board-calendar__item').each(function () {
			var $item = $(this);
			var ts = parseInt($item.data('start'), 10);
			var allDay = String($item.data('all-day')) === '1';
			if (!ts) {
				return;
			}
			var d = new Date(ts * 1000);
			var $dateLabel = $item.find('[data-format="date"]');
			var $timeLabel = $item.find('[data-format="time"]');

			// Short month + day (e.g. "Apr 8")
			var month = d.toLocaleString(undefined, { month: 'short' });
			var day = d.getDate();
			$dateLabel.text(month + ' ' + day);

			if (!allDay && $timeLabel.length) {
				var timeStr = d.toLocaleString(undefined, { hour: 'numeric', minute: '2-digit' });
				$timeLabel.text(timeStr);
			}
		});

		// Filter panel toggle
		$('.owbn-board').on('click', '.owbn-board-calendar__filters-toggle', function () {
			var $panel = $(this).siblings('.owbn-board-calendar__filters-panel');
			if ($panel.prop('hidden')) {
				$panel.prop('hidden', false);
			} else {
				$panel.prop('hidden', true);
			}
		});

		// Save filters
		$('.owbn-board').on('click', '.owbn-board-calendar__filters-save', function () {
			var $panel = $(this).closest('.owbn-board-calendar__filters-panel');
			var genres = [];
			var days = [];
			var types = [];
			$panel.find('input[name="genres"]:checked').each(function () { genres.push($(this).val()); });
			$panel.find('input[name="days"]:checked').each(function () { days.push($(this).val()); });
			$panel.find('input[name="session_types"]:checked').each(function () { types.push($(this).val()); });
			var mode = $panel.find('input[name="chronicles_mode"]:checked').val() || 'mine';

			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_calendar_save_filters',
				nonce: OWBN_BOARD.nonce,
				genres: genres,
				days: days,
				session_types: types,
				chronicles_mode: mode
			}).done(function (response) {
				if (response && response.success) {
					location.reload();
				}
			});
		});
	}

	// ---------- Visitors tile ----------

	function initVisitorsTile() {
		$('.owbn-board').on('submit', '.owbn-board-visitors__form', function (e) {
			e.preventDefault();
			var $form = $(this);
			var $tile = $form.closest('.owbn-board-visitors');
			var data = {
				action: 'owbn_board_visitors_create',
				nonce: OWBN_BOARD.nonce,
				host_chronicle_slug: $form.find('[name="host_chronicle_slug"]').val(),
				character_name: $form.find('[name="character_name"]').val(),
				visit_date: $form.find('[name="visit_date"]').val(),
				home_chronicle_slug: $form.find('[name="home_chronicle_slug"]').val(),
				visitor_email: $form.find('[name="visitor_email"]').val(),
				notes: $form.find('[name="notes"]').val()
			};
			if (!data.character_name || !data.visit_date) {
				return;
			}
			$.post(OWBN_BOARD.ajax_url, data).done(function (response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				var v = response.data;
				var $list = $tile.find('.owbn-board-visitors__list');
				$list.find('.owbn-board-visitors__empty').remove();
				var chronicles = v.home_chronicle_slug
					? 'from <code>' + escapeHtml(v.home_chronicle_slug) + '</code> visiting <code>' + escapeHtml(v.host_chronicle_slug) + '</code>'
					: 'visiting <code>' + escapeHtml(v.host_chronicle_slug) + '</code>';
				var html =
					'<div class="owbn-board-visitors__item">' +
						'<div class="owbn-board-visitors__item-header">' +
							'<strong>' + escapeHtml(v.character_name) + '</strong>' +
							(v.visitor_display_name ? ' <span class="owbn-board-visitors__player">(' + escapeHtml(v.visitor_display_name) + ')</span>' : '') +
							' <span class="owbn-board-visitors__date">' + escapeHtml(v.visit_date_label) + '</span>' +
						'</div>' +
						'<div class="owbn-board-visitors__item-chronicles">' + chronicles + '</div>' +
						(v.notes ? '<div class="owbn-board-visitors__notes">' + escapeHtml(v.notes) + '</div>' : '') +
					'</div>';
				$list.prepend(html);
				// Reset the form fields the user typed, but keep host_chronicle_slug.
				$form.find('[name="character_name"]').val('');
				$form.find('[name="home_chronicle_slug"]').val('');
				$form.find('[name="visitor_email"]').val('');
				$form.find('[name="notes"]').val('');
			});
		});
	}

	// ---------- Events tile ----------

	function initEventsTile() {
		$('.owbn-board').on('click', '.owbn-board-events__rsvp-btn', function () {
			var $btn = $(this);
			var $rsvp = $btn.closest('.owbn-board-events__rsvp');
			var eventId = $rsvp.data('event-id');
			var newStatus = $btn.data('status');
			var wasActive = $btn.hasClass('is-active');

			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_events_rsvp',
				nonce: OWBN_BOARD.nonce,
				event_id: eventId,
				status: wasActive ? 'clear' : newStatus
			}).done(function (response) {
				if (response && response.success) {
					$rsvp.find('.owbn-board-events__rsvp-btn').removeClass('is-active');
					if (!wasActive) {
						$btn.addClass('is-active');
					}
				}
			});
		});
	}

	// ---------- Ballot tile + shortcode ----------

	function initBallotTile() {
		// Submit All button: loops through cards with selections, fires wpvp_cast_ballot per vote
		$(document).on('click', '.owbn-board-ballot__submit-all-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $ballot = $btn.closest('.owbn-board-ballot');
			var $cards = $ballot.find('.owbn-board-ballot__card--open-not-voted');

			if (!$cards.length) {
				alert('No votes to submit.');
				return;
			}

			// Collect selections from each card
			var selections = [];
			var skipped = 0;
			$cards.each(function () {
				var $card = $(this);
				var voteId = $card.data('vote-id');
				var type = $card.data('vote-type');
				var ballotData = collectBallotData($card, type);

				if (ballotData === null) {
					skipped++;
					return;
				}
				selections.push({ voteId: voteId, ballotData: ballotData, $card: $card });
			});

			if (skipped > 0) {
				if (!confirm('You have ' + skipped + ' unvoted positions. Submit anyway?')) {
					return;
				}
			}

			if (!selections.length) {
				alert('No selections made.');
				return;
			}

			var total = selections.length;
			var remaining = total;
			var $remaining = $ballot.find('.owbn-board-ballot__remaining');
			$remaining.text(total + ' remaining');
			$btn.prop('disabled', true);

			// Submit each selection sequentially
			function submitNext(index) {
				if (index >= selections.length) {
					$remaining.text('Done.');
					$btn.prop('disabled', false);
					return;
				}
				var sel = selections[index];
				$.post(OWBN_BOARD.ajax_url, {
					action: 'owbn_board_ballot_cast',
					nonce: OWBN_BOARD.nonce,
					vote_id: sel.voteId,
					ballot_data: JSON.stringify(sel.ballotData)
				}).always(function (response) {
					if (response && response.success) {
						sel.$card.removeClass('owbn-board-ballot__card--open-not-voted')
							.addClass('owbn-board-ballot__card--open-voted');
						sel.$card.find('.owbn-board-ballot__options').html(
							'<span class="owbn-board-ballot__voted-badge">&#10003; Voted</span>'
						);
					} else {
						// Surface the server's error message so users can distinguish
						// wrong nonce, multi-role pick, already voted, etc.
						var serverMsg = (response && response.data && response.data.message)
							? response.data.message
							: 'Vote failed. Try again.';
						var needsRole = response && response.data && response.data.requires_role_selection;
						if (needsRole) {
							serverMsg += ' (Use the wp-voting-plugin native ballot for now — voting-role selection is not yet supported here.)';
						}
						sel.$card.find('.owbn-board-ballot__options').append(
							$('<p>').css({color: '#b32d2e', fontSize: '11px'}).text(serverMsg)
						);
					}
					remaining--;
					$remaining.text(remaining + ' of ' + total + ' remaining');
					submitNext(index + 1);
				});
			}
			submitNext(0);
		});
	}

	/**
	 * Collect ballot data from a single card based on voting type.
	 * Returns the shape wp-voting-plugin expects, or null if no selection made.
	 */
	function collectBallotData($card, type) {
		if (type === 'rcv' || type === 'irv' || type === 'sequential_rcv' || type === 'stv' || type === 'condorcet') {
			// Rank-based: collect ordered array of non-empty selections
			var ranks = [];
			$card.find('.owbn-board-ballot__rank-select').each(function () {
				var v = $(this).val();
				if (v) {
					ranks.push(v);
				}
			});
			return ranks.length ? ranks : null;
		}
		// FPTP / singleton / disciplinary: single selected radio value
		var val = $card.find('input[type="radio"]:checked').val();
		return val ? val : null;
	}

	// ---------- Errata tile ----------

	function initErrataTile() {
		$('.owbn-board').on('change', '.owbn-board-errata__window', function () {
			var days = parseInt($(this).val(), 10);
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_errata_save_window',
				nonce: OWBN_BOARD.nonce,
				days: days
			}).done(function (response) {
				if (response && response.success) {
					location.reload();
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

	// ---------- Tile Access admin page ----------
	// Dirty tracking: Save POSTs only edited fields so untouched defaults
	// aren't frozen as overrides (which would block future default propagation).

	function initTileAccessPage() {
		var $grid = $('.owbn-board-tile-access__grid');
		if (!$grid.length) {
			return;
		}

		$grid.on('input change', '.owbn-board-tile-access__input', function () {
			$(this).attr('data-dirty', 'true');
		});

		$grid.on('click', '.owbn-board-tile-access__save', function () {
			var $card = $(this).closest('.owbn-board-tile-access__card');
			saveTileAccessCard($card);
		});

		$grid.on('click', '.owbn-board-tile-access__reset', function (e) {
			e.preventDefault();
			var $card = $(this).closest('.owbn-board-tile-access__card');
			if (!window.confirm('Reset this tile to its registered defaults? Saved overrides will be cleared.')) {
				return;
			}
			resetTileAccessCard($card);
		});
	}

	function saveTileAccessCard($card) {
		var tileId = $card.data('tile-id');
		var $status = $card.find('.owbn-board-tile-access__status');
		var $read = $card.find('.owbn-board-tile-access__read');
		var $write = $card.find('.owbn-board-tile-access__write');
		var $share = $card.find('.owbn-board-tile-access__share');

		var payload = {
			action: 'owbn_board_tile_access_save',
			nonce: OWBN_BOARD.nonce,
			tile_id: tileId
		};

		var anyDirty = false;
		if ($read.attr('data-dirty') === 'true') {
			payload.read_roles = $read.val();
			anyDirty = true;
		}
		if ($write.attr('data-dirty') === 'true') {
			payload.write_roles = $write.val();
			anyDirty = true;
		}
		if ($share.attr('data-dirty') === 'true' && !$share.prop('disabled')) {
			payload.share_level = $share.val();
			anyDirty = true;
		}

		if (!anyDirty) {
			$status.removeClass('is-saving is-error').addClass('is-saved').text('No changes');
			return;
		}

		$status.removeClass('is-saved is-error').addClass('is-saving').text(OWBN_BOARD.i18n.saving);

		$.post(OWBN_BOARD.ajax_url, payload)
			.done(function (response) {
				if (response && response.success) {
					$read.removeAttr('data-dirty');
					$write.removeAttr('data-dirty');
					$share.removeAttr('data-dirty');
					$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
				} else {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				}
			})
			.fail(function () {
				$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
			});
	}

	function resetTileAccessCard($card) {
		var tileId = $card.data('tile-id');
		var $status = $card.find('.owbn-board-tile-access__status');

		$status.removeClass('is-saved is-error').addClass('is-saving').text(OWBN_BOARD.i18n.saving);

		$.post(OWBN_BOARD.ajax_url, {
			action: 'owbn_board_tile_access_save',
			nonce: OWBN_BOARD.nonce,
			tile_id: tileId,
			read_roles: '__reset__',
			write_roles: '__reset__',
			share_level: '__reset__'
		})
			.done(function (response) {
				if (response && response.success && response.data && response.data.config) {
					var cfg = response.data.config;
					var $read = $card.find('.owbn-board-tile-access__read');
					var $write = $card.find('.owbn-board-tile-access__write');
					var $share = $card.find('.owbn-board-tile-access__share');
					$read.val((cfg.read_roles || []).join('\n')).removeAttr('data-dirty');
					$write.val((cfg.write_roles || []).join('\n')).removeAttr('data-dirty');
					$share.val((cfg.share_level || []).join('\n')).removeAttr('data-dirty');
					$status.removeClass('is-saving is-error').addClass('is-saved').text(OWBN_BOARD.i18n.saved);
				} else {
					$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
				}
			})
			.fail(function () {
				$status.removeClass('is-saving is-saved').addClass('is-error').text(OWBN_BOARD.i18n.save_failed);
			});
	}
})(jQuery);
