(function (document, Drupal, drupalSettings) {

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
  var theme = "default";
  var obj;

  // Clear old highlighting 
  for (var i in this.move) {
	  if (this.move.hasOwnProperty(i) && this.move[i] !== "") {
      var img = "wsquare.jpg";
      if (this.isWhiteSquare(this.move[i])) {
        img = "wsquare.jpg";
      }
      else {
        img = "bsquare.jpg";
      }
      obj = document.getElementById(this.move[i]);
      if (obj) {
        obj.style.backgroundImage = "url(" + drupalSettings.vchess.module_path + "/images/" + theme + "/" + img + ")";
      }
      this.move[i] = "";
    }
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
  for (i in this.move) {
    if (this.move.hasOwnProperty(i) && this.move[i] !== "") {
      if (this.isWhiteSquare(this.move[i])) {
        // White square highlighted.
        img = "whsquare.jpg";
      }
      else {
        // Black square highlighted.
        img = "bhsquare.jpg";
      }
      obj = document.getElementById(this.move.source);
      if (obj) {
          obj.style.backgroundImage = "url(" + drupalSettings.vchess.module_url
            + "/images/" + theme + "/" + img + ")";
      }
    }
  }
};

Board.isWhiteSquare = function (coordinate) {
  var file = coordinate.toLowerCase().charCodeAt(0) - 97;
  var rank = parseInt(coordinate[1]) - 1;

  return (rank + file + 1) % 2 === 0;
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

  // e.g. cmd might contain something like "Pe2-e4"
  if (cmd === part) {
	  form.move.value = "";
  }
  else if (cmd.length === 0 || cmd.length >= 6) {
    if (part.charAt(0) !== '-' && part.charAt(0) !== 'x') {
      form.move.value = part;
    }
//  else if (cmd.length >= 6 && cmd3onwards == part) {
//  if (confirm("Execute move "+cmd+"?")) {
//	onClickMove();
//    }
  } else if (part.charAt(0) === '-' || part.charAt(0) === 'x') {
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
	
  return false;
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
	
	if (form.move.value !== "") {
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
  return document.getElementById('vchess-game-form')
};

Drupal.behaviors.vchess = {
  attach: function (context) {
    jQuery('.board-main')
      .on('click', '.board-square', function () {
        return Board.assembleCmd(this.dataset.chessCommand);
      });

    jQuery('.board-square.active')
      .css('cursor', 'pointer');

    Board.checkMoveButton();
    Board.highlightMove(this.getBoardForm().move.value);
  }
};

})(window.document, Drupal, drupalSettings);
