<?php

namespace Drupal\vchess;

use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\GamePlay;

/**
 * Helper class to provide common game management functionality.
 */
trait GameManagementTrait {

  /**
   * Records the start of a new game and updates game and player info.
   *
   * @param \Drupal\vchess\Entity\Game $game
   * @param \Drupal\user\UserInterface $white_user
   * @param \Drupal\user\UserInterface $black_user
   *
   */
  public static function startGame(Game $game, UserInterface $white_user, UserInterface $black_user) {
    $white = GamerStatistics::loadForUser($white_user);
    $black = GamerStatistics::loadForUser($black_user);

    $white->setCurrent($white->getCurrent() + 1);
    $black->setCurrent($black->getCurrent() + 1);

    $white->save();
    $black->save();

    $game
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(GamePlay::STATUS_IN_PROGRESS);

    $game->save();

    //   watchdog("VChess", "In gamer_start_game(), recording start of new game for game between " .
    //     "@white_name (uid=@white_uid, current=@white_current) and " .
    //     "@black_name (uid=@black_uid, current=@black_current)",
    //     array(
    //       '@white_name' => $white->name(),
    //       '@black_name' => $black->name(),
    //       '@white_uid' => $white_uid,
    //       '@black_uid' => $black_uid,
    //       '@white_current' => $white->current(),
    //       '@black_current' => $black->current()
    //     )
    //   );

  }

}
