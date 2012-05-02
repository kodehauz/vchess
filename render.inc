<?php

/**
 * @file
 * Functions for creating renderable (HTML) versions of different things
 */

/**
 * Create a visible view of the board
 * 
 * @param array $board
 *   This is an array of 64 elements, one per square.  A square will
 *   either be empty or contain a 2-character indication of color
 *   followed by piece, e.g. "wR" is white rook, "bP" is black Pawn.
 *   
 * @param char $player
 *   This indicates the color of the player who is playing, and thus the 
 *   viewing perspective of the board.  It is either 'w' (white) or 'b' (black).  
 *   If it is empty then the game is being observed
 *   by someone who is not the player.  Note that player is not necessarily the
 *   same as the person who is to move.  It may be the player is white but it is
 *   black to move.
 *   
 * @param $active
 *   This indicates whether or not the board allows moves or not (i.e. whether the
 *   board is in a view-only state or not) 
 *   
 * @param $gid
 *   Game id
 * 
 */
function vchess_render_board($board, $player, $active, $gid) {
  global $user;
  
  $uid = $user->uid;
  $path = "http://localhost" . base_path() . drupal_get_path('module', 'vchess');

  $theme = 'default'; // later add global theme

  if (isset($_POST['cmd']) && !empty($_POST['cmd'])) {
    $cmd = $_POST['cmd'];  // e.g. Pe2-e4

    $cmdres = '';

    $game = new Game();
    $game->load($gid);
    
    // Save the values for when we need to save to database
    $player_with_turn = $game->turn();
    $move_no = $game->move_no();
    
    if ($cmd == 'abort') {
      $cmdres = $game->abort($uid);
    }
    elseif ($cmd == 'acceptdraw') {
      $cmdres = $game->make_move($uid, 'accept_draw');
    }
    elseif ($cmd == 'refusedraw') {
      $cmdres = $game->make_move($uid, 'refuse_draw');
    }
    else { // try as chess move
      $cmdres = $game->make_move($uid, $cmd);
    }
    
    // Only save move and game if a move has actually been made
    if ($player_with_turn <> $game->turn()) {
      // Save move
      $game->save_move($move_no, $player_with_turn, $game->last_move());
    
      // Save game
      $game->save();
      
      if ($game->status() <> STATUS_IN_PROGRESS) {
        switch ($game->status()) {
          case STATUS_WHITE_WIN:
            $score = GAMER_WHITE_WIN;
            break;
          case STATUS_BLACK_WIN:
            $score = GAMER_BLACK_WIN;
            break;
          case STATUS_DRAW:
            $score = GAMER_DRAW;
            break;
        }
        
        gamer_update_stats($game->white_uid(), $game->black_uid(), $score);
      }
    
      // Send a notification if email address was supplied.
      //    if ($opponent == 'b') {
      //       $oid = $game['black'];
      //     }
      //     else {
      //       $oid = $game['white'];
      //     }
      //  $email=ioLoadUserEmailAddress($oid);
      $email = FALSE; // Hugh - force following condition to be FALSE
      if ($email) {
        $prot = ($GLOBALS['_SERVER']['HTTPS'] == 'on') ? 'https' : 'http';
        $url = $prot . '://' . $GLOBALS['_SERVER']['HTTP_HOST'] . $GLOBALS['_SERVER']['SCRIPT_NAME'] . '?gid=' . $gid;
        $message = "Dear $oid\n\n$uid has just moved.\n\nMove:\n$move\n\nIt is your turn now!\n\nEnter the game:\n$url";
        mail($email, "[OCC] " . $game['white'] . "vs" . $game['black'] . ": $move->long_format()",
            $message, 'From: ' . $mail_from);
      }
    }
    
    drupal_set_message(check_plain(t($cmdres)));
  }

  // show white at bottom if not playing
  if (empty($player)) {
    $player = 'w';
  }

  // build chessboard
  $content = "";
  $content .= '<table class="boardFrame"><tr><td>';
  $content .= '<table class="board">';
  if ($player == 'w') {
    $index = 56;
    $pos_change = 1;
    $line_change = -16;
  }
  else {
    $index = 7;
    $pos_change = -1;
    $line_change = 16;
  }
  // row [1..8] are normal board rows
  // row 0 contains column labels "a" to "h"
  for ($row = 8; $row >= 0; $row--) {
    $content .= '<tr>';
    // column 0 contains the row labels "1" to "8"
    // columns [1..8] are normal board columns 
    for ($col = 0; $col <= 8; $col++) {
      if ($row == 0) {
        // letters a-h at bottom
        if ($col > 0) {
          // ascii:
          // 97 => a
          // 98 => b
          // ...
          // 104 => h
          if ($player == 'w') {
            $c = chr(96 + $col);
          }
          else {
            $c = chr(105 - $col);
          }
          $content .= '<td align="center"><img height=4 src="' . $path . '/images/spacer.gif"><br /><b class="boardSquare">' . $c . '</B></td>';
        }
        else {
          $content .= '<td></td><td></td>';
        }
      }
      elseif ($col == 0) {
        // number on the left
        if ( $player == 'w' ) {
          $i = $row;
        }
        else {
          $i = 9 - $row;
        }
        $content .= '<td><b class="boardCoord">' . $i . '</B></td><td><img width=4 src="' . $path . '/images/spacer.gif"></td>';
      }
      else {
        // normal square 
        $coord = new Square();
        if ($player == 'w') {
          $coord->set_coord(vchess_col2file($col) . $row);
        }
        else {
          $coord->set_coord(vchess_col2file(9 - $col) . (9 - $row));
        }
        $piece = $board->piece($coord);
        $piece_name = $piece->name();
        $piece_color = $piece->color();

        if ((($row + 1) + ($col)) % 2 == 0) {
          $class = 'boardSquareWhite';
        }
        else {
          $class = 'boardSquareBlack';
        }
        
        if ($piece_name != BLANK) {
          // square with a piece and player has the ability to move
          if ($active) {
            // Build the first part of the move e.g. "Nb1"
            $cmdpart = sprintf('%s%s', $board->piece($coord)->type(), $coord->coord());
            // if the piece is the opposition then the cmdpart becomes e.g. "xNb1"
            if ($piece_color <> $player) {
              $cmdpart = "x" . $cmdpart;
            }
            $content .= '<td id="' . $coord->coord() . '" class="' . $class . '"><a href="" onClick="return assembleCmd(\'' . $cmdpart . '\');"><img border=0 src="' . $path . '/images/' . $theme . '/' . $piece_color . $piece_name . '.gif"></a></td>';
          }
          else {
            $content .= '<td class="' . $class . '"><img src="' . $path . '/images/' . $theme . '/' . $piece_color . $piece_name . '.gif"></td>';
          }
        }
        else {
          if ($active) {
            // Build the target part of the move e.g. "-c3", so the move will end up as "Nb1-c3"
            $cmdpart = sprintf('-%s', vchess_i2square($index)->coord());
            $content .= '<td id="' . $coord->coord() . '" class="' . $class . '"><a href="" onClick="return assembleCmd(\'' . $cmdpart . '\');"><img border=0 src="' . $path . '/images/' . $theme . '/empty.gif"></a></td>';            
          }
          else {
            $content .= '<td class="' . $class . '"><img src="' . $path . '/images/' . $theme . '/empty.gif"></td>';
          }
        }
        $index += $pos_change;
      }
    }
    $index += $line_change;
    $content .= "</tr>";
  }
  $content .= "</table></td></tr></table>";
  
  $content .= "<br />";
  
  // Prepare the scoresheet
  $scoresheet = new Scoresheet();
  $scoresheet->load($gid);
  $content .= $scoresheet->get_table();
  
  return $content;
}


