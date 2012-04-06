<?php

/**
 * @file
 * This file has functions relating specifically to a board
 */

// Define as a FEN string the standard board starting position
// For FEN, we start with the black side of the board (a8-h8), and finish with the white pieces (a1-h1).
// For FEN, white pieces are stored in UPPER CASE, black pieces in lower case and blank squares as space.
// 
// e.g. after 1.e4 the FEN string will be:
// rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR
define("BOARD_DEFAULT", "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR");
define("BOARD_PROMOTION", "k7/4P3/8/8/8/8/8/K7");

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
 * 
 * The Board is only the static view of what a board looks like (i.e. what pieces are
 * where) and does not include any game information (such as whose move it is or 
 * whether or not a player may castle or not).  Such game information is stored in the
 * Game class.  This Board class only contains what you would see if you came to a 
 * room and saw a chessboard setup in a particular position; you would not know the
 * move history, nor whose move it was etc.
 * 
 * This class acts as the MODEL for the board.
 */
class Board {
  // The board is stored as an array where the key is the coord and the elements
  // are items of type Piece
  // e.g. array(
  //      "a1"->Piece /* white rook */, 
  //      "a2"->Piece /* white knight */,
  //      etc.) 
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
    $this->setup_position(BOARD_DEFAULT);
  }
  
  /**
   * Setup the board using a FEN (Forsyth–Edwards Notation) string, e.g.
   * rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR
   * 
   * See http://en.wikipedia.org/wiki/Forsyth-Edwards_Notation
   */
  function setup_position($fen_string) {
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
   * @param coord $coord e.g. "a1"
   */
  function set_piece($piece, $coord) {
    $this->board[$coord] = $piece;
  }
  
  /**
   * Get an array of squares for a given color and piece type.
   * e.g. array("a1", "h1") for white rooks
   * 
   * This function is used for things like checking that the short move 
   * is unique (e.g. Nbc3 rather than just Nc3).  
   */
  function piece_type_squares($search_type, $color) {
    $squares = array();
    
    foreach ($this->board as $piece_coord => $piece) {
      if ($piece->type() == $search_type && $piece->color() == $color) {
        $square = new Square;
        $square->set_coord($piece_coord);
        $squares[] = $square;
      }
    }
    
    return $squares;
  }
  
  /**
   * Get an array of squares for a given piece color
   * e.g. array("a1", "a2", ..., "h2") for white at the start of the game
   *
   * @param $color
   *   Player color whose pieces we would like an array of their locations
   */
  function pieces_squares($color) {
    $squares = array();
  
    foreach ($this->board as $piece_coord => $piece) {
      if ($piece->color() == $color) {
        $square = new Square;
        $square->set_coord($piece_coord);
        $squares[] = $square;
      }
    }
  
    return $squares;
  }
  
  
  /**
   * Get the square that the king is on
   */
  function king_square($color) {
    $squares = $this->piece_type_squares("K", $color);
    
    // There should be only 1 square returned 
    return $squares[0]; 
  }
	
  /**
   * Returns TRUE if the given square is empty
   * 
   * @param coord $coord coordinate, e.g. "a1" 
   */
  function square_is_empty(Square $square) {
    $empty = TRUE;
	if (array_key_exists($square->coord(), $this->board)) {
	  $empty = FALSE;
	}

	return $empty;
  }
  
  /**
   * Returns TRUE if the square at the given coord is empty
   */
  function square_at_coord_is_empty($coord) {
    $square = new Square;
    $square->set_coord($coord);
    
    return $this->square_is_empty($square);
  }

  /**
   * Get the player color whose piece is on a given square
   * 
   * @param $square e.g. "a1"
   *
   * @return 'w', 'b' or ''
   */
  function player_on_square(Square $square) {
	$player_color = "";
	if (!$this->square_is_empty($square)) {
	  $player_color = $this->piece($square)->color();
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
  function position() {
  	$FEN_string = "";
    
  	$coord = new Square;
   	for ($row = 8; $row >= 1; $row--) {
   	  $empty_squares = 0;
   	  for ($col = 1; $col <= 8; $col++) {
   	    $coord->set_coord(vchess_col2file($col) . $row);
  	    if ($this->square_is_empty($coord)) {
   	      $empty_squares++;
   	    }
   	    else {
   	      $piece = $this->piece($coord);
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
   * Check a number of squares given a start, an end square
   * (which is not included to the check) and a position
   * change for each iteration. Return TRUE if not blocked.
   * All values are given for 1dim board.
   *
   * @param 
   *   $start index of square, 0..63
   *   
   * @param 
   *   $end   index of square, 0..63
   * 
   * @param $change
   *   Number of index change
   */
  function path_is_not_blocked($start, $end, $change) {
    $blocked = FALSE;
  
    for ($pos = $start; $pos != $end; $pos += $change) {
      $square = vchess_i2square($pos);
      if (!$this->square_is_empty($square)) {
        $blocked = TRUE;
      }
    }
  
    return !$blocked;
  }
  
  /**
   * Check all pieces of player whether they attack the given position.
   *
   * @param string $attacker
   *   Color of player for whom attack is being tested
   *
   * @param $to_square
   *   The square which is being checked to see if it is under attack e.g. "d4"
   *
   */
  function square_is_under_attack($attacker, Square $to_square) {
    $under_attack = FALSE;
  
    $pieces_squares = $this->pieces_squares($attacker);
    foreach ($pieces_squares as $piece_square) {
      if ($this->piece_attacks($piece_square, $to_square)) {
        $under_attack = TRUE;
      }
    }
  
    return $under_attack;
  }
  
  /**
   * Check if given piece attacks the given square
   * 
   * @param $attack_square
   *   Square of piece trying to attack
   * 
   * @param $to_square
   *   Square which is being tested to see if it is attacked or not
   */
  function piece_attacks($attack_square, $to_square) {
    $attacks = FALSE;
    
    $piece = $this->piece($attack_square);
    if ($piece->type() == "P") {
      // For a pawn, we have to check whether it actually attacks
      $attacks = $this->pawn_attacks($attack_square, $to_square);
    }
    else {
      $attacks = $this->square_is_reachable($attack_square, $to_square); 
    }
    
    return $attacks;
  }
  
  /**
   * Check whether player's king is in check.
   */
  function king_is_in_check($player_color) {
    // First find out on which square is the given player's king
//     $found = FALSE;
//     $index = 0;
//     while (!$found && $index <= 63) {
//       $coord = vchess_i2square($index);
//       if ($this->piece($coord)->color() == $player_color
//           && $this->piece($coord)->type() == "K") {
//         $king_pos = $coord;
//         $found = TRUE;
//       }
//       else {
//         $index++;
//       }
//     }
    $king_square = $this->king_square($player_color);
  
    if ($player_color == 'w') {
      $in_check = $this->square_is_under_attack('b', $king_square);
    }
    else {
      $in_check = $this->square_is_under_attack('w', $king_square);
    }
  
    return $in_check;
  }
  
  /**
   * Move chessman from pos to dest, check whether king is under attack and
   * restore the old board settings. whether pos-> dest is a valid move is
   * NOT checked!
   */
  function move_is_ok($player, $pos, $dest) {
    //global $board;
  
    // DEBUG: echo '$player-$opp: $pos-> $dest: ';
    //  $pos_bc = vchess_i2square($pos);
    //   $old_pos = $board[$pos];
    //   $old_dest = $board[$dest];
    //   $board[$dest] = $board[$pos];
    //   $board[$pos] = '';
    if ($this->king_is_in_check($player)) {
      $move_is_ok = FALSE;
    }
    else {
      $move_is_ok = TRUE;
    }
    //   $board[$pos] = $old_pos;
    //   $board[$dest] = $old_dest;
  
    return $move_is_ok;
  }
  
  
    
  /**
   * Check whether there is no further move possible.  To do this, we look at each of
   * the opponent pieces and see if any of them have a move which they can make.
   */
  function is_stalemate($player, $w_ep, $b_ep) {
    return FALSE; // @todo Check this function works OK 
    
    if ($player == 'w') {
      $opp = 'b';
    }
    else {
      $opp = 'w';
    }
  
    // Look at each square to find each of the opponent pieces
    for ($square_index = 0; $square_index < 64; $square_index++) {
      $square = vchess_i2square($square_index);
      if (!$this->square_is_empty($square)) {
        if ($this->player_on_square($square) != $player) {
          // If the square doesn't have an opponent piece, then skip to the next square
          continue;
        }
        // Can the piece move theoretically thus is there
        // at least one tile free for one piece?
        //      switch ($board[$square_index][1]) {
        switch ($this->piece($square)->type()) {
          case 'K':
            $adj_squares = $this->get_adjacent_squares($square);
//             foreach ($adj_squares as $pos) {
            foreach ($adj_squares as $square) {
              if ($this->player_on_square($square) == $player) {
                continue;
              }
              if ($this->square_is_under_attack($opp, $pos)) {
                continue;
              }
              // Special case: if square is not actively under
              // attack it may still be blocked due to close
              // opponent king
              $king_too_close = FALSE;
              $adj_squares2 = $this->get_adjacent_squares($square);
              foreach ($adj_squares2 as $coord2) {
                if ($board[$coord2] == $opp . 'K') {
                  $king_too_close = TRUE;
                  break;
                }
              }
              if ($king_too_close) {
                continue;
              }
              return FALSE;
            }
            // DEBUG: echo 'King cannot escape alone!';
            break;
          case 'P':
            if ($player == 'w') {
              if ($board[$square_index + 8] == '' && $this->move_is_ok($player, $square_index, $square_index + 8)) {
                return FALSE;
              }
              if (($square_index % 8) > 0 && ($this->player_on_square($square_index + 7) == $opp || vchess_en_passant_is_ok('w', $square_index, $square + 7, $b_ep))) {
                if ($this->move_is_ok($player, $square_index, $square_index + 7)) {
                  return FALSE;
                }
              }
              if (($square_index % 8) < 7 && ($this->player_on_square($square_index + 9) == $opp || vchess_en_passant_is_ok('w', $square_index, $square_index + 9, $b_ep))) {
                if ($this->move_is_ok($player, $square_index, $square_index + 9)) {
                  return FALSE;
                }
              }
            }
            else {
              $square = vchess_i2square($square_index - 8);
              if ($this->square_is_empty($square) && $this->move_is_ok($player, $square_index, $square_index -8)) {
                return FALSE;
              }
              if (($square_index % 8) > 0 && ($this->player_on_square($square_index -9) == $opp || vchess_en_passant_is_ok('b', $square_index, $square_index -9, $w_ep))) {
                if ($this->move_is_ok($player, $square_index, $square_index -9)) {
                  return FALSE;
                }
              }
              if (($square_index % 8) < 7 && ($this->player_on_square($square_index -7) == $opp || vchess_en_passant_is_ok('b', $square_index, $square_index -7, $w_ep))) {
                if ($this->move_is_ok($player, $square_index, $square_index -7)) {
                  return FALSE;
                }
              }
            }
            break;
          case 'B':
            if ($square_index -9 >= 0   && $this->player_on_square($square_index -9) != $player &&
            $this->move_is_ok($player, $square_index, $square_index -9)) {
              return FALSE;
            }
            if ($square_index -7 >= 0   && $this->player_on_square($square_index -7) != $player &&
                $this->move_is_ok($player, $square_index, $square_index -7)) {
              return FALSE;
            }
            if ($square_index + 9 <= 63 && $this->player_on_square($square_index + 9) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 9)) {
              return FALSE;
            }
            if ($square_index + 7 <= 63 && $this->player_on_square($square_index + 7) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 7)) {
              return FALSE;
            }
            break;
          case 'R':
            $square_below = vchess_i2square($square_index - 8);
            if ($square_index - 8 >= 0 && $this->player_on_square($square_below) != $player
            && $this->move_is_ok($player, $square_index, $square_below)) {
              return FALSE;
            }
            if ($square_index -1 >= 0 && $this->player_on_square($square_index -1) != $player &&
                $this->move_is_ok($player, $square_index, $square_index -1)) {
              return FALSE;
            }
            if ($square_index + 8 <= 63 && $this->player_on_square($square_index + 8) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 8)) {
              return FALSE;
            }
            if ($square_index + 1 <= 63 && $this->player_on_square($square_index + 1) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 1)) {
              return FALSE;
            }
            break;
          case 'Q':
            $adj_squares = $this->get_adjacent_squares($square);
            foreach ($adj_squares as $pos) {
              $square = vchess_i2square($pos);
              //            if ($board[$pos][0] != $player) {
              if ($this->player_on_square($pos) != $player) {
                if ($this->move_is_ok($player, $square_index, $pos)) {
                  return FALSE;
                }
              }
            }
            break;
          case 'N':
            //          kpr($board);
            if ($square_index -17 >= 0   && $this->player_on_square($square_index -17) != $player &&
            $this->move_is_ok($player, $square_index, $square_index -17)) {
              return FALSE;
            }
            if ($square_index -15 >= 0   && $this->player_on_square($square_index -15) != $player &&
                $this->move_is_ok($player, $square_index, $square_index -15)) {
              return FALSE;
            }
            if ($square_index -6 >= 0   && $this->player_on_square($square_index -6) != $player &&
                $this->move_is_ok($player, $square_index, $square_index -6)) {
              return FALSE;
            }
            if ($square_index + 10 <= 63 && $this->player_on_square($square_index + 10) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 10)) {
              return FALSE;
            }
            if ($square_index + 17 <= 63 && $this->player_on_square($square_index + 17) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 17)) {
              return FALSE;
            }
            if ($square_index + 15 <= 63 && $this->player_on_square($square_index + 15) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 15)) {
              return FALSE;
            }
            if ($square_index + 6 <= 63 && $this->player_on_square($square_index + 6) != $player &&
                $this->move_is_ok($player, $square_index, $square_index + 6)) {
              return FALSE;
            }
            if ($square_index -10 >= 0   && $this->player_on_square($square_index -10) != $player &&
                $this->move_is_ok($player, $square_index, $square_index -10)) {
              return FALSE;
            }
            break;
        }
      }
    }
  
    return TRUE;
  }
  
  /**
   * Return the array of adjacent squares (<=8).  
   * 
   * @return 
   *   Returns an array of Squares.
   */
  function get_adjacent_squares(Square $square) {
    $adj_squares = array();
    
    $square_index = $square->convert2i();
    $i = 0;
    $x = $square_index % 8;
    $y = floor($square_index / 8);
  
    if ($x > 0 && $y > 0) {
      $adj_squares[$i++] = vchess_i2square($square_index - 9);
    }
    if ($y > 0) {
      $adj_squares[$i++] = vchess_i2square($square_index - 8);
    }
    if ($x < 7 && $y > 0) {
      $adj_squares[$i++] = vchess_i2square($square_index - 7);
    }
    if ($x < 7) {
      $adj_squares[$i++] = vchess_i2square($square_index + 1);
    }
    if ($x < 7 && $y < 7) {
      $adj_squares[$i++] = vchess_i2square($square_index + 9);
    }
    if ($y < 7) {
      $adj_squares[$i++] = vchess_i2square($square_index + 8);
    }
    if ($x > 0 && $y < 7) {
      $adj_squares[$i++] = vchess_i2square($square_index + 7);
    }
    if ($x > 0) {
      $adj_squares[$i++] = vchess_i2square($square_index - 1);
    }
  
    return $adj_squares;
  }
  
  
  /**
   * Check whether player's king is in checkmate
   * 
   * @param $player
   *   Player, either 'w' or 'b'
   */
  function is_checkmate($player) {
    if ($player == 'w') {
      $opp = 'b';
    }
    else {
      $opp = 'w';
    }
  
    // Find the position of the player's king
    $king_square = $this->king_square($player);
  
    // Test adjacent squares while king is temporarily removed
    $adj_squares = $this->get_adjacent_squares($king_square);
    //  $contents = $board[$king_pos];
    //  $board[$king_pos] = '';
    foreach ($adj_squares as $adj_square) {
      // If this adjacent square has a piece of the same color, then
      // we cannot move the king there
      if ($this->piece($adj_square)->color() == $player) {
        continue;
      }
      // If this adjacent square is under attack, then we cannot
      // move the king there
      if ($this->square_is_under_attack($opp, $adj_square)) {
        continue;
      }
      //    $board[$king_pos] = $contents;
      // Since this square is neither occupied by one of our own pieces
      // or is under attack then we can move the king there and so it
      // isn't checkmate
      return FALSE;
    }
  
    // Get all pieces that attack the king
    $enemy_piece_squares = $this->pieces_squares($opp);
    foreach ($enemy_piece_squares as $enemy_piece_square) {
      $piece = $this->piece($enemy_piece_square);
      if ($this->piece_attacks($enemy_piece_square, $king_square)) {
        $attacker_squares[] = $enemy_piece_square;
      }
    }
  
    // If more than one there is no chance to escape
    if (count($attacker_squares) > 1) {
      return TRUE;
    }
  
    // There is only 1 attacker.  Check whether this attacker can be killed by own piece.
    for ($square_index = 0; $square_index < 64; $square_index++) {
      $square_coord = vchess_i2square($square_index);
      if ($this->piece($square_coord)->color() == $player) {
        $piece_type = $this->piece($square_coord)->type();
        if (($piece_type == 'P' && $this->pawn_attacks($square_coord, $attacker_coords[0]))
            || ($piece_type != 'P' && $piece_type != 'K' && $this->square_is_reachable($square_coord, $attacker_coords[0]))
            || ($piece_type == 'K' && $this->square_is_reachable($square_coord, $attacker_coords[0]) && !$this->square_is_under_attack($opp, $attacker_coords[0]))) {
          $can_kill_attacker = FALSE;
          if (!$this->square_is_under_attack($opp, $king_coord)) {
            $can_kill_attacker = TRUE;
          }
          if ($can_kill_attacker) {
            return FALSE;
          }
        }
      }
    }
  
    // Check whether own unit can block the way
  
    // If attacking unit is a knight there
    // is no way to block the path.
    $to_square = vchess_i2square($dest_pos);
    if ($this->piece($to_square)->type() == 'N') {
      return TRUE;
    }
  
    // If enemy is adjacent to king there is no
    // way to escape either
    $dest_x = $dest_pos % 8;
    $dest_y = floor($dest_pos / 8);
    if (abs($dest_x - $king_x) <= 1 && abs($dest_y - $king_y) <= 1) {
      return TRUE;
    }
  
    // Get the list of squares between king and attacking
    // unit that can be blocked to stop the attack
    //  $change = vchess_get_path_change($board[$dest_pos][1], $dest_pos, $king_pos);
    $to_square = vchess_i2square($dest_pos);
    $change = vchess_get_path_change($this->piece($to_square)->type(), $dest_pos, $king_pos);
    $path = vchess_get_path($dest_pos + $change, $king_pos, $change);
    foreach ($path as $pos) {
      for ($square_index = 0; $square_index < 64; $square_index++) {
        $square = vchess_i2square($square_index);
        $piece = $this->piece($square);
        $piece_type = $piece->type();
        if ($piece->color() == $player) {
          if (($piece_type == 'P' && $board->piece_may_move_to_square($square, $pos))
              || ($piece_type != 'P' && $piece_type != 'K' && $this->square_is_reachable($square_index, $pos))) {
            //           $board[$pos] = $board[$square_index];
            //           $old = $board[$square_index];
            //           $board[$square_index] = '';
            $is_in_check = $this->king_is_in_check($player);
            //           $board[$square_index] = $old;
            //           $board[$pos] = '';
            if (!$is_in_check) {
              return FALSE;
            }
          }
        }
      }
    }
    return TRUE;
  }
   
  /**
   * Check whether $to_square is in reach for piece on the
   * $piece_square. It is not checked whether the square
   * itself is occupied but only the squares in between.
   * 
   * @param $piece_square
   *   Square on which piece starts from
   *   
   * @param $to_square
   *   Square where piece would like to go to if possible
   * 
   */
  function square_is_reachable(Square $piece_square, Square $to_square) {
    $reachable = FALSE;
  
    $piece_type = $this->piece($piece_square)->type();
    if ($piece_square != $to_square) {
      $piece_pos = $piece_square->convert2i();
      $dest_pos = $to_square->convert2i();
  
      $piece_y = floor($piece_pos / 8) + 1;
      $piece_x = $piece_pos % 8;
      $dest_y = floor($dest_pos / 8) + 1;
      $dest_x = $dest_pos % 8;
  
      switch ($piece_type) {
        // Pawn
        case 'P':
          // For a pawn we need to take into account the colour since a pawn is the one
          // piece which cannot go backwards
          $piece_color = $this->piece($piece_square)->color();
          if ($piece_color == "w") {
            if (($dest_y - $piece_y) == 1) { // Normal 1-square move
              $reachable = TRUE;
            }
            elseif ($piece_y == 2 && (($dest_y - $piece_y) == 2)) { // Initial 2-square move
              $reachable = TRUE;
            }
          }
          else { // $piece_color == "b"
            if (($dest_y - $piece_y) == -1) {
              $reachable = TRUE;
            }
            else {
              if ($piece_y == 7 && (($dest_y - $piece_y) == -2)) { // Initial 2-square move
                $reachable = TRUE;
              }
            }
          }
          break;
        // Knight
        case 'N':
          if (abs($piece_x - $dest_x) == 1 && abs($piece_y - $dest_y) == 2) {
            $reachable = TRUE;
          }
          if (abs($piece_y - $dest_y) == 1 && abs($piece_x - $dest_x) == 2) {
            $reachable = TRUE;
          }
          break;
        // Bishop
        case 'B':
          if (abs($piece_x - $dest_x) != abs($piece_y - $dest_y)) {
            break;
          }
          if ($dest_y < $piece_y) {
            $change = -8;
          }
          else {
            $change = 8;
          }
          if ($dest_x < $piece_x) {
            $change -= 1;
          }
          else {
            $change += 1;
          }
          if ($this->path_is_not_blocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // rook
        case 'R':
          if ($piece_x != $dest_x && $piece_y != $dest_y) {
            break;
          }
          if ($piece_x == $dest_x) {
            if ($dest_y < $piece_y) {
              $change = -8;
            }
            else {
              $change = 8;
            }
          }
          else {
            if ($dest_x < $piece_x) {
              $change = -1;
            }
            else {
              $change = 1;
            }
          }
          if ($this->path_is_not_blocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // queen
        case 'Q':
          if (abs($piece_x - $dest_x) != abs($piece_y - $dest_y) && $piece_x != $dest_x && $piece_y != $dest_y) {
            break;
          }
          // Check if diagonal
          if (abs($piece_x - $dest_x) == abs($piece_y - $dest_y)) {
            if ($dest_y < $piece_y) {
              // diagonal down the board
              $change = -8;
            }
            else {
              // diagonal up the board
              $change = 8;
            }
            if ($dest_x < $piece_x) {
              // diagonal to the left
              $change -= 1;
            }
            else {
              // diagonal to the right
              $change += 1;
            }
          }
          elseif ($piece_x == $dest_x) {
            // vertical
            if ($dest_y < $piece_y) {
              // vertical down the board
              $change = -8;
            }
            else {
              // vertical up the board
              $change = 8;
            }
          }
          else {
            // horizontal
            if ($dest_x < $piece_x) {
              // horizontal to the left
              $change = -1;
            }
            else {
              // horizontal to the right
              $change = 1;
            }
          }
          if ($this->path_is_not_blocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // king
        case 'K':
          if (abs($piece_x - $dest_x) > 1 || abs($piece_y - $dest_y) > 1) {
            break;
          }
          $kings = 0;
          $adj_squares = $this->get_adjacent_squares($piece_square);
          foreach ($adj_squares as $adj_square) {
            if ($this->piece($adj_square)->type() == 'K') {
              $kings++;
            }
          }
          if ($kings == 2) {
            break;
          }
          $reachable = TRUE;
          break;
      }
  
    }
  
    return $reachable;
  }
  
  /**
   * Get the piece on a given square
   *
   * @param $square e.g. "a1"
   */
    function piece(Square $square) {
      $piece = new Piece;
      if (array_key_exists($square->coord(), $this->board)) {
        $piece = $this->board[$square->coord()];
      }
  
      return $piece;
    }

  /**
   * Move a piece from one square to another
   * 
   * No checking is done here as to the validity of the move
   */
  function move_piece(Square $source_square, Square $to_square) {
    $this->board[$to_square->coord()] = $this->board[$source_square->coord()];
    unset($this->board[$source_square->coord()]);
  }
  
  /**
   * Promote a pawn.  This is effectively a move of a pawn with a change
   * of piece type.
   */
  function promotion(Square $from_square, Square $to_square, Piece $new_piece) {
    $this->move_piece($from_square, $to_square);
    
    $coord = $to_square->coord();
    $this->board[$coord]->set_type($new_piece->type());
  }
  
  /**
   * Check whether pawn at $pawn_square attacks the
   * square $to_square, i.e. the diagonally attacked square
   *
   * Note that it is not necessary for a piece to be on the
   * destination square for that square to be attacked
   *
   * @param $pawn_square 
   *   Square of pawn
   *   
   * @param $to_square 
   *   Square of attacked square
   *   
   * @see piece_attacks()
   */
  function pawn_attacks(Square $pawn_square, Square $to_square) {
    $attacks = FALSE;
  
    $piece_color = $this->piece($pawn_square)->color();
  
    // Convert coord like "d4" into col=4 rank=4
    $piece_col = vchess_file2col($pawn_square->get_file()); // e.g. d -> 4
    $piece_rank = $pawn_square->rank();
  
    $dest_col = vchess_file2col($to_square->get_file());  // e.g. e -> 5
    $dest_rank = $to_square->rank();
  
    if ($piece_color == 'w') {
      if ($dest_rank == $piece_rank + 1
          && ($piece_col == ($dest_col - 1) || $piece_col == ($dest_col + 1))){
        $attacks = TRUE;
      }
    }
    elseif ($piece_color == 'b') {
      if ($dest_rank == $piece_rank - 1
          && ($piece_col == ($dest_col - 1) || $piece_col == ($dest_col + 1))) {
        $attacks = TRUE;
      }
    }
  
    return $attacks;
  }
  
  /**
   * Check whether a given piece may legally move to the given square
   * 
   * @param $piece_square
   * 
   * @param $to_square
   * 
   */
  function piece_may_move_to_square($piece_square, $to_square) {
    $move_ok = FALSE;
    
    $piece = $this->piece($piece_square);
    switch ($piece->type()) {
      case 'P':
        $move_ok = $this->_pawn_may_move_to_square($piece_square, $to_square);
        break;
      case 'N':
      case 'B':
      case 'Q':
      case 'K':
        $move_ok = $this->_piece_may_move_to_square($piece_square, $to_square);
        break;
      default:
        break;
    }
    
    return $move_ok;
  }
  
  /**
   * Check whether pawn at $piece_square may move to $to_square.
   * First move may be two squares instead of just one.
   *
   * @return
   *   TRUE if the pawn may move to the given square
   *   FALSE if the destination square is occupied or if a square
   *     on the way is occupied for the first 2-square move
   */
  function _pawn_may_move_to_square($piece_square, $to_square) {
    $move_ok = FALSE;
  
    if ($this->square_is_empty($to_square)) {
      $piece = $this->piece($piece_square);
      $piece_file = $piece_square->get_file(); // e.g. e
      $piece_rank = $piece_square->rank(); // e.g. 2
      $dest_file = $to_square->get_file();  // e.g. e
      $dest_rank = $to_square->rank();  // e.g. 4
  
      // Check pawn stays on same file.
      // Captures are checked in pawn_attacks()
      if ($piece_file <> $dest_file) {
        $move_ok = FALSE;
      }
      elseif ($piece->color() == 'w') {
        // white pawn
        if ($piece_rank == 2 && $dest_rank == 4) {
          // Pawn moving 2 squares, so check if intermediate square is empty
          $intermediate_coord = new Square;
          $intermediate_coord->set_coord($piece_file . "3");
          if ($this->square_is_empty($intermediate_coord)) {
            $move_ok = TRUE;
          }
        }
        elseif ($dest_rank == ($piece_rank + 1)) {
          $move_ok = TRUE;
        }
      }
      else {
        // black pawn
        if ($piece_rank == 7 && $dest_rank == 5) {
          // Pawn moving 2 squares, so check if intermediate square is empty
          $intermediate_coord = new Square;
          $intermediate_coord->set_coord($piece_file . "6");
          if ($this->square_is_empty($intermediate_coord)) {
            $move_ok = TRUE;
          }
        }
        elseif ($dest_rank == ($piece_rank - 1)) {
          $move_ok = TRUE;
        }
      }
    }
  
    return $move_ok;
  }
  
  /**
   * Check whether piece at $piece_square may move to $to_square.
   *
   * @return
   *   TRUE if the knight may move to the given square
   *   FALSE if the knight may not legally move to the given square
   */
  function _piece_may_move_to_square($piece_square, $to_square) {
    $move_ok = FALSE;
  
    $color = $this->piece($piece_square)->color();
    if ($this->square_is_empty($to_square) && $this->square_is_reachable($piece_square, $to_square)) {
      // The only thing which would stop it would be if moving the piece
      // would expose the player to a discovered check.  To test this,
      // make the move and see if they are in check.
      $new_board = new Board();
      $new_board->setup_position($this->position());
      $new_board->move_piece($piece_square, $to_square);           
      if (!$new_board->king_is_in_check($color)) {
        $move_ok = TRUE;        
      }
    }
  
    return $move_ok;
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
 * Convert index [0..63] to square [a1..h8]
 */
function vchess_i2square($index) {
  $square = new Square();
  
  if ($index < 0 || $index > 63) {
	$square->set_coord('');
  }
  else {
    $y = floor($index / 8) + 1;
    $x = chr(($index % 8) + 97);
    $square->set_coord($x . $y);
  }
  
  return $square;
}

/**
 * Convert a coord [a1..h8] into a square
 */
function vchess_coord2square($coord) {
  $square = new Square;
  
  $square->set_coord($coord);
  
  return $square;
}

/**
 * Convert a file (a..h) into a numerical column (1..8)
 */
function vchess_file2col($file) {
  // "a" = ascii 97
  $col = ord($file) - 96;
  
  return $col;
}

/**
 * Convert column (1..8) as number to a file (a..h) 
 */
function vchess_col2file($col) {
  // "a" = ascii 97
  $file = chr($col + 96);

  return $file;
}

/**
 * Get empty squares between start and end as 1dim array.
 * Whether the path is clear is not checked.
 */
function vchess_get_path($start, $end, $change) {
  $path = array();
  $i = 0;
  for ($pos = $start; $pos != $end; $pos += $change) {
    $path[$i++] = $pos;
  }
  return $path;
}


/**
 * Get the change value that must be added to create
 * the 1dim path for piece moving from piece_pos to
 * dest_pos. It is assumed that the move is valid!
 * No additional checks as in tileIsReachable are
 * performed. Rook,queen and bishop are the only
 * units that can have empty tiles in between.
 *
 * @param $piece_type Type of piece, "K", "Q", "R", "N", "B" or "P"
 * @param $piece_index Position index of piece [0..63]
 * @param $dest_index Destination position index [0..63]
 */
function vchess_get_path_change($piece_type, $piece_index, $dest_index) {
  $change = 0;
  $piece_y = floor($piece_index / 8);
  $piece_x = $piece_index % 8;
  $dest_y = floor($dest_index / 8);
  $dest_x = $dest_index % 8;
  switch ($piece_type) {
    // bishop
    case 'B':
      if ($dest_y < $piece_y) {
        $change = -8;
      }
      else {
        $change = 8;
      }
      if ($dest_x < $piece_x) {
        $change -= 1;
      }
      else {
        $change += 1;
      }
      break;
      // rook
    case 'R':
      if ($piece_x == $dest_x) {
        if ($dest_y < $piece_y) {
          $change = -8;
        }
        else {
          $change = 8;
        }
      }
      else {
        if ($dest_x < $piece_x) {
          $change = -1;
        }
        else {
          $change = 1;
        }
      }
      break;
      // queen
    case 'Q':
      if (abs($piece_x -$dest_x) == abs($piece_y -$dest_y)) {
        if ($dest_y < $piece_y) {
          $change = -8;
        }
        else {
          $change = 8;
        }
        if ($dest_x < $piece_x) {
          $change -= 1;
        }
        else {
          $change += 1;
        }
      }
      elseif ($piece_x == $dest_x) {
        if ($dest_y < $piece_y) {
          $change = -8;
        }
        else {
          $change = 8;
        }
      }
      else {
        if ($dest_x < $piece_x) {
          $change = -1;
        }
        else {
          $change = 1;
        }
      }
      break;
  }
  return $change;
}

