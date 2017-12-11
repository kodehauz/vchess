<?php

namespace Drupal\vchess\Game;

/*
 * Contains functionality for individual chess pieces.
 */
class Piece {

  /**
   * Represents a blank piece.
   */
  const BLANK = ' ';

  /**
   * The type of a piece (P, R, N, B, Q, K or a blank).
   *
   * @var string
   */
  public $type;

  /**
   * The color of the piece (w or b).
   *
   * @var string
   */
  public $color;

  /**
   * Gets the type one of P, R, N, B, Q, K or a blank
   *
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Sets the type, one of P, R, N, B, Q, K or a blank
   *
   * @param string $type
   *   The piece type - always in uppercase.
   *
   * @return $this
   *   For method chaining.
   */
  public function setType($type) {
    $this->type = strtoupper($type);
    return $this;
  }

  /**
   * Get the piece color.  Color is w or b.
   *
   * @return string
   */
  public function getColor() {
    return $this->color;
  }

  /**
   * Sets the piece color.
   *
   * @param string $color
   *   The piece color - either w or b.
   *
   * @return $this
   *   For method chaining.
   */
  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  /**
   * Gets the name for this piece.
   *
   * @return string
   */
  public function getName() {
    return $this->nameFromType($this->type);
  }

  /**
   * Gets the FEN (case-sensitive) type for the piece.
   *
   * White pieces are returned as uppercase letters and black pieces as
   * lowercase letters
   */
  public function getFenType() {
    // By default, $type is already upper case.
    $type = $this->getType();
    if ($this->getColor() === 'b') {
      $type = strtolower($type);
    }

    return $type;
  }

  /**
   * Get full name of chessman from chessman identifier.
   */
  protected function nameFromType($type) {
    $name = static::BLANK;

    $type = strtoupper($type);
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

  public static function fromFenType($fen_type) {
    $piece = new static();
    $piece->setColor($fen_type === strtoupper($fen_type) ? 'w' : 'b');
    $piece->setType($fen_type);
    return $piece;
  }

}
