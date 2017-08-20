<?php

namespace Drupal\gamer;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

class GamerController extends ControllerBase {

  public function players() {
    /** @var \Drupal\user\UserInterface[] $users */
    $users = User::loadMultiple();
    $info = [];
    foreach ($users as $user) {
      if ($user->id() <> 0) {
        $stats = GamerStatistics::loadForUser($user);
        $info[] = [
          'uid' => $user->id(),
          // @todo url
          'name' => "<a href='" . url("vchess/player/" . $user->getAccountName()) . "'>" . $user->getDisplayName() . "</a>",
          'rating' => $stats->getRating(),
          'played' => $stats->getPlayed(),
          'won' => $stats->getWon(),
          'lost' => $stats->getLost(),
          'drawn' => $stats->getDrawn(),
          'rating_change' => $stats->getRchanged(),
          // the name tag is used so that the column still sorts correctly
          'current' => "<a name=" . $stats->getCurrent() .
            // @todo url
            " href='" . url("vchess/current_games/" .
              $user->getAccountName()) . "'>" . $stats->getCurrent() . "</a>"
        ];
      }
    }

    $header = array(
      array('data' => t('uid'), 'field' => 'uid'),
      array('data' => t('name'), 'field' => 'name'),
      array('data' => t('rating'), 'field' => 'rating'),
      array('data' => t('played'), 'field' => 'played'),
      array('data' => t('won'), 'field' => 'won'),
      array('data' => t('lost'), 'field' => 'lost'),
      array('data' => t('drawn'), 'field' => 'drawn'),
      array('data' => t('rating change'), 'field' => 'rating_change'),
      array('data' => t('in progress'), 'field' => 'current')
    );

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $info,
      '#empty' => 'The message to display in an extra row if table does not have any rows.'
    ];
  }

  function playerStatsTable(UserInterface $user) {
    $stats = GamerStatistics::loadForUser($user);

    $header = array('Played', 'Won', 'Drawn', 'Lost', 'Rating', 'Rating change', 'Current games');
    $rows = array(array($stats->getPlayed(), $stats->getWon(), $stats->getDrawn(),
      $stats->getLost(), $stats->getRating(), $stats->getRchanged(), $stats->getCurrent()));

    return [
      'title' => [
        '#type' => 'markup',
        '#markup' => 'Statistics for <b>' . $user->getDisplayName() . '</b>:'
      ],
      'table' => [
        '#type'   => 'table',
        '#header' => $header,
        '#rows'   => $rows,
        '#empty'  => 'Nothing to display.',
      ]
    ];
  }

  /**
   * Record the start of a new game
   *
   * @param \Drupal\user\UserInterface $white_user
   * @param \Drupal\user\UserInterface $black_user
   */
  public static function startGame(UserInterface $white_user, UserInterface $black_user) {
    $white = GamerStatistics::loadForUser($white_user);
    $black = GamerStatistics::loadForUser($black_user);

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

    $white->setCurrent($white->getCurrent() + 1);
    $black->setCurrent($black->getCurrent() + 1);

    $white->save();
    $black->save();
  }

}