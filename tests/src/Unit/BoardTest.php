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

  use BoardTestTrait;

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
    $squares = TestBoard::singleDiagonalSquares(Square::fromCoordinate($from_a1), $direction);
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

    $actual_squares = Board::getSquaresOnRankFile(Square::fromCoordinate('e5'));
    $expected_squares =$this->makeSquares(['a5','b5','c5','d5','f5','g5','h5','e1','e2','e3','e4','e5','e6','e7','e8']);
    $this->assertEquals($expected_squares, $actual_squares);
  }

  /**
   * @covers ::getDiagonalSquares
   * @dataProvider providerGetDiagonalSquares()
   */
  public function testGetDiagonalSquares($from_square, array $expected_squares) {
    $expected_squares = $this->makeSquares($expected_squares);
    $squares = TestBoard::getDiagonalSquares(Square::fromCoordinate($from_square));
    sort($squares);
    $this->assertEquals($expected_squares, $squares);
  }
  
  public function providerGetDiagonalSquares() {
    return [
      ['e4', ['b1','h1','c2','g2','d3','f3','e4','d5','f5','c6','g6','b7','h7','a8']],
      ['a1', ['a1','b2','c3','d4','e5','f6','g7','h8']],
    ];
  }

  /**
   * @covers ::getKnightMoveSquares
   * @dataProvider providerGetKnightMoveSquares()
   */
  public function testGetKnightMoveSquares($from_square, array $expected_squares) {
    $expected_squares = $this->makeSquares($expected_squares);
    $squares = TestBoard::getKnightMoveSquares(Square::fromCoordinate($from_square));
    $this->assertEquals($expected_squares, $squares);
  }
  
  public function providerGetKnightMoveSquares() {
    return [
      ['e5', ['d3','f3','c4','g4','c6','g6','d7','f7']],
      ['e8', ['d6','f6','c7','g7']],
      ['a8', ['b6', 'c7']],
    ];
  }

  /**
   * @covers ::getSquareInFront
   * @dataProvider providerGetSquareInFront()
   */
  public function testGetSquareInFront(Board $board, $square, $expected) {
    $square = Square::fromCoordinate($square);
    $expected = Square::fromCoordinate($expected);
    $this->assertEquals($expected, $board->getSquareInFront($square));
  }

  public function providerGetSquareInFront() {
    $board = $this->getOpenBoard();
    return [
      [$board, 'a2', 'a3'], [$board, 'e2', 'e3'], [$board, 'c4', 'c5'],
      [$board, 'e7', 'e6'], [$board, 'g6', 'g5'], [$board, 'b6', 'b5'],
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

  /**
   * @covers ::getLongMove
   * @dataProvider providerGetLongMove()
   */
  public function testGetLongMove(Board $board, array $squares, $expected) {
    $square_from = Square::fromCoordinate($squares[0]);
    $square_to = Square::fromCoordinate($squares[1]);
    $this->assertEquals($expected, $board->getLongMove($square_from, $square_to));
  }

  public function providerGetLongMove() {
    $board = $this->getOpenBoard();
    $board2 = $this->getAggressiveOpenBoard();
    return [
      [$board, ['a1','b1'], 'Ra1-b1'], [$board, ['f3','g5'], 'Nf3-g5'],
      [$board, ['a8','b8'], 'Ra8-b8'], [$board, ['f6','g4'], 'Nf6-g4'],
      [$board, ['a2','a3'], 'Pa2-a3'], [$board, ['e7','e6'], 'Pe7-e6'],
      [$board2, ['g5','h7'], 'Ng5xh7'], [$board2, ['a8','a3'], 'Ra8xa3'],
      [$board2, ['e4','g3'], 'Ne4xg3'], [$board2, ['g2','e4'], 'Bg2xe4'],
    ];
  }

  /**
   * @covers ::getAdjacentSquares
   * @dataProvider providerGetAdjacentSquares()
   */
  public function testGetAdjacentSquares(Board $board, $square, array $expected) {
    $square = Square::fromCoordinate($square);
    $expected = $this->makeSquares($expected);
    $this->assertEquals($expected, $board->getAdjacentSquares($square));
  }

  public function providerGetAdjacentSquares() {
    $open_board = $this->getOpenBoard();
    return [
      [$open_board, 'a1', ['a2','b2','b1']],
      [$open_board, 'e1', ['e2','f2','f1','d1','d2']],
      [$open_board, 'e5', ['e6','f6','f5','f4','e4','d4','d5','d6']],
      [$open_board, 'h8', ['h7','g7','g8']],
      [$open_board, 'g7', ['g8','h8','h7','h6','g6','f6','f7','f8']],
    ];
  }

  /**
   * @covers ::getKingSquare
   * @dataProvider providerGetKingSquare()
   */
  public function testGetKingSquare(Board $board, $white_square, $black_square) {
    $white_square = Square::fromCoordinate($white_square);
    $black_square = Square::fromCoordinate($black_square);
    $this->assertEquals($white_square, $board->getKingSquare('w'));
    $this->assertEquals($black_square, $board->getKingSquare('b'));
  }

  public function providerGetKingSquare() {
    $board = $this->getOpenBoard();
    return [
      [$board, 'e1', 'e8'],
      [$this->setUpBoard(['h1' => 'K', 'a1' => 'k']), 'h1', 'a1'],
      [$this->setUpBoard(['b5' => 'K', 'c3' => 'k']), 'b5', 'c3'],
      [$this->setUpBoard(['g4' => 'K', 'c6' => 'k']), 'g4', 'c6'],
      [$this->setUpBoard(['b8' => 'K', 'f6' => 'k']), 'b8', 'f6'],
    ];
  }

  /**
   * @covers ::getSquaresAttackingSquare
   * @dataProvider providerGetSquaresAttackingSquare()
   */
  public function testGetSquaresAttackingSquare(Board $board, $attacked_square, $attacker, array $expected) {
    $attacked_square = Square::fromCoordinate($attacked_square);
    $expected = $this->makeSquares($expected);
    $this->assertEquals($expected, $board->getSquaresAttackingSquare($attacked_square, $attacker));
  }

  public function providerGetSquaresAttackingSquare() {
    $board = $this->setUpBoard([
      'e5' => 'P', 'g7' => 'b', 'd6' => 'p', 'e7' => 'q',
      'a8' => 'r', 'e8' => 'k', 'e1' => 'K', 'd1' => 'Q',
    ]);
    return [
      [$board, 'e5', 'b', ['g7', 'd6', 'e7']],
      [$board, 'd6', 'w', ['e5', 'd1']],
    ];
  }

  /**
   * @covers ::performCastling
   * @dataProvider providerPerformCastling()
   */
  public function testPerformCastling(Board $board, $from, $to, $expected) {
    $castling = $board->performCastling(Square::fromCoordinate($from), Square::fromCoordinate($to));
    $this->assertEquals($expected, $castling);
    $to_square = Square::fromCoordinate($to);
    $this->assertEquals($expected, $board->getPiece($to_square)->getType() === 'K');
    $rook_square = Square::fromCoordinate($from);
    // Rook final square should be in between king's start and end squares.
    $rook_square->setColumn(($rook_square->getColumn() + $to_square->getColumn()) / 2);
    $this->assertEquals($expected, $board->getPiece($rook_square)->getType() === 'R');
  }

  public function providerPerformCastling() {
    $board = $this->getOpenBoard();
    $board2 = $this->getOpenBoard();
    $board2->movePiece(Square::fromCoordinate('h1'), Square::fromCoordinate('g1'));
    $board2->movePiece(Square::fromCoordinate('h8'), Square::fromCoordinate('g8'));
    $board2->movePiece(Square::fromCoordinate('c1'), Square::fromCoordinate('b2'));
    $board2->movePiece(Square::fromCoordinate('c8'), Square::fromCoordinate('b7'));
    $board2->movePiece(Square::fromCoordinate('d8'), Square::fromCoordinate('d7'));
    return [
      [$board, 'e1', 'g1', TRUE], [$board, 'e1', 'c1', FALSE],
      [$board, 'e8', 'g8', TRUE], [$board, 'e8', 'c8', FALSE],
      [$board2, 'e1', 'g1', FALSE], [$board2, 'e1', 'c1', FALSE],
      [$board2, 'e8', 'g8', FALSE], [$board2, 'e8', 'c8', TRUE],
    ];
  }

}

class TestBoard extends Board {

  public static function singleDiagonalSquares(Square $from_square, $direction) {
    return parent::singleDiagonalSquares($from_square, $direction);
  }

}
