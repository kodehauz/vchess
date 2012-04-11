<?php

/**
 * @file
 * Functions concerning a game
 */

// Define game statuses
define('STATUS_WHITE_WIN', '1-0');
define('STATUS_BLACK_WIN', '0-1');
define('STATUS_DRAW', '1/2-1/2');
define('STATUS_IN_PROGRESS', 'in progress');

// Define errors
define('ERROR_CASTLING_SQUARES_BLOCKED', 'ERROR: Castling squares are blocked!');
define('ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING', 'ERROR: You cannot escape check by castling!');
define('ERROR_CANNOT_CASTLE_ACROSS_CHECK', 'ERROR: You cannot castle across check!');
define('ERROR_CANNOT_CASTLE_SHORT', 'ERROR: You cannot castle short anymore!');
define('ERROR_CANNOT_CASTLE_LONG', 'ERROR: You cannot castle long anymore!');

class Game {
  
  protected $game = array();
  
  protected $scoresheet;
  
  protected $white_uid;
  protected $black_uid;
	
  // chatter: list of chatter lines (first is newest)
  
  // Dynamic (based on user id) entries:
  // p_maymove: whether it's player's turn (always 0 if user is not playing)
  // p_mayundo: player may undo last move
  // p_mayabort: player may abort game (first move or opponent took too long)
  // p_mayarchive: player may move game to archive
  
  /**
   * Game constructor
   */
  function __construct() {
  	$this->scoresheet = new Scoresheet();
  	$this->board = new Board;

  	// Ensure the game has players defined by default
  	$this->white_uid = 1;
  	$this->black_uid = 1;
  	
  	// turn: color of whose turn it is to move (w or b)
  	$this->turn = "w";
  	
  	// status: status of game ('in progress, '1-0', '0-1', '1/2-1/2', D=draw offered, ?=open)
  	$this->status = STATUS_IN_PROGRESS;

  	// I think the following is for en-passant
  	// w2spm: white 2-step pawn move (x or a-h)
  	// b2spm: black 2-step pawn move
  	$this->w2spm = 'x';
  	$this->b2spm = 'x';
  	
  	// Castling record.  For example, when white king has moved, white may no
  	// longer castle.  When rook has moved, player may only castle on other side.
  	$this->white_may_castle_short = TRUE;
  	$this->white_may_castle_long = TRUE;
  	$this->black_may_castle_short = TRUE;
  	$this->black_may_castle_long = TRUE;
  }
  
  /**
   * Create a new game. 
   * 
   * @param $white_uid
   *   Userid of white player
   *   
   * @param $black_uid
   *   Userid of black player
   *   
   * @param $bcomment
   *   Comment typed in by player at start of game.  If $bcomment is not empty 
   *   it is black's initial comment.
   */
  function new_game($white_uid, $black_uid, $bcomment) {
  	// Build new game context 
  	$this->white_uid = $white_uid;
  	$this->black_uid = $black_uid;
  	
  	$this->game['lastmove'] = 'x';
  	$this->game['lastkill'] = 'x';
  	$this->game['old_castle_short'] = 'x';
  	$this->game['old_castle_long'] = 'x';
  	
    $this->board->setup_as_standard();
//   $this->board->setup_position("rnbqkbnr/ppppp1pp/8/5p2/4P3/8/PPPP1PPP/RNBQKBNR");
  	
  	if (empty($bcomment)) {
  		$bcomment = '(silence)';
  	}
  	else {
  		$bcomment = str_replace("\\", '', strip_tags($bcomment));
  		$bcomment = str_replace("\n", '<br>', $bcomment);
  	}
  	
  	$black_player = new Player($black_uid);
  	$bcomment = '<u>' . $black_player->name() . '</u>: ' . $bcomment;
  	$this->game['chatter'] = array($bcomment);
  
  	//  $gfname = sprintf('%s-%s-%s-', date('YmdHi', $this->game['ts_start']), $white, $black);
  	//  vchess_io_save($this->game, $gfname);
  
  	//  return $gfname;
//  	return $this->game;

  	// Converted to the D7 database API syntax.
  	$gid = db_insert('vchess_games')
  	->fields(array(
  			'turn' => 'w',
  			'white_uid' => $white_uid,
  			'black_uid' => $black_uid,
  			'board' => $this->board->position())
  			)
  	->execute();
  	
  	return $gid;
  }
    
