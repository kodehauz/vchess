(function (window, document, Drupal, drupalSettings, $) {

Board = {
  move: {source: "", destination: ""},
  square: ""
};

/**
 * Highlight a move square
 * 
 * @param cmd
 */
Board.highlightMove = function (cmd) {
  let obj;

  // Clear old highlighting in source and destination.
  if (this.move.source !== "") {
    obj = document.getElementById(this.move.source);
    if (obj) obj.classList.remove('highlighted');
    this.move.source = "";
  }

  if (this.move.destination) {
    obj = document.getElementById(this.move.destination);
    if (obj) obj.classList.remove('highlighted');
    this.move.destination = "";
  }

  // If command is empty don't highlight again.
  if (cmd === null || cmd === "") {
	  return;
  }
		
  // Parse command for source/destination and highlight it 
  // cmd is e.g. "Nb1" or "Nb1-c3"
  this.move.source = cmd.substr(1, 2); // e.g. "b1"
  if (cmd.length >= 6) {
    this.move.destination = cmd.substr(5,6); // e.g. "c3"
  }
  else {
    this.move.destination = "";
  }
	
  // Set new highlighting
  obj = document.getElementById(this.move.source);
  if (obj) obj.classList.add('highlighted');
};

/**
 * 
 */
Board.checkMoveButton = function () {
  const form = this.getBoardForm();

  // Move button
  if (form && document.getElementById("edit-move-button")) {
    document.getElementById("edit-move-button").disabled = form.move.value.length < 6;
  }
};

/**
 * Assemble command into commandForm.move and submit move if destination is
 * clicked twice.
 * 
 * @param: part
 *   This might contain something like:
 *     'xb8' = piece on the b8 square (which belongs to the non-moving player)
 *     'Ke1' = King on e1 square
 *     '-b5' = empty b5 square 
 */
Board.assembleCmd = function (part) {
  const form = this.getBoardForm();
  const cmd = form.move.value;

  if (form) {
    // e.g. cmd might contain something like "Pe2-e4"
    if (cmd === part) {
      form.move.value = "";
    }
    else
      if (cmd.length === 0 || cmd.length >= 6) {
        if (part.charAt(0) !== '-' && part.charAt(0) !== 'x') {
          form.move.value = part;
        }
      }
      else
        if (part.charAt(0) === '-' || part.charAt(0) === 'x') {
          form.move.value = cmd + part;
        }
        else {
          form.move.value = part;
        }

    if (form.move.value.length >= 6) {
      this.onClickMove();
    }

    this.highlightMove(form.move.value);
    this.checkMoveButton();
  }
};

/**
 * Make a move
 * 
 * A move may be a move to a square or a capture, e.g.:
 * - "Pe2-e4"
 * - "Qd1xBg4"
 */
Board.onClickMove = function () {
	const form = this.getBoardForm();
	
	if (form && form.move.value !== "") {
		let move = form.move.value;
		const move_type = move[3];
		let to_rank;

		// Find out the rank of the square we are going to
		if (move_type === "-") {
		  to_rank = move[5];
		}
		else { // move_type == "x"
		  to_rank = move[6];
		}
		
		// If pawn enters last line ask for promotion
		if (move[0] === 'P' && (to_rank === '8' || to_rank === '1')) {
			if (confirm('Promote to Queen? (Press Cancel for other options)'))
				move = move + '=Q';
			else if (confirm('Promote to Rook? (Press Cancel for other options)'))
				move = move + '=R';
			else if (confirm('Promote to Bishop? (Press Cancel for other options)'))
				move = move + '=B';
			else if (confirm('Promote to Knight? (Press Cancel to abort move)'))
				move = move + '=N';
			else
				return;
		}
		form.cmd.value = move;
		form.submit();
	}
};

/**
 * Get the user to confirm resignation
 */
Board.confirm_resign = function () {
	const resign = confirm("Are you sure you want to resign?");
	if (resign === true) {
	  alert(Drupal.t("You pressed OK!"));
	}
	else {
	  alert(Drupal.t("You pressed Cancel!"));
	}
};

Board.getBoardForm = function () {
  return document.getElementsByClassName('vchess-game-form')[0];
};

Board.refresh = function () {
  if (this.refreshAjax) {
    const button = document.querySelector('[data-drupal-selector="edit-refresh-button"]');
    const evt = new $.Event();
    this.refreshAjax['board'].eventResponse(button, evt);
    if (this.refreshAjax['movelist']) {
      // Because moves list is in a block, we avoid the problem of duplicated
      // DOM element IDs by using the class and pulling the first.
      this.refreshAjax['movelist'].wrapper = '#' + $('.vchess-moves-list').get(0).id;
      this.refreshAjax['movelist'].execute();
    }
    if (this.refreshAjax['captured']) {
      // Because captured pieces are in a block, we avoid the problem of
      // duplicated DOM element IDs by using the class and pulling the first.
      this.refreshAjax['captured'].wrapper = '#' + $('.vchess-captured-pieces').get(0).id;
      this.refreshAjax['captured'].execute();
    }
  }
};

Board.createAjaxEvent = function () {
  const button = document.querySelector('[data-drupal-selector="edit-refresh-button"]');
  const form = this.getBoardForm();
  if (form) {
    // Create different ajax handlers to refresh the game board area.
    this.refreshAjax = {};

    // The board itself.
    const refresh_board_settings = {
      url: form.action + '?ajax_form=1',
      callback: "::refreshBoard",
      wrapper: "vchess-container",
      setClick: true,
      base: button.id,
      element: button,
      progress: false,
      submit: {
        js: true,
        _triggering_element_name: "refresh_button"
      }
    };
    this.refreshAjax['board'] = Drupal.ajax(refresh_board_settings);

    // The move list block.
    const movelist = $('.vchess-moves-list').get(0);
    if (movelist) {
      let refresh_movelist_settings = {
        url: drupalSettings.vchess.movelist_url,
        wrapper: movelist.id,
        progress: false
      };
      this.refreshAjax['movelist'] = Drupal.ajax(refresh_movelist_settings);
    }

    // The captured pieces block.
    const captured_pieces = $('.vchess-captured-pieces').get(0);
    if (captured_pieces) {
      let refresh_captured_settings = {
        url: drupalSettings.vchess.captured_pieces_url,
        wrapper: captured_pieces.id,
        progress: false
      };
      this.refreshAjax['captured'] = Drupal.ajax(refresh_captured_settings);
    }
  }

  // Create interval for refreshing board.
  const interval = Math.max(drupalSettings.vchess.refresh_interval, 10) * 1000;
  Board.interval = window.setInterval(function () {
    // Refresh the board in case a move was made.
    Board.refresh();
  }, interval);
};

Drupal.behaviors.vchess = {
  attach: function (context) {
    $('table.board-main')
      .on('click', 'td.board-square.enabled', function (event) {
        Board.assembleCmd(this.dataset.chessCommand);
        event.stopImmediatePropagation();
      });

    Board.checkMoveButton();
    const form = Board.getBoardForm();
    if (form) {
      Board.highlightMove(form.move.value);
    }

    // Create ajax request for refreshing board.
    // Only if it is not current user's turn to play.
    const isActivePlayer = $('td.board-square.enabled').length > 0;
    if (Board.refreshAjax === undefined && !isActivePlayer) {
      Board.createAjaxEvent();
    }
    // Remove the ajax refresh request if the player is active since it is not
    // needed.
    else if (Board.refreshAjax !== undefined && isActivePlayer) {
      delete Board.refreshAjax; // = undefined;
      window.clearInterval(Board.interval);
    }
  }
};

})(window, window.document, Drupal, drupalSettings, jQuery);
