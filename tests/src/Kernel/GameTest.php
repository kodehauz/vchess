<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\gamer\Entity\GamerStatistics;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Game\GamePlay;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Entity\Game
 */
class GameTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_game');
    $this->installEntitySchema('vchess_move');
    $this->installEntitySchema('gamer_statistics');
  }

  /**
   * @covers ::setWhiteUser
   * @covers ::setBlackUser
   * @covers ::setBoard
   * @covers ::setCastling
   * @covers ::setEnPassantSquare
   * @covers ::getWhiteUser
   * @covers ::getBlackUser
   * @covers ::getBoard
   * @covers ::getCastling
   * @covers ::getEnPassantSquare
   */
  public function testGetterSetters() {
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();

    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $board = $this->randomString();
    $castling = $this->randomString(4);
    $time_started = time();

    /** @var \Drupal\vchess\Entity\Game $game */
    $game = Game::create()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare('c3')
      ->setTimePerMove(19)
      ->setWhiteTimeLeft(1000)
      ->setBlackTimeLeft(900)
      ->setChallenger($white_user)
      ->setStatus(GamePlay::STATUS_IN_PROGRESS)
      ->setTimeStarted($time_started)
      ->setTurn('b');
    $game->save();

    /** @var \Drupal\vchess\Entity\Game $saved_game */
    $saved_game = Game::load($game->id());
    $this->assertEquals($board, $saved_game->getBoard());
    $this->assertEquals($black_user->id(), $saved_game->getBlackUser()->id());
    $this->assertEquals($white_user->id(), $saved_game->getWhiteUser()->id());
    $this->assertEquals($castling, $saved_game->getCastling());
    $this->assertEquals('c3', $saved_game->getEnPassantSquare());
    $this->assertEquals(19, $saved_game->getTimePerMove());
    $this->assertEquals(1000, $saved_game->getWhiteTimeLeft());
    $this->assertEquals(900, $saved_game->getBlackTimeLeft());
    $this->assertEquals('w', $saved_game->getPlayerColor($white_user));
    $this->assertEquals('b', $saved_game->getPlayerColor($black_user));
    $this->assertEquals($white_user->getAccountName(), $saved_game->getChallenger()->getAccountName());
    $this->assertEquals(1, $saved_game->getMoveNumber());
    $this->assertEquals(GamerStatistics::loadForUser($black_user)->getRating(), $saved_game->getOpponent($white_user)->getRating());
    $this->assertEquals(GamerStatistics::loadForUser($white_user)->getRating(), $saved_game->getOpponent($black_user)->getRating());
    $this->assertEquals('19 days', $saved_game->getSpeed());
    $this->assertEquals(GamePlay::STATUS_IN_PROGRESS, $saved_game->getStatus());
    $this->assertEquals($time_started, $saved_game->getTimeStarted());
    $this->assertEquals('b', $saved_game->getTurn());

    // Check the other code-path for getChallenger
    $saved_game->setChallenger($black_user);
    $this->assertTrue($black_user->getAccountName(), $saved_game->getChallenger()->getAccountName());
  }

  /**
   * Helper method, creates a random game.
   *
   * @param string|null $board
   * @param string|null $en_passant_square
   *
   * @return \Drupal\vchess\Entity\Game
   */
  protected function createRandomGame($board = NULL, $en_passant_square = NULL){
    $board = $board ?: $this->randomMachineName();
    $castling = 'KQkq';
    $en_passant_square = $en_passant_square ?: 'c3';
    $turn = mt_rand(0, 1) === 1 ? 'w' : 'b';

    $game = Game::create()
      ->setBoard($board)
      ->setCastling($castling)
      ->setEnPassantSquare($en_passant_square)
      ->setTurn($turn);
    return $game;
  }

  /**
   * @covers ::countUsersCurrentGames
   */
  public function testCountUsersCurrentGames (){
    $black_user = User::create()->setUsername($this->randomMachineName());
    $black_user->save();
    $white_user = User::create()->setUsername($this->randomMachineName());
    $white_user->save();

    $this->assertEquals(0, Game::countUsersCurrentGames($black_user));

    $game1 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(GamePlay::STATUS_IN_PROGRESS);
    $game1->save();

    $game2 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(GamePlay::STATUS_IN_PROGRESS);
    $game2->save();

    $game3 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(GamePlay::STATUS_IN_PROGRESS);
    $game3->save();

    $this->assertEquals(3, Game::countUsersCurrentGames($black_user));
  }

  /**
   * Tests that a game gets a default label like 'User1 vs. User2'.
   */
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

    // Ensure label already set is not clobbered.
    $game1 = $this->createRandomGame()
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setLabel('A label for a game');
    $game1->save();

    $this->assertEquals('A label for a game', $game1->label());

  }

  /**
   * @todo Entity schema constraints test.
   */
  public function testEntitySchemaConstraints() {
    $this->markTestIncomplete();
  }

  /**
   * @covers ::loadUsersCurrentGames
   * @covers ::loadAllCurrentGames
   * @covers ::loadChallenges
   */
  public function testStaticGamesLoaders() {
    /** @var \Drupal\user\UserInterface[] $users */
    $users = [];
    /** @var \Drupal\vchess\Entity\Game[] $games */
    $games = [];
    /** @var \Drupal\vchess\Entity\Game[][] $user_games */
    $user_games = [];
    /** @var \Drupal\vchess\Entity\Game[] $games */
    $challenges = [];
    $num_users = mt_rand(1, 8);
    $num_games = mt_rand(1, 8);
    for ($i = 0; $i < $num_users; $i++) {
      $user = User::create(['name' => $this->randomMachineName()]);
      $user->save();
      $users[$user->id()] = $user;
    }

    $this->assertCount(0, Game::loadAllCurrentGames());

    for ($i = 0; $i < $num_games; $i++) {
      $is_current = mt_rand(0, 1) === 0;
      /** @var \Drupal\vchess\Entity\Game $game */
      if ($is_current) {
        $white_user = $users[mt_rand(1, $num_users)];
        $black_user = $users[mt_rand(1, $num_users)];
        $game = Game::create()
          ->setWhiteUser($white_user)
          ->setBlackUser($black_user)
          ->setStatus(GamePlay::STATUS_IN_PROGRESS);
        $game->save();
        $games[$game->id()] = $game;
        $user_games[$white_user->id()][$game->id()] = $game;
        $user_games[$black_user->id()][$game->id()] = $game;
      }
      else {
        $game = Game::create()
          ->setLabel($this->randomMachineName());
        $game->save();
        $challenges[$game->id()] = $game;
      }
    }

    $this->assertCount(count($games), Game::loadAllCurrentGames());
//    $this->assertEquals($games, Game::loadAllCurrentGames());

    foreach ($user_games as $uid => $list) {
      $this->assertCount(count($user_games[$uid]), Game::loadUsersCurrentGames($users[$uid]));
//      $this->assertEquals($user_games[$uid], Game::loadUsersCurrentGames($users[$uid]));
    }

    $this->assertCount(count($challenges), Game::loadChallenges());
//    $this->assertEquals($challenges, Game::loadChallenges());
  }

  /**
   * @covers ::countGamesWonByUser
   * @covers ::countGamesLostByUser
   */
  public function testGamesWonLostByUser() {
    $white_user = User::create(['name' => $this->randomMachineName()]);
    $white_user->save();
    $black_user = User::create(['name' => $this->randomMachineName()]);
    $black_user->save();
    $black_won_count = 0;
    $white_won_count = 0;
    $total_count = mt_rand(5, 15);
    for ($i = 0; $i < $total_count; $i++) {
      $white_won = mt_rand(0, 1) === 0;
      $game = Game::create()
        ->setWhiteUser($white_user)
        ->setBlackUser($black_user)
        ->setStatus($white_won ? GamePlay::STATUS_WHITE_WIN : GamePlay::STATUS_BLACK_WIN);
      $game->save();
      if ($white_won) {
        $white_won_count++;
      }
      else {
        $black_won_count++;
      }
    }
    $this->assertEquals($white_won_count, Game::countGamesWonByUser($white_user));
    $this->assertEquals($black_won_count, Game::countGamesWonByUser($black_user));
    $this->assertEquals($white_won_count, Game::countGamesLostByUser($black_user));
    $this->assertEquals($black_won_count, Game::countGamesLostByUser($white_user));
  }

}
