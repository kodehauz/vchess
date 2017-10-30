<?php

namespace Drupal\vchess\Controller;

use Drupal\gamer\Entity\GamerStatistics;
use Drupal\vchess\GameManagementTrait;

class TestController {

  use GameManagementTrait;

  /**
   * A single test
   *
   * This function is for running individual tests normally found
   * within the vchess.test file, but without all the heavy setup
   * and teardown time which those functions need.
   */
  public function testVchess() {
    $html = "";

    static::initializeGame(1, 1);

    $player = GamerStatistics::loadForUser(1);
    $player->setCurrent(-25);

    $html .= "time() is: " . date("Y-m-d H:i:s", time()) . "<br />";
    $html .= "SERVER REQUEST_TIME is: " . date("Y-m-d H:i:s", $_SERVER['REQUEST_TIME']) . "<br />";
    $html .= "gmdate() is: " . gmdate("Y-m-d H:i:s");

    return $html;
  }

}
