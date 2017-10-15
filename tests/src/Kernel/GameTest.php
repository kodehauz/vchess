<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\gamer\GamerController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\GamePlay;

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
    $this->installEntitySchema('vchess_move');
  }

  public function testGetterSetters() {
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();

    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $board = $this->randomString();
    $castling = $this->randomString(4);

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

  /**
   * Helper method, creates a random game.
   */
  protected function createRandomGame($board = NULL){
    $board = $this->randomMachineName();
    $castling = 'KQkq';
    $en_passant = $this->randomString();
    $turn = rand(0, 1) === 1 ? 'w' : 'b';

    $game = Game::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare('c3')
      ->setTurn($turn);
    return $game;
  }

  public function testCountUsersCurrentGames (){
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();
    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $this->assertEquals(0, Game::countUsersCurrentGames($black_user));

    $game1 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user);
    $game1->save();

    $game2 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user);
    $game2->save();

    $game3 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user);
    $game3->save();

    $this->assertEquals(3, Game::countUsersCurrentGames($black_user));
  }

  public function testDefaultLabel() {
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();
    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $game = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user);
    $game->save();

    $this->assertEquals($white_user->getDisplayName() . ' vs. ' . $black_user->getDisplayName(),
      $game->label());
  }

  /**
   * @todo Entity schema constraints test.
   */
  public function testEntitySchemaConstraints() {
    $this->markTestIncomplete();
  }

}
