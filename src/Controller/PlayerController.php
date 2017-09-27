<?php

namespace Drupal\vchess\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gamer\GamerController;

class PlayerController extends ControllerBase {


  /**
   * Display the page for a given player
   *
   * @param $uid
   */
  public function displayPlayer($name) {
    $player = $this->entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name]);

    if ($player = reset($player)) {
      $html = GamerController::playerStatsTable($player);
      $html .= $this->usersCurrentGames($player);

      return $html;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * page callback to display the table of players
   */
  function displayPlayers() {
    GamePlay::checkForLostOnTimeGames();

    return GamerStatistics::players();
  }

}
