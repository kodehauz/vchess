<?php

namespace Drupal\vchess;

use Drupal\Core\Url;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;

/**
 * Helper class to provide common game management functionality.
 */
class GameManager {

  /**
   * Accepts a challenge to play a particular game.
   *
   * @param \Drupal\vchess\Entity\Game $game
   *   The game pulled from the Request.
   *
   * @return bool
   *   Whether the challenge was successfully accepted or not.
   */
  public static function acceptChallenge(Game $game) {
    $user = User::load(\Drupal::currentUser()->id());

    // Check that the game has not already got players (should never happen!)
    if ($game->getWhiteUser() === NULL || $game->getBlackUser() === NULL) {
      $t_args = [
        '@username' => $user->getDisplayName(),
        '@game' => $game->label(),
      ];
      $color = $game->setPlayerRandomly($user);

      $extra = '';
      $its_your_move = '';
      if ($color === 'w') {
        $opponent = $game->getBlackUser();
        // @todo This is an outlier...???
        static::startGame($user, $opponent);
        $its_your_move = t('Now, it is your move!');
        $t_args += [
          '@white' => $user->getDisplayName(),
          '@black' => $opponent->getDisplayName(),
        ];
      }
      else {
        $opponent = $game->getWhiteUser();
        // @todo This is an outlier...???
        static::startGame($opponent, $user);
        $extra = t('Since you are playing black, you will have to wait for @opponent to move.<br />',
          ['@opponent' => $opponent->getDisplayName()]);
        $t_args += [
          '@white' => $opponent->getDisplayName(),
          '@black' => $user->getDisplayName(),
        ];
      }
      $t_args += [
        '@opponent' => $opponent->getDisplayName(),
        '@extra' => $extra,
        ':url' => Url::fromRoute('vchess.my_current_games')->toString(),
      ];
      $msg = t('Congratulations, you have started a game against <b>@opponent</b>.<br />@extra
      You can keep an eye on the status of this game and all your games on your <a href=":url">current games page</a>.<br />',
        $t_args);

      drupal_set_message($msg);

      if ($its_your_move) {
        drupal_set_message($its_your_move);
      }

//      rules_invoke_event('vchess_challenge_accepted', $opponent, $gid);

      \Drupal::logger('vchess')
        ->info('Player @username has accepted challenge for game @game. W:@white vs. B:@black.', $t_args);

      return TRUE;

    }
    else {
      drupal_set_message(t('Players are already assigned so challenge cannot be fulfilled.'));

      \Drupal::logger('vchess')
        ->error('Players are already assigned so challenge cannot be fulfilled. Player @username accepted challenge for game @game. W:@white vs. B:@black.',
          ['@username' => $user->getDisplayName(),
            '@game' => $game->label(),
            '@white' => $game->getWhiteUser()->getDisplayName(),
            '@black' => $game->getBlackUser()->getDisplayName()
          ]);

      return FALSE;
    }
  }

  /**
   * Record the start of a new game
   *
   * @param \Drupal\user\UserInterface $white_user
   * @param \Drupal\user\UserInterface $black_user
   *
   * @todo This method needs repurposing.
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