  /**
  * Load an existing game (try active games first, then archived games) and set various
  * user-based variables, too. Return game as array or NULL on error.
  *
  * @param $gid: Game id
  *
  */
  function load($gid) {
	$sql = "SELECT gid, turn, white_uid, black_uid, status, board, castling_availability, en_passant_target " .
	 " FROM {vchess_games} WHERE gid = '" .
			$gid . "'";
	// Converted to the D7 database API syntax.
	$result = db_query($sql);
	$row = $result->fetchAssoc();

	$this->gid = $row['gid'];
	$this->turn = $row['turn'];
	
	// Fill chess board
	$this->board->setup_position($row['board']);
	
	$this->white_uid = $row['white_uid'];
	$this->black_uid = $row['black_uid'];
	$this->status = $row['status'];
	
	// Handle castling availability
	$this->initialise_castling($row['castling_availability']);	
    
    $this->scoresheet->load($gid); 
  }	
  
  /**
   * Get the last move
   */
  function last_move() {
    return $this->scoresheet->last_move();
  }
  
  /**
   * This sets up the castling state
   * 
   * If neither side can castle, this is "-". 
   * Otherwise, this has one or more letters: 
   * - "K" (White can castle kingside), 
   * - "Q" (White can castle queenside), 
   * - "k" (Black can castle kingside), and/or 
   * - "q" (Black can castle queenside)
   * 
   * @see http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation 
   */
  function initialise_castling($castling_availability) {
    if (strpos($castling_availability, "K") !== FALSE) {
      $this->game['white_may_castle_short'] = TRUE;
    }
    else {
      $this->game['white_may_castle_short'] = FALSE;
    }
    if (strpos($castling_availability, "Q") !== FALSE) {
      $this->game['white_may_castle_long'] = TRUE;
    }
    else {
      $this->game['white_may_castle_long'] = FALSE;
    }
    if (strpos($castling_availability, "k") !== FALSE) {
      $this->game['black_may_castle_short'] = TRUE;
    }
    else {
      $this->game['black_may_castle_short'] = FALSE;
    }
    if (strpos($castling_availability, "q") !== FALSE) {
      $this->game['black_may_castle_long'] = TRUE;
    }
    else {
      $this->game['black_may_castle_long'] = FALSE;
    }
  }
  
  /** 
   * Create $castling_availability string.
   * 
   * If neither side can castle, this is "-". 
   * Otherwise, this has one or more letters: 
   * - "K" (White can castle kingside), 
   * - "Q" (White can castle queenside), 
   * - "k" (Black can castle kingside), and/or 
   * - "q" (Black can castle queenside)
   * e.g. "KQkq"
   * 
   * @see http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation 
   */
  function castling_availability() {
    $castling_availability = "";
    
    if ($this->game['white_may_castle_short']) {
      $castling_availability .= "K";
    }
    if ($this->game['white_may_castle_long']) {
      $castling_availability .= "Q";
    }
    if ($this->game['black_may_castle_short']) {
      $castling_availability .= "k";
    }
    if ($this->game['black_may_castle_long']) {
      $castling_availability .= "q";
    }
    if ($castling_availability == "") {
      $castling_availability = "-";
    }
    
    return $castling_availability;
  }
  
  /**
   * Get the white player
   */
  function get_white_player() {
    return new Player($this->white_uid);
  }
  
  /**
   * Get the the black player
   */
  function get_black_player() {
  	return new Player($this->black_uid);
  }
  
  /**
   * Get the number of the current move.  The move number will be the number
   * of the move which is currently not yet complete.  Each move has a white
   * move and a black move.
   * 
   * i.e.
   * No moves, i.e.
   * 1. ... ...
   * move_no = 1 (i.e. waiting for move 1 of white)
   * After 1.e4 ... 
   * move_no = 1 (i.e. waiting for move 1 of black)
   * After 1. e4 Nf6 
   * move_no = 2 (i.e. waiting for move 2) 
   */
  function move_no() {
  	return $this->scoresheet->move_no();
  }
  
