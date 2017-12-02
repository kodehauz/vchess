<?php

namespace Drupal\vchess\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * @RenderElement("vchess_game_timer")
 */
class GameTimer extends RenderElement {

    /**
     * {@inheritdoc}
     */
    public function getInfo() {
      $class = get_class($this);
      return [
        '#theme' => 'vchess_game_timer',
        '#title' => 'Game timer',
        '#description' => 'Game timer for Vchess',
        '#game' => NULL,
        '#player' => 'b',
        '#pre_render' => [
          [$class, 'preRenderGameTimer'],
        ],
      ];
    }

  /**
   * {@inheritdoc}
   */

    public static function preRenderGameTimer($element) {
      /** @var \Drupal\vchess\Entity\Game $game */
      $game = $element['#game'];

      $element['#white_name'] = $game->getWhiteUser()->getDisplayName();
//      $element['#white_time'] = $game->getWhiteTimeLeft();
      $element['#white_time'] = 900;
      $element['#attached']['library'][] = 'vchess/game_timer';

      $element['#black_name'] = $game->getBlackUser()->getDisplayName();
//      $element['#black_time'] = $game->getBlackTimeLeft();
      $element['#black_time'] = 400;

      // Which timer should be counting down.
      $element['#attached']['drupalSettings']['vchess']['active_timer'] = $element['#player'];


      return $element;
    }

}
