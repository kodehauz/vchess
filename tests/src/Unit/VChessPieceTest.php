<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Piece;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Piece
 */
class VChessPieceTest extends UnitTestCase {

  /**
   * @covers ::setType
   * @covers ::getType
   * @covers ::setColor
   * @covers ::getColor
   * @covers ::getName
   * @covers ::getFenType
   * @dataProvider providerGetterSetter()
   */
  public function testSettersAndGetters($type, $color, $name, $fen_type) {
    $piece = (new Piece())->setType($type)->setColor($color);
    $this->assertEquals($name, $piece->getName());
    $this->assertEquals($fen_type, $piece->getFenType());
    $this->assertEquals(strtoupper($type), $piece->getType());
    $this->assertEquals($color, $piece->getColor());
  }

  /**
   * Provides data for getter and setter tests.
   */
  public function providerGetterSetter() {
    return [
      ['P', 'w', 'Pawn', 'P'],   ['p', 'w', 'Pawn', 'P'],   ['P', 'b', 'Pawn', 'p'],   ['p', 'b', 'Pawn', 'p'],
      ['R', 'w', 'Rook', 'R'],   ['r', 'w', 'Rook', 'R'],   ['R', 'b', 'Rook', 'r'],   ['r', 'b', 'Rook', 'r'],
      ['B', 'w', 'Bishop', 'B'], ['b', 'w', 'Bishop', 'B'], ['B', 'b', 'Bishop', 'b'], ['b', 'b', 'Bishop', 'b'],
      ['N', 'w', 'Knight', 'N'], ['n', 'w', 'Knight', 'N'], ['N', 'b', 'Knight', 'n'], ['n', 'b', 'Knight', 'n'],
      ['K', 'w', 'King', 'K'],   ['k', 'w', 'King', 'K'],   ['K', 'b', 'King', 'k'],   ['k', 'b', 'King', 'k'],
      ['Q', 'w', 'Queen', 'Q'],  ['q', 'w', 'Queen', 'Q'],  ['Q', 'b', 'Queen', 'q'],  ['q', 'b', 'Queen', 'q'],
    ];
  }

}
