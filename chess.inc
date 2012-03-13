<?php

/**
 * @file
 * 
 * Chess game logic to handle moves. 
 */

/**
 * Return the array of adjacent squares (<=8)
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

/**
 * Check a number of squares given a start, an end square
 * (which is not included to the check) and a position
 * change for each iteration. Return TRUE if not blocked.
 * All values are given for 1dim board. 
 * 
 * @param $start index of square, 0..63
 * @param $end   index of square, 0..63 
 */
function vchess_path_is_not_blocked($start, $end, $change, $board) {
  $blocked = FALSE;

  for ($pos = $start; $pos != $end; $pos += $change) {
    $coord = vchess_i2coord($pos);
    if (!$board->square_is_empty($coord)) {
      $blocked = TRUE;
    }
  }
  
  return !$blocked;
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
 */
function vchess_get_path_change($piece, $piece_pos, $dest_pos) {
  $change = 0;
  $piece_y = floor($piece_pos / 8);
  $piece_x = $piece_pos % 8;
  $dest_y = floor($dest_pos / 8);
  $dest_x = $dest_pos % 8;
  switch ($piece) {
    /* bishop */
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
      /* rook */
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
      /* queen */
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
 * Check whether dest_coord is in reach for piece of piece_type
 * at square piece_coord. It is not checked whether the square
 * itself is occupied but only the squares in between.
 */
function vchess_square_is_reachable($piece, $piece_coord, $dest_coord, Board $board) {
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
        $reachable = vchess_check_pawn_attacks($piece_coord, $dest_coord, $board);
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
        if (vchess_path_is_not_blocked($piece_pos + $change, $dest_pos, $change, $board)) {
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
        if (vchess_path_is_not_blocked($piece_pos + $change, $dest_pos, $change, $board)) {
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
        if (vchess_path_is_not_blocked($piece_pos + $change, $dest_pos, $change, $board)) {
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
          if ($board->get_piece($coord)->get_type() == 'K') {
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
function vchess_check_pawn_attacks($piece_coord, $dest_coord, $board) {
  $attacks = FALSE;

  $piece_color = $board->get_piece($piece_coord)->get_color();

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
 * Check whether pawn at $piece_coord may move to $dest_coord.
 * First move may be two squares instead of just one.
 * 
 * @return 
 *   TRUE if the pawn may move to the given square
 *   FALSE if the destination square is occupied or if a square
 *     on the way is occupied for the first 2-square move
 */
function vchess_check_pawn_move($piece_coord, $dest_coord, $board) {
  $move_ok = FALSE;  

  if ($board->square_is_empty($dest_coord)) {
    $piece = $board->get_piece($piece_coord);
    $piece_file = vchess_coord_file($piece_coord); // e.g. e
    $piece_rank = vchess_coord_rank($piece_coord); // e.g. 2
    $dest_file = vchess_coord_file($dest_coord);  // e.g. e
    $dest_rank = vchess_coord_rank($dest_coord);  // e.g. 4
    
    // Check pawn stays on same file.  
    // Captures are checked in vchess_check_pawn_attacks()
    if ($piece_file <> $dest_file) {
      $move_ok = FALSE;
    }
    elseif ($piece->get_color() == 'w') {
      // white pawn
      if ($piece_rank == 2 && $dest_rank == 4) {
        // Pawn moving 2 squares, so check if intermediate square is empty
        $intermediate_coord = $piece_file . "3";
        if ($board->square_is_empty($intermediate_coord)) {
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
        if ($board->square_is_empty($intermediate_coord)) {
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
 * Check all pieces of player whether they attack the given position.
 * 
 * @param $attacker
 *   Color of player for whom attack is being tested
 *   
 * @param $dest_coord 
 *   The coordinate which is being checked to see if it is under attack e.g. "d4"
 * 
 * @param $board
 * 
 */
function vchess_square_is_under_attack($attacker, $dest_coord, $board) {
  $under_attack = FALSE;
  
  for ($square = 0; $square < 64; $square++) {
    $piece_coord = vchess_i2coord($square);
    $piece = $board->get_piece($piece_coord);
    $piece_type = $piece->get_type();
    $piece_color = $piece->get_color();
    if ($piece_type <> "" && $piece_color == $attacker
    && vchess_square_is_reachable($piece_type, $piece_coord, $dest_coord, $board)) {
      $under_attack = TRUE;
    }
  }

  return $under_attack;
}

/**
 * Check whether player's king is in check. 
 */
function vchess_king_is_in_check($player_color, Board $board) {
  // First find out on which square is the given player's king
  $found = FALSE;
  $index = 0;
  while (!$found && $index <= 63) {
    $coord = vchess_i2coord($index);
    if ($board->get_piece($coord)->get_color() == $player_color
    && $board->get_piece($coord)->get_type() == "K") {
      $king_pos = $coord;
      $found = TRUE;
    }
    else {
      $index++;
    }  
  }
  
  if ($player_color == 'w') {
//    return vchess_square_is_under_attack('b', $king_pos, $board);
      return vchess_square_is_under_attack('b', $coord, $board);
  }
  else {
//    return vchess_square_is_under_attack('w', $king_pos, $board);
    return vchess_square_is_under_attack('w', $coord, $board);
  }

  return FALSE;
}

/**
 * Check whether player's king is in checkmate
 */
function vchess_is_checkmate($player, Board $board) {
  return FALSE; // Hugh's temporary fudge 
  
  //global $board;

  if ($player == 'w') {
    $opp = 'b';
  }
  else {
    $opp = 'w';
  }

  // Find the position of the player's king
  for ($square = 0; $square < 64; $square++) {
    $coord = vchess_i2coord($square);
    $piece = $board->get_piece($coord);
    if ($piece->get_type() == 'K' && $piece->get_color() == $player) {
      $king_pos = $square;
      $king_x = $square % 8;
      $king_y = floor($square / 8);
      break;
    }
  }

  // Test adjacent squares while king is temporarily removed 
  $adj_squares = vchess_get_adjacent_squares($king_pos);
//  kpr($adj_squares);
  $contents = $board[$king_pos];
  $board[$king_pos] = '';
  foreach ($adj_squares as $dest_pos) {
    if ($board[$dest_pos][0] == $player) {
      continue;
    }
    if (vchess_square_is_under_attack($opp, $dest_pos, $board)) {
      continue;
    }
    $board[$king_pos] = $contents;
    return 0;
  }
  $board[$king_pos] = $contents;

  // DEBUG: echo 'King cannot escape by itself! '; 

  // Get all pieces that attack the king
  $attackers = array();
  $count = 0;
  for ($square = 0; $square < 64; $square++) {
    if ($board[$square][0] == $opp) {
      if (($board[$square][1] == 'P' && vchess_check_pawn_attacks($square, $king_pos, $board)) 
      || ($board[$square][1] != 'P' && vchess_square_is_reachable($board[$square][1], $square, $king_pos, $board))) {
        $attackers[$count++] = $square;
      }
    }
  }

  // If more than one there is no chance to escape
  if ($count > 1) {
    return 1;
  }

  // Check whether attacker can be killed by own piece
  $dest_pos = $attackers[0];
  for ($square = 0; $square < 64; $square++) {
    if ($board[$square][0] == $player) {
      $square_coord = vchess_i2coord($square);
      $dest_coord = vchess_i2coord($dest_pos);
      if (($board[$square][1] == 'P' 
      && vchess_check_pawn_attacks($square_coord, $dest_coord, $board)) ||
      ($board[$square][1] != 'P' &&
      $board[$square][1] != 'K' &&
      vchess_square_is_reachable($board[$square][1], $square, $dest_pos, $board)) ||
      ($board[$square][1] == 'K' &&
      vchess_square_is_reachable($board[$square][1], $square,
      $dest_pos, $board) &&
      !vchess_square_is_under_attack($opp, $dest_pos, $board))) {
          // DEBUG: echo 'candidate: $square '; 
          $can_kill_atk = 0;
          $contents_def = $board[$square];
          $contents_atk = $board[$dest_pos];
          $board[$dest_pos] = $board[$square];
          $board[$square] = '';
          if (!vchess_square_is_under_attack($opp, $king_pos, $board)) {
            $can_kill_atk = 1;
          }
          $board[$square] = $contents_def;
          $board[$dest_pos] = $contents_atk;
          if ($can_kill_atk) {
            // DEBUG: echo '$square can kill attacker';
          return 0;
        }
      }
    }
  }

  // Check whether own unit can block the way

  // If attacking unit is a knight there
  // is no way to block the path. 
  if ($board[$dest_pos][1] == 'N') {
    return 1;
  }

  // If enemy is adjacent to king there is no
  // way either 
  $dest_x = $dest_pos % 8;
  $dest_y = floor($dest_pos / 8);
  if (abs($dest_x -$king_x) <= 1 && abs($dest_y -$king_y) <= 1) {
    return 1;
  }

  /* Get the list of tiles between king and attacking
   * unit that can be blocked to stop the attack */
  $change = vchess_get_path_change($board[$dest_pos][1], $dest_pos, $king_pos);
  /* DEBUG:  echo 'path change: $change '; */
  $path = vchess_get_path($dest_pos + $change, $king_pos, $change);
  /* DEBUG: foreach($path as $tile) echo 'tile: $tile '; */
  foreach ($path as $pos) {
    for ($square = 0; $square < 64; $square++) {
      if ($board[$square][0] == $player) {
        if (($board[$square][1] == 'P' &&
      vchess_check_pawn_move($square, $pos, $board)) ||
      ($board[$square][1] != 'P' &&
      $board[$square][1] != 'K' &&
      vchess_square_is_reachable($board[$square][1], $square, $pos, $board))) {
          $board[$pos] = $board[$square];
          $old = $board[$square];
          $board[$square] = '';
          $is_bound = vchess_king_is_in_check($player, $board);
          $board[$square] = $old;
          $board[$pos] = '';
          if (!$is_bound) {
            /*DEBUG: echo '$square can block ';*/
            return 0;
          }
        }
      }
    }
  }
  return 1;
}

/**
 * HACK: this function checks whether en-passant is possible 
 */
function vchess_en_passant_is_ok($player, $pos, $dest, $opp_ep_flag) {
  if ($opp_ep_flag != 'x') {
    if ($dest % 8 == $opp_ep_flag) { /* if (vchess_check_pawn_attacks($pos,$dest)) right now
   * this is not required as we only use this
   * function in isStaleMate which uses correct dests */
      if (($player == 'w' && floor($dest / 8) == 5) ||
  ($player == 'b' && floor($dest / 8) == 2)) {
        return 1;
      }
    }
  }
  return 0;
}


/**
 * Move chessman from pos to dest, check whether king is under attack and
 * restore the old board settings. whether pos-> dest is a valid move is
 * NOT checked! 
 */
function vchess_move_is_ok($player, $pos, $dest, $board) {
  //global $board;

  // DEBUG: echo '$player-$opp: $pos-> $dest: '; 
//  $pos_bc = vchess_i2coord($pos);
//   $old_pos = $board[$pos];
//   $old_dest = $board[$dest];
//   $board[$dest] = $board[$pos];
//   $board[$pos] = '';
  if (vchess_king_is_in_check($player, $board)) {
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
function vchess_is_stalemate($player, $w_ep, $b_ep, Board $board /*line of en-passant*/) {
  if ($player == 'w') {
    $opp = 'b';
  }
  else {
    $opp = 'w';
  }

  // Look at each square to find each of the opponent pieces
  for ($square = 0; $square < 64; $square++) {
    $coord = vchess_i2coord($square);
    if (!$board->square_is_empty($coord)) {
      if ($board->player_on_square($coord) != $player) {
        // If the square doesn't have an opponent piece, then skip to the next square
        continue;
      }
      // Can the piece move theoretically thus is there
      // at least one tile free for one piece?
//      switch ($board[$square][1]) {
      switch ($board->get_piece($coord)->get_type()) {
        case 'K':
          $adj_squares = vchess_get_adjacent_squares($square);
          foreach ($adj_squares as $pos) {
            if ($board->player_on_square($pos) == $player) {
              continue;
            }
            if (vchess_square_is_under_attack($opp, $pos, $board)) {
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
            if ($board[$square + 8] == '' && vchess_move_is_ok($player, $square, $square + 8, $board)) {
              return FALSE;
            }
            if (($square % 8) > 0 && ($board->player_on_square($square + 7) == $opp || vchess_en_passant_is_ok('w', $square, $square + 7, $b_ep))) {
              if (vchess_move_is_ok($player, $square, $square + 7, $board)) {
                return FALSE;
              }
            }
            if (($square % 8) < 7 && ($board->player_on_square($square + 9) == $opp || vchess_en_passant_is_ok('w', $square, $square + 9, $b_ep))) {
              if (vchess_move_is_ok($player, $square, $square + 9, $board)) {
                return FALSE;
              }
            }
          }
          else {
            $coord = vchess_i2coord($square - 8);
            if ($board->square_is_empty($coord) && vchess_move_is_ok($player, $square, $square -8, $board)) {
              return FALSE;
            }
            if (($square % 8) > 0 && ($board->player_on_square($square -9) == $opp || vchess_en_passant_is_ok('b', $square, $square -9, $w_ep))) {
              if (vchess_move_is_ok($player, $square, $square -9, $board)) {
                return FALSE;
              }
            }
            if (($square % 8) < 7 && ($board->player_on_square($square -7) == $opp || vchess_en_passant_is_ok('b', $square, $square -7, $w_ep))) {
              if (vchess_move_is_ok($player, $square, $square -7, $board)) {
                return FALSE;
              }
            }
          }
          break;
        case 'B':
          if ($square -9 >= 0   && $board->player_on_square($square -9) != $player &&
          vchess_move_is_ok($player, $square, $square -9, $board)) {
            return FALSE;
          }
          if ($square -7 >= 0   && $board->player_on_square($square -7) != $player &&
          vchess_move_is_ok($player, $square, $square -7, $board)) {
            return FALSE;
          }
          if ($square + 9 <= 63 && $board->player_on_square($square + 9) != $player &&
          vchess_move_is_ok($player, $square, $square + 9, $board)) {
            return FALSE;
          }
          if ($square + 7 <= 63 && $board->player_on_square($square + 7) != $player &&
          vchess_move_is_ok($player, $square, $square + 7, $board)) {
            return FALSE;
          }
          break;
        case 'R':
//          kpr($board);
          if ($square -8 >= 0 && $board->player_on_square($square -8) != $player 
          && vchess_move_is_ok($player, $square, $square -8, $board)) {
            return FALSE;
          }
          if ($square -1 >= 0 && $board->player_on_square($square -1) != $player &&
          vchess_move_is_ok($player, $square, $square -1, $board)) {
            return FALSE;
          }
          if ($square + 8 <= 63 && $board->player_on_square($square + 8) != $player &&
          vchess_move_is_ok($player, $square, $square + 8, $board)) {
            return FALSE;
          }
          if ($square + 1 <= 63 && $board->player_on_square($square + 1) != $player &&
          vchess_move_is_ok($player, $square, $square + 1, $board)) {
            return FALSE;
          }
          break;
        case 'Q':
          $adj_squares = vchess_get_adjacent_squares($square);
          foreach ($adj_squares as $pos) {
            $coord = vchess_i2coord($pos);
//            if ($board[$pos][0] != $player) {
            if ($board->player_on_square($pos) != $player) {
              if (vchess_move_is_ok($player, $square, $pos, $board)) {
                return FALSE;
              }
            }
          }
          break;
        case 'N':
//          kpr($board);
          if ($square -17 >= 0   && $board->player_on_square($square -17) != $player &&
          vchess_move_is_ok($player, $square, $square -17, $board)) {
            return FALSE;
          }
          if ($square -15 >= 0   && $board->player_on_square($square -15) != $player &&
          vchess_move_is_ok($player, $square, $square -15, $board)) {
            return FALSE;
          }
          if ($square -6 >= 0   && $board->player_on_square($square -6) != $player &&
          vchess_move_is_ok($player, $square, $square -6, $board)) {
            return FALSE;
          }
          if ($square + 10 <= 63 && $board->player_on_square($square + 10) != $player &&
          vchess_move_is_ok($player, $square, $square + 10, $board)) {
            return FALSE;
          }
          if ($square + 17 <= 63 && $board->player_on_square($square + 17) != $player &&
          vchess_move_is_ok($player, $square, $square + 17, $board)) {
            return FALSE;
          }
          if ($square + 15 <= 63 && $board->player_on_square($square + 15) != $player &&
          vchess_move_is_ok($player, $square, $square + 15, $board)) {
            return FALSE;
          }
          if ($square + 6 <= 63 && $board->player_on_square($square + 6) != $player &&
          vchess_move_is_ok($player, $square, $square + 6, $board)) {
            return FALSE;
          }
          if ($square -10 >= 0   && $board->player_on_square($square -10) != $player &&
          vchess_move_is_ok($player, $square, $square -10, $board)) {
            return FALSE;
          }
          break;
      }
    }
  }
  
  return TRUE;
}


/**
 * Verify move (full notation), execute it and modify game.
 */
function vchess_handle_move($gid, $uid, $move, $comment) {
//  global $acerror, $mail_from;
  
  $game = new Game();
  $game->load_game($gid);
  
  $mate_type = "Hugh was here setting a default";
  
  // w2spm = white 2 square piece move?
  // b2spm = black 2 square piece move?
//  $game['w2spm'] = ""; // Hugh - added initialisation
//  $game['b2spm'] = ""; // Hugh - added initialisation
  
  $board = $game->get_board();

  $move = trim($move);
  $result = 'undefined';
  $move_handled = FALSE;

  // Check whether moving is okay and get some vars.
//  $player_w = $game['white'];
//  $player_b = $game['black'];
//  $cur_move = $game['turn'];
  
  if (!$game->is_players_move($uid)) {
    return 'It is not your turn!';
  }
  if ($game->get_turn() == 'w') {
    $cur_opp = 'b';
  }
  else {
    $cur_opp = 'w';
  }

  // if ($game['curstate'] != '?' && $game['curstate'] != 'D') {
//    return 'Game is over.';
//  }

  /* Castling meaning:
   * 0 - rook or king moved
   * 1 - possible
   * 9 - performed */
  /*
  if ($game['turn'] == 'w') {
    $may_castle_short = $game['wcs']; // wcs = white castle short
    $may_castle_long = $game['wcl'];  // wcl = white castle long
  }
  else {
    $may_castle_short = $game['bcs'];  // bcs = black castle short
    $may_castle_long = $game['bcl'];   // bcl = black castle long
  }
  */
  $may_castle_short = FALSE; // Hugh default
  $may_castle_long = FALSE;  // Hugh default

  /* DEBUG echo 'HANDLE: w=$game['white'],b=$game['black'],c=$game['turn'],';
   echo 'm=$cur_move,may_castle=$may_castle_short,';
   echo '$may_castle_long  <BR>';*/

  // Allow two-step of king to indicate castling. 
  if ($game->get_turn() == 'w' && $move == 'Ke1-g1') {
    $move = 'O-O';
  }
  elseif ($game->get_turn() == 'w' && $move == 'Ke1-c1') {
    $move = 'O-O-O';
  }
  elseif ($game->get_turn() == 'b' && $move == 'Ke8-g8') {
    $move = 'O-O';
  }
  elseif ($game->get_turn() == 'b' && $move == 'Ke8-c8') {
    $move = 'O-O-O';
  }

  // Accept "---" although it is now called resign.
//  if ($move == '---' || $move == 'resign') {
//    $move = 'resigned';
//  }

  // Remember move for history 
  $history_move = $move;

  // Clear last move 
//  $game['lastmove'] = 'x';
//  $game['lastkill'] = 'x';
//  $game['oscf'] = 'x';
//  $game['olcf'] = 'x';

  // HANDLE MOVES:
  // resign                            resign
  // O-O                               short castling
  // O-O-O                             long castling
  // draw?                             offer a draw
  // accept_draw                       accept the draw
  // refuse_draw                       refuse the draw
  // [PRNBQK][a-h][1-8][-:x][a-h][1-8] unshortened move
  if ($move == 'draw?' && $game->get_curstate() == '?') {
    // Offer draw
    $game->set_curstate('D');
    $result = 'You have offered a draw.';
    $draw_handled = 1;
    $game['lastmove'] = 'DrawOffered';
  }
  elseif ($move == 'refuse_draw' && $game->get_curstate() == 'D') {
    // Refuse draw
    $game->set_curstate('?');
    $draw_handled = 1;
    $result = 'You refused the draw.';
    $game['lastmove'] = 'DrawRefused';
  }
  elseif ($move == 'accept_draw' && $game->get_curstate() == 'D') {
    // Accept draw
    $game->set_curstate('-');
    $draw_handled = 1;
    $result = 'You accepted the draw.';
    $game->set_last_move('DrawAccepted');
    if ($game['curplyr'] == 'b') {
      $game['curmove']++; // new move as white offered
    }
    $game['mhistory'][count($game['mhistory'])] = 'draw';
  }
  elseif ($move == 'O-O') {
    // Short castling
    if ($may_castle_short != 1 || $may_castle_long == 9) {
      return 'ERROR: You cannot castle short anymore!';
    }
    if ($game['turn'] == 'b' && $board[61] == '' && $board[62] == '') {
      if (vchess_king_is_in_check('b', $board)) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if (vchess_square_is_under_attack('w', 62, $board) ||
      vchess_square_is_under_attack('w', 61, $board)) {
        return 'ERROR: Either king or rook would be under attack after short castling!';
      }
      $may_castle_short = 9;
      $board[60] = '';
      $board[62] = 'bK';
      $board[61] = 'bR';
      $board[63] = '';
    }
    if ($game['turn'] == 'w' && $board[5] == '' && $board[6] == '') {
      if (vchess_king_is_in_check('w', $board)) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if (vchess_square_is_under_attack('b', 5, $board) ||
      vchess_square_is_under_attack('b', 6, $board)) {
        return 'ERROR: Either king or rook would be under attack after short castling!';
      }
      $may_castle_short = 9;
      $board[4] = '';
      $board[6] = 'wK';
      $board[5] = 'wR';
      $board[7] = '';
    }
    if ($may_castle_short != 9) {
      return 'ERROR: Cannot castle short because the way is blocked!';
    }
    $result = 'You castled short.';
    $move_handled = 1;
    $game['lastmove'] = 'O-O';
  }
  elseif ($move == 'O-O-O') {
    // Long castling
    if ($may_castle_long != 1 || $may_castle_short == 9) {
      return 'ERROR: You cannot castle long anymore!';
    }
    if ($game['turn'] == 'b' && $board[57] == '' && $board[58] == '' &&
    $board[59] == '') {
      if (vchess_king_is_in_check('b', $board)) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if (vchess_square_is_under_attack('w', 58, $board) ||
      vchess_square_is_under_attack('w', 59, $board)) {
        return 'ERROR: Either king or rook would be under attack after short castling!';
      }
      $may_castle_long = 9;
      $board[56] = '';
      $board[58] = 'bK';
      $board[59] = 'bR';
      $board[60] = '';
    }
    if ($game['turn'] == 'w' && $board[1] == '' && $board[2] == '' &&
    $board[3] == '') {
      if (vchess_king_is_in_check('w', $board)) {
        return 'ERROR: You cannot escape check by castling!';
      }
      if (vchess_square_is_under_attack('b', 2, $board) ||
      vchess_square_is_under_attack('b', 3, $board)) {
        return 'ERROR: Either king or rook would be under attack after short castling!';
      }
      $may_castle_long = 9;
      $board[0] = '';
      $board[2] = 'wK';
      $board[3] = 'wR';
      $board[4] = '';
    }
    if ($may_castle_long != 9) {
      return 'ERROR: Cannot castle long because the way is blocked!';
    }
    $result = 'You castled long.';
    $move_handled = 1;
    $game['lastmove'] = 'O-O-O';
  }
  else {
    // Normal move: [PRNBQK][a-h][1-8][-:x][a-h][1-8][RNBQK]

    // Autocomplete short notation (or keep full)
    $acmove = vchess_short_to_long_move($game->get_turn(), $move, $board);
    if ($acmove == NULL) {
      return 'ERROR: autocomplete: ' . $acerror;
    }
    else {
      $move = $acmove;
    }

    // A final capital letter may only be N,B,R,Q for the
    // appropiate chessman.
    // FIXME it is not checked whether multiple promotion identifiers are
    // there. In that case last one is used and move executed properly
    // but the history browsing will be broken.
    $c = $move[strlen($move) -1];
    if ($c >= 'A' && $c <= 'Z' &&
    $c != 'N' && $c != 'B' && $c != 'R' && $c != 'Q') {
      return 'ERROR: only N (knight),B (bishop),R (rook) and Q (queen) are valid chessman';
    }

    // Validate piece and position.
    // Move is e.g. "Nb1-c3"
    $piece_type = $move[0];
    $piece = new Piece();
    $piece->set_type($piece_type);
    $piece->set_color($game->get_turn());
//     $piece_name = vchess_get_piece_name($piece_type);
//     if ($piece_name == BLANK) {
//       return 'ERROR: Piece ' . $piece_type . ' is unknown!';
//     }
    $piece_coord = $move[1] . $move[2];
    $piece_pos = vchess_coord2i($piece_coord);
//     if ($piece_pos == 64) {
//       return 'ERROR: ' . $piece_coord . ' is invalid!';
//     }
    /* DEBUG  echo 'piece_type: $piece_type,piece_pos: $piece_pos<BR>'; */
//     if ($board[$piece_pos] == '') {
//       return 'ERROR: ' . $piece_coord . ' is empty.';
//     }
//     if ($board[$piece_pos][0] != $game->get_turn()) {
//       return 'ERROR: Piece does not belong to you!';
//     }
//     if ($board[$piece_pos][1] != $piece_type) {
//       return 'ERROR: Piece does not exist!';
//     }

    // Get target square
    $dest_coord = vchess_move_dest_coord($move);
    if ($dest_coord == $piece_coord) {
      return 'ERROR: Current position and destination are equal!';
    }

    // Get action
    if ($move[3] == '-') {
      $action = 'M'; /* move */
    }
    elseif ($move[3] == 'x') {
      $action = 'A'; /* attack */
    }
    else {
      return 'ERROR: ' . $action . ' is unknown! Please use "-" for move and "x" for attack.';
    }
    // Replace - with x if this is meant to be en-passant.
    $dest_pos = vchess_coord2i($dest_coord);
    if ($piece_type == 'P' && (abs($piece_pos - $dest_pos) == 7 || abs($piece_pos - $dest_pos) == 9)) {
      $action = 'A';
      $history_move = str_replace('-', 'x', $history_move);
      $move = str_replace('-', 'x', $move);
    }

    // Save for undo 
    $game->set_last_move(str_replace('?', '', $move));

    // If attacking, an enemy unit must be present on square
    // and if move then square must be empty. In both cases
    // the king must not be in check after moving.

    // Check whether the move is along a valid path and
    // whether all squares in between are empty thus the path
    // is not blocked. The final destination square is not
    // checked here.
    $piece_name = $piece->get_name();
    $piece_coord = vchess_i2coord($piece_pos);
    $dest_coord = vchess_i2coord($dest_pos);
    if ($piece_type != 'P') {
      if (!vchess_square_is_reachable($piece_type, $piece_coord, $dest_coord, $board)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
    }
    else {
      if ($action == 'M' && !vchess_check_pawn_move($piece_coord, $dest_coord, $board)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal advancing move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
      elseif ($action == 'A' && !vchess_check_pawn_attacks($piece_coord, $dest_coord, $board)) {
        return 'ERROR: ' . $dest_coord . ' is not a legal capturing move of ' . $piece_name . ' at ' . $piece_coord . '!';
      }
    }

    $en_passant_okay = 0;
    // Check action
    $coord = vchess_i2coord($dest_pos);
    if ($action == 'M' && !$board->square_is_empty($coord)) {
      return 'ERROR: ' . $dest_coord . ' is occupied!';
    }
    if ($action == 'A' && $board->square_is_empty($dest_coord)) {
      // En passant of pawn? 
      if ($piece_type == 'P') {
        if ($game['turn'] == 'w') {
          if ($game['b2spm'] != 'x' &&
          $dest_pos % 8 == $game['b2spm'] &&
          floor($dest_pos / 8) == 5) {
            $en_passant_okay = 1;
          }
        }
        else {
          if ($game['w2spm'] != 'x' &&
          $dest_pos % 8 == $game['w2spm'] &&
          floor($dest_pos / 8) == 2) {
            $en_passant_okay = 1;
          }
        }
        if ($en_passant_okay == 0) {
          return 'ERROR: En-passant is not possible!';
        }
      }
      else {
        return 'ERROR: ' . $dest_coord . ' is empty!';
      }
    }
    if ($action == 'A' && $board->get_piece($dest_coord)->get_color() == $game->get_turn()) {
      return 'ERROR: You cannot attack own chessman at ' . $dest_coord . '!';
    }

    // Backup affected squares
    $piece_coord = vchess_i2coord($piece_pos);
    $dest_coord = vchess_i2coord($dest_pos);
    $old_piece_square = $board->get_piece($piece_coord);
    $old_dest_square = $board->get_piece($dest_coord);

    // Get short notation of move for history before
    // actually moving since this function relies on
    // autocomplete which in turn requires the initial board
    // to work properly (after move, first chessman is on
    // destination square already and second can't reach it
    // which would result in 'no one can reach' error)
    if (strlen($history_move) >= 6) {
      $history_move = vchess_long_to_short_move($game->get_turn(), $history_move, $board);
    }
    // DEBUG: echo 'Move: $move ($history_move)<BR>'; 

    // Perform move 
//    $board[$piece_pos] = '';
    $dest_coord = vchess_i2coord($dest_pos);
    if ($board->get_piece($dest_coord)->get_type() != BLANK) {
//      $game->set_last_kill(sprintf('%s%s', $board->get_piece_by_index($dest_pos), $dest_pos));
      $game->set_last_kill($board->get_piece($dest_coord));
    }
    $board->move_piece($piece_coord, $dest_coord);
    if ($en_passant_okay) {
      // Kill pawn
      if ($game['turn'] == 'w') {
//         $board[$dest_pos - 8] = '';
//        $game->set_last_kill(sprintf('bP%s', $dest_pos -8));
//         $game->set_last_kill(sprintf('bP%s', $dest_pos -8));
      }
      else {
//         $board[$dest_pos + 8] = '';
//         $game->set_last_kill(sprintf('wP%s', $dest_pos + 8));
      }
    }

    // If king is in check undo 
    if (vchess_king_is_in_check($game->get_turn(), $board)) {
//       $board[$piece_pos] = $old_piece_square;
//       $board[$dest_pos] = $old_dest_square;
//       if ($en_passant_okay) {
//         // Respawn en-passant pawn 
//         if ($game->get_turn() == 'w') {
//           $board[$dest_pos -8] = 'bP';
//         }
//         else {
//           $board[$dest_pos + 8] = 'wP';
//         }
//       }
      return 'ERROR: King is in check.';
    }

    // Check whether this forbids any castling
    if ($piece_type == 'K') {
      if ($may_castle_short) {
        $may_castle_short = FALSE;
      }
      if ($may_castle_long) {
        $may_castle_long = FALSE;
      }
    }
    if ($piece_type == 'R') {
      if ($may_castle_long && ($piece_pos % 8) == 0) {
        $may_castle_long = FALSE;
      }
      if ($may_castle_short && ($piece_pos % 8) == 7) {
        $may_castle_short = FALSE;
      }
    }

    // If a pawn moved two squares this will allow 'en passant'
    // for next turn. 
    //
    if ($piece_type == 'P' && abs($piece_pos - $dest_pos) == 16) {
      if ($game->get_turn() == 'w') {
        $game->set_w2spm($piece_pos % 8);
      }
      else {
        $game->set_b2spm($piece_pos % 8);
      }
    }
    else {
      // Clear 'en passant' of OUR last move 
      if ($game->get_turn() == 'w') {
        $game->set_w2spm('x');
      }
      else {
        $game->set_b2spm('x');
      }
    }

    $result = 'Your last move: ' . vchess_long_to_short_move($game->get_player_color($uid), 
        str_replace('?', '', $move), $board);

    // If pawn reached last line promote it
    if ($piece_type == 'P') {
      if (($game->get_turn() == 'w' && $dest_pos >= 56) ||
      ($game->get_turn() == 'b' && $dest_pos <= 7)) {
        $pawn_upg = $move[strlen($move) -1];
        if ($pawn_upg == '?') {
          $pawn_upg = 'Q';
          $history_move = sprintf('%sQ',
          $history_move);
        }
        $board[$dest_pos] = $game->get_turn() . $pawn_upg;
        $result = sprintf('%s... promotion to %s!',
        $result, vchess_get_piece_name($pawn_upg));
      }
    }

    $move_handled = TRUE;
  }

  // If move was executed update game state. 
  if ($move_handled) {
    // Check checkmate/stalemate 
    if (vchess_king_is_in_check($cur_opp, $board)) {
      // If this is check mate finish the game otherwise
      // add '+' to the move. 
      if (vchess_is_checkmate($cur_opp, $board)) {
        $game->set_curstate($game->get_turn());
        $mate_type = 1;
      }
      else {
        $result = $result . '... CHECK!';
      }
      $history_move = sprintf('%s+', $history_move);
    }
    elseif (vchess_is_stalemate($cur_opp, $game->get_w2spm(), $game->get_b2spm(), $board)) {
      $game->set_curstate('-');
      $mate_type = 2;
    }

    // Backup castling info if rook or king has moved
//     if ($piece_type == 'R' || $piece_type == 'K') {
//       if ($game->get_turn() == 'w') {
//         // oscf = old short castle flag??
//         // olcf = old long castle flag??
//         // wcs = white castle short
//         // wcl = white castle long
//         $game['oscf'] = $game['wcs'];
//         $game['olcf'] = $game['wcl'];
//       }
//       else {
//         $game['oscf'] = $game['bcs'];
//         $game['olcf'] = $game['bcl'];
//       }
//     }

    // Update castling flags
    if ($game->get_turn() == 'w') {
      $game->set_white_may_castle_short($may_castle_short);
      $game->set_white_may_castle_long($may_castle_long);
    }
    else {
      $game->set_black_may_castle_short($may_castle_short);
      $game->set_black_may_castle_long($may_castle_long);
    }

    // Update move number and current player
    if ($game->get_turn() == 'w') {
//      $game['curmove']++;
//        $game->set_curmove(1234); // Hugh default
    }
//    $game['mhistory'][count($game['mhistory'])] = $history_move;
//    $game['mhistory'][42] = $history_move; // Hugh hardcoding 42!

    // If other player can't move anymore end the game. 
    if ($mate_type > 0) {
      if ($mate_type == 1) {
        $mate_name = 'mate';
        $result = $result . '... CHECKMATE!';
      }
      else {
        $mate_name = 'stalemate';
        $result = $result . '... STALEMATE!';
      }
 //     if ($game['curplyr'] == 'b') {
//        $game['curmove']++;
      //}
//      $game['mhistory'][count($game['mhistory'])] = $mate_name;
    }
  }

  // Save move
  vchess_io_save_move($gid, $game->get_move_no(), $game->get_turn(), $move);
  
  if ($move_handled || $draw_handled) {
//    kpr($move_handled);
    // If game is over update user stats (includes resignation).
    $game->set_curstate("?"); // Hugh default
    if ($game->get_curstate() != '?' && $game->get_curstate() != 'D') {
      vchess_update_stats($game->get_player('w'), $game->get_player('b'), $game['curstate']);
//      drupal_set_message('game is over');
    }

    // Set next player
    if ($game->get_turn() == 'b') {
      $game->set_turn_white();
    }
    else {
      $game->set_turn_black();
    }

    // Add comment to head of chatter. Right now we have only two
    // chatter items. Strip backslashes and replace newlines to get
    // a single line. 
    if (empty($comment)) {
      $comment = '(silence)';
    }
    else {
      $comment = str_replace("\\", '', strip_tags($comment));
      $comment = str_replace("\n", '<br>', $comment);
    }
    $comment = '<u>' . $uid . '</u>: ' . $comment;
//    $game['chatter'][1] = $game['chatter'][0];
//    $game['chatter'][1] = "Hugh hard coding for now";
//    $game['chatter'][0] = $comment;

    // Store changed board
    $game->set_board($board);

    // Save game
    vchess_io_save_game($gid, $game);
    
    // Send a notification if email address was supplied.
//    if ($cur_opp == 'b') {
//       $oid = $game['black'];
//     }
//     else {
//       $oid = $game['white'];
//     }
    //	$email=ioLoadUserEmailAddress($oid);
    $email = FALSE; // Hugh - force following condition to be FALSE
    if ($email) {
      $prot = ($GLOBALS['_SERVER']['HTTPS'] == 'on') ? 'https' : 'http';
      $url = $prot . '://' . $GLOBALS['_SERVER']['HTTP_HOST'] . $GLOBALS['_SERVER']['SCRIPT_NAME'] . '?gid=' . $gid;
      $message = "Dear $oid\n\n$uid has just moved.\n\nMove:\n$move\n\nComment:\n$comment\n\nIt is your turn now!\n\nEnter the game:\n$url";
      mail($email, "[OCC] " . $game['white'] . "vs" . $game['black'] . ": $move",
      $message, 'From: ' . $mail_from);
    }

  }
  return $result;
}

/**
 * Undo last move (only possible if game is not over yet).
 */
function vchess_handle_undo($gid, $uid) {
  $game = new Game($gid);
  if ($game == NULL) {
    return 'ERROR: Game "' . $gid . '" does not exist!';
  }
  if (!$game['p_mayundo']) {
    return 'ERROR: Undo is not possible!';
  }

  $move = $game['lastmove'];
  $fixhist = 1;
  if ($move == 'DrawOffered' || $move == 'DrawRefused') {
    $fixhist = 0;
  }
  elseif ($move == 'O-O') {
    if ($game['curplyr'] == 'b') {
      // white castled short
      $game['board'][5] = '';
      $game['board'][6] = '';
      $game['board'][4] = 'wK';
      $game['board'][7] = 'wR';
      $game['wcs'] = 1;
    }
    else {
      // black castled short
      $game['board'][61] = '';
      $game['board'][62] = '';
      $game['board'][60] = 'bK';
      $game['board'][63] = 'bR';
      $game['bcs'] = 1;
    }
  }
  elseif ($move == 'O-O-O') {
    if ($game['curplyr'] == 'b') {
      // white castled long
      $game['board'][2] = '';
      $game['board'][3] = '';
      $game['board'][4] = 'wK';
      $game['board'][0] = 'wR';
      $game['wcl'] = 1;
    }
    else {
      // black castled long
      $game['board'][58] = '';
      $game['board'][59] = '';
      $game['board'][60] = 'bK';
      $game['board'][56] = 'bR';
      $game['bcl'] = 1;
    }
  }
  else {
    // Undo normal move (includes en passant)
    $src = vchess_coord2i($move[1] . $move[2]);
    $dest = vchess_coord2i($move[4] . $move[5]);
    if ($src >= 0 && $src < 64 && $dest >= 0 && $dest < 64) {
      if ($game['curplyr'] == 'w') {
        $oldclr = 'b';
      }
      else {
        $oldclr = 'w';
      }
      $game['board'][$src] = $oldclr . $move[0];
      $game['board'][$dest] = '';
      if ($game['lastkill'] != 'x') {
        $kill = $game['lastkill'];
        $dest = $kill[2] . $kill[3];
        $game['board'][$dest] = $kill[0] . $kill[1];
      }
      // Adjust castling flags
      if ($move[0] == 'R' || $move[0] == 'K') {
        if ($game['curplyr'] == 'b') {
          // white castling info
          $game['wcs'] = $game['oscf'];
          $game['wcl'] = $game['olcf'];
        }
        else {
          // black castling info
          $game['bcs'] = $game['oscf'];
          $game['bcl'] = $game['olcf'];
        }
      }
    }
  }

  // Fix chatter. Fill in ... for lost chatter.
  $game['chatter'][0] = $game['chatter'][1];
  $game['chatter'][1] = '<u>' . $uid . '</u>: ...';
  // Correct game state 
  if ($move == 'DrawRefused') {
    $game['curstate'] = 'D';
  }
  else {
    $game['curstate'] = '?';
  }
  
  // Clear own en passant flag
  if ($game['curplyr'] == 'b') {
    $game['w2spm'] = 'x';
  }
  else {
    $game['b2spm'] = 'x';
  }
  
  // Modify history
  if ($fixhist) {
    // Adjust turn counter if nescessary
    if ($game['curplyr'] == 'b') {
      $game['curmove'] = $game['curmove'] -1;
    }
    // Delete last move
    $square = count($game['mhistory']) -1;
    unset($game['mhistory'][$square]);
  }
  // Switch players 
  if ($game['curplyr'] == 'b') {
    $game['curplyr'] = 'w';
  }
  else {
    $game['curplyr'] = 'b';
  }
  
  // Clear last move 
  $game['lastmove'] = 'x';
  $game['lastkill'] = 'x';
  $game['oscf'] = 'x';
  $game['olcf'] = 'x';
  
  // Save game
  vchess_io_save_game($gid, $game);

  return 'Move "' . $move . '" undone!';
}
