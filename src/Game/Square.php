<?php

namespace Drupal\vchess\Game;

/**
 * Represents a chessboard square.
 * 
 * A Square represents one of the squares on the chessboard, it knows its
 * coordinate (rank and file), and can convert to and from different formats
 * like the "a1" format and also the 1 dimensional index [0..63] where 0 is "a1"
 * and 63 is "h8".
 */
class Square {

  /**
   * The rank of the square on the board.
   *
   * @var string
   */
  protected $rank = '';

  /**
   * The file of the square on the board.
   *
   * @var string
   */
  protected $file = '';

  /**
   * Get the rank (=row number) as a string, e.g. "4" for "d4"
   * 
   * @return string
   */
  function getRank() {
    return $this->rank;
  }
  
  /** 
   * Get the file (=column letter) e.g. "d" for "d4"
   */
  function getFile() {
    return $this->file;
  }

  function getColumn() {
    return ord($this->file) - 96;
  }
  
  /**
   * Get the coord, e.g. "d4"
   */
  function getCoordinate() {
    return $this->file . $this->rank;
  }

  /**
   * Set up the coord
   *
   * @param string $coordinate e.g. "d4"
   *
   * @return $this
   *   For method chaining.
   */
  public function setCoordinate($coordinate) {
    $this->rank = $coordinate[1];
    $this->file = $coordinate[0];
    return $this;
  }

  public function setColumn($column) {
    assert(is_integer($column) && $column >= 1 && $column <= 8);
    $this->file = chr($column + 96);
    return $this;
  }

  public function setRow($row) {
    // @todo Assert value is in correct range.
    $this->rank = $row;
    return $this;
  }

  /**
   * Convert coordinate [a1..h8] to 1dim index [0..63]
   *
   * a8=56, b8=57, c8=58, d8=59, e8=60, f8=61, g8=62, h8=63
   * a7=48, b7=49, c7=50, d7=51, e7=52, f7=53, g7=54, h7=55
   * a6=40, b6=41, c6=42, d6=43, e6=44, f6=45, g6=46, h6=47 
   * a5=32, b5=33, c5=34, d5=35, e5=36, f5=37, g5=38, h5=39
   * a4=24, b4=25, c4=26, d4=27, e4=28, f4=29, g4=30, h4=31
   * a3=16, b3=17, c3=18, d3=19, e3=20, f3=21, g3=22, h3=23 
   * a2=8,  b2=9,  c2=10, d2=11, e2=12, f2=13, g2=14, h2=15
   * a1=0,  b1=1,  c1=2,  d1=3,  e1=4,  f1=5,  g1=6,  h1=7
   * 
   */
  public function getIndex() {
    $row = $this->rank - 1;
    $col = ord($this->file) - 97;
    if ($row < 0 || $row > 7 || $col < 0 || $col > 7) {
      // 64 basically means an invalid position.
      return NULL;
    }
    return $row * 8 + $col;
  }

  /**
   * Calculates the next available square to move in the specified direction.
   *
   * @param int $direction
   *   The direction from among those in the \Drupal\vchess\Game\Direction class.
   * @param int $steps
   *   The number of steps to move. Defaults to 1.
   *
   * @return \Drupal\vchess\Game\Square
   *   A valid square to move to in the given direction. Note that this has no
   *   knowledge of whether there is a piece or not there.
   */
  public function nextSquare($direction, $steps = 1) {
    // Check boundaries.
    $available_steps = 7;
    if (Direction::isLeftward($direction)) {
      $available_steps = min($available_steps, $this->getColumn() - 1);
    }
    if (Direction::isRightward($direction)) {
      $available_steps = min($available_steps, 8 - $this->getColumn());
    }
    if (Direction::isUpward($direction)) {
      $available_steps = min($available_steps, 8 - $this->getRank());
    }
    if (Direction::isDownward($direction)) {
      $available_steps = min($available_steps, $this->getRank() - 1);
    }

    $steps = min($steps, $available_steps);

    if ($steps) {
      return static::fromIndex($this->getIndex() + ($direction * $steps));
    }
    else {
      return static::fromIndex($this->getIndex());
    }
  }

  /**
   * Creates an instance of a square from a coordinate position in "a1" format.
   *
   * @param string $coordinate
   *
   * @return $this
   */
  public static function fromCoordinate($coordinate) {
    return (new Square())->setCoordinate($coordinate);
  }

  /**
   * Create a square instance from a board index number [0..63].
   *
   * @param integer $index
   *   The index number.
   *
   * @return $this
   */
  public static function fromIndex($index) {
    $square = new static();

    if ($index >= 0 && $index <= 63) {
      $y = floor($index / 8) + 1;
      $x = chr(($index % 8) + 97);
      $square->setCoordinate($x . $y);
    }

    return $square;
  }

  /**
   * Allows for comparisons.
   *
   * @return string
   */
  public function __toString() {
    return $this->getCoordinate();
  }

}
