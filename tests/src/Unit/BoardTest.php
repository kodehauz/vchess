<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Direction;
use Drupal\vchess\Game\Square;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Board
 */
class BoardTest extends UnitTestCase {

  /**
   * @covers ::getSquaresOfPieceType
   * @dataProvider providerSquaresOfPieceType()
   */
  public function testGetSquaresOfPieceType(Board $board, $type, $color, array $squares) {
    $this->assertEquals($squares, $board->getSquaresOfPieceType($type, $color));
  }

  /**
   * Provides data for tests that translate between square notations.
   */
  public function providerSquaresOfPieceType() {
    $full_board = (new Board())->setupAsStandard();
    return [
      [$full_board, 'P', 'w', $this->makeSquares(['a2','b2','c2','d2','e2','f2','g2','h2'])],
      [$full_board, 'P', 'b', $this->makeSquares(['a7','b7','c7','d7','e7','f7','g7','h7'])],
      [$full_board, 'R', 'w', $this->makeSquares(['a1','h1'])],
      [$full_board, 'R', 'b', $this->makeSquares(['a8','h8'])],
      [$full_board, 'N', 'w', $this->makeSquares(['b1','g1'])],
      [$full_board, 'N', 'b', $this->makeSquares(['b8','g8'])],
      [$full_board, 'B', 'w', $this->makeSquares(['c1','f1'])],
      [$full_board, 'B', 'b', $this->makeSquares(['c8','f8'])],
      [$full_board, 'K', 'w', $this->makeSquares(['e1'])],
      [$full_board, 'K', 'b', $this->makeSquares(['e8'])],
      [$full_board, 'Q', 'w', $this->makeSquares(['d1'])],
      [$full_board, 'Q', 'b', $this->makeSquares(['d8'])],
    ];
  }

  /**
   * @covers ::getSquaresOfPieceType
   * @dataProvider providerSquaresOfPieceColor()
   */
  public function testGetSquaresOfPieceColor(Board $board, $color, array $squares) {
    $this->assertEquals($squares, $board->getSquaresOfPieceColor($color));
  }

  /**
   * Provides data for tests that translate between square notations.
   */
  public function providerSquaresOfPieceColor() {
    $full_board = (new Board())->setupAsStandard();
    return [
      [$full_board, 'w', $this->makeSquares(['a2','b2','c2','d2','e2','f2','g2','h2','a1','b1','c1','d1','e1','f1','g1','h1'])],
      [$full_board, 'b', $this->makeSquares(['a8','b8','c8','d8','e8','f8','g8','h8','a7','b7','c7','d7','e7','f7','g7','h7'])],
    ];
  }

  /**
   * @covers ::singleDiagonalSquares
   * @dataProvider providerSingleDiagonalSquares()
   */
  public function testSingleDiagonalSquares($from_a1, $direction, array $expected_squares) {
    $expected_squares = $this->makeSquares($expected_squares);
    $squares = TestBoard::singleDiagonalSquares((new Square())->setCoordinate($from_a1), $direction);
    $this->assertEquals($expected_squares, $squares);
  }

  /**
   * Data provider for testSingleDiagonalSquares()
   */
  public function providerSingleDiagonalSquares() {
    return [
      ['a1', Direction::UP_RIGHT, ['a1','b2','c3','d4','e5','f6','g7','h8']],
      ['c6', Direction::UP, ['c6','c7','c8']],
      ['h2', Direction::UP_LEFT, ['h2','g3','f4','e5','d6','c7','b8']],
      ['h2', Direction::DOWN_RIGHT, ['h2']],
    ];
  }

  /**
   * @covers ::getSquaresOnRankFile
   * @covers ::getSquaresOnRank
   * @covers ::getSquaresOnFile
   */
  public function testGetSquaresOnRankFile() {
    $actual_squares = Board::getSquaresOnRank('5');
    $expected_squares =$this->makeSquares(['a5','b5','c5','d5','e5','f5','g5','h5']);
    $this->assertEquals($expected_squares, $actual_squares);

    $actual_squares = Board::getSquaresOnFile('e');
    $expected_squares =$this->makeSquares(['e1','e2','e3','e4','e5','e6','e7','e8']);
    $this->assertEquals($expected_squares, $actual_squares);

    $actual_squares = Board::getSquaresOnRankFile((new Square())->setCoordinate('e5'));
    $expected_squares =$this->makeSquares(['a5','b5','c5','d5','f5','g5','h5','e1','e2','e3','e4','e5','e6','e7','e8']);
    $this->assertEquals($expected_squares, $actual_squares);
  }

  /**
   * @covers ::getDiagonalSquares
   * @dataProvider providerGetDiagonalSquares()
   */
  public function testGetDiagonalSquares($from_square, array $expected_squares) {
    $expected_squares = $this->makeSquares($expected_squares);
    $squares = TestBoard::getDiagonalSquares((new Square())->setCoordinate($from_square));
    $this->assertEquals($expected_squares, $squares);
  }
  
  public function providerGetDiagonalSquares() {
    return [
      ['e4', ['b1','c2','d3','e4','f5','g6','h7','a8','b7','c6','d5','f3','g2','h1']],
      ['a1', ['a1','b2','c3','d4','e5','f6','g7','h8']],
    ];
  }

  public function testFenNotation() {
    $board = new Board();

    $notation = Board::BOARD_DEFAULT;
    $board->setupPosition($notation);
    $this->assertEquals($notation, $board->getFenString());

    $notation = Board::BOARD_PROMOTION;
    $board->setupPosition($notation);
    $this->assertEquals($notation, $board->getFenString());
  }

  /**
   * Creates Square objects from specified coordinates.
   * @param array $coordinates
   * @return array
   */
  protected function makeSquares(array $coordinates) {
    $squares = [];
    foreach ($coordinates as $coordinate) {
      $squares[] = (new Square())->setCoordinate($coordinate);
    }
    return $squares;
  }

  /**
   * @dataProvider providerSquareIsEmpty()
   */
  public function testSquareIsEmpty($coordinate, $expected) {
    $board = new Board();
    $board->setupAsStandard();
    $this->assertEquals($expected, $board->squareIsEmpty(Square::fromCoordinate($coordinate)));
  }

  public function providerSquareIsEmpty() {
    return [
      ['a1', FALSE],
      ['a1', FALSE],
      ['h1', FALSE],
      ['e5', TRUE],
      ['f6', TRUE],
    ];
  }

}

class TestBoard extends Board {

  public static function singleDiagonalSquares(Square $from_square, $direction) {
    return parent::singleDiagonalSquares($from_square, $direction);
  }

}
