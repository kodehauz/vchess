<?php

/**
 * @file
 * The player class defines attributes of a player such as a player name, rating and uid
 */

class Player {
  public $uid = 0;
  public $name = "";
  public $rating = 0;
  
  /**
   * Constructor
   * @param $uid user id of player
   */
  function Player($uid) {
    $this->uid = $uid;
    
    $user = user_load($uid);
    
    $this->name = $user->name;
  }
  
  /**
   * Get a players name
   */
  function name() {
    return $this->name;
  }
  
  /**
   * Get a players rating
   */
  function get_rating() {
    return $this->rating;
  }
  
  /**
   * Find out how many games this player has played
   */
  function played() {
    $played = db_query("SELECT count(gid) FROM {vchess_games} WHERE (white_uid = :uid OR black_uid = :uid) AND status IN ('1-0', '0-1', 'draw')", 
      array('uid' => $uid))->fetchField();
    
    return $played;
  }
}