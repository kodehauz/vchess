<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Square;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Board
 */
class BoardValidMoveTest extends UnitTestCase {

  use BoardTestTrait;

  /**
   * @dataProvider providerGetValidMoves()
   * @covers ::getValidMoves
   */
  public function testGetValidMoves(Board $board, $coordinate, array $moves) {
    $square = Square::fromCoordinate($coordinate);
    $this->assertEquals($moves, $board->getValidMoves($square));
  }

  public function providerGetValidMoves() {
    $default_board = (new Board())->setupAsStandard();
    return [
      [$default_board, 'a1', []],
      [$default_board, 'b1', ['Nb1-a3','Nb1-c3']],
      [$default_board, 'c1', []],
      [$default_board, 'g2', ['Pg2-g3','Pg2-g4']],
      [$default_board, 'g1', ['Ng1-f3','Ng1-h3']],
      [$default_board, 'h1', []],
      [$default_board, 'e2', ['Pe2-e3', 'Pe2-e4']],
      [$this->setUpBoard(['e5'=>'n','e1'=>'K','e8'=>'k']), 'e5', ['Ne5-d3','Ne5-f3','Ne5-c4','Ne5-g4','Ne5-c6','Ne5-g6','Ne5-d7','Ne5-f7']],
      [$this->setUpBoard(['e8'=>'n','e1'=>'K','f8'=>'k']), 'e8', ['Ne8-d6','Ne8-f6','Ne8-c7','Ne8-g7']],
      [$this->setUpBoard(['a8'=>'n','e1'=>'K','e8'=>'k']), 'a8', ['Na8-b6','Na8-c7']],
      [$this->setUpBoard(['c2'=>'P','b3'=>'p','d3'=>'p']), 'c2', ['Pc2-c3', 'Pc2-c4', 'Pc2xb3', 'Pc2xd3']],
    ];
  }

  /**
   * @covers ::moveIsOk
   * @dataProvider providerMoveIsOk()
   */
  public function testMoveIsOk(Board $board, $move, $expected) {
    $from_square = Square::fromCoordinate(substr($move, 1, 2));
    $to_square = Square::fromCoordinate(substr($move, -2, 2));
    $this->assertEquals($expected, $board->moveIsOk($from_square, $to_square), 'Move ' . $move . ' is ' . ($expected ? 'ok' : 'not ok'));
  }

  public function providerMoveIsOk() {
    $default_board = (new Board())->setupAsStandard();
    $open_board = $this->getOpenBoard();
    $simple_board = $this->setUpBoard(['c2'=>'P','b3'=>'p','d3'=>'p','e5'=>'p','f5'=>'P'])->setEnPassantSquare('e6');
    return [
      [$default_board, 'Nb1-c3', TRUE], [$default_board, 'Nb1-a3', TRUE], [$default_board, 'Ng1-f3', TRUE],
      [$default_board, 'Ng1-h3', TRUE], [$default_board, 'Nb1-a2', FALSE], [$default_board, 'Ng1-e3', FALSE],
      [$default_board, 'Pb2-b3', TRUE], [$default_board, 'Ph2-h3', TRUE], [$default_board, 'Pg2-g4', TRUE],
      [$default_board, 'Ng2-h3', FALSE], [$default_board, 'Qb1-a2', FALSE], [$default_board, 'Kg1-e3', FALSE],
      [$default_board, 'Ng8-h6', TRUE], [$default_board, 'Nb8-a6', TRUE], [$default_board, 'Ng8-f6', TRUE],
      [$default_board, 'Qg8-h8', FALSE], [$default_board, 'Ra8-b8', FALSE], [$default_board, 'Bc1-b2', FALSE],
      
      [$open_board, 'Bg2-h3', TRUE], [$open_board, 'Qd1-d2', TRUE], [$open_board, 'Ke1-f1', TRUE],
      [$open_board, 'Ke1-g1', TRUE], [$open_board, 'Rh1-g1', TRUE], [$open_board, 'Rh1-f1', TRUE],
      [$open_board, 'Ke1-a1', FALSE], [$open_board, 'Rh8-h1', FALSE], [$open_board, 'Bg7-f6', FALSE],
      [$open_board, 'Pe6-e4', FALSE], [$open_board, 'Ke8-e7', FALSE], [$open_board, 'Qd1-a1', FALSE],

      [$simple_board, 'Pc2-c3', TRUE], [$simple_board, 'Pc2-c4', TRUE], [$simple_board, 'Pc2xb3', TRUE],
      [$simple_board, 'Pc2xd3', TRUE], [$simple_board, 'Pd3xc2', TRUE], [$simple_board, 'Pb3xc2', TRUE],
      [$simple_board, 'Pf5xe6', TRUE], [$simple_board, 'Pe5xd4', FALSE], [$simple_board, 'Pe5xf5', FALSE],
      [$simple_board, 'Pf5-f4', FALSE], [$simple_board, 'Pf5-f6', TRUE], [$simple_board, 'Pf5xg6', FALSE],
      [$simple_board, 'Pe5-e6', FALSE], [$simple_board, 'Pe5-e4', TRUE], [$simple_board, 'Pe5xd4', FALSE],
    ];
  }