/** 
 * Render command form which contains information about players and last
 * moves and all main buttons (shown when nescessary). The final command
 * is mapped to hidden field 'cmd' on submission. Show previous command
 * result $cmdres if set or last move if any. Fill move edit with $move
 * if set (to restore move when notes were saved).
 */
function vchess_command_form($form, $context, Game $game, $cmdres, $move) {
  global $user;   
  
  $form['cmd'] = array(
    '#type' => 'hidden',
    '#value' => '',
  );

  $form['comment'] = array(
    '#type' => 'value',
    '#value' => '',
  );

  $form['privnotes'] = array(
    '#type' => 'value',
    '#value' => '',
  );

  $form['flipBoardButton'] = array(
    '#type' => 'submit', //original button
    '#value' => 'Flip Board',
    );
  
  if (!$game->is_over()
  && ($game->white_uid() == $user->uid || $game->black_uid() == $user->uid)) {
    $form['resignButton'] = array(
      '#type' => 'submit',
      '#value' => 'Resign',
      '#validate' => array('vchess_resign_clicked')
    );
  }
  
  $form['gid'] = array(
    '#type' => 'hidden',
    '#value' => $game->gid()
  );
  
  $form['white'] = array(
    '#type' => 'hidden',
    '#value' => $game->white_player()->name()
  );
  
  $form['black'] = array(
    '#type' => 'hidden',
    '#value' => $game->black_player()->name()
  );
  
  $form['turn'] = array(
    '#type' => 'hidden',
    '#value' => $game->turn()
  );

  $form['move'] = array(
    '#type' => 'hidden',
    '#value' => $move,
  );

  // abort options
  return $form;
}

/**
 * @todo Please document this function.
 * @see http://drupal.org/node/1354
 */
function vchess_render_command_form($game) {
  $cmdres = ""; // Hugh - removed this as function parm, since used to get this parm but was always empty!
  $move = ""; // Hugh - removed this as function parm, since used to get this parm but was always empty!
  
  $out = drupal_get_form('vchess_command_form', $game, $cmdres, $move);
//  $out = drupal_get_form('vchess_command_form', $game);
  $out = drupal_render($out);
  
  return $out;
}
