<?php

namespace Drupal\vchess;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class GameListBuilder extends EntityListBuilder {

  public function buildHeader() {
    return [
        ['data' => $this->t('Your move?'), 'field' => 'move'],
        ['data' => $this->t('White'), 'field' => 'white'],
        ['data' => $this->t('Black'), 'field' => 'black'],
        ['data' => $this->t('Move #'), 'field' => 'move_no'],
        ['data' => $this->t('Time left'), 'field' => 'time_left'],
        ['data' => $this->t('Time per move'), 'field' => 'speed'],
        ['data' => $this->t('Turn'), 'field' => 'turn'],
        ['data' => $this->t('View')],
      ] + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    // We need to check first if the game has recently been lost on time, in
    // which case it is no longer a current game.  If it has, then this is the
    // first time it has been noticed (since this game was in "In progress")
    // and so we need to update the gamer statistics
    /** @var \Drupal\vchess\Entity\Game $game */
    $game = $entity;
    $user = User::load(\Drupal::currentUser());
    $markup_arguments = [];
    if ($game->isUserPlaying($user)) {
      if ($game->isPlayersMove($user)) {
        $markup_arguments['mark'] = 'greenmark.gif';
        $markup_arguments['@alt'] = '1.green'; // alt text is used so sort order is green, red, grey
      }
      else {
        $markup_arguments['mark'] = 'redmark.gif';
        $markup_arguments['@alt'] = '2.red';
      }
    }
    else {
      $markup_arguments['mark'] = 'greymark.gif';
      $markup_arguments['@alt'] = '3.grey';
    }

    if ($game->getTurn() === 'w') {
      $player_to_move = $game->getWhiteUser();
    }
    else {
      $player_to_move = $game->getBlackUser();
    }

    $time_left = $game->calculateTimeLeft();

    global $base_url;
    $markup_arguments += [
      ':src' => $base_url . "/" . drupal_get_path('module', 'vchess') . '/images/default/' . $markup_arguments['mark'],
      ':white-player-url' => Url::fromRoute('vchess.player', [
        'player' => $game->getWhiteUser()
          ->getAccountName()
      ])->toString(),
      '@white-player-name' => $game->getWhiteUser()->getDisplayName(),
      ':black-player-url' => Url::fromRoute('vchess.player', [
        'player' => $game->getBlackUser()
          ->getAccountName()
      ])->toString(),
      '@black-player-name' => $game->getBlackUser()->getDisplayName(),
      ':player-to-move-url' => Url::fromRoute('vchess.player', ['player' => $player_to_move->getAccountName()])
        ->toString(),
      '@player-to-move-name' => $player_to_move->getDisplayName(),
      ':game-url' => Url::fromRoute('vchess.game', ['vchess_game' => $game->id()])
        ->toString(),
      '@long-time' => sprintf("%07d", $time_left),
      '@time' => $this->formatUserFriendlyTime($time_left),
    ];
    return [
        'move' => new FormattableMarkup('<img alt="@alt" src=":src">', $markup_arguments),
        'white' => new FormattableMarkup('<a href=":white-player-url">@white-player-name</a>', $markup_arguments),
        'black' => new FormattableMarkup('<a href=":black-player-url">@black-player-name</a>', $markup_arguments),
        'move_no' => $game->getMoveNumber(),
        // We use div id in secs to ensure sort works correctly
        'time_left' => new FormattableMarkup('<div id="@long-time">@time</div>', $markup_arguments),
        'speed' => $game->getSpeed(),
        'turn' => new FormattableMarkup('<a href=":player-to-move-url">@player-to-move-name</a>', $markup_arguments),
        'gid' => new TranslatableMarkup('<a href=":game-url">View</a>', $markup_arguments),
      ] + parent::buildRow($entity);
  }

}
