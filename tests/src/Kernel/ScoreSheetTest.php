<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Entity\Move;
use Drupal\vchess\Entity\Scoresheet;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Entity\Scoresheet
 */
class ScoreSheetTest extends KernelTestBase {

  public static $modules = ['user', 'vchess'];

  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('vchess_move');
    $this->installEntitySchema('vchess_game');
  }

  /**
   * @covers ::__construct
   */
  public function testConstructorWithUnsavedGame() {
    $scoresheet = new Scoresheet(0);
    $this->assertEquals([], $scoresheet->getMoves());
  }

  /**
   * @covers ::getNextMoveNumber
   * @covers ::getMoves
   */
  public function testAppendMove() {
    $game = Game::create();
    $game->save();
    $scoresheet = new Scoresheet($game->id());

    $this->assertEquals([], $scoresheet->getMoves());
    $this->assertEquals(1, $scoresheet->getNextMoveNumber());

    $move1 = Move::create(['long_move' => 'Ng1-f3']);
    $scoresheet->appendMove($move1);

    $this->assertEquals([ 1 => ['w' => $move1] ], $scoresheet->getMoves());
    $this->assertEquals(1, $scoresheet->getNextMoveNumber());

    $move2 = Move::create(['long_move' => 'Ng8-f6']);
    $scoresheet->appendMove($move2);

    $this->assertEquals([ 1 => ['w' => $move1, 'b' => $move2] ], $scoresheet->getMoves());
    $this->assertEquals(2, $scoresheet->getNextMoveNumber());

    // Append 10 more games.
    for ($i = 0; $i < 10; $i++) {
      $scoresheet->appendMove(Move::create());
    }

    $this->assertEquals(7, $scoresheet->getNextMoveNumber());
    $this->assertEquals(6, count($scoresheet->getMoves()));
  }

  public function testGetLastMove() {
    $game = Game::create();
    $game->save();
    $scoresheet = new Scoresheet($game->id());

    $this->assertEquals(NULL, $scoresheet->getLastMove());

    $move1 = Move::create(['long_move' => 'Ng1-f3']);
    $scoresheet->appendMove($move1);

    $this->assertEquals($move1, $scoresheet->getLastMove());

    // Append 10 more games.
    for ($i = 0; $i < 10; $i++) {
      $scoresheet->appendMove(Move::create());
    }

    $move2 = Move::create(['long_move' => 'Pa7-a6']);
    $scoresheet->appendMove($move2);

    $this->assertEquals($move2, $scoresheet->getLastMove());
  }

  public function testGetMove() {
    $scoresheet = new Scoresheet(0);



    $move1 = Move::create(['long_move' => 'Ng1-f3']);
    $scoresheet->appendMove($move1);


  }

  public function testSaveMoves() {
    $moves = ['Ng1-f3', 'Ng8-f6', 'Pd2-d4', 'Pd7-d6', 'Pe2-e3', 'Nb8-c6', 'Bf1-c4'];
    $game = Game::create();
    $game->save();
    $scoresheet = new Scoresheet($game->id());
    foreach ($moves as $move) {
      $scoresheet->appendMove(Move::create(['long_move' => $move]));
    }
    $scoresheet->saveMoves();

    $scoresheet2 = new Scoresheet($game->id());
    $this->assertEquals($scoresheet, $scoresheet2);
  }

}
