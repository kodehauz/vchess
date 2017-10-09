<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\GamePlay;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\GamePlay
 */
class GamePlayTest extends KernelTestBase {

  public static $modules = ['user', 'gamer', 'pos', 'vchess'];

  /**
   * GamePlay object under test.
   *
   * @var \Drupal\vchess\Game\GamePlay
   */
//  protected $gameplay;

  public function setUp() {
    parent::setUp();
//    $this->gameplay = new GamePlay();

    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('vchess_move');
  }

  public function testConstructor() {
    $gameplay = new GamePlay();
    // Confirm that a board is created.
    $this->assertNotNull($gameplay->getBoard());
    // Assert that board is default.
    $this->assertEquals(Board::BOARD_DEFAULT, $gameplay->getBoard()->getFenString());

    $game = Game::create()->setBoard(Board::BOARD_PROMOTION);
    $gameplay2 = new GamePlay($game);
    // Confirm that a board is created.
    $this->assertNotNull($gameplay->getBoard());
    // Assert that board is default.
    $this->assertEquals($game->getBoard(), $gameplay2->getBoard()->getFenString());

  }

  public function testSimpleMoveSequence() {
    $gameplay = new TestGamePlay(Game::create());
    // Confirm move number initially.
    $this->assertEquals(1, $gameplay->getGame()->getMoveNumber());


  }

}


class TestGamePlay extends GamePlay {

  public function getGame() {
    return $this->game;
  }

}