  /**
   * See if it's the given players move
   */
  function is_players_move($uid) {
  	if (($this->turn == 'w' && $this->white_uid == $uid) 
  	|| ($this->turn == 'b' && $this->black_uid == $uid)) {
  	  $players_move = TRUE;	
  	}
  	else {
  	  $players_move = FALSE;
  	}
  	
  	return $players_move;
  }
  
  /** 
   * Get the game id
   */
  function get_gid() {
  	return $this->gid;
  }
  
  /**
   * Get the game board
   */
  function get_board() {
  	return $this->board;
  }
  
  /**
   * Set the board
   */
  function set_board(Board $board) {
  	$this->board = $board;
  }
  
  /**
   * Setup a postion
   * 
   * @param $fen_string
   *   A FEN string, e.g. after 1.e4 the FEN string will be:
   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
   */
  function setup_position($fen_string) {
    $this->board->setup_position($fen_string);
  }
  
  /**
   * Get the current position as a FEN string
   * 
   * @return the current position as a FEN string
   *   e.g. after 1.e4 the FEN string will be:
   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
   */
  function position() {
    return $this->board->position();
  }

  /**
   * Get the uid of the white player
   */
  function get_white_uid() {
    return $this->white_uid;
  }
  
  /**
   * Get the uid of the black player
   */
  function get_black_uid() {
    return $this->black_uid;
  }
  
  /**
   * Get the player whose turn it is, either 'w' or 'b'
   */
  function get_turn() {
  	return $this->turn;
  }
  
  /**
   * Get the status
   * 
   * Status can be one of:
   * - "1-0"
   * - "0-1"
   * - "draw"
   * - "in progress"
   */
  function get_status() {
  	return $this->status;
  }
  
  /**
   * Set the player whose turn it is to move to be 'w'
   */
  function set_turn_white() {
  	$this->turn = 'w';
  }
  
  /**
   * Set the player whose turn it is to move to be 'b'
   */
  function set_turn_black() {
  	$this->turn = 'b';
  }
  
  /**
   * Say whether the game is over or not
   */
  function is_game_over() {
  	$sql = "SELECT status FROM {vchess_games} WHERE gid = '" . $this->gid . "'";
  
  	// Converted to the D7 database API syntax.
  	$result = db_query($sql);
  	$row = $result->fetchAssoc();
  
  	if ($row['status'] == STATUS_IN_PROGRESS) {
  		$game_over = FALSE;
  	}
  	else {
  		$game_over = TRUE;
  	}
  	
  	return $game_over;
  }
  
  /**
   * Say whether the king is in checkmate
   * 
   * @param $player
   *   Player, either 'w' or 'b'
   */
  function king_is_in_checkmate($player) {
    return $this->board->king_is_in_checkmate($player);
  }
  
  /**
   * Say whether the king is in check
   * 
   * @param $player
   *   Player, either 'w' or 'b'
   */
  function king_is_in_check($player) {
    return $this->board->king_is_in_check($player);
  }
  
  /**
   * Find for a particular player who the opponent is.
   *
   * @param $gid
   *   Game id
   *
   * @param $uid
   *   User id of one of the players
   *
   * @return Player $player
   *   The opposing player
   */
  function get_opponent($uid) {
  	if ($this->white_uid == $uid) {
  		$opponent = $this->get_black_player();
  	}
  	else {
  		$opponent = $this->get_white_player();
  	}
  
  	return $opponent;
  }
  
  /**
   * Resign a particular game
   */
  function resign($uid) {
  	$resigning_player = new Player($uid);
  
  	$winner = $this->get_opponent($uid);
  	vchess_update_stats_win($winner, $resigning_player);
  
  	if ($this->get_player_color($uid) == 'w') {
  		$this->set_status(STATUS_BLACK_WIN);
  	}
  	else {
  		$this->set_status(STATUS_WHITE_WIN);
  	}
  
  	drupal_set_message(t('Thank you for resigning.'));
  }
  
  /**
   * Find out what color a particular player is
   * 
   * In the case where a player is playing against themself (!), which we allow
   * at least for testing purposes, the color is the color of whoever's turn it 
   * is to move.
   */
  function get_player_color($uid) {
    if ($this->white_uid == $this->black_uid) {
      $color = $this->get_turn();
    }
  	elseif ($this->white_uid == $uid) {
      $color = 'w';
  	}
  	else {
      $color = 'b';
  	}
  
  	return $color;
  }
  
