<?php

namespace Drupal\vchess\Game;
/*
 * @file
 *
 * This file contains functions related to individual pieces
 */

use Drupal\Component\Utility\Unicode;

class Piece {
  public $type;  // R, N, B, Q, K or a blank
  public $color; // w or b

  /**
   * Set the type, one of R, N, B, Q, K or a blank
   *
   * @param string $type
   */
  public function setType($type) {
    $this->type = Unicode::strtoupper($type);
  }

  /**
   * Get the type one of R, N, B, Q, K or a blank
   *
   * @return string
   */
  public function type() {
    return $this->type;
  }

  /**
   * Get the piece color.  Color is w or b
   *
   * @return string
   */
  public function color() {
    return $this->color;
  }

  /**
   * Set the piece color.  Color is w or b
   */
  public function setColor($color) {
    $this->color = $color;
  }

  public function name() {
    return $this->nameFromType($this->type);
  }

  /**
   * Get the FEN (case-sensitive) type for the piece
   * i.e. white pieces are returned as upper case letters
   * and black pieces as lower case letters
   */
  public function getFenType() {
    $type = $this->type(); // by default already upper case
    if ($this->color() == 'b') {
      $type = Unicode::strtolower($type);
    }

    return $type;
  }

  /**
   * Get full name of chessman from chessman identifier.
   */
  protected function nameFromType($type) {
    $name = BLANK;

    $type = Unicode::strtoupper($type);
    switch ($type) {
      case 'P':
        $name = 'Pawn';
        break;
      case 'R':
        $name = 'Rook';
        break;
      case 'N':
        $name = 'Knight';
        break;
      case 'B':
        $name = 'Bishop';
        break;
      case 'K':
        $name = 'King';
        break;
      case 'Q':
        $name = 'Queen';
        break;
    }
    return $name;
  }
}


