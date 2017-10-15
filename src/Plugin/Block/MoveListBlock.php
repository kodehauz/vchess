<?php

namespace Drupal\vchess\Plugin\Block;

use Drupal\Component\Utility\Html;
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
      return static::buildContent($game);
    }
    return ['#markup' => ''];
  }

  public static function buildContent(Game $game) {
    $class = 'vchess-moves-list';
    $id = Html::getUniqueId($class);
    return [
      '#cache' => [
        'max-age' => 0,
      ],
      '#type' => 'vchess_move_list',
      '#moves' => $game->getScoresheet()->getMoves(),
      '#prefix' => '<div id="' . $id . '" class="' . $class . '">',
      '#suffix' => '</div>',
    ];
  }

}
