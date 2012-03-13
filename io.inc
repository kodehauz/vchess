<?php

/*
 * @file
 * This file contains functions relating to database i/o
 */


/**
 * Load array of game infos according to the given filter criteria. NULL for a
 * criteria means any value. $location must be set either 'opengames' or
 * 'archive'.
 * Always returns an array but it may be empty if no matching games were found
 * and entries are sorted most recent first. 
 * 
 * @see http://drupal.org/node/1354
 */
function vchess_compareTimestamp($a, $b) {
  // Bof!  I dunno.  Let's return 0.  Hugh
  
//  if ($a['ts_last'] == $b['ts_last']) {
//    return 0;
//  }
//  else if ($a['ts_last'] < $b['ts_last']) {
//    return 1;
//  }
//  else {
//    return -1;
//  }
}

/**
 * Load a list of games for the given userid
 */
function vchess_io_load_users_games($uid) {
  $game_list = array();
  
//  $sql = "SELECT gid FROM {vchess_games} WHERE (white_uid = $uid OR black_uid = $uid) AND status = 'in progress'";
  $sql = "SELECT gid FROM {vchess_games} WHERE (white_uid = :uid OR black_uid = :uid) AND status = 'in progress'";
  $result = db_query($sql, array('uid' => $uid));

//  while ($data = db_fetch_object($result)) {  /* Hugh removed 9/1/2012 */
  foreach ($result as $data) {
    $gid = $data->gid;
    
    // Add a game to the list
    $game = new Game();
    $game->load_game($gid);
    $game_list[] = $game;
  }

  // NB: sort will destroy index key, therefore $game_list['gid'] is used
  // later instead.
  if (count($game_list) > 0) {
    usort($game_list, 'vchess_compareTimestamp');
  }
  return $game_list;
}

/**
/* Save an open game and update last move time stamp to current time. Chatter
 * must have been updated already (newest is first in list). 
 */
function vchess_io_save_game($gid, $game) {
  $timestamps = REQUEST_TIME;
  
  // Convert game array to string
//   $board_white = vchess_board_to_string($game->get_board(), 'w');
//   $board_black = vchess_board_to_string($game->get_board(), 'b');
  /**
  for ($i = 0; $i < 64; $i++) {
    if ($game['board'][$i] != '' && $game['board'][$i][0] == 'w') {
      $c = vchess_i2coord($i);
      $board_white .= $game['board'][$i][1] . $c . ' ';
    }
  }
  for ($i = 0; $i < 64; $i++) {
    if ($game['board'][$i] != '' && $game['board'][$i][0] == 'b') {
      $c = vchess_i2coord($i);
      $board_black .= $game['board'][$i][1] . $c . ' ';
    }
  }
  */

  $sql = "SELECT gid FROM {vchess_games} WHERE gid = '" . $gid . "'";
  // Converted to the D7 database API syntax.
  $result = db_query($sql);
  $out = $result->fetchField();

  if (!$out) {
    /**
      // Converted to the D7 database API syntax.
  $gid = db_insert('vchess_games')
    ->fields(array(
    'timestamps' => $timestamps,
    'white' => $game['white'],
    'black' => $game['black'],
    'state' => $state,
    'board_white' => $board_white,
    'board_black' => $board_black
    ))
    ->execute();
    **/
  }
  else {
    // Converted to the D7 database API syntax.
    db_update('vchess_games')->fields(array(
      'turn' => $game->get_turn(),
      'status' => $game->get_status(), 
      'white_uid' => $game->get_white_uid(),
      'black_uid' => $game->get_black_uid(),
      'board' => $game->get_board()->to_FEN_string()
    ))
    ->condition('gid', $gid)
    ->execute();
  }
}

/**
 * Abort an open game. This is only possible if your opponent did not move
 * at all yet or did not move for more than four weeks. Aborting a game will
 * have NO influence on the game statistics. Return a status message. 
 */
function vchess_ioAbortGame($gid, $uid) {
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
 * Save a game move
 * 
 * @param unknown_type $move
 */
function vchess_io_save_move($gid, $move_no, $turn, $move) {
  db_insert('vchess_moves')
	->fields(array(
	  'gid' => $gid,
	  'move_no' => $move_no,
	  'color' => $turn,
	  'move' => $move))
	->execute();
}


/**
 * Load stats of user. Always return array but values are all zero if not found.
 * 
 * wins: number of wins
 * draws: number of draws
 * losses: number of losses
 * rating: current ELO rating
 * rgames: number of rating change games (update every 5 games)
 * rchange: base value of next rating modification
 * 
 * @param $uid User id of player whose stats you want to load
 * 
 * @see http://drupal.org/node/1354
 */
function vchess_io_load_user_stats($uid) {

  $sql = "SELECT won, drawn, lost, rating, played, rchange FROM {vchess_stats} WHERE uid = '" . $uid . "'";
  // Converted to the D7 database API syntax.
  $result = db_query($sql);
  $stats = $result->fetchAssoc();   

  if (!$stats) {  
//    $rating = vchess_get_init_rating($stats['won'], $stats['drawn'], $stats['lost'], 1200);
//    $stats['rating'] = floor($rating);
//  }
//  else {
    $stats = array();
    $stats['won'] = 0;
    $stats['drawn'] = 0;
    $stats['lost'] = 0;
    $stats['rating'] = INITIAL_RATING;
    $stats['played'] = 0;
    $stats['rchange'] = 0;
  }
  
  $stats['name'] = user_load($uid)->name;
  
  return $stats;
}

/**
 * Save user stats to file: replace if entry exists or append if it is new.
 */
function vchess_io_save_user_stats($uid, $stats) {
  $sql = "SELECT uid FROM {vchess_stats} WHERE uid = '" . $uid . "'";
  // Converted statement to the D7 database API syntax.
  $result = db_query($sql);
  $out = $result->fetchAssoc();
  if (!$out) {
//    db_query('INSERT INTO {vchess_stats} (uid, wins, draws, losses, rating, rgames, rchange) ' . 
//      "VALUES ('%s', %d, %d, %d, %d, %d, %d)", $uid, $stats['wins'], $stats['draws'], $stats['losses'], $stats['rating'], $stats['rgames'], $stats['rchange']);
    // Converted to the D7 database API syntax.
    db_insert('vchess_stats')
    ->fields(array(
      'uid' => $uid, 
      'won' => $stats['won'], 
      'drawn' => $stats['drawn'], 
      'lost' => $stats['lost'],
      'rating' => $stats['rating'], 
      'played' => $stats['played'], 
      'rchange' => $stats['rchange']))
    ->execute();
  }
  else {
  	db_update('vchess_stats')
  	->fields(array(
  			'won' => $stats['won'],
  			'drawn' => $stats['drawn'],
  			'lost' => $stats['lost'],
  			'rating' => $stats['rating'],
  			'played' => $stats['played'],
  			'rchange' => $stats['rchange']))
  	->condition('uid', $uid)		
  	->execute();
  	
  }
}
