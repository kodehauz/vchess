<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Direction;
use Drupal\vchess\Game\Square;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Square
 */
class VChessSquareTest extends UnitTestCase {

  /**
   * @covers ::getCoordinate
   * @covers ::setCoordinate
   * @covers ::getRank
   * @covers ::getFile
   * @covers ::getIndex
   * @dataProvider providerI2Square()
   */
  public function testSettersAndGetters($square_index, $square_a1) {
    $square = (new Square())->setCoordinate($square_a1);
    $this->assertEquals($square_index, $square->getIndex());
    $this->assertEquals($square_a1, $square->getCoordinate());
    $this->assertEquals($square_a1[0], $square->getFile());
    $this->assertEquals($square_a1[1], $square->getRank());
  }

  /**
   * @covers ::getColumn
   * @dataProvider providerSetGetColumn()
   */
  public function testSetGetColumn($coordinate, $column, $row) {
    $square = new Square();

    $square->setCoordinate($coordinate);
    $this->assertEquals($column, $square->getColumn());

    $square->setColumn($column)->setRow($row);
    $this->assertEquals($coordinate, $square->getCoordinate());
  }

  public function providerSetGetColumn() {
    return [
      ['a1', 1, 1],['c1', 3, 1],['a8', 1, 8],['e5', 5, 5],['h3', 8, 3],['f7', 6, 7],
    ];
  }

  /**
   * @covers ::fromIndex
   * @dataProvider providerI2Square()
   */
  public function testFromIndex($square_index, $square_a1) {
    $square = Square::fromIndex($square_index);
    $this->assertEquals($square_a1, $square->getCoordinate());
  }

  /**
   * @covers ::fromCoordinate
   * @dataProvider providerI2Square()
   */
  public function testFromCoordinate($square_index, $square_a1) {
    $square = Square::fromCoordinate($square_a1);
    $this->assertEquals($square_index, $square->getIndex());
  }

  /**
   * Provides data for tests that translate between square notations.
   */
  public function providerI2Square() {
    return [
      [0, 'a1'], [1, 'b1'],[2, 'c1'],[3, 'd1'],[4, 'e1'],[5, 'f1'],[6, 'g1'], [7, 'h1'],
      [8, 'a2'], [9, 'b2'],[10, 'c2'],[11, 'd2'],[12, 'e2'],[13, 'f2'],[14, 'g2'], [15, 'h2'],
      [16, 'a3'], [17, 'b3'],[18, 'c3'],[19, 'd3'],[20, 'e3'],[21, 'f3'],[22, 'g3'], [23, 'h3'],
      [24, 'a4'], [25, 'b4'],[26, 'c4'],[27, 'd4'],[28, 'e4'],[29, 'f4'],[30, 'g4'], [31, 'h4'],
      [32, 'a5'], [33, 'b5'],[34, 'c5'],[35, 'd5'],[36, 'e5'],[37, 'f5'],[38, 'g5'], [39, 'h5'],
      [40, 'a6'], [41, 'b6'],[42, 'c6'],[43, 'd6'],[44, 'e6'],[45, 'f6'],[46, 'g6'], [47, 'h6'],
      [48, 'a7'], [49, 'b7'],[50, 'c7'],[51, 'd7'],[52, 'e7'],[53, 'f7'],[54, 'g7'], [55, 'h7'],
      [56, 'a8'], [57, 'b8'],[58, 'c8'],[59, 'd8'],[60, 'e8'],[61, 'f8'],[62, 'g8'], [63, 'h8'],
    ];
  }

  /**
   * @covers ::nextSquare
   * @dataProvider providerNextSquare()
   */
  public function testNextSquare($square_a1, $direction, $next_square_a1) {
    $this->assertEquals($next_square_a1, (new Square())->setCoordinate($square_a1)->nextSquare($direction)->getCoordinate());
  }

  public function providerNextSquare() {
    return [
      ['a1', Direction::UP, 'a2'], ['h6', Direction::UP, 'h7'],['f5', Direction::UP, 'f6'],
      ['f8', Direction::UP, 'f8'], ['e4', Direction::UP, 'e5'],['h1', Direction::UP, 'h2'],
      ['a1', Direction::DOWN, 'a1'], ['h6', Direction::DOWN, 'h5'],['f5', Direction::DOWN, 'f4'],
      ['f8', Direction::DOWN, 'f7'], ['e4', Direction::DOWN, 'e3'],['h1', Direction::DOWN, 'h1'],
      ['a1', Direction::LEFT, 'a1'], ['h6', Direction::LEFT, 'g6'],['f5', Direction::LEFT, 'e5'],
      ['f8', Direction::LEFT, 'e8'], ['e4', Direction::LEFT, 'd4'],['h1', Direction::LEFT, 'g1'],
      ['a1', Direction::RIGHT, 'b1'], ['h6', Direction::RIGHT, 'h6'],['f5', Direction::RIGHT, 'g5'],
      ['f8', Direction::RIGHT, 'g8'], ['e4', Direction::RIGHT, 'f4'],['h1', Direction::RIGHT, 'h1'],
      ['a1', Direction::UP_LEFT, 'a1'], ['h6', Direction::UP_LEFT, 'g7'],['f5', Direction::UP_LEFT, 'e6'],
      ['f8', Direction::UP_LEFT, 'f8'], ['e4', Direction::UP_LEFT, 'd5'],['h1', Direction::UP_LEFT, 'g2'],
      ['a1', Direction::DOWN_LEFT, 'a1'], ['h6', Direction::DOWN_LEFT, 'g5'],['f5', Direction::DOWN_LEFT, 'e4'],
      ['f8', Direction::DOWN_LEFT, 'e7'], ['e4', Direction::DOWN_LEFT, 'd3'],['h1', Direction::DOWN_LEFT, 'h1'],
      ['a1', Direction::UP_RIGHT, 'b2'], ['h6', Direction::UP_RIGHT, 'h6'],['f5', Direction::UP_RIGHT, 'g6'],
      ['f8', Direction::UP_RIGHT, 'f8'], ['e4', Direction::UP_RIGHT, 'f5'],['h1', Direction::UP_RIGHT, 'h1'],
      ['a1', Direction::DOWN_RIGHT, 'a1'], ['h6', Direction::DOWN_RIGHT, 'h6'],['f5', Direction::DOWN_RIGHT, 'g4'],
      ['f8', Direction::DOWN_RIGHT, 'g7'], ['e4', Direction::DOWN_RIGHT, 'f3'],['h1', Direction::DOWN_RIGHT, 'h1'],
    ];
  }

  public function providerInvalidData() {
    return [
      [NULL, 'q4'], [NULL, '03'],
    ];
  }

}
