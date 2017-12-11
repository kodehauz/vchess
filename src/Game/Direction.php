<?php

namespace Drupal\vchess\Game;

/**
 * Does direction calculation for board movements.
 */
class Direction {

  // Constants for direction of movement along squares.
  const UP_RIGHT = 9;
  const UP_LEFT = 7;
  const DOWN_RIGHT = -7;
  const DOWN_LEFT = -9;
  const UP = 8;
  const DOWN = -8;
  const LEFT = -1;
  const RIGHT = 1;

  public static function isLeftward($direction) {
    return $direction === static::UP_LEFT || $direction === static::DOWN_LEFT || $direction === static::LEFT;
  }

  public static function isRightward($direction) {
    return $direction === static::UP_RIGHT || $direction === static::DOWN_RIGHT || $direction === static::RIGHT;
  }

  public static function isUpward($direction) {
    return $direction === static::UP_LEFT || $direction === static::UP_RIGHT || $direction === static::UP;
  }

  public static function isDownward($direction) {
    return $direction === static::DOWN_RIGHT || $direction === static::DOWN_LEFT || $direction === static::DOWN;
  }

}
