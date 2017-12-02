<?php

namespace Drupal\vchess\Views;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for VChess game entities.
 */
class GameViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // User's game streak (fake field).
    $data['users']['game_streak'] = [
      'real field' => 'uid',
      'field' => [
        'title' => $this->t('Game streak'),
        'help' => $this->t('The user\'s game streak'),
        'id' => 'vchess_game_streak',
      ],
    ];

    return $data;
  }

}
