<?php

namespace Drupal\vchess;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\Entity\User;

class VchessController extends ControllerBase {

  /**
   * page callback vchess_main_page to display main vchess window
   */
  public function mainPage() {
    $user = User::load($this->currentUser());
    $out = "";

    if ($user->isAuthenticated()) {
      $gamefolder =  $this->config('vchess.settings')->get('game_files_folder');
      $res_games = $gamefolder;
//
//      if (!$user->getDisplayName()) {
//        $txt = t('Please, register to play chess');
//        return $txt;
//      }

      $player = GamerStatistics::loadForUser($user);
      $out .= "<p>My current rating: <b>" . $player->getRating() . "</b></p>";

      $out .= vchess_users_current_games($user->getDisplayName());
      $out .= vchess_challenges();

      $out .= vchess_player_stats_table($user->id());
    }
    else {
      $out = "Please log in to access this page";
    }

    return [
      '#type' => 'markup',
      '#markup' => $out,
    ];
  }
}