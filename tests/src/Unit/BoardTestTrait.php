<?php

namespace Drupal\Tests\vchess\Unit;

use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Piece;
use Drupal\vchess\Game\Square;

/**
 * Provides common methods for board tests.
 */
trait BoardTestTrait {

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

  /**
   * Returns a more open board position with pieces in attacking positions.
   */
  protected function getAggressiveOpenBoard() {
    return $this->setUpBoard([
      'a1' => 'R', 'c1' => 'B', 'f4' => 'Q', 'e1' => 'K', 'h1' => 'R',
      'a3' => 'P', 'e2' => 'P', 'f2' => 'P', 'h2' => 'P',
      'b4' => 'P', 'c4' => 'P', 'd3' => 'P', 'g3' => 'P',
      'g2' => 'B', 'c3' => 'N', 'g5' => 'N',
      'a8' => 'r', 'c8' => 'b', 'd8' => 'q', 'e8' => 'k', 'h8' => 'r',
      'e7' => 'p', 'f7' => 'p', 'h7' => 'p',
      'b6' => 'p', 'c5' => 'p', 'e6' => 'p', 'g6' => 'p',
      'g7' => 'b', 'c6' => 'n', 'e4' => 'n',
    ]);
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

}
