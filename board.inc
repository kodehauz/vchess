<?php

/**
 * @file
 * This file has functions relating specifically to a board
 */

// Define as a FEN string the standard board starting position
define("BOARD_DEFAULT", "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR");

// Define the column letters
define("COL_a", 1);
define("COL_b", 2);
define("COL_c", 3);
define("COL_d", 4);
define("COL_e", 5);
define("COL_f", 6);
define("COL_g", 7);
define("COL_h", 8);

/**
 * The Board is designed to take some of the complexity away from the $board array
 * by adding useful functions rather than having the rest of the program need to understand
 * and handle issues like whether a square is blank or not.
 */
class Board {
  protected $board = array();

  /**
   * Initialise the board as empty.  We choose later whether to fill with
   * the default board or not.
   */
  function __construct() {

  }

  /**
   * Setup with the standard position
   */
  function setup_as_standard() {
    $this->setup_with_FEN(BOARD_DEFAULT);
  }
  
  /**
   * Setup the board using a FEN (Forsyth–Edwards Notation) string, e.g.
   * rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR
   * 
   * See http://en.wikipedia.org/wiki/Forsyth-Edwards_Notation
   */
  function setup_with_FEN($fen_string) {
    $chars = str_split($fen_string, 1);
    
    // The FEN string starts from the black side
    $col = COL_a;
    $row = 8;
    foreach ($chars as $char) {
      if ($char == "/") {
        $col = COL_a;
        $row--;
      }
      elseif (is_numeric($char)) {
        $col += $char;
      }
      else {
        $piece = new Piece();
        if (strtoupper($char) == $char) {
           // White piece
          $piece->set_type($char);
          $piece->set_color("w");
        }
        else {
          // Black piece
          $piece->set_type($char);
          $piece->set_color("b"); 
        }
        
        $coord = vchess_col_row2coord($col, $row);
        $this->board[$coord] = $piece;
        $col++;
      }
    }
  }
  
  /**
   * Set the piece at a given coordinate
   * 
   * @param Piece $piece
   * @param coordinate $coord e.g. "a1"
   */
  function set_piece($piece, $coord) {
    $this->board[$coord] = $piece;
  }
	
  /**
   * Returns TRUE if the given square is empty
   * 
   * @param coordinate $coord coordinate, e.g. "a1" 
   */
  function square_is_empty($coord) {
    $empty = TRUE;
	if (array_key_exists($coord, $this->board)) {
	  $empty = FALSE;
	}

	return $empty;
  }

  /**
   * Get the player color whose piece is on a given square
   * 
   * @param coordinate $coord e.g. "a1"
   *
   * @return 'w', 'b' or ''
   */
  function player_on_square($coord) {
	$player_color = "";
	if (!$this->square_is_empty($coord)) {
	  $player_color = $this->get_piece($coord)->get_color();
	}

	return $player_color;
  }

  /**
   * Convert board in array format (for use in the program) into
   * FEN string (for saving in the database).
   * 
   * e.g. after 1.e4 the FEN string will be:
   * rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR
   *
   * The board is kept internally with one piece per square, starting with a1, a2, ..., b1, b2, ... h8.
   * For FEN, we start with the black side of the board (a8-h8), and finish with the white pieces (a1-h1).
   * For FEN, white pieces are stored in UPPER CASE, black pieces in lower case and blank squares as space.
   *
   */
  function to_FEN_string() {
  	$FEN_string = "";
    
   	for ($row = 8; $row >= 1; $row--) {
   	  $empty_squares = 0;
   	  for ($col = 1; $col <= 8; $col++) {
   	    $coord = vchess_col_row2coord($col, $row);
  	    if ($this->square_is_empty($coord)) {
   	      $empty_squares++;
   	    }
   	    else {
   	      $piece = $this->get_piece($coord);
   	      if ($empty_squares > 0) {
   	        $FEN_string .= $empty_squares;
   	        $empty_squares = 0;
   	      }
   	      $FEN_string .= $piece->get_FEN_type();
   	    }
   	  }
   	  // The row may end with empty squares
   	  if ($empty_squares > 0) {
   	    $FEN_string .= $empty_squares;
   	    $empty_squares = 0;
   	  }
   	  // All rows except the row 1 with a / 
   	  if ($row > 1) {
   	    $FEN_string .= "/";
   	  }   	    
    }
   
   	return $FEN_string;
  }
  
  
  /**
   * Get the piece on a given square
   *
   * @param Coordinate $coord e.g. "a1"
   */
    function get_piece($coord) {
      $piece = new Piece;
      if (array_key_exists($coord, $this->board)) {
        $piece = $this->board[$coord];
      }
  
      return $piece;
    }

  /**
   * Move a piece from one square to another
   * 
   * No checking is done here as to the validity of the move
   */
  function move_piece($source_coord, $dest_coord) {
    $this->board[$dest_coord] = $this->board[$source_coord];
    unset($this->board[$source_coord]);
  }
  
  /**
   * Convert $row (1..8), $col (1..8) to 1dim index [0..63]
   */
  function _xy2i($row, $col) {
    return ($row * 8) + $col;	
  }
}
  
/**
 * Convert $row (1..8), $col (1..8) to coordinate [a1..h8]
 */
function vchess_col_row2coord($col, $row) {
  $coord = "";
  switch ($col) {
    case 1:
      $coord = 'a';
      break;
    case 2:
      $coord = 'b';
      break;
    case 3:
      $coord = 'c';
      break;
    case 4:
      $coord = 'd';
      break;
    case 5:
      $coord = 'e';
      break;
    case 6:
      $coord = 'f';
      break;
    case 7:
      $coord = 'g';
      break;
    case 8:
      $coord = 'h';
      break;  
  }   
  
  $coord .= $row;
  
  return $coord;
}

 /**
 * Convert index [0..63] to coordinate [a1..h8]
 */
function vchess_i2coord($index) {
  if ($index < 0 || $index > 63) {
	$coord = '';
  }
  else {
    $y = floor($index / 8) + 1;
    $x = chr(($index % 8) + 97);
    $coord = $x . $y;
  }
  
  return $coord;
}

/**
 * Extract the rank (1..8) from a coordinate (a1..h8)
 */
function vchess_coord_rank($coord) {
  return substr($coord, 1, 1);
}

/**
 * Extract the file (a..h) from a coordinate (a1..h8)
 */
function vchess_coord_file($coord) {
  return substr($coord, 0, 1);
}

/**
 * Convert a file (a..h) into a numerical column (1..8)
 */
function vchess_file2col($file) {
  // a = ascii 97
  $col = ord($file) - 96;
  
  return $col;
}

/**
 * Convert coordinate [a1..h8] to 1dim index [0..63]
 */
function vchess_coord2i($coord) {
	$row = $coord[0];
	$col = $coord[1];
	switch ($row) {
		case 'a':
			$x = 0;
			break;
		case 'b':
			$x = 1;
			break;
		case 'c':
			$x = 2;
			break;
		case 'd':
			$x = 3;
			break;
		case 'e':
			$x = 4;
			break;
		case 'f':
			$x = 5;
			break;
		case 'g':
			$x = 6;
			break;
		case 'h':
			$x = 7;
			break;
		default:
			return 64; /* error code */
	}
	$y = $col - 1;
	if ($y < 0 || $y > 7 ) {
		return 64; /* error code */
	}
	$index = $y * 8 + $x;
	return $index;
}


