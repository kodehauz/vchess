<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\vchess\Entity\Move;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Square;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Entity\Move
 */
class MoveTest extends KernelTestBase {

  public static $modules = ['system', 'user', 'vchess', 'pos', 'gamer'];

  public function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('vchess_move');
  }

  public function testGetterSetters() {
    $game_id = mt_rand(1, 10);
    $move_no = mt_rand(1, 10);
    $longmove = $this->randomString();
    $algebraic = $this->randomString();

    /** @var \Drupal\vchess\Entity\Move $move */
    $move = Move::create()
      ->setGameId($game_id)
      ->setMoveNo($move_no)
      ->setColor('b')
      ->setLongMove($longmove)
      ->setAlgebraic($algebraic)
      ->setTimestamp(1234555);
    $move->save();

    /** @var \Drupal\vchess\Entity\Move $game_move */
    $game_move = Move::load($move->id());
    $this->assertEquals($game_id, $game_move->getGameId());
    $this->assertEquals($move_no, $game_move->getMoveNo());
    $this->assertEquals('b', $game_move->getColor());
    $this->assertEquals($longmove, $game_move->getLongMove());
    $this->assertEquals($algebraic, $game_move->getAlgebraic());
    $this->assertEquals(1234555, $game_move->getTimestamp());
  }

  /**
   * @covers ::calculateAlgebraic
   * @dataProvider providerCalculateAlgebraic()
   */
  public function testCalculateAlgebraic(Board $board, $player, $long_move, $expected) {
    /** @var \Drupal\vchess\Entity\Move $move */
    $move = Move::create()->setLongMove($long_move);
    $clone_board = clone $board;
    $this->assertEquals('', $move->getAlgebraic());
    $move->calculateAlgebraic($player, $clone_board);
    $this->assertEquals($expected, $move->getAlgebraic());
  }

  public function providerCalculateAlgebraic() {
    $board = (new Board())->setupAsStandard();
    $this->makeMoves($board, ['Pg2-g3', 'Bf1-g2','Ng1-f3']);
    return [
      [$board, 'w', 'Nb1-c3', 'Nc3'], [$board, 'w', 'Ke1-g1', 'O-O'],
      [$board, 'b', 'Ng8-f6', 'Nf6'], [$board, 'w', 'Pe2-e4', 'e4'],
    ];
  }

  protected function makeMoves(Board $board, array $longmoves) {
    foreach ($longmoves as $move) {
      $delimiter = $move[3];
      list($start, $end) = explode($delimiter, $move);
      $start_square = Square::fromCoordinate(substr($start, -2));
      $end_square = Square::fromCoordinate(substr($end, -2));
      $board->movePiece($start_square, $end_square);
    }
  }

}