  /**
   * Set status
   */
  function set_status($status) {
  	$this->status = $status;
  }
  
  /**
   * Set last move
   */
  function set_last_move($last_move) {
//   	$this->game['last_move'] = $last_move;
  }
  
  /**
   * Set w2spm (white 2-space move)
   * 
   * I think this is for en-passant handling
   */
  function set_w2spm($value) {
  	$this->w2spm = $value;
  }
  
  /**
   * Get w2spm (white 2-space move)
   * 
   * I think this is for en-passant handling
   */
 function get_w2spm() {
  	return $this->w2spm;
  }
  
  /**
   * Set b2spm (black 2-space move)
   * 
   * I think this is for en-passant handling
   */
  function set_b2spm($value) {
  	$this->b2spm = $value;
  }
  
  /**
   * Get b2spm (black 2-space move)
   * 
   * I think this is for en-passant handling
   */
  function get_b2spm() {
  	return $this->b2spm;
  }
  
  function white_may_not_castle() {
    $this->game['white_may_castle_short'] = FALSE;
    $this->game['white_may_castle_long'] = FALSE;
  }
  
  function black_may_not_castle() {
    $this->game['black_may_castle_short'] = FALSE;
    $this->game['black_may_castle_long'] = FALSE;
  }
  
  function set_white_may_castle_short($may_castle_short) {
    $this->game['white_may_castle_short'] = $may_castle_short;	
  }
  
  function set_white_may_castle_long($may_castle_long) {
  	$this->game['white_may_castle_long'] = $may_castle_long;
  }
  
  function set_black_may_castle_short($may_castle_short) {
  	$this->game['black_may_castle_short'] = $may_castle_short;
  }
  
  function set_black_may_castle_long($may_castle_long) {
  	$this->game['black_may_castle_long'] = $may_castle_long;
  }
  
  function black_may_castle_long() {
    return $this->black_may_castle_long;
  }
  
  function black_may_castle_short() {
    return $this->black_may_castle_short;
  }

  function white_may_castle_long() {
    return $this->white_may_castle_long;
  }
  
  function white_may_castle_short() {
    return $this->white_may_castle_short;
  }
  
  /**
   * Find out if the given player may castle short
   * 
   * @param unknown_type $player
   * @return TRUE if player may castle short
   */
  function may_castle_short($player) {
    if ($player == 'w') {
      $may_castle_short = $this->white_may_castle_short();
    }
    else {
      $may_castle_short = $this->black_may_castle_short();
    }
    
    return $may_castle_short;
  }
  
  function may_castle_long($player) {
    if ($player == 'w') {
      $may_castle_short = $this->white_may_castle_long();
    }
    else {
      $may_castle_short = $this->black_may_castle_long();
    }
  
    return $may_castle_short;
  }
  
