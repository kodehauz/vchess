<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Piece;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Board
 */
class BoardExtendedTest extends UnitTestCase {

  use BoardTestTrait;

  /**
   * @covers ::isStalemate
   * @dataProvider providerIsStalemate()
   */
  public function testIsStalemate(Board $board, $player, $expected) {
    $this->assertEquals($expected, $board->isStalemate($player));
  }

  public function providerIsStalemate() {
    $board = (new Board())->setupAsStandard();
    return [
      [$board, 'w', FALSE],
      [$board, 'b', FALSE],
    ];
  }

  /**
   * @covers ::getCapturedPieces
   * @dataProvider providerGetCapturedPieces()
   */
  public function testGetCapturedPieces(Board $board, $expected) {
    $this->assertEquals($expected, $board->getCapturedPieces());
  }

  public function providerGetCapturedPieces() {
    $board = $this->setUpBoard([
      'a1' => 'R', 'c1' => 'B', 'f4' => 'Q', 'e1' => 'K', 'h1' => 'R',
      'a3' => 'P', 'e2' => 'P', 'f2' => 'P', 'h2' => 'P',
      'e7' => 'p', 'f7' => 'p', 'h7' => 'p',
      'a8' => 'r', 'c8' => 'b', 'e8' => 'k',
      'b6' => 'p', 'c5' => 'p', 'e6' => 'p', 'g6' => 'p',
      'g7' => 'b', 'c6' => 'n', 'e4' => 'n',
    ]);
    return [
      [$board, $this->makePieces(['B', 'N', 'N', 'P', 'P', 'P', 'P', 'p', 'q', 'r'])],
    ];
  }

  protected function makePieces(array $pieces) {
    $return = [];
    foreach ($pieces as $piece) {
      $return[] = Piece::fromFenType($piece);
    }
    return $return;
  }

}
