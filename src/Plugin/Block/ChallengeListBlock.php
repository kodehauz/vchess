<?php

namespace Drupal\vchess\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\vchess\Controller\GameController;
use Drupal\vchess\Entity\Game;

/**
 * @Block(
 *   id = "vchess_challenges_list",
 *   admin_label = @Translation("VChess challenges list"),
 *   category = @Translation("VChess")
 * )
 */
class ChallengeListBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return GameController::buildChallengesTable(Game::loadChallenges());
  }

}
