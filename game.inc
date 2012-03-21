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
  // wcs: white may castle short
  // wcl: white may castle long
  // bcs, bcl: dito for black
  // w2spm: 2-step pawn move of white (x or a-h)
  // b2spm: dito for black
  // lastmove: last move in full notation (e.g. Pd2-d4 or x)
  // lastkill: chessman captured in last move with board index (e.g. wP08 or x)
  // oscf: old short castling flag (only set by king/rook move)
  // olcf: dito for long castling
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
  	$this->game['wcs'] = 1;
  	$this->game['wcl'] = 1;
  	$this->game['bcs'] = 1;
  	$this->game['bcl'] = 1;
  	$this->game['w2spm'] = 'x';
  	$this->game['b2spm'] = 'x';
  	$this->game['lastmove'] = 'x';
  	$this->game['lastkill'] = 'x';
  	$this->game['oscf'] = 'x';
  	$this->game['olcf'] = 'x';
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
	$sql = "SELECT gid, turn, white_uid, black_uid, status, board FROM {vchess_games} WHERE gid = '" .
			$gid . "'";
	// Converted to the D7 database API syntax.
	$result = db_query($sql);
	$this->game = $result->fetchAssoc();
	
//	$white_player = user_load($this->game['white_uid']);
//	$black_player = user_load($this->game['black_uid']);
	
//	$this->game['white'] = $white_player->name;
//	$this->game['black'] = $black_player->name;
	
	// Fill chess board
	$board = new Board;
	$board->setup_with_FEN($this->game['board']);
	$this->game['board'] = $board; 
	
// 	$wcm = explode(' ', trim($this->game['board']));
// 	foreach ($wcm as $cm) {
//       $this->game['board'][vchess_coord2i($cm[1] . $cm[2])] = 'w' . $cm[0];
// 	}
// 	$bcm = explode(' ', trim($this->game['board_black']));
//     foreach ($bcm as $cm) {
//       $this->game['board'][vchess_coord2i($cm[1] . $cm[2])] = 'b' . $cm[0];
//     }
    
    // w2spm: 2-step pawn move of white (x or a-h)
    // b2spm: dito for black
    $this->game['w2spm'] = 'x';
    $this->game['b2spm'] = 'x';
    
    $this->scoresheet->load($gid); 
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
  
}



