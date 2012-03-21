<?php

/**
 * @file
 * Functions concerning a game
 */

define('STATUS_WHITE_WIN', '1-0');
define('STATUS_BLACK_WIN', '0-1');
define('STATUS_IN_PROGRESS', 'in progress');
define('END_TYPE_STALEMATE', 'stalemate');
define('END_TYPE_CHECKMATE', 'checkmate');

class Game {
  
  protected $game = array();
  
  protected $scoresheet;
	
  // Static (loaded) entries:
  // archived: resides in archive
  // ts_start: timestamp of starting date (secs)
  // ts_last: timestamp of last move (secs)
  // white: name of white player
  // black: name of black player
  // curmove: number of current move (start at 0)
  // turn: color of whose turn it is to move (w or b)
  // curstate: state of game (w/b=white/black won,-=draw,D=draw offered,?=open)
  // white_may_castle_short: white may castle short
  // white_may_castle_long: white may castle long
  // black_may_castle_short, black_may_castle_long: dito for black
  // w2spm: white 2-step pawn move (x or a-h)
  // b2spm: black 2-step pawn move
  // lastmove: last move in full notation (e.g. Pd2-d4 or x)
  // lastkill: chessman captured in last move with board index (e.g. wP08 or x)
  // old_castle_short: old short castling flag (only set by king/rook move)
  // old_castle_long: dito for long castling
  // board: chess board array (0=a1,63=h8) with e.g. 'bP', 'wQ' or ''
  // mhistory: move history list (w1,b1,w2,b2,...)
  // chatter: list of chatter lines (first is newest)
  // Dynamic (based on user id) entries:
  // p_maymove: whether it's player's turn (always 0 if user is not playing)
  // p_mayundo: player may undo last move
  // p_mayabort: player may abort game (first move or opponent took too long)
  // p_mayarchive: player may move game to archive
  // p_color: player color (w=white,b=black or empty if not playing)
  // p_opponent: name of opponent (based on player color, empty if not playing)
  
  /**
   * Game constructor
   */
  function __construct() {
  	$this->scoresheet = new Scoresheet();
  }
  
