<?php

/**
 * @file
 * Functions concerning a move
 */

class Move {
  var $long_move; // e.g. "Pd2-d4", "Nb1xBc3"
  
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
  function get_long_move() {
    return $this->long_move;
  }
  
  /**
   * Get the destination coord from a given move
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
  function dest_coord() {
    $dest_coord = new Square();
    
    if ($this->long_move[3] == "x") {
      $dest_coord->set_filerank(substr($this->long_move, 5, 2));
    }
    else { // Move type = "-"
      $dest_coord->set_filerank(substr($this->long_move, 4, 2));
    }
  
    return $dest_coord;
  }
  
  /**
   * Get the source coord from a given move
   *
   * In a move like "Bf5xPe4" return "f5"
   * In a move like "Rh4-d4" return "h4"
   */
  function source_coord() {
    $source_coord = new Square();
    $source_coord->set_filerank(substr($this->long_move, 1, 2));
  
    return $source_coord;
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
   * Get short notation of move for move history.
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
  
    $source_coord = $this->source_coord();
    $dest_coord = $this->dest_coord();
  
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
        $short_move_string = $dest_coord->get_filerank();
      }
      else {
        // e.g. cxd4
        $short_move_string = $this->long_move[1] . "x" . $dest_coord->get_filerank();
      }
    }
    else {
      // First find out how where all possible pieces of this type are
      $pieces_coords = $board->get_pieces_coords($player, $source_piece_type);
  
      // If there is only 1 piece of this type, then move is unambiguous
      if (count($pieces_coords) == 1) {
        if ($this->type() == '-') {
          // e.g. Ne4
          $short_move_string = $source_piece_type . $dest_coord->get_filerank();
        }
        else {
          // e.g. Nxd4
          $short_move_string = $source_piece_type . "x" . $dest_coord->get_filerank();
        }
      }
      else {
        // Find how many pieces of this type may move to the dest square
        $eligible_coords = array();
        foreach ($pieces_coords as $piece_coord) {
          if ($board->piece_may_move_to_square($piece_coord, $dest_coord)) {
            $eligible_coords[] = $piece_coord;
          }
        }
  
        if (count($eligible_coords) == 1) {
          // Only 1 piece of this type can reach the square, so unambiguous
          if ($this->type() == '-') {
            // e.g. Ne4
            $short_move_string = $source_piece_type . $dest_coord->get_filerank();
          }
          else {
            // e.g. Nxd4
            $short_move_string = $source_piece_type . "x" . $dest_coord->get_filerank();
          }
        }
        else {
          // First get an array with just the coords of the piece(s) which
          // can also get to the square.  Unless there are 3 pieces of the same
          // type of the board, this will just be the square of the other piece.
          $source_coords = array($source_coord);
          $trouble_coords = array_diff($eligible_coords, $source_coords);
  
          // First try to disambiguate by looking at the file, e.g. Ngf3
          $source_file = $source_coord->get_file();
          $file_unique = TRUE;
          foreach ($trouble_coords as $trouble_coord) {
            if ($trouble_coord->get_file() == $source_file) {
              $file_unique = FALSE;
            }
          }
  
          if ($file_unique) {
            if ($this->type() == '-') {
              $short_move_string = $source_piece_type . $source_file . $dest_coord->get_filerank();
            }
          }
        }
      }
    }
  
    return $short_move_string;
  }
}

/**
 * Convert short to full chess notation (e.g. Pe4 -> Pe2-e4).
 *
 * @param $move: short notation of move
 * @param $player: player color (w or b)
 *
 * Return NULL on error or new move (or same if already full notation).
 * If an error occured set global acerror to reason. Return values:
 * [a-h][1-8|a-h][RNBQK]              pawn move/attack
 * [PRNBQK][a-h][1-8]                 piece move
 * [PRNBQK][:x][a-h][1-8]             piece attack
 * [PRNBQK][1-8|a-h][a-h][1-8]        ambigous piece move
 * [a-h][:x][a-h][1-8][[RNBQK]        ambigous pawn attack
 * [PRNBQK][1-8|a-h][:x][a-h][1-8]    ambigous piece attack
 *
 */
// function vchess_short_to_long_move($player, $move, $board) {
//   // Strip away # from possible PGN move
//   if ($move[strlen($move) -1] == '#') {
//     $move = substr($move, 0, strlen($move) -1);
//   }

