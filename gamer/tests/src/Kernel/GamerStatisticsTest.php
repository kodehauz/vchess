<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\gamer\Entity\GamerStatistics;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * @group gamer_statistics
 * @coversDefaultClass \Drupal\gamer\Entity\GamerStatistics
 */
class GamerStatisticsTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('gamer_statistics');
  }

  public function testGetterSetters() {
    $owner = User::create()->setUsername($this->randomMachineName());
    $owner->save();

    $current = rand(1, 10);
    $won = rand(1, 10);
    $drawn = rand(1, 10);
    $lost = rand(1, 10);
    $rating = rand(1200, 22200);
    $played = rand(1, 10);
    $rchanged = rand(1, 10);

    /** @var \Drupal\gamer\Entity\GamerStatistics $game */
    $game = GamerStatistics::create()
      ->setOwner($owner)
      ->setCurrent($current)
      ->setWon($won)
      ->setDrawn($drawn)
      ->setLost($lost)
      ->setRating($rating)
      ->setPlayed($played)
      ->setRchanged($rchanged);
    $game->save();

    /** @var \Drupal\gamer\Entity\GamerStatistics $saved_game */
    $saved_game = GamerStatistics::load($game->id());
    $this->assertEquals($owner->id(), $saved_game->getOwner()->id());
    $this->assertEquals($current, $saved_game->getCurrent());
    $this->assertEquals($won, $saved_game->getWon());
    $this->assertEquals($drawn, $saved_game->getDrawn());
    $this->assertEquals($lost, $saved_game->getLost());
    $this->assertEquals($rating, $saved_game->getRating());
    $this->assertEquals($played, $saved_game->getPlayed());
    $this->assertEquals($rchanged, $saved_game->getRchanged());
  }

  public function testLoadForUser() {
    $user = User::create([
      'name' => $this->randomString()
    ]);
    $user->save();

    $loaded_stats = GamerStatistics::loadForUser($user);
    // User default rating is 1200
    $this->assertEquals(1200, $loaded_stats->getRating());
    $this->assertEquals($user->id(), $loaded_stats->getOwner());
  }

}
