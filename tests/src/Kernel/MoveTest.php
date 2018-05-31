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

  /**
   * @covers ::fromAlgebraic
   * @dataProvider providerFromAlgebraic()
   */
  public function testFromAlgebraic($algebraic, $fen, $player, $expected) {
    $board = new Board();
    $board->setupPosition($fen);
    $move = Move::fromAlgebraic($algebraic, $board, $player);
    $this->assertEquals($expected, $move->getLongMove());
  }

  public function providerFromAlgebraic() {
    return [
      'Pa2-a3' => ['Pa2-a3', Board::BOARD_DEFAULT, 'w', 'Pa2-a3'],
      'pa2-a3' => ['pa2-a3', Board::BOARD_DEFAULT, 'w', 'Pa2-a3'],
      'nf3' => ['nf3', Board::BOARD_DEFAULT, 'w', 'Ng1-f3'],
      'Nf3' => ['Nf3', Board::BOARD_DEFAULT, 'w', 'Ng1-f3'],
      'e3' => ['e3', Board::BOARD_DEFAULT, 'w', 'Pe2-e3'],
      'Nf6' => ['Nf6', Board::BOARD_DEFAULT, 'b', 'Ng8-f6'],
      'Bg7' => ['Bg7', 'rnbqkbnr/pppppp1p/6p1/8/8/6P1/PPPPPP1P/RNBQKBNR', 'b', 'Bf8-g7'],
      'e5' => ['e5', Board::BOARD_DEFAULT, 'b', 'Pe7-e5'],
      'dxe4' => ['dxe4', 'rnbqk1nr/ppp2pbp/4p1p1/3p4/3PP3/2N3P1/PPP2PBP/R1BQK1NR', 'b', 'Pd5xe4'],
      'Bxe4' => ['Bxe4', 'rnbqk1nr/ppp2pbp/4p1p1/8/3Pp3/2N3P1/PPP2PBP/R1BQK1NR', 'w', 'Bg2xe4'],
      'Ngf6' => ['Ngf6', 'r1bqk1nr/pppn1pbp/4p1p1/8/3PB3/2N3P1/PPP1NP1P/R1BQK2R', 'b', 'Ng8-f6'],
      'N7f6' => ['N7f6', 'r1bq1rk1/pppn1pbp/4p1p1/3n4/3P4/2NQB1PP/PPP1NPB1/R4RK1', 'b', 'Nd7-f6'],
      'Ng8f6' => ['Ng8f6', 'r1bqk1nr/pppn1pbp/4p1p1/8/3PB3/2N3P1/PPP1NP1P/R1BQK2R', 'b', 'Ng8-f6'],
      'O-Ob' => ['O-O', 'r1bq1rk1/pppn1pbp/4pnp1/8/3P4/2N3P1/PPP1NPBP/R1BQK2R', 'b', 'Ke8-g8'],
      'O-Ow' => ['O-O', 'r1bq1rk1/pppn1pbp/4pnp1/8/3P4/2N3P1/PPP1NPBP/R1BQK2R', 'w', 'Ke1-g1'],
      'Qf3+' => ['Qf3+', '8/5ppk/4p3/3qP3/R7/3r4/2Q1pK2/4B3', 'b', 'Qd5-f3+'],
      'Rg7+' => ['Rg7+', '8/5p1k/4p1R1/4P3/7p/8/7K/4qq2', 'w', 'Rg6-g7+'],
      'e1=Q' => ['e1=Q', '8/5p2/4p2k/4P1p1/3Q4/6R1/4p2K/5q2', 'b', 'Pe2-e1=Q'],
    ];
  }

  /**
   * @covers ::fromAlgebraic
   */
  public function testInvalidAlgebraic() {
    $board = new Board();
    $board->setupPosition(Board::BOARD_DEFAULT);
    $move = Move::fromAlgebraic('dxe5', $board, 'w');
    $this->assertNull($move);
  }

}
