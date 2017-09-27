<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\Board;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Entity\Game
 */
class GameTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_game');
  }

  public function testGetterSetters() {
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();

    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $board = $this->randomString();
    $castling = $this->randomString();

    /** @var \Drupal\vchess\Entity\Game $game */
    $game = Game::create()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare('c3');
    $game->save();

    /** @var \Drupal\vchess\Entity\Game $saved_game */
    $saved_game = Game::load($game->id());
    $this->assertEquals($board, $saved_game->getBoard());
    $this->assertEquals($black_user->id(), $saved_game->getBlackUser()->id());
    $this->assertEquals($white_user->id(), $saved_game->getWhiteUser()->id());
    $this->assertEquals($castling, $saved_game->getCastling());
    $this->assertEquals('c3', $saved_game->getEnPassantSquare());
  }

}