  /**
   * Verify move, execute it and modify game.
   *
   * @param $uid 
   *   User id of current player
   *   
   * @param $move_string 
   *   String of current move in long format, e.g. "Pe2-e4"
   *   
   * @param $comment
   *   @todo Please document this parameter
   */
  function make_move($uid, $move_string, $comment) {
    $mate_type = "";
  
    $move = new Move();
    $move->initialise($move_string);
    
    $board = $this->get_board();
    
    // We will use this board at the end to get the algebraic move
    $board_clone = clone $board;
  
    $result = "";
    $pawn_promoted = FALSE;
    $move_ok = TRUE;
    
    $piece_square = $move->from_square();
    $to_square = $move->to_square();
    
//    $result .= "move_string = $move_string. ";
  
    if (!$this->is_players_move($uid)) {
      $result .= 'It is not your turn!';
    }
    else {
      if ($this->get_turn() == 'w') {
        $opponent = 'b';
      }
      else {
        $opponent = 'w';
      }
   
      // HANDLE MOVES:
     if ($move->type() == 'draw?' && $this->get_status() == '?') {
        // Offer draw
        $this->set_status('D');
        $result .= 'You have offered a draw.';
        $draw_handled = 1;
        $game['lastmove'] = 'DrawOffered';
      }
      elseif ($move->type() == 'refuse_draw' && $this->get_status() == 'D') {
        // Refuse draw
        $this->set_status('?');
        $draw_handled = 1;
        $result .= 'You refused the draw.';
        $game['lastmove'] = 'DrawRefused';
      }
      elseif ($move->type() == 'accept_draw' && $this->get_status() == 'D') {
        // Accept draw
        $this->set_status('-');
        $draw_handled = 1;
        $result .= 'You accepted the draw.';
        $this->set_last_move('DrawAccepted');
        if ($game['curplyr'] == 'b') {
          $game['curmove']++; // new move as white offered
        }
        $game['mhistory'][count($game['mhistory'])] = 'draw';
      }
      elseif ($move->long_format() == 'Ke1-g1' || $move->long_format() == 'Ke8-g8' 
           || $move->long_format() == 'Ke1-c1' || $move->long_format() == 'Ke8-c8') {
        switch ($move->long_format()) {
          case 'Ke1-g1':
            $error = $this->castle('w', 'e1', 'g1', 'h1', 'f1', array('f1', 'g1'), $board);
            break;
          case 'Ke1-c1':  
            $error = $this->castle('w', 'e1', 'c1', 'a1', 'd1', array('b1', 'c1', 'd1'), $board);
            break;
          case 'Ke8-g8':
            $error = $this->castle('b', 'e8', 'g8', 'h8', 'f8', array('f8', 'g8'), $board);
            break;
          case 'Ke8-c8':
            $error = $this->castle('b', 'e8', 'c8', 'a8', 'd8', array('b8', 'c8', 'd8'), $board);
            break;
          default:
            break;  
        }
        if ($error <> "") {
          return $error;
        }
      }
      elseif ($move->type() == "-") {
        // Validate piece and position.
        // Move is e.g. "Nb1-c3"
        $piece = new Piece();
        $piece->set_type($move->source_piece_type());
        $piece->set_color($this->get_turn());

        if (!$board->piece_may_move_to_square($piece_square, $to_square)) {
          $move_ok = FALSE;
        }
        
        // Check destination square is empty
        if (!$board->square_is_empty($to_square)) {
          $move_ok = FALSE;
          $result .= 'ERROR: ' . $to_square->coord() . ' is occupied!';
        }
        
        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handle_pawn_promotion($move, $board);
        
        if ($move_ok && !$pawn_promoted) {
          // Perform normal move
          $board->move_piece($piece_square, $to_square);
        }
      }
      elseif ($move->type() == "x") {
        if ($board->square_is_empty($to_square)) {
          // En passant of pawn?
          if ($piece_type == 'P') {
            if ($this->turn == 'w') {
              if ($this->b2spm != 'x' &&
                  $dest_pos % 8 == $this->b2spm &&
                  floor($dest_pos / 8) == 5) {
                $en_passant_ok = 1;
              }
            }
            else {
              if ($this->w2spm != 'x' &&
                  $dest_pos % 8 == $this->w2spm &&
                  floor($dest_pos / 8) == 2) {
                $en_passant_ok = 1;
              }
            }
            if ($en_passant_ok == 0) {
              $result .= 'ERROR: En-passant is not possible!';
              $move_ok = FALSE;
            }
          }
          else {
            $result .= 'ERROR: ' . $to_square->coord() . ' is empty!';
            $move_ok = FALSE;
          }
        }
        elseif ($board->piece($to_square)->color() == $this->get_turn()) {
          $result .= 'ERROR: You cannot attack own chessman at ' . $to_square->coord() . '!';
          $move_ok = FALSE;
        }
        
        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handle_pawn_promotion($move, $board);
        
        if ($move_ok && !$pawn_promoted) {
          // Perform normal capture
          $board->move_piece($piece_square, $to_square);
        }
      }
    
      $en_passant_ok = FALSE;
      if ($en_passant_ok) {
        // Kill pawn
        if ($this->turn == 'w') {
          //         $board[$dest_pos - 8] = '';
        }
        else {
          //         $board[$dest_pos + 8] = '';
        }
      }
  
      // If OWN king is still in check, then invalid move
      if ($board->king_is_in_check($this->get_turn())) {
        $result .= 'ERROR: King is in check. ';
        $move_ok = FALSE;        
      }
   
      // If a pawn moved two squares this will allow 'en passant'
      // for next turn.
//       if ($this->get_turn() == 'w') {
// //           $this->set_w2spm($piece_pos % 8);
//       }
//       else {
// //           $this->set_b2spm($piece_pos % 8);
//       }
      
//         else {
//           // Clear 'en passant' of OUR last move
//           if ($this->get_turn() == 'w') {
//             $this->set_w2spm('x');
//           }
//           else {
//             $this->set_b2spm('x');
//           }
//         }

      $move->calculate_algebraic($this->get_turn(), $board_clone);
      
      // If move was executed update game state.
      if ($move_ok) {
        $result .= 'Your last move: ' . $move->algebraic();
  
        // Check checkmate/stalemate
        if ($board->king_is_in_check($opponent)) {
          // If this is check mate finish the game otherwise
          // add '+' to the move.
          if ($board->king_is_in_checkmate($opponent)) {
            if ($this->get_turn() == 'w') {
              $this->set_status(STATUS_WHITE_WIN);
            }
            else {
              $this->set_status(STATUS_BLACK_WIN);
            }
            $result .= '... CHECKMATE!';
          }
        }
        elseif ($board->is_stalemate($opponent, $this->get_w2spm(), $this->get_b2spm())) {
          $this->set_status(STATUS_DRAW);
          $result .= '... STALEMATE!';
        }
        else {
          // Game is not over yet
          // Set next player
          if ($this->get_turn() == 'b') {
            $this->set_turn_white();
          }
          else {
            $this->set_turn_black();
          }
        }    
      
        // Add comment to head of chatter. Right now we have only two
        // chatter items. Strip backslashes and replace newlines to get
        // a single line.
        if (empty($comment)) {
          $comment = '(silence)';
        }
        else {
          $comment = str_replace("\\", '', strip_tags($comment));
          $comment = str_replace("\n", '<br>', $comment);
        }
        $comment = '<u>' . $uid . '</u>: ' . $comment;
        //    $game['chatter'][1] = $game['chatter'][0];
        //    $game['chatter'][1] = "Hugh hard coding for now";
        //    $game['chatter'][0] = $comment;
      
        // Store changed board
        $this->set_board($board);
        
        $this->scoresheet->write_move_down($move);
      }
      else {
        $result .= 'ERROR: ' . $move->algebraic() . ' is not a legal move!  ';
      }
    }
    return $result;
  }
  
