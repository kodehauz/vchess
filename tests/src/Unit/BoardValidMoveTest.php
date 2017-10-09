<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Piece;
use Drupal\vchess\Game\Square;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Board
 */
class BoardValidMoveTest extends UnitTestCase {

  /**
   * @dataProvider providerGetValidMoves()
   * @covers ::getValidMoves
   */
  public function testGetValidMoves(Board $board, $coordinate, array $moves) {
    $square = (new Square())->setCoordinate($coordinate);
    $this->assertEquals($moves, $board->getValidMoves($square));
  }

  public function providerGetValidMoves() {
    $default_board = (new Board())->setupAsStandard();
    return [
      [$default_board, 'a1', []],
      [$default_board, 'b1', ['Nb1-a3', 'Nb1-c3']],
      [$default_board, 'c1', []],
      [$default_board, 'g2', ['Pg2-g3', 'Pg2-g4']],
      [$default_board, 'g1', ['Ng1-f3', 'Ng1-h3']],
      [$default_board, 'h1', []],
      [$default_board, 'e2', ['Pe2-e3', 'Pe2-e4']],
      [$this->setUpBoard(['e5' => 'n']), 'e5', ['Ne5-f7', 'Ne5-g6', 'Ne5-g4', 'Ne5-f3', 'Ne5-d7', 'Ne5-c6', 'Ne5-c4', 'Ne5-d3']],
      [$this->setUpBoard(['e8' => 'n']), 'e8', ['Ne8-f6', 'Ne8-g7', 'Ne8-d6', 'Ne8-c7']],
      [$this->setUpBoard(['a8' => 'n']), 'a8', ['Ne8-f6', 'Ne8-g7']],
      [$this->setUpBoard(['c2' => 'P', 'b3' => 'p', 'd3' => 'p']), 'c2', ['Pc2-c3', 'Pc2-c4', 'Pc2xb3', 'Pc2xd3']],
    ];
  }

  /**
   * @covers ::moveIsOk
   * @dataProvider providerMoveIsOk()
   */
  public function testMoveIsOk(Board $board, $move, $expected) {
    $from_square = (new Square())->setCoordinate(substr($move, 1, 2));
    $to_square = (new Square())->setCoordinate(substr($move, -2, 2));
    $this->assertEquals($expected, $board->moveIsOk($from_square, $to_square), 'Move ' . $move . ' is ' . ($expected ? 'ok' : 'not ok'));
  }

  public function providerMoveIsOk() {
    $default_board = (new Board())->setupAsStandard();
    $open_board = $this->getOpenBoard();
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
    ];
  }

  /**
   * Helper method to construct a board FEN string quickly.
   *
   * @param array $piece_positions
   *   Array holding positions of pieces keyed by position. E.g.
   *   ['a1' => 'R', 'b3' => 'p'] means a board with white rook at a1 and black
   *   pawn at b3.
   * 
   * @return \Drupal\vchess\Game\Board
   *   The board that is setup.
   */
  protected function setUpBoard(array $piece_positions) {
    $board = new Board();
    foreach ($piece_positions as $position => $piece_string) {
      $piece = new Piece();
      if (strtoupper($piece_string) === $piece_string) {
        $piece->setColor('w');
      }
      else {
        $piece->setColor('b');
      }
      $piece->setType($piece_string);
      $board->setPiece($piece, $position);
    }
    return $board;
  }

  /**
   * Returns slightly more open board position for testing purposes. 
   */
  protected function getOpenBoard() {
    return $this->setUpBoard([
      'a1' => 'R', 'c1' => 'B', 'd1' => 'Q', 'e1' => 'K', 'h1' => 'R',
      'a2' => 'P', 'e2' => 'P', 'f2' => 'P', 'h2' => 'P',
      'b3' => 'P', 'c4' => 'P', 'd3' => 'P', 'g3' => 'P',
      'g2' => 'B', 'c3' => 'N', 'f3' => 'N',
      'a8' => 'r', 'c8' => 'b', 'd8' => 'q', 'e8' => 'k', 'h8' => 'r',
      'a7' => 'p', 'e7' => 'p', 'f7' => 'p', 'h7' => 'p',
      'b6' => 'p', 'c5' => 'p', 'd6' => 'p', 'g6' => 'p',
      'g7' => 'b', 'c6' => 'n', 'f6' => 'n',
    ]);
  }
}
