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
 * 
 * @param
 *   $uid the userid of the user whose games we want
 *   
 * @return
 *   An array of in progress games
 */
function vchess_load_users_in_progress_games($uid) {
  $game_list = array();
  
  $sql = "SELECT gid FROM {vchess_games} WHERE (white_uid = :uid OR black_uid = :uid) AND status = 'in progress'";
  $result = db_query($sql, array('uid' => $uid));

  foreach ($result as $data) {
    $gid = $data->gid;
    
    // Add a game to the list
    $game = new Game();
    $game->load($gid);
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
 * Load a list of games for the given userid
 *
 * @return
 *   An array of in progress games
 */
function vchess_load_all_in_progress_games() {
  $game_list = array();

  $sql = "SELECT gid FROM {vchess_games} WHERE status = 'in progress'";
  $result = db_query($sql);

  //  while ($data = db_fetch_object($result)) {  /* Hugh removed 9/1/2012 */
  foreach ($result as $data) {
    $gid = $data->gid;

    // Add a game to the list
    $game = new Game();
    $game->load($gid);
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

  $sql = "SELECT current, won, drawn, lost, rating, played, rchange FROM {vchess_stats} WHERE uid = '" . $uid . "'";

  $result = db_query($sql);
  $stats = $result->fetchAssoc();   

  if (!$stats) {  
    $stats = array();
    $stats['won'] = 0;
    $stats['drawn'] = 0;
    $stats['lost'] = 0;
    $stats['rating'] = INITIAL_RATING;
    $stats['played'] = 0;
    $stats['rchange'] = 0;
    $stats['current'] = 0;
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
      'rchange' => $stats['rchange'],
      'current' => $stats['current']))
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
      'rchange' => $stats['rchange'],
      'current' => $stats['current']))
    ->condition('uid', $uid)
    ->execute();
  
  }
}