  /**
   * Handle pawn promotion
   */
  function handle_pawn_promotion(Move $move, Board $board) {
    $pawn_promoted = FALSE;
    
    $piece = $board->piece($move->from_square());
    if ($piece->type() == 'P') {
      if (($this->get_turn() == 'w' && $move->to_square()->rank() == 8) ||
          ($this->get_turn() == 'b' && $move->to_square()->rank() == 1)) {
        $promote_to = new Piece();
        $promote_to->set_type($move->promotion_piece_type());
        $promote_to->set_color($this->get_turn());
    
        $board->promotion($move->from_square(), $move->to_square(), $promote_to);
        
        $pawn_promoted = TRUE;
      }
    }
    
    return $pawn_promoted;
  }
  
  /**
   * Handle castling
   * 
   * @param $turn either 'w' or 'b'
   * @param $king_coord the coord of the king, e.g. "e1"
   * @param $castling_coords array of the coords of all the squares involved, from
   * left to right, e.g. array("e1", "f1", "g1", "h1"
   *       
   */
  function castle($turn, $king_from, $king_to, $rook_from, $rook_to, $gap_coords, $board) {
    $error = "";  
    
    if ($turn == 'w') {
      $opponent = 'b';
    }
    else {
      $opponent = 'w';
    }
  
    if (count($gap_coords) == 2) {
      if (!$this->may_castle_short($turn)) {
        $error = ERROR_CANNOT_CASTLE_SHORT;
      }
    }
    else { // count == 3
      if (!$this->may_castle_long($turn)) {
        $error = ERROR_CANNOT_CASTLE_LONG;
      }
    }
    
    if ($error == "") {
      foreach ($gap_coords as $gap_coord) {    
        if (!$board->square_at_coord_is_empty($gap_coord)) {
          $error = ERROR_CASTLING_SQUARES_BLOCKED;
        }
      }
    }
    
    if ($error == "") {      
      if ($board->king_is_in_check($turn)) {
        $error = ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING;
      }
    }
    // Check the squares between the king's current position and where he will move to are not attacked  
    if ($error == "") {
      foreach ($gap_coords as $gap_coord) {
        $square = new Square;
        $square->set_coord($gap_coord);
        if ($board->square_is_under_attack($opponent, $square)) {
          $error = ERROR_CANNOT_CASTLE_ACROSS_CHECK;
        }
      }
    }

    if ($error == "") {
      $board->move_piece(vchess_coord2square($king_from), vchess_coord2square($king_to));  // White King
      $board->move_piece(vchess_coord2square($rook_from), vchess_coord2square($rook_to));  // Rook
      
      $this->may_not_castle($turn);
      $this->set_last_move('Ke1-g1');
    }
    
    return $error;
  }
  
