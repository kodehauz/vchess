<?php

/**
 * @file
 * Functions concerning a move
 */

class Move {
  var $long_move; // e.g. "Pd2-d4", "Nb1xBc3", "Ke1-g1"
  
  /**
   * Initialise a move with a given string
   * 
   * A move is one of the following formats:
   * - "Bf5xPe4" i.e. a capture which includes the type of the piece being captured 
   * - "Rh4-d4" i.e. a move to a square
   */
  function initialise($long_move) {
    $this->long_move = $long_move;
  }
  
  /**
   * Get the long move notation
   */
  function long_format() {
    return $this->long_move;
  }
  
  /**
   * Get the destination square from a given move
   *
   * In a move like "Bf5xPe4":
   *   $move[0] = source piece
   *   $move[1-2] = source coord
   *   $move[3] = move type, "x"
   *   $move[4] = dest piece
   *   $move[5-6] = dest square
   *
   * In a move like "Rh4-d4":
   *   $move[0] = source piece
   *   $move[1-2] = source coord
   *   $move[3] = move type, "-"
   *   $move[4-5] = dest square
   */
  function to_square() {
    $to_square = new Square();
    
    if ($this->long_move[3] == "x") {
      $to_square->set_coord(substr($this->long_move, 5, 2));
    }
    else { // Move type = "-"
      $to_square->set_coord(substr($this->long_move, 4, 2));
    }
  
    return $to_square;
  }
  
  /**
   * Get the source coord from a given move
   *
   * In a move like "Bf5xPe4" return "f5"
   * In a move like "Rh4-d4" return "h4"
   */
  function from_square() {
    $from_square = new Square();
    $from_square->set_coord(substr($this->long_move, 1, 2));
  
    return $from_square;
  }
  
  /**
   * Get the move type
   * e.g. "-" for a move like "Ra1-a4"
   *   or "x" for a move like "Ra1xNa6"
   */
  function type() {
    return $this->long_move[3];
  }
  
  /**
   * Get the source piece from a given move
   * e.g. "Ra1-a4" gives "R"
   */
  function source_piece_type() {
    return $this->long_move[0];
  }
  
  /**
   * Get the destination piece from a given move
   *
   * If there is no destination piece, return ""
   *
   * e.g.
   * "Qd1xBd7" returns "B"
   * "Ra1-a4" returns ""
   */
  function dest_piece_type() {
    if ($this->long_move[3] == "x") {
      $dest_piece_type = $this->long_move[4];
    }
    else {
      $dest_piece_type = "";
    }
  
    return $dest_piece_type;
  }
  
