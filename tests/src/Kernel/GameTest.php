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

  public function createRandomGame(){
    $board = $this->randomMachineName();
    $castling = $this->randomString();
    $en_passant = $this->randomString();
    $turn = $this->randomString();

    $game = Game::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare('c3')
//      ->setWhiteUser($white_user)
//      ->setBlackUser($black_user)
      ->setTurn($turn)
      ->setEnPassantSquare($en_passant);
    return $game;

//    $game_2 = Game::create()
//      ->setBoard($board)
//      ->setCastling($castling)
//      ->setEnPassantSquare('c3')
//      ->setWhiteUser($white_user)
//      ->setBlackUser($black_user)
//      ->setTurn($turn)
//      ->setEnPassantSquare($en_passant);
//    $game_2->save();
//
//    $game_3 = Game::create()
//      ->setBoard($board)
//      ->setCastling($castling)
//      ->setEnPassantSquare('c3')
//      ->setWhiteUser($white_user)
//      ->setBlackUser($black_user)
//      ->setTurn($turn)
//      ->setEnPassantSquare($en_passant);
//    $game_3->save();

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
}