  /**
   * Set the fact that player may not castle
   * 
   * @param $turn 'w' or 'b'
   */
  function may_not_castle($turn) {
    if ($turn == 'w') {
      $this->white_may_not_castle();
    }
    else {
      $this->black_may_not_castle();
    }
  }
  
  /**
   * Save a game move
   *
   * @param int $gid Game id
   * @param int $move_no Number of move, e.g. 3
   * @param $turn Whose turn it is, either "w" or "b"
   * @param $move e.g. "Nb1-c3" or "Bc1xNf4"
   */
  function save_move($move_no, $turn, Move $move) {
    db_insert('vchess_moves')
    ->fields(array(
        'gid' => $this->gid,
        'move_no' => $move_no,
        'color' => $turn,
        'long_move' => $move->long_format(),
        'algebraic' => $move->algebraic())
        )
        ->execute();
  }
  
  /**
   * Save an open game and update last move time stamp to current time. Chatter
   * must have been updated already (newest is first in list).
   */
  function save() {
    $timestamps = REQUEST_TIME;
  
    $sql = "SELECT gid FROM {vchess_games} WHERE gid = '" . $this->gid . "'";
    // Converted to the D7 database API syntax.
    $result = db_query($sql);
    $exists = $result->fetchField();
  
    if ($exists) {
      // Converted to the D7 database API syntax.
      db_update('vchess_games')->fields(array(
          'turn' => $this->get_turn(),
          'status' => $this->get_status(),
          'white_uid' => $this->get_white_uid(),
          'black_uid' => $this->get_black_uid(),
          'board' => $this->get_board()->position(),
          'castling_availability' => $this->castling_availability(),
      ))
      ->condition('gid', $this->gid)
      ->execute();
    }
  }
  
  
  /**
   * Abort an open game. This is only possible if your opponent did not move
   * at all yet or did not move for more than four weeks. Aborting a game will
   * have NO influence on the game statistics. Return a status message.
   */
  function abort($uid) {
    //	global $res_games;
  
    $gamefolder =  variable_get('vchess_game_files_folder', 'vchess-data');
    $res_games = $gamefolder;
  
    //	ioLock();
  
    $game = new Game($gid);
    if ($game == NULL) {
      return 'ERROR: Game "' . $gid . '" does not exist!';
    }
    if (!$game['p_mayabort']) {
      return 'ERROR: You cannot abort the game!';
    }
    unlink("$res_games/$gid");
  
    //	ioUnlock();
  
    return 'Game "' . $gid . '" deleted.';
  }
  
