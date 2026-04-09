/* OWBN Board — client-side behaviors: tile state, notebook autosave, admin layout save. */

(function ($) {
	'use strict';

	if (typeof OWBN_BOARD === 'undefined') {
		return;
	}

	$(function () {
		initTileActions();
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

	// ---------- Notebook autosave + group switcher ----------
	//
	// debounceTimers and lastSavedContent are shared across both functions
	// so the group switcher can flush a pending save for the old id before
	// calling setContent on the editor, and so autosave can skip POSTs when
	// the editor's current content already matches what's in the DB (which
	// is the state right after a group switch loads content from the server).

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

			// The TinyMCE editor's DOM id is assigned at render time using
			// the FIRST notebook id this container showed. We use that id
			// only to find the editor — NOT to save. Saves always read the
			// current data-notebook-id attribute so group switches route
			// writes to the correct notebook.
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

				// Seed the last-saved snapshot so the first spurious change
				// event (from TinyMCE settling) doesn't queue a no-op save.
				notebookLastSaved[initialId] = editor.getContent();

				editor.on('change keyup', function () {
					var currentId = $notebook.data('notebook-id');
					if (!currentId) {
						return;
					}
					var currentContent = editor.getContent();
					if (notebookLastSaved[currentId] === currentContent) {
						// Editor content equals the last server-known state
						// (typically right after setContent from a group
						// switch). No real change — don't queue a save.
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

			// Flush any pending debounced save for the OLD notebook so the
			// queued setTimeout doesn't fire after we've swapped content
			// and write stale input to the wrong row.
			flushPendingNotebookSave(oldId);

			// If the editor has unsaved changes for the OLD notebook, flush
			// them synchronously before swapping content. Without this the
			// user's in-flight edits would be silently dropped when they
			// switch groups.
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

					// Record the freshly-loaded content as the last-saved
					// snapshot so the setContent call below (which triggers
					// a TinyMCE change event) doesn't queue a no-op save.
					if (newId) {
						notebookLastSaved[newId] = newContent;
					}

					// Swap TinyMCE editor content. The editor's DOM id was
					// fixed at page render, so we address it by the initial
					// id captured at autosave bind time — NOT by newId.
					// Autosave reads data-notebook-id dynamically so writes
					// land on the right row.
					if (typeof tinymce !== 'undefined') {
						// Walk all tinymce instances inside this container —
						// there should be exactly one, regardless of what
						// id it was bound under.
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
			$.post(OWBN_BOARD.ajax_url, {
				action: 'owbn_board_message_post',
				nonce: OWBN_BOARD.nonce,
				role_path: $tile.data('role-path'),
				content: content
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				$input.val('');
				var data = response.data;
				var $feed = $tile.find('.owbn-board-message__feed');
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
					action: 'wpvp_cast_ballot',
					// wp-voting-plugin's cast-ballot endpoint checks the
					// 'wpvp_public' nonce, NOT our owbn_board nonce. Use the
					// wpvp_nonce localized by owbn_board_enqueue_assets().
					nonce: OWBN_BOARD.wpvp_nonce || OWBN_BOARD.nonce,
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
						// Surface the server's error message if present so the
						// user can tell the difference between "wrong nonce",
						// "you have multiple eligible roles, pick one", "you
						// already voted", etc. The wpvp endpoint returns
						// {success:false, data:{message:'...'}}.
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
	//
	// Dirty tracking: each textarea is marked with data-dirty="true" the
	// first time the user edits it. Save POSTs ONLY dirty fields, so
	// clicking Save without touching a field leaves its registered default
	// tied to the tile (no override is created). Without dirty tracking,
	// the pre-populated default values would get persisted as overrides
	// on every Save, freezing the tile's registered defaults in place and
	// blocking future plugin updates from propagating new defaults.

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
					// Clear dirty flags so the next Save is a no-op unless
					// the admin edits again.
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
