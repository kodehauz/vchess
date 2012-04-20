<?php

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
 * @param $uid 
 *   User id of player whose stats you want to load
 */
function gamer_load_user_stats($uid) {

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
function gamer_save_user_stats($uid, $stats) {
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