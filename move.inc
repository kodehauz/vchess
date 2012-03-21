<?php

/**
 * @file
 * Functions concerning a move
 */

class Move {
  var $move;
  
  /**
   * Initialise a move with a given string
   * 
   * A move is one of the following formats:
   * - "Bf5xPe42 i.e. a capture which includes the type of the piece being captured 
   * - "Rh4-d4" i.e. a move to a square
   */
  function initialise($move) {
    $this->move = $move;
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
    if ($this->move[3] == "x") {
      $dest_coord = substr($this->move, 5, 2);
    }
    else { // Move type = "-"
      $dest_coord = substr($this->move, 4, 2);
    }
  
    return $dest_coord;
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
  function source_coord() {
    if ($this->move[3] == "x") {
      $dest_coord = substr($this->move, 5, 2);
    }
    else { // Move type = "-"
      $dest_coord = substr($this->move, 4, 2);
    }
  
    return $dest_coord;
  }
  
  /**
   * Get the move type
   * e.g. "-" for a move like "Ra1-a4"
   *   or "x" for a move like "Ra1xNa6"
   */
  function type() {
    return $this->move[3];
  }
  
  /**
   * Get the source piece from a given move
   * e.g. "Ra1-a4" gives "R"
   */
  function source_piece() {
    return $this->move[0];
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
  function dest_piece() {
    if ($this->move[3] == "x") {
      $dest_piece = $this->move[4];
    }
    else {
      $dest_piece = "";
    }
  
    return $dest_piece;
  }
}





/**
 * Get the source file (a..h)
 * 
 * @param move e.g. "Ra1-a4" or "Ra1xNa7" 
 */
function vchess_move_source_file($move) {
  return $move[1];
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
function vchess_short_to_long_move($player, $move, $board) {
  // Strip away # from possible PGN move
  if ($move[strlen($move) -1] == '#') {
    $move = substr($move, 0, strlen($move) -1);
  }

  if (strlen($move) >= 6) {
    // Full move: a pawn requires a ? in the end
    // to automatically choose a queen on last line.
    if ($move[0] == 'P') {
      if ($move[strlen($move) -1] < 'A' || $move[strlen($move) -1] > 'Z') {
        $move = $move . '?';
      }
    }
    return $move;
  }

  // For a pawn the last character may be A-Z to indicate promotion
  // chessman. We split this character to keep the autocompletion
  // process the same.
  $pawn_upg = '?';
  if ($move[strlen($move) -1] >= 'A' && $move[strlen($move) -1] <= 'Z') {
    $pawn_upg = $move[strlen($move) -1];
    $move = substr($move, 0, strlen($move) -1);
  }
  if ($pawn_upg != 'N' && $pawn_upg != 'B' && $pawn_upg != 'R' &&
      $pawn_upg != 'Q' && $pawn_upg != '?') {
    $acerror = 'pawn may only become Knight, Bishop, Rook or Queen';
    drupal_set_message(check_plain($acerror), 'error');
    return NULL;
  }

  if ($move[0] >= 'a' && $move[0] <= 'h') {
    // Pawn move
    if (strlen($move) == 4) {
      // [a-h]x[a-h][1-8]
      if ($move[1] != 'x') {
        $acerror = 'use x to indicate attack';
        drupal_set_message(check_plain($acerror), 'error');
        return NULL;
      }
      $dest_x = $move[2];
      $dest_y = $move[3];
      $src_x = $move[0];
      if ($player == 'w') {
        $src_y = $dest_y -1;
      }
      else {
        $src_y = $dest_y + 1;
      }
      return sprintf('P%s%dx%s%d%s',
          $src_x, $src_y, $dest_x, $dest_y,
          $pawn_upg);
    }
    elseif (strlen($move) == 2) {
      $piece = sprintf('%sP', $player);
      if ($move[1] >= '1' && $move[1] <= '8') {
        /* [a-h][1-8] */
        $pos = vchess_coord2i($move);
        if ($pos == 64) {
          $acerror = 'coordinate ' . $move . ' is invalid';
          drupal_set_message(check_plain($acerror), 'error');
          return NULL;
        }
        if ($player == 'w') {
          while ($pos >= 0 && $board[$pos] != $piece) {
            $pos -= 8;
          }
          if ($pos < 0) {
            $not_found = 1;
          }
        }
        else {
          while ($pos <= 63 && $board[$pos] != $piece) {
            $pos += 8;
          }
          if ($pos > 63) {
            $not_found = 1;
          }
        }
        $pos = vchess_i2coord($pos);
        if ($not_found || $pos == '') {
          $acerror = 'could not find ' . $player . ' pawn in ' . $move[0];
          drupal_set_message(check_plain($acerror), 'error');
          return NULL;
        }
        return sprintf('P%s-%s%s', $pos, $move, $pawn_upg);
      }
      else {
        // [a-h][a-h] old attack notation: only
        // possible if single pawn in column
        $pawns = 0;
        $start = vchess_coord2i(sprintf('%s1', $move[0]));
        if ($start == 64) {
          $acerror = 'coordinate ' . $move[0] . ' is invalid';
          drupal_set_message(check_plain($acerror), 'error');
          return NULL;
        }
        for ($square = 1; $square <= 8; $square++, $start += 8) {
          if ($board[$start] == $piece) {
            $pawns++;
            $pawn_line = $square;
          }
        }
        if ($pawns == 0) {
          $acerror = 'no pawns in ' . $move[0];
          drupal_set_message(check_plain($acerror), 'error');
          return NULL;
        }
        if ($pawns > 1) {
          $acerror = 'multiple pawns in ' . $move[0];
          drupal_set_message(check_plain($acerror), 'error');
          return NULL;
        }
        if ($player == 'w') {
          $dest_line = $pawn_line + 1;
        }
        else {
          $dest_line = $pawn_line -1;
        }
        return sprintf('P%s%dx%s%d', $move[0], $pawn_line,
            $move[1], $dest_line);
      }
    }
    // If we got here pawn move could not be parsed
    $acerror = 'could not parse pawn move ' . $move;
    drupal_set_message(check_plain($acerror), 'error');
    return NULL;
  }

  // Other chessman move
  $dest_coord = substr($move, strlen($move) -2, 2);
  $action = $move[strlen($move) - 3];
  if ($action != 'x') {
    $action = '-';
  }
  $piece_count = 0;
  for ($square = 0; $square < 64; $square++) {
    // Example:
    //   $player = "w"
    //   $move[0] = "N" if $move = "Nc3"
    //   $board[$square] = "wN" for white Knight

    $coord = vchess_i2coord($square);
    // Check if the piece on $square matches the player color
    // and the color of the piece moved
    //    if ($board[$square] == $player . $move[0]) {
    $piece = $board->get_piece($coord);
    if ($piece->get_color() == $player && $piece->get_type() == $move[0]) {
      $piece_count++;
      if ($piece_count == 1) {
        $coord1 = vchess_i2coord($square);
      }
      else {
        $coord2 = vchess_i2coord($square);
      }
    }
  }
  if ($piece_count == 0) {
    $piece = new Piece();
    $piece->set_type($move[0]);
    //    $acerror = sprintf('%s=%s not found', $move[0], vchess_get_piece_name($move[0]));
    $acerror = sprintf('%s=%s not found', $move[0], $piece->get_name());
    drupal_set_message(check_plain($acerror), 'error');
    return NULL;
  }
  if ($piece_count == 1) {
    return sprintf('%s%s%s%s', $move[0], $coord1, $action, $dest_coord);
  }
  // Two chessmen - may cause ambiguity
  $dest_pos = vchess_coord2i($dest_coord);
  if ($dest_pos == 64) {
    $acerror = 'coordinate ' . $dest_coord . ' is invalid';
    drupal_set_message(check_plain($acerror), 'error');
    return NULL;
  }

  // Check if 2 pieces of the same type can reach the destination square
  $piece1_can_reach = FALSE; // Hugh setting default
  $piece2_can_reach = FALSE; // Hugh setting default
  if ($board->square_is_reachable($move[0], $coord1, $dest_coord)) {
    $piece1_can_reach = TRUE;
  }
  if ($board->square_is_reachable($move[0], $coord2, $dest_coord)) {
    $piece2_can_reach = TRUE;
  }
  if (!$piece1_can_reach && !$piece2_can_reach) {
    $piece = new Piece();
    $piece->set_type($move[0]);
    $acerror = sprintf('no %s can reach %s', $piece->get_name(), $dest_coord);
    drupal_set_message(check_plain($acerror), 'error');
    return NULL;
  }
  if ($piece1_can_reach && $piece2_can_reach) {
    // Ambiguity - check whether a hint is given
    if (($action == '-' && strlen($move) == 4) ||
        ($action == 'x' &&
            strlen($move) == 5)) {
      $hint = $move[1];
    }
    if (empty($hint)) {
      $acerror = sprintf('both %s can reach %s', vchess_get_piece_name($move[0]), $dest_coord);
      drupal_set_message(check_plain($acerror), 'error');
      return NULL;
    }
    if ($hint >= '1' && $hint <= '8') {
      if ($coord1[1] == $hint && $coord2[1] != $hint) {
        $move_piece1 = 1;
      }
      if ($coord2[1] == $hint && $coord1[1] != $hint) {
        $move_piece2 = 1;
      }
    }
    else {
      if ($coord1[0] == $hint && $coord2[0] != $hint) {
        $move_piece1 = 1;
      }
      if ($coord2[0] == $hint && $coord1[0] != $hint) {
        $move_piece2 = 1;
      }
    }
    if (!$move_piece1 && !$move_piece2) {
      $acerror = 'could not resolve ambiguity';
      drupal_set_message(check_plain($acerror), 'error');
      return NULL;
    }
    if ($move_piece1) {
      return sprintf('%s%s%s%s', $move[0], $coord1, $action,
          $dest_coord);
    }
    else {
      return sprintf('%s%s%s%s', $move[0], $coord2, $action,
          $dest_coord);
    }
  }
  else {
    if ($piece1_can_reach) {
      return sprintf('%s%s%s%s', $move[0], $coord1, $action,
          $dest_coord);
    }
    else {
      return sprintf('%s%s%s%s', $move[0], $coord2, $action,
          $dest_coord);
    }
  }

  // If we got here chessman move could not be parsed
  $acerror = 'could not parse chessman move ' . $move;
  drupal_set_message(check_plain($acerror), 'error');
  return NULL;
}


/**
 * Get short notation of move for move history.  
 * e.g. Pe2-e4 -> e4
 *      Re1xNa6 -> Rxa6
 *
 * XXX Use autocomplete for this in a pretty messy way.
 * If an error occurs the move is kept unchanged (no error is generated).
 *
 */
function vchess_long_to_short_move($player, $long_move_string, $board) {
  // If all else fails, just return the long move
  $short_move_string = $long_move_string;
  
  $move = new Move();
  $move->initialise($long_move_string);

  $source_piece = $move->source_piece();
  $dest_piece = $move->dest_piece();
  
  $source_coord = $move->source_coord();
  $dest_coord = $move->dest_coord();

  switch ($source_piece) {
    case 'P':
      // Always skip P. For attacks skip source digit
      // and for moves skip source pos and "-"
      if ($move->type() == '-') {
        $short_move_string = substr($long_move_string, 4);
      }
      else {
        $short_move_string = $long_move_string[1] . "x" . $dest_coord;
      }
      break;
    case 'B':
    case 'Q':
    case 'K':
      // Since bishop always travels on its own color and the queen and king 
      // are both unique, these pieces always have unambiguous moves
      // @todo: In fact, the only piece which is unique is the king, since, with pawn promotion
      // you can have 2 queens, 2 white bishops   
      if ($move->type() == "x") {
        $short_move_string = $source_piece . "x" . $move->dest_coord();
      }
      else {
        $short_move_string = $source_piece . $move->dest_coord();
      }
      break;
    default: 
      // Try to remove the source position and check whether it
      // is a non-ambigious move. If it is not add one of the components
      // and check again.
      //     if ($long_move_string[3] == '-') {
      //       $dest = substr($long_move_string, 4);
      //     }
      //     elseif ($long_move_string[3] == 'x') {
      //       $dest = substr($long_move_string, 3);
      //     }
      
      //     $short_move_string = sprintf('%s%s', $long_move_string[0], $dest);
      //     if (vchess_short_to_long_move($player, $short_move_string, $board) == NULL) {
      //       // Add source column [a-h]
      //       $short_move_string = sprintf('%s%s%s', $long_move_string[0], $long_move_string[1], $dest);
      //       if (vchess_short_to_long_move($player, $short_move_string, $board) == NULL) {
      //         // Add source row [1-8]
      //         $short_move_string = sprintf('%s%s%s', $long_move_string[0], $long_move_string[2], $dest);
      //         if (vchess_short_to_long_move($player, $short_move_string, $board) == NULL) {
      //           $short_move_string = $long_move_string; // give up
      //         }
      //       }
      //     }
      
  }

  return $short_move_string;
}