//   if (strlen($move) >= 6) {
//     // Full move: a pawn requires a ? in the end
//     // to automatically choose a queen on last line.
//     if ($move[0] == 'P') {
//       if ($move[strlen($move) -1] < 'A' || $move[strlen($move) -1] > 'Z') {
//         $move = $move . '?';
//       }
//     }
//     return $move;
//   }

//   // For a pawn the last character may be A-Z to indicate promotion
//   // chessman. We split this character to keep the autocompletion
//   // process the same.
//   $pawn_upg = '?';
//   if ($move[strlen($move) -1] >= 'A' && $move[strlen($move) -1] <= 'Z') {
//     $pawn_upg = $move[strlen($move) -1];
//     $move = substr($move, 0, strlen($move) -1);
//   }
//   if ($pawn_upg != 'N' && $pawn_upg != 'B' && $pawn_upg != 'R' &&
//       $pawn_upg != 'Q' && $pawn_upg != '?') {
//     $acerror = 'pawn may only become Knight, Bishop, Rook or Queen';
//     drupal_set_message(check_plain($acerror), 'error');
//     return NULL;
//   }

//   if ($move[0] >= 'a' && $move[0] <= 'h') {
//     // Pawn move
//     if (strlen($move) == 4) {
//       // [a-h]x[a-h][1-8]
//       if ($move[1] != 'x') {
//         $acerror = 'use x to indicate attack';
//         drupal_set_message(check_plain($acerror), 'error');
//         return NULL;
//       }
//       $dest_x = $move[2];
//       $dest_y = $move[3];
//       $src_x = $move[0];
//       if ($player == 'w') {
//         $src_y = $dest_y -1;
//       }
//       else {
//         $src_y = $dest_y + 1;
//       }
//       return sprintf('P%s%dx%s%d%s',
//           $src_x, $src_y, $dest_x, $dest_y,
//           $pawn_upg);
//     }
//     elseif (strlen($move) == 2) {
//       $piece = sprintf('%sP', $player);
//       if ($move[1] >= '1' && $move[1] <= '8') {
//         /* [a-h][1-8] */
//         $pos = convert2i($move);
//         if ($pos == 64) {
//           $acerror = 'coordinate ' . $move . ' is invalid';
//           drupal_set_message(check_plain($acerror), 'error');
//           return NULL;
//         }
//         if ($player == 'w') {
//           while ($pos >= 0 && $board[$pos] != $piece) {
//             $pos -= 8;
//           }
//           if ($pos < 0) {
//             $not_found = 1;
//           }
//         }
//         else {
//           while ($pos <= 63 && $board[$pos] != $piece) {
//             $pos += 8;
//           }
//           if ($pos > 63) {
//             $not_found = 1;
//           }
//         }
//         $pos = vchess_i2coord($pos);
//         if ($not_found || $pos == '') {
//           $acerror = 'could not find ' . $player . ' pawn in ' . $move[0];
//           drupal_set_message(check_plain($acerror), 'error');
//           return NULL;
//         }
//         return sprintf('P%s-%s%s', $pos, $move, $pawn_upg);
//       }
//       else {
//         // [a-h][a-h] old attack notation: only
//         // possible if single pawn in column
//         $pawns = 0;
//         $start = convert2i(sprintf('%s1', $move[0]));
//         if ($start == 64) {
//           $acerror = 'coordinate ' . $move[0] . ' is invalid';
//           drupal_set_message(check_plain($acerror), 'error');
//           return NULL;
//         }
//         for ($square = 1; $square <= 8; $square++, $start += 8) {
//           if ($board[$start] == $piece) {
//             $pawns++;
//             $pawn_line = $square;
//           }
//         }
//         if ($pawns == 0) {
//           $acerror = 'no pawns in ' . $move[0];
//           drupal_set_message(check_plain($acerror), 'error');
//           return NULL;
//         }
//         if ($pawns > 1) {
//           $acerror = 'multiple pawns in ' . $move[0];
//           drupal_set_message(check_plain($acerror), 'error');
//           return NULL;
//         }
//         if ($player == 'w') {
//           $dest_line = $pawn_line + 1;
//         }
//         else {
//           $dest_line = $pawn_line -1;
//         }
//         return sprintf('P%s%dx%s%d', $move[0], $pawn_line,
//             $move[1], $dest_line);
//       }
//     }
//     // If we got here pawn move could not be parsed
//     $acerror = 'could not parse pawn move ' . $move;
//     drupal_set_message(check_plain($acerror), 'error');
//     return NULL;
//   }

