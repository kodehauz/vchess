<?php

/*
 * @file
 * 
 * This file contains functions related to individual pieces
 */

class Piece {
  public $type;  // R, N, B, Q, K or a blank 
  public $color; // w or b

  /**
   * Set the type, one of R, N, B, Q, K or a blank
   *  
   * @param string $type
   */
  function set_type($type) {
	$this->type = strtoupper($type);
  }
	
  /**
   * Get the type one of R, N, B, Q, K or a blank
   *  
   * @return string
   */
  function get_type() {
	return $this->type;
  }
	  
  /**
   * Get the piece color.  Color is w or b
   * 
   * @return string
   */
  function get_color() {
    return $this->color;
  }
  
  /**
   * Set the piece color.  Color is w or b
   */
  function set_color($color) {
	$this->color = $color;
  }
  
  function get_name() {
    return $this->_get_name_from_type($this->type);
  }
  
  /** 
   * Get the FEN (case-sensitive) type for the piece
   * i.e. white pieces are returned as upper case letters
   * and black pieces as lower case letters
   */
  function get_FEN_type() {
    $type = $this->get_type(); // by default already upper case
    if ($this->get_color() == 'b') {
      $type = strtolower($type);
    }
    
    return $type;
  }
  
  /**
   * Get full name of chessman from chessman identifier.
   */
  function _get_name_from_type($type) {
    $name = BLANK;
  
    $type = strtoupper($type);
    switch ($type) {
      case 'P':
        $name = 'Pawn';
        break;
      case 'R':
        $name = 'Rook';
        break;
      case 'N':
        $name = 'Knight';
        break;
      case 'B':
        $name = 'Bishop';
        break;
      case 'K':
        $name = 'King';
        break;
      case 'Q':
        $name = 'Queen';
        break;
    }
    return $name;
  }
}


