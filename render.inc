<?php

/**
 * @file
 * Functions for creating renderable (HTML) versions of different things
 */

use Drupal\vchess\Entity\Game;

/**
 * Render a FEN string as an HTML board
 */
function vchess_render_FEN($fen_string) {
  $game = new Game();
  
  $game->setup_position($fen_string);
  
  $html = vchess_render_board_squares($game->board(), "w", FALSE, FALSE);
  
  return $html;
}

