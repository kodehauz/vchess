<?php

namespace Drupal\vchess\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * @FormElement("vchess_game_timer")
 */
class GameTimer extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#theme' => 'vchess_game_timer',
      '#input' => TRUE,
      '#title' => 'Game timer',
      '#description' => 'Game timer for Vchess',
      '#game' => NULL,
      '#player' => 'w',
      '#active' => FALSE,
      '#pre_render' => [
        [$class, 'preRenderGameTimer'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      return ['white_time' => $input['white'], 'black_time' => $input['black']];
    }
    return NULL;
  }

  /**
   * Prepares the variables needed to render the game timer.
   */
  public static function preRenderGameTimer($element) {
    /** @var \Drupal\vchess\Entity\Game $game */
    $game = $element['#game'];

    $element['#white_name'] = $game->getWhiteUser()->getDisplayName();
    $element['#white_time'] = $game->getWhiteTimeLeft();
    $element['#attached']['library'][] = 'vchess/game_timer';

    $element['#black_name'] = $game->getBlackUser()->getDisplayName();
    $element['#black_time'] = $game->getBlackTimeLeft();

    // Which timer should be counting down.
    if ($element['#active']) {
      $element['#attached']['drupalSettings']['vchess']['active_timer'] = $element['#player'];
    }
    else {
      $element['#attached']['drupalSettings']['vchess']['active_timer'] = '';
    }

    return $element;
  }

}
