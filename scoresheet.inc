<?php

class Scoresheet {
  protected $scoresheet = array();
	
  /**
   * Create a new scoresheet
   * 
   * @param unknown_type $gid Game id
   */
  function __construct() {
	  
  }
	
  /**
   * Load the moves for a scoresheet
   */
  function load($gid) {
	$sql = "SELECT move_no, color, long_move, algebraic, timestamp FROM {vchess_moves} WHERE gid = :gid";
	$result = db_query($sql, array('gid' => $gid));
		
	foreach ($result as $row) {
	  $this->scoresheet[$row->move_no][$row->color] = $row->algebraic;
	}
  }
	
  /**
   * Get the move number
   */
  function get_move_no() {
    $count = count($this->scoresheet);
    // If the scoresheet is empty then we are on move 1
	if ($count == 0) {
	  $count = 1;
    }
	else {
// 	  if black has not yet moved, then the move number is the length of the array, 
// 	  	otherwise it is the length plus one
	  	
// 	  	e.g. if 3. Nc3
// 	  	then scoresheet has:
// 	  	$scoresheet[3]['w'] = "Nc3"
// 	  	and so => move number = 3
	  	
// 	  	e.g. if 3. Nc3 Nc6 
// 	  	then scoresheet has:
// 	  	$scoresheet[3]['w'] = "Nc3"
// 	  	$scoresheet[3]['b'] = "Nc6"
// 	  	and so => move number = 4
  	  if (array_key_exists("b", $this->scoresheet[count($this->scoresheet)])) {
        $count = $count + 1;
  	  }
    }
  
    return $count;
  }

  /**
   * Get the white move of a particular number
   * 
   * @param int $move_no
   */
  function get_white_move($move_no) {
    return $this->_get_move($move_no, "w");
  }
	
  /**
   * Get the black move of a particular number
   * 
   * @param int $move_no
   */
  function get_black_move($move_no) {
	return $this->_get_move($move_no, "b");
  }
	
  /**
   * Get the move of a given color
   * 
   * Outside this class, use instead one of:
   * - get_white_move()
   * - get_black_move()
   */
  function _get_move($move_no, $color) {
    $move = "";
	
    if (array_key_exists($move_no, $this->scoresheet)) {
      if (array_key_exists('w', $this->scoresheet[$move_no])) {
		$move = $this->scoresheet[$move_no][$color];
      }
    }
	  
	return $move;
  }
	
  /** 
   * Get the scoresheet as a table
   * 
   * @return
   *   Returns a themed table of moves.
   */
  function get_table() {
    $rows = array();
    foreach ($this->scoresheet as $move_no => $move) {
      if (array_key_exists('b', $move)) {
        $rows[] = array($move_no, $move['w'], $move['b']);        
      }
      else {
        $rows[] = array($move_no, $move['w'], "");
      }
    }
    $table['header'] = array('Move #', 'White', 'Black');
    $table['rows'] = $rows;
    $table['empty'] = "There are no moves played yet.";
    $table['attributes'] = array();  // I don't care, but I'm forced to define this
    $table['caption'] = array();   // I don't care about this either
    $table['colgroups'] = array();  // I just don't give a **** about this either
    $table['sticky'] = "";  // Nope, don't care
    
    return theme_table($table);
  }
}