  /**
   * Create a new game. If $bcomment is not empty it is black's initial comment.
   */
  function new_game($white, $black, $white_uid, $black_uid, $bcomment) {
  	/* Build new game context */
  	$this->game['ts_start'] = REQUEST_TIME;
  	$this->game['white'] = $white;
  	$this->game['black'] = $black;
  	$this->game['white_uid'] = $white_uid;
  	$this->game['black_uid'] = $black_uid;
  	$this->game['curstate'] = '?';
  	$this->game['white_may_castle_short'] = TRUE;  
  	$this->game['white_may_castle_long'] = TRUE;  
  	$this->game['black_may_castle_short'] = TRUE;
  	$this->game['black_may_castle_long'] = TRUE;
  	$this->game['w2spm'] = 'x';
  	$this->game['b2spm'] = 'x';
  	$this->game['lastmove'] = 'x';
  	$this->game['lastkill'] = 'x';
  	$this->game['old_castle_short'] = 'x';
  	$this->game['old_castle_long'] = 'x';
  	$board = new Board();
  	$board->setup_as_standard();
  	$this->game['board'] = $board;
  	
  	if (empty($bcomment)) {
  		$bcomment = '(silence)';
  	}
  	else {
  		$bcomment = str_replace("\\", '', strip_tags($bcomment));
  		$bcomment = str_replace("\n", '<br>', $bcomment);
  	}
  	$bcomment = '<u>' . $black . '</u>: ' . $bcomment;
  	$this->game['chatter'] = array($bcomment);
  
  	//  $gfname = sprintf('%s-%s-%s-', date('YmdHi', $this->game['ts_start']), $white, $black);
  	//  vchess_io_save_game($this->game, $gfname);
  
  	//  return $gfname;
//  	return $this->game;

  	// Converted to the D7 database API syntax.
  	$gid = db_insert('vchess_games')
  	->fields(array(
  			'turn' => 'w',
  			'white_uid' => $white_uid,
  			'black_uid' => $black_uid,
  			'board' => $this->game['board']->to_FEN_string())
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
  function load_game($gid) {
	$sql = "SELECT gid, turn, white_uid, black_uid, status, board, castling_availability, en_passant_target " .
	 " FROM {vchess_games} WHERE gid = '" .
			$gid . "'";
	// Converted to the D7 database API syntax.
	$result = db_query($sql);
	$this->game = $result->fetchAssoc();
	
	// Fill chess board
	$board = new Board;
	$board->setup_with_FEN($this->game['board']);
	$this->game['board'] = $board; 
	
	// Handle castling availability
	$this->initialise_castling($this->game['castling_availability']);	
    
    // w2spm: 2-step pawn move of white (x or a-h)
    // b2spm: dito for black
    $this->game['w2spm'] = 'x';
    $this->game['b2spm'] = 'x';
    
    $this->scoresheet->load($gid); 
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
    return new Player($this->game['white_uid']);
  }
  
  /**
   * Get the black player
   */
  function get_black_player() {
  	return new Player($this->game['black_uid']);
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
  function get_move_no() {
  	return $this->scoresheet->get_move_no();
  }
  
  /**
   * See if it's the given players move
   */
  function is_players_move($uid) {
  	if (($this->game['turn'] == 'w' && $this->game['white_uid'] == $uid) 
  	|| ($this->game['turn'] == 'b' && $this->game['black_uid'] == $uid)) {
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
  	return $this->game['gid'];
  }
  
  /**
   * Get the game board
   */
  function get_board() {
  	return $this->game['board'];
  }
  
  /**
   * Set the board
   */
  function set_board($board) {
  	$this->game['board'] = $board;
  }

  /**
   * Get the uid of the white player
   */
  function get_white_uid() {
    return $this->game['white_uid'];
  }
  
  /**
   * Get the uid of the black player
   */
  function get_black_uid() {
    return $this->game['black_uid'];
  }
  
  /**
   * Get the player whose turn it is, either 'w' or 'b'
   */
  function get_turn() {
  	return $this->game['turn'];
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
  	return $this->game['status'];
  }
  
  /**
   * Set the player whose turn it is to move to be 'w'
   */
  function set_turn_white() {
  	$this->game['turn'] = 'w';
  }
  
  /**
   * Set the player whose turn it is to move to be 'b'
   */
  function set_turn_black() {
  	$this->game['turn'] = 'b';
  }
  
  /**
   * Say whether the game is over or not
   */
  function is_game_over() {
  	$sql = "SELECT status FROM {vchess_games} WHERE gid = '" . $this->game['gid'] . "'";
  
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
  	if ($this->game['white_uid'] == $uid) {
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
  		$game_status = STATUS_BLACK_WIN;
  	}
  	else {
  		$game_status = STATUS_WHITE_WIN;
  	}
  	$this->save_status($game_status);
  
  	drupal_set_message(t('Thank you for resigning.'));
  }
  
  /**
   * Update the status of a game
   */
  function save_status($status) {
  	db_update('vchess_games')->fields(array(
  			'status' => $status,
  	))
  	->condition('gid', $this->game['gid'])
  	->execute();
  }
  
  /**
   * Find out what color a particular player is
   * 
   * In the case where a player is playing against themself (!), which we allow
   * at least for testing purposes, the color is the color of whoever's turn it 
   * is to move.
   */
  function get_player_color($uid) {
    if ($this->game['white_uid'] == $this->game['black_uid']) {
      $color = $this->get_turn();
    }
  	elseif ($this->game['white_uid'] == $uid) {
      $color = 'w';
  	}
  	else {
      $color = 'b';
  	}
  
  	return $color;
  }
  
  /**
   * Get curstate
   */
  function get_curstate() {
  	return $this->game['curstate'];
  }
  
  /**
   * Set curstate
   */
  function set_curstate($curstate) {
  	$this->game['curstate'] = $curstate;
  }
  
  /**
   * Get last move
   */
  function get_last_move() {
  	return $this->game['last_move'];
  }
  
  /**
   * Set last move
   */
  function set_last_move($last_move) {
  	$this->game['last_move'] = $last_move;
  }
  
  /**
   * Get last kill
   */
  function get_last_kill() {
  	return $this->game['last_kill'];
  }
  
  /** 
   * Set last kill
   */
  function set_last_kill($last_kill) {
  	$this->game['last_kill'] = $last_kill;
  }
  
  /**
   * Set w2spm
   */
  function set_w2spm($value) {
  	$this->game['w2spm'] = $value;
  }
  
  /**
   * Get w2spm
   */
 function get_w2spm() {
  	return $this->game['w2spm'];
  }
  
  /**
   * Set b2spm
   */
  function set_b2spm($value) {
  	$this->game['b2spm'] = $value;
  }
  
  /**
   * Get b2spm
   */
  function get_b2spm() {
  	return $this->game['b2spm'];
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
    return $this->game['black_may_castle_long'];
  }
  
  function black_may_castle_short() {
    return $this->game['black_may_castle_short'];
  }

  function white_may_castle_long() {
    return $this->game['white_may_castle_long'];
  }
  
  function white_may_castle_short() {
    return $this->game['white_may_castle_short'];
  }
  
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
}

/**
 * HACK: this function checks whether en-passant is possible
 */
function vchess_en_passant_is_ok($player, $pos, $dest, $opp_ep_flag) {
  if ($opp_ep_flag != 'x') {
    if ($dest % 8 == $opp_ep_flag) {
      // if (check_pawn_attacks($pos,$dest)) right now
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

/**
 * Verify move (full notation), execute it and modify game.
 *
 * @param $gid Game id
 * @param $uid User id of current player
 * @param $move Current move
 */
function vchess_handle_move($gid, $uid, $move, $comment) {
  //  global $acerror, $mail_from;

  $game = new Game();
  $game->load_game($gid);

  $mate_type = "Hugh was here setting a default";

  // w2spm = white 2 square piece move?
  // b2spm = black 2 square piece move?
  //  $game['w2spm'] = ""; // Hugh - added initialisation
  //  $game['b2spm'] = ""; // Hugh - added initialisation

  $board = $game->get_board();

  $move = trim($move);
  $result = 'undefined';
  $move_handled = FALSE;

  if (!$game->is_players_move($uid)) {
    return 'It is not your turn!';
  }
  $turn = $game->get_turn();
  if ($turn == 'w') {
    $cur_opp = 'b';
  }
  else {
    $cur_opp = 'w';
  }

  // if ($game['curstate'] != '?' && $game['curstate'] != 'D') {
  //    return 'Game is over.';
  //  }

  /* Old castling meaning:
   * 0 - rook or king moved
  * 1 - possible
  * 9 - performed */
  
  $may_castle_short = $game->may_castle_short($turn); 
  $may_castle_long = $game->may_castle_long($turn);  

  /* DEBUG echo 'HANDLE: w=$game['white'],b=$game['black'],c=$game['turn'],';
   echo 'm=$cur_move,may_castle=$may_castle_short,';
  echo '$may_castle_long  <BR>';*/

  // Allow two-step of king to indicate castling.
  if ($game->get_turn() == 'w' && $move == 'Ke1-g1') {
    $move = 'O-O';
  }
  elseif ($game->get_turn() == 'w' && $move == 'Ke1-c1') {
    $move = 'O-O-O';
  }
  elseif ($game->get_turn() == 'b' && $move == 'Ke8-g8') {
    $move = 'O-O';
  }
  elseif ($game->get_turn() == 'b' && $move == 'Ke8-c8') {
    $move = 'O-O-O';
  }

  // Accept "---" although it is now called resign.
  //  if ($move == '---' || $move == 'resign') {
  //    $move = 'resigned';
  //  }

  // Remember move for history
  $history_move = $move;

  // Clear last move
  //  $game['lastmove'] = 'x';
  //  $game['lastkill'] = 'x';
  //  $game['old_castle_short'] = 'x';
  //  $game['old_castle_long'] = 'x';

  // HANDLE MOVES:
  // resign                            resign
  // O-O                               short castling
  // O-O-O                             long castling
  // draw?                             offer a draw
  // accept_draw                       accept the draw
  // refuse_draw                       refuse the draw
  // [PRNBQK][a-h][1-8][-:x][a-h][1-8] unshortened move
  if ($move == 'draw?' && $game->get_curstate() == '?') {
    // Offer draw
    $game->set_curstate('D');
    $result = 'You have offered a draw.';
    $draw_handled = 1;
    $game['lastmove'] = 'DrawOffered';
  }
  elseif ($move == 'refuse_draw' && $game->get_curstate() == 'D') {
    // Refuse draw
    $game->set_curstate('?');
    $draw_handled = 1;
    $result = 'You refused the draw.';
    $game['lastmove'] = 'DrawRefused';
  }
  elseif ($move == 'accept_draw' && $game->get_curstate() == 'D') {
    // Accept draw
    $game->set_curstate('-');
    $draw_handled = 1;
    $result = 'You accepted the draw.';
    $game->set_last_move('DrawAccepted');
    if ($game['curplyr'] == 'b') {
      $game['curmove']++; // new move as white offered
    }
    $game['mhistory'][count($game['mhistory'])] = 'draw';
  }
  elseif ($move == 'O-O') {
    // Short castling - white
    if ($turn == 'w' && $board->square_is_empty("f1") && $board->square_is_empty("g1")) {
      if ($board->king_is_in_check('w')) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if ($board->square_is_under_attack('b', 'f1')) {
        return 'ERROR: You cannot castle across check!';
      }
    
      $board->move_piece("e1", "g1");  // White King
      $board->move_piece("h1", "f1");  // Rook
    
      $game->white_may_not_castle();
    }
    // Short castling - black
    if (!$game->may_castle_short($turn)) {
      return 'ERROR: You cannot castle short anymore!';
    }
    if ($turn == 'b' && $board->square_is_empty("f8") && $board->square_is_empty("g8")) {
      if ($board->king_is_in_check('b')) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if ($board->square_is_under_attack('w', 'f8')) {
        return 'ERROR: You cannot castle across check!';
      }
      
      $board->move_piece("e8", "g8"); // Black king
      $board->move_piece("h8", "f8"); // Black rook
      
      $game->black_may_not_castle();
    }
    $result = 'You castled short.';
    $move_handled = 1;
    $game->set_last_move('O-O');
  }
  elseif ($move == 'O-O-O') {
    // Long castling
    if (!$game->may_castle_long($turn)) {
      return 'ERROR: You cannot castle long anymore!';
    }
    if ($turn == 'b' 
    && $board->square_is_empty("b8") 
    && $board->square_is_empty("c8") 
    && $board->square_is_empty("d8")) {
      if ($board->king_is_in_check('b')) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if ($board->square_is_under_attack('w', 'c8')
      || $board->square_is_under_attack('w', 'd8')) {
        return 'ERROR: You cannot castle across check!';
      }

      $board->move_piece("e8", "c8");  // Black King
      $board->move_piece("a8", "d8");  // Rook
      
      $game->black_may_not_castle();
    }
    if ($turn == 'w' 
    && $board->square_is_empty("b1") 
    && $board->square_is_empty("c1") 
    && $board->square_is_empty("d1")) {
      if ($board->king_is_in_check('w')) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if ($board->square_is_under_attack('b', 'c1')
      || $board->square_is_under_attack('b', 'd1')    ) {
        return 'ERROR: You cannot castle across check!';
      }

    }
    if ($may_castle_long != 9) {
      return 'ERROR: Cannot castle long because the way is blocked!';
    }
    $result = 'You castled long.';
    $move_handled = 1;
    $game->set_last_move('O-O-O');
  }
  else {
    // Normal move: [PRNBQK][a-h][1-8][-:x][a-h][1-8][RNBQK]

    // Autocomplete short notation (or keep full)
    $acmove = vchess_short_to_long_move($game->get_turn(), $move, $board);
    if ($acmove == NULL) {
      return 'ERROR: autocomplete: ' . $acerror;
    }
    else {
      $move = $acmove;
    }

    // A final capital letter may only be N,B,R,Q for the
    // appropiate chessman.
    // FIXME it is not checked whether multiple promotion identifiers are
    // there. In that case last one is used and move executed properly
    // but the history browsing will be broken.
    $c = $move[strlen($move) -1];
    if ($c >= 'A' && $c <= 'Z' &&
        $c != 'N' && $c != 'B' && $c != 'R' && $c != 'Q') {
      return 'ERROR: only N (knight),B (bishop),R (rook) and Q (queen) are valid chessman';
    }

    // Validate piece and position.
    // Move is e.g. "Nb1-c3"
    $piece_type = $move[0];
    $piece = new Piece();
    $piece->set_type($piece_type);
    $piece->set_color($game->get_turn());
    //     $piece_name = vchess_get_piece_name($piece_type);
    //     if ($piece_name == BLANK) {
    //       return 'ERROR: Piece ' . $piece_type . ' is unknown!';
    //     }
    $piece_coord = $move[1] . $move[2];
    $piece_pos = vchess_coord2i($piece_coord);
    //     if ($piece_pos == 64) {
    //       return 'ERROR: ' . $piece_coord . ' is invalid!';
    //     }
    /* DEBUG  echo 'piece_type: $piece_type,piece_pos: $piece_pos<BR>'; */
    //     if ($board[$piece_pos] == '') {
    //       return 'ERROR: ' . $piece_coord . ' is empty.';
    //     }
    //     if ($board[$piece_pos][0] != $game->get_turn()) {
    //       return 'ERROR: Piece does not belong to you!';
    //     }
    //     if ($board[$piece_pos][1] != $piece_type) {
    //       return 'ERROR: Piece does not exist!';
    //     }

    // Get target square
    $move_obj = new Move();
    $move_obj->initialise($move);
    $dest_coord = $move_obj->dest_coord();
    if ($dest_coord == $piece_coord) {
      return 'ERROR: Current position and destination are equal!';
    }

    // Get action
    if ($move[3] == '-') {
      $action = MOVE; /* move */
    }
    elseif ($move[3] == 'x') {
      $action = CAPTURE; /* attack */
    }
    else {
      return 'ERROR: ' . $action . ' is unknown! Please use "-" for move and "x" for attack.';
    }
    // Replace - with x if this is meant to be en-passant.
    $dest_pos = vchess_coord2i($dest_coord);
    if ($piece_type == 'P' && (abs($piece_pos - $dest_pos) == 7 || abs($piece_pos - $dest_pos) == 9)) {
      $action = CAPTURE;
      $history_move = str_replace('-', 'x', $history_move);
      $move = str_replace('-', 'x', $move);
    }

    // Save for undo
    $game->set_last_move(str_replace('?', '', $move));

    // If attacking, an enemy unit must be present on square
    // and if move then square must be empty. In both cases
    // the king must not be in check after moving.

    // Check whether the move is along a valid path and
    // whether all squares in between are empty thus the path
    // is not blocked. The final destination square is not
    // checked here.
    $piece_name = $piece->get_name();
    $piece_coord = vchess_i2coord($piece_pos);
    $dest_coord = vchess_i2coord($dest_pos);
    if ($piece_type != 'P') {
      if (!$board->square_is_reachable($piece_type, $piece_coord, $dest_coord)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
    }
    else {
      if ($action == MOVE && !$board->pawn_may_move_to_square($piece_coord, $dest_coord)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal advancing move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
      elseif ($action == CAPTURE && !$board->check_pawn_attacks($piece_coord, $dest_coord)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal capturing move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
    }

    $en_passant_okay = FALSE;
    // Check action
    $coord = vchess_i2coord($dest_pos);
    if ($action == MOVE && !$board->square_is_empty($coord)) {
      return 'ERROR: ' . $dest_coord . ' is occupied!';
    }
    if ($action == CAPTURE && $board->square_is_empty($dest_coord)) {
      // En passant of pawn?
      if ($piece_type == 'P') {
        if ($game['turn'] == 'w') {
          if ($game['b2spm'] != 'x' &&
              $dest_pos % 8 == $game['b2spm'] &&
              floor($dest_pos / 8) == 5) {
            $en_passant_okay = 1;
          }
        }
        else {
          if ($game['w2spm'] != 'x' &&
              $dest_pos % 8 == $game['w2spm'] &&
              floor($dest_pos / 8) == 2) {
            $en_passant_okay = 1;
          }
        }
        if ($en_passant_okay == 0) {
          return 'ERROR: En-passant is not possible!';
        }
      }
      else {
        return 'ERROR: ' . $dest_coord . ' is empty!';
      }
    }
    if ($action == CAPTURE && $board->get_piece($dest_coord)->get_color() == $game->get_turn()) {
      return 'ERROR: You cannot attack own chessman at ' . $dest_coord . '!';
    }

    // Backup affected squares
    $piece_coord = vchess_i2coord($piece_pos);
    $dest_coord = vchess_i2coord($dest_pos);
    $old_piece_square = $board->get_piece($piece_coord);
    $old_dest_square = $board->get_piece($dest_coord);

    // Get short notation of move for history before
    // actually moving since this function relies on
    // autocomplete which in turn requires the initial board
    // to work properly (after move, first chessman is on
    // destination square already and second can't reach it
    // which would result in 'no one can reach' error)
    if (strlen($history_move) >= 6) {
      $history_move = vchess_long_to_short_move($game->get_turn(), $history_move, $board);
    }
    // DEBUG: echo 'Move: $move ($history_move)<BR>';

    // Perform move
    //    $board[$piece_pos] = '';
    $dest_coord = vchess_i2coord($dest_pos);
    if ($board->get_piece($dest_coord)->get_type() != BLANK) {
      //      $game->set_last_kill(sprintf('%s%s', $board->get_piece_by_index($dest_pos), $dest_pos));
      $game->set_last_kill($board->get_piece($dest_coord));
    }
    $board->move_piece($piece_coord, $dest_coord);
    if ($en_passant_okay) {
      // Kill pawn
      if ($game['turn'] == 'w') {
        //         $board[$dest_pos - 8] = '';
        //        $game->set_last_kill(sprintf('bP%s', $dest_pos -8));
        //         $game->set_last_kill(sprintf('bP%s', $dest_pos -8));
      }
      else {
        //         $board[$dest_pos + 8] = '';
        //         $game->set_last_kill(sprintf('wP%s', $dest_pos + 8));
      }
    }

    // If king is in check undo
    if ($board->king_is_in_check($game->get_turn())) {
      //       $board[$piece_pos] = $old_piece_square;
      //       $board[$dest_pos] = $old_dest_square;
      //       if ($en_passant_okay) {
      //         // Respawn en-passant pawn
      //         if ($game->get_turn() == 'w') {
      //           $board[$dest_pos -8] = 'bP';
      //         }
      //         else {
      //           $board[$dest_pos + 8] = 'wP';
      //         }
      //       }
      return 'ERROR: King is in check.';
    }

    // Check whether this forbids any castling
    if ($piece_type == 'K') {
      if ($may_castle_short) {
        $may_castle_short = FALSE;
      }
      if ($may_castle_long) {
        $may_castle_long = FALSE;
      }
    }
    if ($piece_type == 'R') {
      if ($may_castle_long && ($piece_pos % 8) == 0) {
        $may_castle_long = FALSE;
      }
      if ($may_castle_short && ($piece_pos % 8) == 7) {
        $may_castle_short = FALSE;
      }
    }

    // If a pawn moved two squares this will allow 'en passant'
    // for next turn.
    //
    if ($piece_type == 'P' && abs($piece_pos - $dest_pos) == 16) {
      if ($game->get_turn() == 'w') {
        $game->set_w2spm($piece_pos % 8);
      }
      else {
        $game->set_b2spm($piece_pos % 8);
      }
    }
    else {
      // Clear 'en passant' of OUR last move
      if ($game->get_turn() == 'w') {
        $game->set_w2spm('x');
      }
      else {
        $game->set_b2spm('x');
      }
    }

    $result = 'Your last move: ' . vchess_long_to_short_move($game->get_player_color($uid),
        str_replace('?', '', $move), $board);

    // If pawn reached last line promote it
    if ($piece_type == 'P') {
      if (($game->get_turn() == 'w' && $dest_pos >= 56) ||
          ($game->get_turn() == 'b' && $dest_pos <= 7)) {
        $pawn_upg = $move[strlen($move) -1];
        if ($pawn_upg == '?') {
          $pawn_upg = 'Q';
          $history_move = sprintf('%sQ', $history_move);
        }
        $board[$dest_pos] = $game->get_turn() . $pawn_upg;
        $result = sprintf('%s... promotion to %s!',
            $result, vchess_get_piece_name($pawn_upg));
      }
    }

    $move_handled = TRUE;
  }

  // If move was executed update game state.
  if ($move_handled) {
    // Check checkmate/stalemate
    if ($board->king_is_in_check($cur_opp)) {
      // If this is check mate finish the game otherwise
      // add '+' to the move.
      if ($board->is_checkmate($cur_opp)) {
        if ($game->get_turn() == 'w') {
          $game->set_curstate(STATUS_WHITE_WIN);
        }
        else {
          $game->set_curstate(STATUS_BLACK_WIN);
        }
        $mate_type = END_TYPE_CHECKMATE;
      }
      else {
        $result = $result . '... CHECK!';
      }
      $history_move = sprintf('%s+', $history_move);
    }
    elseif ($board->is_stalemate($cur_opp, $game->get_w2spm(), $game->get_b2spm())) {
      $game->set_curstate('-');
      $mate_type = END_TYPE_STALEMATE;
    }

    // Backup castling info if rook or king has moved
    //     if ($piece_type == 'R' || $piece_type == 'K') {
    //       if ($game->get_turn() == 'w') {
    //         // old_castle_short = old short castle flag??
    //         // old_castle_long = old long castle flag??
    //         // white_may_castle_short = white castle short
    //         // white_may_castle_long = white castle long
    //         $game['old_castle_short'] = $game['white_may_castle_short'];
    //         $game['old_castle_long'] = $game['white_may_castle_long'];
    //       }
    //       else {
    //         $game['old_castle_short'] = $game['black_may_castle_short'];
    //         $game['old_castle_long'] = $game['black_may_castle_long'];
    //       }
    //     }

    // Update move number and current player
    if ($game->get_turn() == 'w') {
      //      $game['curmove']++;
      //        $game->set_curmove(1234); // Hugh default
    }
    //    $game['mhistory'][count($game['mhistory'])] = $history_move;
    //    $game['mhistory'][42] = $history_move; // Hugh hardcoding 42!

    // If other player can't move anymore end the game.
    if ($mate_type == END_TYPE_CHECKMATE) {
      $mate_name = 'mate';
      $result = $result . '... CHECKMATE!';
    }
    elseif ($mate_type == END_TYPE_STALEMATE) {
      $mate_name = 'stalemate';
      $result = $result . '... STALEMATE!';
    }


    // $game['mhistory'][count($game['mhistory'])] = $mate_name;
  }

  // Save move
  vchess_io_save_move($gid, $game->get_move_no(), $game->get_turn(), $move);

  if ($move_handled || $draw_handled) {
    // If game is over update user stats (includes resignation).
    //    $game->set_curstate("?"); // Hugh default
    //     if ($game->get_curstate() != '?' && $game->get_curstate() != 'D') {
    //       vchess_update_stats($game->get_white_player(), $game->get_black_player(), $game->get_curstate());
    //      drupal_set_message('game is over');
    //     }

    // Set next player
    if ($game->get_turn() == 'b') {
      $game->set_turn_white();
    }
    else {
      $game->set_turn_black();
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
    $game->set_board($board);

    // Save game
    vchess_io_save_game($gid, $game);

    // Send a notification if email address was supplied.
    //    if ($cur_opp == 'b') {
    //       $oid = $game['black'];
    //     }
    //     else {
    //       $oid = $game['white'];
    //     }
    //	$email=ioLoadUserEmailAddress($oid);
    $email = FALSE; // Hugh - force following condition to be FALSE
    if ($email) {
      $prot = ($GLOBALS['_SERVER']['HTTPS'] == 'on') ? 'https' : 'http';
      $url = $prot . '://' . $GLOBALS['_SERVER']['HTTP_HOST'] . $GLOBALS['_SERVER']['SCRIPT_NAME'] . '?gid=' . $gid;
      $message = "Dear $oid\n\n$uid has just moved.\n\nMove:\n$move\n\nComment:\n$comment\n\nIt is your turn now!\n\nEnter the game:\n$url";
      mail($email, "[OCC] " . $game['white'] . "vs" . $game['black'] . ": $move",
          $message, 'From: ' . $mail_from);
    }

  }
  return $result;
}

/**
 * Undo last move (only possible if game is not over yet).
 */
function vchess_handle_undo($gid, $uid) {
  $game = new Game($gid);
  if ($game == NULL) {
    return 'ERROR: Game "' . $gid . '" does not exist!';
  }
  if (!$game['p_mayundo']) {
    return 'ERROR: Undo is not possible!';
  }

  $move = $game['lastmove'];
  $fixhist = 1;
  if ($move == 'DrawOffered' || $move == 'DrawRefused') {
    $fixhist = 0;
  }
  elseif ($move == 'O-O') {
    if ($game['curplyr'] == 'b') {
      // white castled short
      $game['board'][5] = '';
      $game['board'][6] = '';
      $game['board'][4] = 'wK';
      $game['board'][7] = 'wR';
      $game['white_may_castle_short'] = 1;
    }
    else {
      // black castled short
      $game['board'][61] = '';
      $game['board'][62] = '';
      $game['board'][60] = 'bK';
      $game['board'][63] = 'bR';
      $game['black_may_castle_short'] = 1;
    }
  }
  elseif ($move == 'O-O-O') {
    if ($game['curplyr'] == 'b') {
      // white castled long
      $game['board'][2] = '';
      $game['board'][3] = '';
      $game['board'][4] = 'wK';
      $game['board'][0] = 'wR';
      $game['white_may_castle_long'] = 1;
    }
    else {
      // black castled long
      $game['board'][58] = '';
      $game['board'][59] = '';
      $game['board'][60] = 'bK';
      $game['board'][56] = 'bR';
      $game['black_may_castle_long'] = 1;
    }
  }
  else {
    // Undo normal move (includes en passant)
    $src = vchess_coord2i($move[1] . $move[2]);
    $dest = vchess_coord2i($move[4] . $move[5]);
    if ($src >= 0 && $src < 64 && $dest >= 0 && $dest < 64) {
      if ($game['curplyr'] == 'w') {
        $oldclr = 'b';
      }
      else {
        $oldclr = 'w';
      }
      $game['board'][$src] = $oldclr . $move[0];
      $game['board'][$dest] = '';
      if ($game['lastkill'] != 'x') {
        $kill = $game['lastkill'];
        $dest = $kill[2] . $kill[3];
        $game['board'][$dest] = $kill[0] . $kill[1];
      }
      // Adjust castling flags
      if ($move[0] == 'R' || $move[0] == 'K') {
        if ($game['curplyr'] == 'b') {
          // white castling info
          $game['white_may_castle_short'] = $game['old_castle_short'];
          $game['white_may_castle_long'] = $game['old_castle_long'];
        }
        else {
          // black castling info
          $game['black_may_castle_short'] = $game['old_castle_short'];
          $game['black_may_castle_long'] = $game['old_castle_long'];
        }
      }
    }
  }

  // Fix chatter. Fill in ... for lost chatter.
  $game['chatter'][0] = $game['chatter'][1];
  $game['chatter'][1] = '<u>' . $uid . '</u>: ...';
  // Correct game state
  if ($move == 'DrawRefused') {
    $game['curstate'] = 'D';
  }
  else {
    $game['curstate'] = '?';
  }

  // Clear own en passant flag
  if ($game['curplyr'] == 'b') {
    $game['w2spm'] = 'x';
  }
  else {
    $game['b2spm'] = 'x';
  }

  // Modify history
  if ($fixhist) {
    // Adjust turn counter if nescessary
    if ($game['curplyr'] == 'b') {
      $game['curmove'] = $game['curmove'] -1;
    }
    // Delete last move
    $square = count($game['mhistory']) -1;
    unset($game['mhistory'][$square]);
  }
  // Switch players
  if ($game['curplyr'] == 'b') {
    $game['curplyr'] = 'w';
  }
  else {
    $game['curplyr'] = 'b';
  }

  // Clear last move
  $game['lastmove'] = 'x';
  $game['lastkill'] = 'x';
  $game['old_castle_short'] = 'x';
  $game['old_castle_long'] = 'x';

  // Save game
  vchess_io_save_game($gid, $game);

  return 'Move "' . $move . '" undone!';
}





