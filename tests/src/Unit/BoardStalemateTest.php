<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\vchess\Game\Board;

/**
 * @group vchess
 * @coversDefaultClass \Drupal\vchess\Game\Board
 */
class BoardStalemateTest extends UnitTestCase {

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
}
