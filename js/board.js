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
  var obj;

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
  var form = this.getBoardForm();

  // Move button
  if (form && document.getElementById("edit-move-button")) {
    if (form.move.value.length >= 6) {
      document.getElementById("edit-move-button").disabled = false;
    }
    else {
      document.getElementById("edit-move-button").disabled = true;
    }
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
  var form = this.getBoardForm();
  var cmd = form.move.value;
  var cmd3onwards = cmd.substring(3);

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
	var form = this.getBoardForm();
	
	if (form && form.move.value !== "") {
		var move = form.move.value;
		var move_type = move[3];
		var to_rank;
		
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
		this.gatherCommandFormData();
		form.submit();
	}
};

/**
 * Get the user to confirm resignation
 */
Board.confirm_resign = function () {
	var resign = confirm("Are you sure you want to resign?");
	if (resign === true) {
	  alert(Drupal.t("You pressed OK!"));
	}
	else {
	  alert(Drupal.t("You pressed Cancel!"));
	}
};

/**
 * 
 */
Board.gatherCommandFormData = function () {
	fm = this.getBoardForm();
//	if (document.commentForm && document.commentForm.comment)
//		fm.comment.value=document.commentForm.comment.value;
//	if (document.pnotesForm && document.pnotesForm.privnotes)
//		fm.privnotes.value=document.pnotesForm.privnotes.value;
//	else
//		fm.privnotes.disabled=true;
};

Board.getBoardForm = function () {
  return document.getElementsByClassName('vchess-game-form')[0];
};

Board.refresh = function () {
  if (this.refreshAjax) {
    var button = document.querySelector('[data-drupal-selector="edit-refresh-button"]');
    var evt = new $.Event();
    this.refreshAjax.eventResponse(button, evt);
  }
};

Board.createAjaxEvent = function () {
  var button = document.querySelector('[data-drupal-selector="edit-refresh-button"]');
  var form = this.getBoardForm();
  if (form) {
    var ajax_settings = {
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
    this.refreshAjax = Drupal.ajax(ajax_settings);
  }

  // Create interval for refreshing board.
  var interval = Math.max(drupalSettings.vchess.refresh_interval, 10) * 1000;
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
    var form = Board.getBoardForm();
    if (form) {
      Board.highlightMove(form.move.value);
    }

    // Create ajax request for refreshing board.
    // Only if it is not current user's turn to play.
    var isActivePlayer = $('td.board-square.enabled').size() > 0;
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