  /**
   * @covers ::squareIsReachable
   * @dataProvider providerSquareIsReachable()
   */
  public function testSquareIsReachable(Board $board, $square_from, $square_to, $expected) {
    $this->assertEquals($expected, $board->squareIsReachable(Square::fromCoordinate($square_from), Square::fromCoordinate($square_to)));
  }

  public function providerSquareIsReachable() {
    $board = $this->getAggressiveOpenBoard();
    return [
      [$board, 'a8', 'a3', TRUE], [$board, 'b4', 'b5', TRUE],
      [$board, 'c4', 'c5', TRUE], [$board, 'd3', 'd4', TRUE],
      [$board, 'e4', 'c5', TRUE], [$board, 'e4', 'c3', TRUE],
      [$board, 'c3', 'e4', TRUE], [$board, 'f4', 'e6', FALSE],
      [$board, 'f4', 'f6', TRUE], [$board, 'f4', 'g5', TRUE],
      [$board, 'f4', 'e4', TRUE], [$board, 'f4', 'c4', FALSE],
      [$board, 'd8', 'd3', TRUE], [$board, 'd8', 'g5', FALSE],
      [$board, 'a1', 'a3', TRUE], [$board, 'g7', 'c3', TRUE],
      [$board, 'g2', 'e4', TRUE], [$board, 'g2', 'c6', FALSE],
      [$board, 'g2', 'h3', TRUE], [$board, 'g2', 'f1', TRUE],
      [$board, 'g2', 'h1', TRUE], [$board, 'g2', 'd5', FALSE],
      [$board, 'c6', 'e7', TRUE], [$board, 'c6', 'd4', TRUE],
      [$board, 'c6', 'a4', FALSE], [$board, 'c6', 'b4', TRUE],
      [$board, 'f4', 'd8', FALSE], [$board, 'f4', 'b8', TRUE],
      [$board, 'f4', 'h6', FALSE], [$board, 'f4', 'h4', TRUE],
      [$board, 'a1', 'd1', FALSE], [$board, 'a1', 'b1', TRUE],
      [$board, 'a1', 'a6', FALSE], [$board, 'a1', 'a2', TRUE],
    ];
  }

  /**
   * @covers ::pawnAttacks
   * @dataProvider providerPawnAttacks()
   */
  public function testPawnAttacks(Board $board, $square_from, $square_to, $expected) {
    $this->assertEquals($expected, $board->pieceAttacks(Square::fromCoordinate($square_from), Square::fromCoordinate($square_to)));
  }

  public function providerPawnAttacks() {
    $board = $this->getAggressiveOpenBoard();
    return [
      [$board, 'a3', 'b4', TRUE], [$board, 'a3', 'a4', FALSE],
      [$board, 'b4', 'c5', TRUE], [$board, 'b4', 'b3', FALSE],
      [$board, 'b4', 'a5', TRUE], [$board, 'b4', 'b6', FALSE],
      [$board, 'e2', 'f3', TRUE], [$board, 'e2', 'g4', FALSE],
    ];
  }

  /**
   * @covers ::pathIsNotBlocked
   * @dataProvider providerPathIsNotBlocked()
   */
  public function testPathIsNotBlocked(Board $board, $square_from, $square_to, $direction, $expected) {
    $start = Square::fromCoordinate($square_from)->getIndex();
    $end = Square::fromCoordinate($square_to)->getIndex();
    $this->assertEquals($expected, $board->pathIsNotBlocked($start, $end, $direction));
  }

  public function providerPathIsNotBlocked() {
    $board = $this->getAggressiveOpenBoard();
    return [
      [$board, 'a7', 'a3', -8, TRUE],
      [$board, 'd7', 'd3', -8, TRUE],
      [$board, 'd5', 'g5', 1, TRUE],
      [$board, 'f6', 'c3', -9, TRUE],
    ];
  }

}
