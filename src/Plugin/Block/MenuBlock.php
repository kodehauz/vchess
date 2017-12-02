<?php

namespace Drupal\vchess\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 *   id = "vchess_menu_block",
 *   admin_label = @Translation("VChess menu"),
 *   category = @Translation("VChess")
 * )
 */
class MenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'markup',
      '#markup' => $this->generateMenu(),
    ];
  }

  /**
   * Generate the HTML for the VChess block
   */
  protected function generateMenu() {
    $html = "";

    $html .= "Status:";
    $html .= "<ul>";
    $html .= "<li><a href='" . "?q=vchess/main'>Main</a>";
    $html .= "<li><a href='" . "?q=vchess/my_current_games'>My current games</a>";
    $html .= "<li><a href='" . "?q=vchess/all_current_games'>All current games</a>";
    $html .= "<li><a href='" . "?q=vchess/players'>Players</a>";
    $html .= "</ul>";

    $html .= "Challenges:";
    $html .= "<ul>";
    $html .= "<li><a href='" . "?q=vchess/challenges'>Challenges</a>";
    $html .= "<li><a href='" . "?q=vchess/create_challenge'>Create challenge</a>";
    $html .= "<li><a href='" . "?q=vchess/default_challenge'>Default challenge</a>";
    $html .= "</ul>";

    $html .= "New games:";
    $html .= "<ul>";
    $html .= "<li><a href='" . "?q=vchess/random_game_form'>New random game</a>";
    $html .= "<li><a href='" . "?q=vchess/opponent_game_form'>New opponent game</a>";
    $html .= "</ul>";

    $html .= "Admin:";
    $html .= "<ul>";
    $html .= "<li><a href='" . "?q=admin/config/development/testing'>Run tests</a>";
    $html .= "<li><a href='" . "?q=vchess/test'>Run single test</a>";
    $html .= "<li><a href='" . "?q=vchess/reset_games'>Reset games</a>";
    $html .= "</ul>";

    return $html;
  }

}
