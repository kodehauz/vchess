<?php

namespace Drupal\vchess\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\vchess\Entity\Game;

/**
 * @Block(
 *   id = "vchess_moves_list",
 *   admin_label = @Translation("VChess moves list"),
 *   category = @Translation("VChess")
 * )
 */
class MoveListBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (($game_id = \Drupal::request()->get('vchess_game')) && ($game = Game::load($game_id))) {
      return [
        '#cache' => [
          'max-age' => 0,
        ],
        '#type' => 'vchess_move_list',
        '#moves' => $game->getScoresheet()->getMoves(),
      ];
    }
    else {
      return [
        '#markup' => '',
      ];
    }
  }

}