//   // Other chessman move
//   $dest_coord = substr($move, strlen($move) -2, 2);
//   $action = $move[strlen($move) - 3];
//   if ($action != 'x') {
//     $action = '-';
//   }
//   $piece_count = 0;
//   for ($square = 0; $square < 64; $square++) {
//     // Example:
//     //   $player = "w"
//     //   $move[0] = "N" if $move = "Nc3"
//     //   $board[$square] = "wN" for white Knight

//     $coord = vchess_i2coord($square);
//     // Check if the piece on $square matches the player color
//     // and the color of the piece moved
//     //    if ($board[$square] == $player . $move[0]) {
//     $piece = $board->get_piece($coord);
//     if ($piece->get_color() == $player && $piece->get_type() == $move[0]) {
//       $piece_count++;
//       if ($piece_count == 1) {
//         $coord1 = vchess_i2coord($square);
//       }
//       else {
//         $coord2 = vchess_i2coord($square);
//       }
//     }
//   }
//   if ($piece_count == 0) {
//     $piece = new Piece();
//     $piece->set_type($move[0]);
//     //    $acerror = sprintf('%s=%s not found', $move[0], vchess_get_piece_name($move[0]));
//     $acerror = sprintf('%s=%s not found', $move[0], $piece->get_name());
//     drupal_set_message(check_plain($acerror), 'error');
//     return NULL;
//   }
//   if ($piece_count == 1) {
//     return sprintf('%s%s%s%s', $move[0], $coord1, $action, $dest_coord);
//   }
//   // Two chessmen - may cause ambiguity
//   $dest_pos = convert2i($dest_coord);
//   if ($dest_pos == 64) {
//     $acerror = 'coordinate ' . $dest_coord . ' is invalid';
//     drupal_set_message(check_plain($acerror), 'error');
//     return NULL;
//   }

//   // Check if 2 pieces of the same type can reach the destination square
//   $piece1_can_reach = FALSE; // Hugh setting default
//   $piece2_can_reach = FALSE; // Hugh setting default
//   if ($board->square_is_reachable($move[0], $coord1, $dest_coord)) {
//     $piece1_can_reach = TRUE;
//   }
//   if ($board->square_is_reachable($move[0], $coord2, $dest_coord)) {
//     $piece2_can_reach = TRUE;
//   }
//   if (!$piece1_can_reach && !$piece2_can_reach) {
//     $piece = new Piece();
//     $piece->set_type($move[0]);
//     $acerror = sprintf('no %s can reach %s', $piece->get_name(), $dest_coord);
//     drupal_set_message(check_plain($acerror), 'error');
//     return NULL;
//   }
//   if ($piece1_can_reach && $piece2_can_reach) {
//     // Ambiguity - check whether a hint is given
//     if (($action == '-' && strlen($move) == 4) ||
//         ($action == 'x' &&
//             strlen($move) == 5)) {
//       $hint = $move[1];
//     }
//     if (empty($hint)) {
//       $acerror = sprintf('both %s can reach %s', vchess_get_piece_name($move[0]), $dest_coord);
//       drupal_set_message(check_plain($acerror), 'error');
//       return NULL;
//     }
//     if ($hint >= '1' && $hint <= '8') {
//       if ($coord1[1] == $hint && $coord2[1] != $hint) {
//         $move_piece1 = 1;
//       }
//       if ($coord2[1] == $hint && $coord1[1] != $hint) {
//         $move_piece2 = 1;
//       }
//     }
//     else {
//       if ($coord1[0] == $hint && $coord2[0] != $hint) {
//         $move_piece1 = 1;
//       }
//       if ($coord2[0] == $hint && $coord1[0] != $hint) {
//         $move_piece2 = 1;
//       }
//     }
//     if (!$move_piece1 && !$move_piece2) {
//       $acerror = 'could not resolve ambiguity';
//       drupal_set_message(check_plain($acerror), 'error');
//       return NULL;
//     }
//     if ($move_piece1) {
//       return sprintf('%s%s%s%s', $move[0], $coord1, $action,
//           $dest_coord);
//     }
//     else {
//       return sprintf('%s%s%s%s', $move[0], $coord2, $action,
//           $dest_coord);
//     }
//   }
//   else {
//     if ($piece1_can_reach) {
//       return sprintf('%s%s%s%s', $move[0], $coord1, $action,
//           $dest_coord);
//     }
//     else {
//       return sprintf('%s%s%s%s', $move[0], $coord2, $action,
//           $dest_coord);
//     }
//   }

//   // If we got here chessman move could not be parsed
//   $acerror = 'could not parse chessman move ' . $move;
//   drupal_set_message(check_plain($acerror), 'error');
//   return NULL;
// }