  /**
   * Undo last move (only possible if game is not over yet).
   */
  function handle_undo($uid) {
//     $game = new Game($gid);
//     if ($game == NULL) {
//       return 'ERROR: Game "' . $gid . '" does not exist!';
//     }
//     if (!$game['p_mayundo']) {
//       return 'ERROR: Undo is not possible!';
//     }
  
//     $move = $this->game['lastmove'];
//     $fixhist = 1;
//     if ($move == 'DrawOffered' || $move == 'DrawRefused') {
//       $fixhist = 0;
//     }
//     elseif ($move == 'O-O') {
//       if ($this->game['curplyr'] == 'b') {
//         // white castled short
//         $this->game['board'][5] = '';
//         $this->game['board'][6] = '';
//         $this->game['board'][4] = 'wK';
//         $this->game['board'][7] = 'wR';
//         $this->game['white_may_castle_short'] = 1;
//       }
//       else {
//         // black castled short
//         $this->game['board'][61] = '';
//         $this->game['board'][62] = '';
//         $this->game['board'][60] = 'bK';
//         $this->game['board'][63] = 'bR';
//         $this->game['black_may_castle_short'] = 1;
//       }
//     }
//     elseif ($move == 'O-O-O') {
//       if ($this->game['curplyr'] == 'b') {
//         // white castled long
//         $this->game['board'][2] = '';
//         $this->game['board'][3] = '';
//         $this->game['board'][4] = 'wK';
//         $this->game['board'][0] = 'wR';
//         $this->game['white_may_castle_long'] = 1;
//       }
//       else {
//         // black castled long
//         $this->game['board'][58] = '';
//         $this->game['board'][59] = '';
//         $this->game['board'][60] = 'bK';
//         $this->game['board'][56] = 'bR';
//         $this->game['black_may_castle_long'] = 1;
//       }
//     }
//     else {
//       // Undo normal move (includes en passant)
// //       $src = convert2i($move[1] . $move[2]);
// //       $dest = convert2i($move[4] . $move[5]);
//       if ($src >= 0 && $src < 64 && $dest >= 0 && $dest < 64) {
//         if ($this->game['curplyr'] == 'w') {
//           $oldclr = 'b';
//         }
//         else {
//           $oldclr = 'w';
//         }
//         $this->game['board'][$src] = $oldclr . $move[0];
//         $this->game['board'][$dest] = '';
//         if ($this->game['lastkill'] != 'x') {
//           $kill = $this->game['lastkill'];
//           $dest = $kill[2] . $kill[3];
//           $this->game['board'][$dest] = $kill[0] . $kill[1];
//         }
//         // Adjust castling flags
//         if ($move[0] == 'R' || $move[0] == 'K') {
//           if ($this->game['curplyr'] == 'b') {
//             // white castling info
//             $this->game['white_may_castle_short'] = $this->game['old_castle_short'];
//             $this->game['white_may_castle_long'] = $this->game['old_castle_long'];
//           }
//           else {
//             // black castling info
//             $this->game['black_may_castle_short'] = $this->game['old_castle_short'];
//             $this->game['black_may_castle_long'] = $this->game['old_castle_long'];
//           }
//         }
//       }
//     }
  
//     // Fix chatter. Fill in ... for lost chatter.
//     $this->game['chatter'][0] = $this->game['chatter'][1];
//     $this->game['chatter'][1] = '<u>' . $uid . '</u>: ...';
//     // Correct game state
//     if ($move == 'DrawRefused') {
//       $this->game['status'] = 'D';
//     }
//     else {
//       $this->game['status'] = '?';
//     }
  
//     // Clear own en passant flag
//     if ($this->game['curplyr'] == 'b') {
//       $this->game['w2spm'] = 'x';
//     }
//     else {
//       $this->game['b2spm'] = 'x';
//     }
  
//     // Modify history
//     if ($fixhist) {
//       // Adjust turn counter if nescessary
//       if ($this->game['curplyr'] == 'b') {
//         $this->game['curmove'] = $this->game['curmove'] -1;
//       }
//       // Delete last move
//       $square = count($this->game['mhistory']) -1;
//       unset($this->game['mhistory'][$square]);
//     }
//     // Switch players
//     if ($this->game['curplyr'] == 'b') {
//       $this->game['curplyr'] = 'w';
//     }
//     else {
//       $this->game['curplyr'] = 'b';
//     }
  
//     // Clear last move
//     $this->game['lastmove'] = 'x';
//     $this->game['lastkill'] = 'x';
//     $this->game['old_castle_short'] = 'x';
//     $this->game['old_castle_long'] = 'x';
  
//     // Save game
//     $this->save();
  
//     return 'Move "' . $move . '" undone!';
   }
  
}

/**
 * HACK: this function checks whether en-passant is possible
 */
function vchess_en_passant_is_ok($player, $pos, $dest, $opp_ep_flag) {
  if ($opp_ep_flag != 'x') {
    if ($dest % 8 == $opp_ep_flag) {
      // if (pawn_attacks($pos,$dest)) right now
      // this is not required as we only use this
      // function in isStaleMate which uses correct dests
      if (($player == 'w' && floor($dest / 8) == 5)
          || ($player == 'b' && floor($dest / 8) == 2)) {
        return 1;
      }
    }
  }
  return 0;
}



