<?php

/**
 * @file
 * The player class defines attributes of a player such as a player name, rating and uid
 */

class Player {
  public $uid = 0;
  public $name = "";
  public $rating = 0;
  
  // wins: number of wins
  // draws: number of draws
  // losses: number of losses
  // rating: current ELO rating
  // rgames: number of rating change games (update every 5 games)
  // rchange: base value of next rating modification
  
  /**
   * Constructor
   * @param $uid user id of player
   */
  function Player($uid) {
    $this->uid = $uid;
    
    $user = user_load($uid);
    
    $this->name = $user->name;
    
    $this->load_stats($uid);
  }
  
  /**
   * Load stats of player.
   *
   * @param $uid
   *   User id of player whose stats you want to load
   */
  function load_stats($uid) {
  
    $sql = "SELECT current, won, drawn, lost, rating, played, rchange FROM {gamer_stats} WHERE uid = '" . $uid . "'";
  
    $result = db_query($sql);
    $stats = $result->fetchAssoc();
  
    if (!$stats) {
      $this->won = 0;
      $this->drawn = 0;
      $this->lost = 0;
      $this->rating = INITIAL_RATING;
      $this->played = 0;
      $this->rchange = 0;
      $this->current = 0;
    }
    else {
      $this->won = $stats['won'];
      $this->drawn = $stats['drawn'];
      $this->lost = $stats['lost'];
      $this->rating = $stats['rating'];
      $this->played = $stats['played'];
      $this->rchange = $stats['rchange'];
      $this->current = $stats['current'];
    }
  }
  
  
  /**
   * Get how many games this player has won
   * 
   * @return
   *   The number of games won
   */
  function won() {
    return $this->won;
  }
  
  /**
   * Get the user id of the player
   *
   * @return 
   *   The user id
   */
  function uid() {
    return $this->uid;
  }
  
  /**
   * Get how many games this player has lost
   *
   * @return
   *   The number of games lost
   */
  function lost() {
    return $this->lost;
  }
  
  /**
   * Get how many games this player has drawn
   *
   * @return
   *   The number of games drawn
   */
  function drawn() {
    return $this->drawn;
  }
  
  /**
   * Get the rating of this player 
   *
   * @return
   *   The player rating
   */
  function rating() {
    return $this->rating;
  }
  
  /**
   * Get the rating change of this player
   *
   * @return
   *   The player rating change
   */
  function rchange() {
    return $this->rchange;
  }
  
  /**
   * Get the number of current games of this player
   *
   * @return
   *   The number of current games
   */
  function current() {
    return $this->current;
  }
  
  /**
   * Save player stats to file: replace if entry exists or append if it is new.
   */
  function save_stats() {
    $sql = "SELECT uid FROM {gamer_stats} WHERE uid = '" . $this->uid . "'";
    // Converted statement to the D7 database API syntax.
    $result = db_query($sql);
    $out = $result->fetchAssoc();
    if (!$out) {
      db_insert('gamer_stats')
      ->fields(array(
          'uid' => $this->uid,
          'won' => $this->won,
          'drawn' => $this->drawn,
          'lost' => $this->lost,
          'rating' => $this->rating,
          'played' => $this->played,
          'rchange' => $this->rchange,
          'current' => $this->current))
          ->execute();
    }
    else {
      db_update('gamer_stats')
      ->fields(array(
          'won' => $this->won,
          'drawn' => $this->drawn,
          'lost' => $this->lost,
          'rating' => $this->rating,
          'played' => $this->played,
          'rchange' => $this->rchange,
          'current' => $this->current))
          ->condition('uid', $this->uid)
          ->execute();
  
    }
  }
  
  /**
   * Get a players name
   */
  function name() {
    return $this->name;
  }
  

  /**
   * Find out how many games this player has played
   */
  function played() {
    return $this->played;
  }
  
  /** 
   * Set the number of games won
   */
  function set_won($won) {
    $this->won = $won;
  }
  
  /**
   * Set the number of games lost
   */
  function set_lost($lost) {
    $this->lost = $lost;
  }
  
  /**
   * Set the number of games played
   */
  function set_played($played) {
    $this->played = $played;
  }
  
  /**
   * Set the rating change
   */
  function set_rchange($rchange) {
    $this->rchange = $rchange;
  }
  
  /**
   * Set the rating
   */
  function set_rating($rating) {
    $this->rating = $rating;
  }
  
  /**
   * Set the number of current (in progress) games
   */
  function set_current($current) {
    $this->current = $current;
  }
}