  /**
   * Get short notation of move
   * e.g. Pe2-e4 -> e4
   *      Re1xNa6 -> Rxa6
   *
   * When two (or more) identical pieces can move to the same square,
   * the moving piece is uniquely identified by specifying the piece's letter,
   * followed by (in descending order of preference):
   * - the file of departure (if they differ); or
   * - the rank of departure (if the files are the same but the ranks differ);
   * - or both the rank and file (if neither alone is sufficient to identify the piece—which occurs only in rare cases where one or more pawns have promoted, resulting
   *   in a player having three or more identical pieces able to reach the same square).
   *
   * For example, with knights on g1 and d2, either of which might move to f3,
   * the move is specified as Ngf3 or Ndf3, as appropriate. With knights on g5 and g1,
   * the moves are N5f3 or N1f3. As above, an x can be inserted to indicate a capture,
   * for example: N5xf3.
   *
   * Occasionally, it may be possible to disambiguate in two different ways -
   * for example, two rooks on d3 and h5, either one of which may move to d5.
   * If the rook on d3 moves to d5, it is possible to disambiguate with either Rdd5
   * or R3d5. In cases such as these the file takes precedence over the rank,
   * so Rdd5 is correct.
   *
   * See http://en.wikipedia.org/wiki/Algebraic_notation_(chess)
   */
  function get_algebraic($player, Board $board) {
    // If all else fails, just return the long move
    $short_move_string = $this->long_move;
  
    $source_piece_type = $this->source_piece_type();
    $dest_piece_type = $this->dest_piece_type();
  
    $from_square = $this->from_square();
    $to_square = $this->to_square();
  
    // Castling short
    if ($this->long_move == "Ke1-g1" || $this->long_move == "Ke8-g8") {
      $short_move_string = "O-O";
    }
    // Castling long
    elseif ($this->long_move == "Ke1-c1" || $this->long_move == "Ke8-c8") {
      $short_move_string = "O-O-O";
    }
    elseif ($source_piece_type == 'P') {
      // P moves are always unambiguous. For attacks skip source digit
      // and for moves skip source pos and "-"
      if ($this->type() == '-') {
        // e.g. e4
        $short_move_string = $to_square->coord();
      }
      else {
        // e.g. cxd4
        $short_move_string = $this->long_move[1] . "x" . $to_square->coord();
      }
    }
    else {
      // First find out how where all possible pieces of this type are
      $pieces_squares = $board->get_pieces_squares($player, $source_piece_type);
  
      // If there is only 1 piece of this type, then move is unambiguous
      if (count($pieces_squares) == 1) {
        if ($this->type() == '-') {
          // e.g. Ne4
          $short_move_string = $source_piece_type . $to_square->coord();
        }
        else {
          // e.g. Nxd4
          $short_move_string = $source_piece_type . "x" . $to_square->coord();
        }
      }
      else {
        // Find how many other pieces of this type may move to the dest square
        $trouble_squares = array();
        foreach ($pieces_squares as $piece_square) {
          // Only look at the other pieces
          if ($piece_square != $from_square) {
            if ($board->piece_may_move_to_square($piece_square, $to_square)) {
              $trouble_squares[] = $piece_square;
            }
          }
        }
  
        if (count($trouble_squares) == 0) {
          // No other piece of this type can reach the square, so unambiguous
          if ($this->type() == '-') {
            // e.g. Ne4
            $short_move_string = $source_piece_type . $to_square->coord();
          }
          else {
            // e.g. Nxd4
            $short_move_string = $source_piece_type . "x" . $to_square->coord();
          }
        }
        else {
          // First try to disambiguate by looking at the file, e.g. Ngf3
          $source_file = $from_square->get_file();
          $file_unique = TRUE;
          foreach ($trouble_squares as $trouble_coord) {
            if ($trouble_coord->get_file() == $source_file) {
              $file_unique = FALSE;
            }
          }
  
          // In this case the file is enough to make the move unique, e.g. Ngf3
          if ($file_unique) {
            if ($this->type() == '-') {
              $short_move_string = $source_piece_type . $source_file . $to_square->coord();
            }
            else {
              // e.g. Nxd4
              $short_move_string = $source_piece_type . $source_file . "x" . $to_square->coord();
            }
          }
          else {
            // Try to disambiguate by looking at the rank, e.g. N1f3
            $source_rank = $from_square->rank();
            $rank_unique = TRUE;
            foreach ($trouble_squares as $trouble_coord) {
              if ($trouble_coord->rank() == $source_rank) {
                $rank_unique = FALSE;
              }
            }
            
            // In this case the rank is enough to make the move unique, e.g. N1f3
            if ($rank_unique) {
              if ($this->type() == '-') {
                // e.g. N1f3
                $short_move_string = $source_piece_type . $source_rank . $to_square->coord();
              }
              else {
                // e.g. N1xf3
                $short_move_string = $source_piece_type . $source_rank . "x" . $to_square->coord();
              } 
            }
            else {
              // File is not unique, rank is not unique, so we need full source square, e.g. Ng1f3  
              // This can only ever happen when promotion to a third piece has occured.
              $prefix = $source_piece_type . $source_rank . $source_file;
              if ($this->type() == '-') {
                // e.g. Ng1f3
                $short_move_string = $prefix . $to_square->coord();
              }
              else {
                // e.g. Ng1xf3
                $short_move_string = $prefix . "x" . $to_square->coord();
              }
            }
          }
        }
      }
    }
  
    return $short_move_string;
  }
}
