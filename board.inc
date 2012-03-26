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

// Define the types of move
define('MOVE', '-');
define('CAPTURE', 'x');


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
   * @param coord $coord e.g. "a1"
   */
  function set_piece($piece, $coord) {
    $this->board[$coord] = $piece;
  }
  
  /**
   * Get an array of coords for a given color and piece type.
   * e.g. array("a1", "h1") for white rooks
   * 
   * This function is used for things like checking that the short move 
   * is unique (e.g. Nbc3 rather than just Nc3).  
   */
  function get_pieces_coords($color, $search_type) {
    $pieces_coords = array();
    
    foreach ($this->board as $piece_coord => $piece) {
      if ($piece->get_type() == $search_type) {
        $pieces_coords[] = $piece_coord;
      }
    }
    
    return $pieces_coords;
  }
	
  /**
   * Returns TRUE if the given square is empty
   * 
   * @param coord $coord coordinate, e.g. "a1" 
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
   * @param coord $coord e.g. "a1"
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
   * Check a number of squares given a start, an end square
   * (which is not included to the check) and a position
   * change for each iteration. Return TRUE if not blocked.
   * All values are given for 1dim board.
   *
   * @param $start index of square, 0..63
   * @param $end   index of square, 0..63
   */
  function path_is_not_blocked($start, $end, $change) {
    $blocked = FALSE;
  
    for ($pos = $start; $pos != $end; $pos += $change) {
      $coord = vchess_i2coord($pos);
      if (!$this->square_is_empty($coord)) {
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
   * @param coord $dest_coord
   *   The coordinate which is being checked to see if it is under attack e.g. "d4"
   *
   * @param $board
   *
   */
  function square_is_under_attack($attacker, $dest_coord) {
    $under_attack = FALSE;
  
    for ($square = 0; $square < 64; $square++) {
      $piece_coord = vchess_i2coord($square);
      $piece = $this->get_piece($piece_coord);
      $piece_type = $piece->get_type();
      $piece_color = $piece->get_color();
      if ($piece_type <> "" && $piece_color == $attacker
          && $this->square_is_reachable($piece_type, $piece_coord, $dest_coord)) {
        $under_attack = TRUE;
      }
    }
  
    return $under_attack;
  }
  
  /**
   * Check whether player's king is in check.
   */
  function king_is_in_check($player_color) {
    // First find out on which square is the given player's king
    $found = FALSE;
    $index = 0;
    while (!$found && $index <= 63) {
      $coord = vchess_i2coord($index);
      if ($this->get_piece($coord)->get_color() == $player_color
          && $this->get_piece($coord)->get_type() == "K") {
        $king_pos = $coord;
        $found = TRUE;
      }
      else {
        $index++;
      }
    }
  
    if ($player_color == 'w') {
      return $this->square_is_under_attack('b', $king_pos);
    }
    else {
      return $this->square_is_under_attack('w', $king_pos);
    }
  
    return FALSE;
  }
  
  /**
   * Move chessman from pos to dest, check whether king is under attack and
   * restore the old board settings. whether pos-> dest is a valid move is
   * NOT checked!
   */
  function move_is_ok($player, $pos, $dest) {
    //global $board;
  
    // DEBUG: echo '$player-$opp: $pos-> $dest: ';
    //  $pos_bc = vchess_i2coord($pos);
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
    if ($player == 'w') {
      $opp = 'b';
    }
    else {
      $opp = 'w';
    }
  
    // Look at each square to find each of the opponent pieces
    for ($square = 0; $square < 64; $square++) {
      $coord = vchess_i2coord($square);
      if (!$this->square_is_empty($coord)) {
        if ($this->player_on_square($coord) != $player) {
          // If the square doesn't have an opponent piece, then skip to the next square
          continue;
        }
        // Can the piece move theoretically thus is there
        // at least one tile free for one piece?
        //      switch ($board[$square][1]) {
        switch ($this->get_piece($coord)->get_type()) {
          case 'K':
            $adj_squares = vchess_get_adjacent_squares($square);
            foreach ($adj_squares as $pos) {
              if ($this->player_on_square($pos) == $player) {
                continue;
              }
              if ($this->square_is_under_attack($opp, $pos)) {
                continue;
              }
              // Special case: if square is not actively under
              // attack it may still be blocked due to close
              // opponent king
              $kingtooclose = 0;
              $adj_squares2 = vchess_get_adjacent_squares($pos);
              foreach ($adj_squares2 as $coord2) {
                if ($board[$coord2] == $opp . 'K') {
                  $kingtooclose = 1;
                  break;
                }
              }
              if ($kingtooclose) {
                continue;
              }
              return FALSE;
            }
            // DEBUG: echo 'King cannot escape alone!';
            break;
          case 'P':
            if ($player == 'w') {
              if ($board[$square + 8] == '' && $this->move_is_ok($player, $square, $square + 8)) {
                return FALSE;
              }
              if (($square % 8) > 0 && ($this->player_on_square($square + 7) == $opp || vchess_en_passant_is_ok('w', $square, $square + 7, $b_ep))) {
                if ($this->move_is_ok($player, $square, $square + 7)) {
                  return FALSE;
                }
              }
              if (($square % 8) < 7 && ($this->player_on_square($square + 9) == $opp || vchess_en_passant_is_ok('w', $square, $square + 9, $b_ep))) {
                if ($this->move_is_ok($player, $square, $square + 9)) {
                  return FALSE;
                }
              }
            }
            else {
              $coord = vchess_i2coord($square - 8);
              if ($this->square_is_empty($coord) && $this->move_is_ok($player, $square, $square -8)) {
                return FALSE;
              }
              if (($square % 8) > 0 && ($this->player_on_square($square -9) == $opp || vchess_en_passant_is_ok('b', $square, $square -9, $w_ep))) {
                if ($this->move_is_ok($player, $square, $square -9)) {
                  return FALSE;
                }
              }
              if (($square % 8) < 7 && ($this->player_on_square($square -7) == $opp || vchess_en_passant_is_ok('b', $square, $square -7, $w_ep))) {
                if ($this->move_is_ok($player, $square, $square -7)) {
                  return FALSE;
                }
              }
            }
            break;
          case 'B':
            if ($square -9 >= 0   && $this->player_on_square($square -9) != $player &&
            $this->move_is_ok($player, $square, $square -9)) {
              return FALSE;
            }
            if ($square -7 >= 0   && $this->player_on_square($square -7) != $player &&
                $this->move_is_ok($player, $square, $square -7)) {
              return FALSE;
            }
            if ($square + 9 <= 63 && $this->player_on_square($square + 9) != $player &&
                $this->move_is_ok($player, $square, $square + 9)) {
              return FALSE;
            }
            if ($square + 7 <= 63 && $this->player_on_square($square + 7) != $player &&
                $this->move_is_ok($player, $square, $square + 7)) {
              return FALSE;
            }
            break;
          case 'R':
            //          kpr($board);
            if ($square -8 >= 0 && $this->player_on_square($square -8) != $player
            && $this->move_is_ok($player, $square, $square -8)) {
              return FALSE;
            }
            if ($square -1 >= 0 && $this->player_on_square($square -1) != $player &&
                $this->move_is_ok($player, $square, $square -1)) {
              return FALSE;
            }
            if ($square + 8 <= 63 && $this->player_on_square($square + 8) != $player &&
                $this->move_is_ok($player, $square, $square + 8)) {
              return FALSE;
            }
            if ($square + 1 <= 63 && $this->player_on_square($square + 1) != $player &&
                $this->move_is_ok($player, $square, $square + 1)) {
              return FALSE;
            }
            break;
          case 'Q':
            $adj_squares = vchess_get_adjacent_squares($square);
            foreach ($adj_squares as $pos) {
              $coord = vchess_i2coord($pos);
              //            if ($board[$pos][0] != $player) {
              if ($this->player_on_square($pos) != $player) {
                if ($this->move_is_ok($player, $square, $pos)) {
                  return FALSE;
                }
              }
            }
            break;
          case 'N':
            //          kpr($board);
            if ($square -17 >= 0   && $this->player_on_square($square -17) != $player &&
            $this->move_is_ok($player, $square, $square -17)) {
              return FALSE;
            }
            if ($square -15 >= 0   && $this->player_on_square($square -15) != $player &&
                $this->move_is_ok($player, $square, $square -15)) {
              return FALSE;
            }
            if ($square -6 >= 0   && $this->player_on_square($square -6) != $player &&
                $this->move_is_ok($player, $square, $square -6)) {
              return FALSE;
            }
            if ($square + 10 <= 63 && $this->player_on_square($square + 10) != $player &&
                $this->move_is_ok($player, $square, $square + 10)) {
              return FALSE;
            }
            if ($square + 17 <= 63 && $this->player_on_square($square + 17) != $player &&
                $this->move_is_ok($player, $square, $square + 17)) {
              return FALSE;
            }
            if ($square + 15 <= 63 && $this->player_on_square($square + 15) != $player &&
                $this->move_is_ok($player, $square, $square + 15)) {
              return FALSE;
            }
            if ($square + 6 <= 63 && $this->player_on_square($square + 6) != $player &&
                $this->move_is_ok($player, $square, $square + 6)) {
              return FALSE;
            }
            if ($square -10 >= 0   && $this->player_on_square($square -10) != $player &&
                $this->move_is_ok($player, $square, $square -10)) {
              return FALSE;
            }
            break;
        }
      }
    }
  
    return TRUE;
  }
  
  
  /**
   * Check whether player's king is in checkmate
   */
  function is_checkmate($player) {
    if ($player == 'w') {
      $opp = 'b';
    }
    else {
      $opp = 'w';
    }
  
    // Find the position of the player's king
    for ($square = 0; $square < 64; $square++) {
      $coord = vchess_i2coord($square);
      $piece = $this->get_piece($coord);
      if ($piece->get_type() == 'K' && $piece->get_color() == $player) {
        $king_pos = $square;
        $king_coord = vchess_i2coord($king_pos);
        $king_x = $square % 8;
        $king_y = floor($square / 8);
        break;
      }
    }
  
    // Test adjacent squares while king is temporarily removed
    $adj_squares = vchess_get_adjacent_squares($king_pos);
    //  $contents = $board[$king_pos];
    //  $board[$king_pos] = '';
    foreach ($adj_squares as $dest_pos) {
      $coord = vchess_i2coord($dest_pos);
      // If this adjacent square has a piece of the same color, then
      // we cannot move the king there
      if ($this->get_piece($coord)->get_color() == $player) {
        continue;
      }
      // If this adjacent square is under attack, then we cannot
      // move the king there
      if ($this->square_is_under_attack($opp, $dest_pos)) {
        continue;
      }
      //    $board[$king_pos] = $contents;
      // Since this square is neither occupied by one of our own pieces
      // or is under attack then we can move the king there and so it
      // isn't checkmate
      return FALSE;
    }
    //  $board[$king_pos] = $contents;
  
    // Get all pieces that attack the king
    $attacker_coords = array();
    for ($square = 0; $square < 64; $square++) {
      $square_coord = vchess_i2coord($square);
      $piece = $this->get_piece($square_coord);
      if ($piece->get_color() <> $player) {
        if (($piece->get_type() == 'P' && $this->check_pawn_attacks($square_coord, $king_coord))
            || ($piece->get_type() != 'P' && $this->square_is_reachable($piece->get_type(), $square_coord, $king_coord))) {
          $attacker_coords[] = $square_coord;
        }
      }
    }
  
    // If more than one there is no chance to escape
    if (count($attacker_coords) > 1) {
      return TRUE;
    }
  
    // There is only 1 attacker.  Check whether this attacker can be killed by own piece.
    for ($square = 0; $square < 64; $square++) {
      $square_coord = vchess_i2coord($square);
      if ($this->get_piece($square_coord)->get_color() == $player) {
        $piece_type = $this->get_piece($square_coord)->get_type();
        if (($piece_type == 'P' && $this->check_pawn_attacks($square_coord, $attacker_coords[0]))
            || ($piece_type != 'P' && $piece_type != 'K' && $this->square_is_reachable($piece_type, $square_coord, $attacker_coords[0]))
            || ($piece_type == 'K' && $this->square_is_reachable($piece_type, $square_coord, $attacker_coords[0], $board) && !$this->square_is_under_attack($opp, $attacker_coords[0]))) {
          // DEBUG: echo 'candidate: $square ';
          $can_kill_attacker = FALSE;
          //           $contents_def = $board[$square];
          //           $contents_atk = $board[$dest_pos];
          //           $board[$dest_pos] = $board[$square];
          //           $board[$square] = '';
          if (!$this->square_is_under_attack($opp, $king_coord)) {
            $can_kill_attacker = TRUE;
          }
          //           $board[$square] = $contents_def;
          //           $board[$dest_pos] = $contents_atk;
          if ($can_kill_attacker) {
            return FALSE;
          }
        }
      }
    }
  
    // Check whether own unit can block the way
  
    // If attacking unit is a knight there
    // is no way to block the path.
    $dest_coord = vchess_i2coord($dest_pos);
    if ($this->get_piece($dest_coord)->get_type() == 'N') {
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
    $dest_coord = vchess_i2coord($dest_pos);
    $change = vchess_get_path_change($this->get_piece($dest_coord)->get_type(), $dest_pos, $king_pos);
    $path = vchess_get_path($dest_pos + $change, $king_pos, $change);
    foreach ($path as $pos) {
      for ($square = 0; $square < 64; $square++) {
        $coord = vchess_i2coord($square);
        $piece = $this->get_piece($coord);
        $piece_type = $piece->get_type();
        if ($piece->get_color() == $player) {
          if (($piece_type == 'P' && $board->piece_may_move_to_square($square, $pos))
              || ($piece_type != 'P' && $piece_type != 'K' && $this->square_is_reachable($piece_type, $square, $pos))) {
            //           $board[$pos] = $board[$square];
            //           $old = $board[$square];
            //           $board[$square] = '';
            $is_in_check = $this->king_is_in_check($player);
            //           $board[$square] = $old;
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
   * Check whether dest_coord is in reach for piece of piece_type
   * at square piece_coord. It is not checked whether the square
   * itself is occupied but only the squares in between.
   */
  function square_is_reachable($piece, $piece_coord, $dest_coord) {
    $reachable = FALSE;
  
    if ($piece_coord != $dest_coord) {
      $piece_pos = vchess_coord2i($piece_coord);
      $dest_pos = vchess_coord2i($dest_coord);
  
      $piece_y = floor($piece_pos / 8);
      $piece_x = $piece_pos % 8;
      $dest_y = floor($dest_pos / 8);
      $dest_x = $dest_pos % 8;
  
      switch ($piece) {
        // Pawn
        case 'P':
          $reachable = $this->check_pawn_attacks($piece_coord, $dest_coord);
          break;
          // knight
        case 'N':
          if (abs($piece_x - $dest_x) == 1 && abs($piece_y - $dest_y) == 2) {
            $reachable = TRUE;
          }
          if (abs($piece_y - $dest_y) == 1 && abs($piece_x - $dest_x) == 2) {
            $reachable = TRUE;
          }
          break;
          // bishop
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
          $adj_squares = vchess_get_adjacent_squares($dest_pos);
          foreach ($adj_squares as $square) {
            $coord = vchess_i2coord($square);
            //        if ($board[$tile][1] == 'K') {
            if ($this->get_piece($coord)->get_type() == 'K') {
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
   * Check whether pawn at $piece_coord attacks the
   * square $dest_coord, i.e. the diagonally attacked square
   *
   * Note that it is not necessary for a piece to be on the
   * destination square for that square to be attacked
   *
   * @param $piece_coord Coordinate (e.g. "d2") of piece
   * @param $dest_coord Coordinate (e.g. "e3") of attacked square
   * @param $board Board
   */
  function check_pawn_attacks($piece_coord, $dest_coord) {
    $attacks = FALSE;
  
    $piece_color = $this->get_piece($piece_coord)->get_color();
  
    // Convert coord like "d4" into col=4 rank=4
    $piece_col = vchess_file2col(vchess_coord_file($piece_coord)); // e.g. d -> 4
    $piece_rank = vchess_coord_rank($piece_coord);
  
    $dest_col = vchess_file2col(vchess_coord_file($dest_coord));  // e.g. e -> 5
    $dest_rank = vchess_coord_rank($dest_coord);
  
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
   * Check whether a given piece may legally move to the given square. 
   */
  function piece_may_move_to_square($piece_coord, $dest_coord) {
    $move_ok = FALSE;
    
    $piece = $this->get_piece($piece_coord);
    switch ($piece->get_type()) {
      case 'P':
        $move_ok = $this->_pawn_may_move_to_square($piece_coord, $dest_coord);
        break;
      case 'K':
        $move_ok = $this->_king_may_move_to_square($piece_coord, $dest_coord);
        break;
      default:
        break;
    }
    
    return $move_ok;
  }
  
  /**
   * Check whether pawn at $piece_coord may move to $dest_coord.
   * First move may be two squares instead of just one.
   *
   * @return
   *   TRUE if the pawn may move to the given square
   *   FALSE if the destination square is occupied or if a square
   *     on the way is occupied for the first 2-square move
   */
  function _pawn_may_move_to_square($piece_coord, $dest_coord) {
    $move_ok = FALSE;
  
    if ($this->square_is_empty($dest_coord)) {
      $piece = $this->get_piece($piece_coord);
      $piece_file = vchess_coord_file($piece_coord); // e.g. e
      $piece_rank = vchess_coord_rank($piece_coord); // e.g. 2
      $dest_file = vchess_coord_file($dest_coord);  // e.g. e
      $dest_rank = vchess_coord_rank($dest_coord);  // e.g. 4
  
      // Check pawn stays on same file.
      // Captures are checked in check_pawn_attacks()
      if ($piece_file <> $dest_file) {
        $move_ok = FALSE;
      }
      elseif ($piece->get_color() == 'w') {
        // white pawn
        if ($piece_rank == 2 && $dest_rank == 4) {
          // Pawn moving 2 squares, so check if intermediate square is empty
          $intermediate_coord = $piece_file . "3";
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
          $intermediate_coord = $piece_file . "6";
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

/**
 * Return the array of adjacent squares (<=8).  Squares are in the format
 * of a [0..63] index.
 */
function vchess_get_adjacent_squares($piece_pos) {
  $adj_squares = array();
  $i = 0;
  $x = $piece_pos % 8;
  $y = floor($piece_pos / 8);

  if ($x > 0 && $y > 0) {
    $adj_squares[$i++] = $piece_pos - 9;
  }
  if ($y > 0) {
    $adj_squares[$i++] = $piece_pos - 8;
  }
  if ($x < 7 && $y > 0) {
    $adj_squares[$i++] = $piece_pos - 7;
  }
  if ($x < 7) {
    $adj_squares[$i++] = $piece_pos + 1;
  }
  if ($x < 7 && $y < 7) {
    $adj_squares[$i++] = $piece_pos + 9;
  }
  if ($y < 7) {
    $adj_squares[$i++] = $piece_pos + 8;
  }
  if ($x > 0 && $y < 7) {
    $adj_squares[$i++] = $piece_pos + 7;
  }
  if ($x > 0) {
    $adj_squares[$i++] = $piece_pos - 1;
  }

  /* DEBUG: foreach($adj_squares as $tile)
   echo 'adj: '.$tile.' '; */

  return $adj_squares;
}




