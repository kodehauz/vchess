<?php

namespace Drupal\vchess\Game;

/**
 * The Board is designed to take some of the complexity away from the $board
 * array by adding useful functions rather than having the rest of the program
 * need to understand and handle issues like whether a square is blank or not.
 *
 * The Board is basically the static view of what a board looks like (i.e. what
 * pieces are where) and but also includes the following game information:
 * - whether or not a player may castle (queenside and/or kingside)
 * - what the en passant target square is, if any
 *
 * This class acts as the MODEL for the board.
 */
class Board {

  // Define as a FEN string the standard board starting position.
  // For FEN, we start with the black side of the board (a8-h8), and finish with
  // the white pieces (a1-h1).
  // For FEN, white pieces are stored in UPPER CASE, black pieces in lower case
  // and blank squares as space.
  //
  // e.g. after 1.e4 the FEN string will be:
  // rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR
  const BOARD_DEFAULT = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR";
  const BOARD_PROMOTION = "k7/4P3/8/8/8/8/8/K7";

    // Define the column letters
  const COL_a = 1;
  const COL_b = 2;
  const COL_c = 3;
  const COL_d = 4;
  const COL_e = 5;
  const COL_f = 6;
  const COL_g = 7;
  const COL_h = 8;

  /**
   * The entire chess game board.
   *
   * The board is stored as an array of \Drupal\vchess\Game\Piece objects keyed
   * by the board coordinate in "a1" notation.
   *
   * @var \Drupal\vchess\Game\Piece[]
   */
  protected $board = [];

  /**
   * Whether the board is in en-passant mode.
   *
   * @var boolean
   */
  protected $enPassantSquare;

  /**
   * Setup with the standard position.
   *
   * @return $this
   */
  public function setupAsStandard() {
    return $this->setupPosition(static::BOARD_DEFAULT);
  }

  /**
   * Setup the board using a FEN (Forsyth�Edwards Notation) string, e.g.
   * rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR
   *
   * @param string $fen_string
   *   The FEN string representing the board position.
   *
   * @return $this
   *
   * See http://en.wikipedia.org/wiki/Forsyth-Edwards_Notation
   */
  public function setupPosition($fen_string) {
    // Ensure any previous position is cleared
    $this->board = [];

    $chars = str_split($fen_string, 1);

    // The FEN string starts from the black side
    $col = static::COL_a;
    $row = 8;
    foreach ($chars as $char) {
      if ($char === '/') {
        $col = static::COL_a;
        $row--;
      }
      elseif (is_numeric($char)) {
        $col += $char;
      }
      else {
        $piece = new Piece();
        if (strtoupper($char) === $char) {
           // White piece.
          $piece
            ->setType($char)
            ->setColor('w');
        }
        else {
          // Black piece.
          $piece
            ->setType($char)
            ->setColor('b');
        }

        $coordinate = chr($col + 96) . $row;
        $this->board[$coordinate] = $piece;
        $col++;
      }
    }

    return $this;
  }

  /**
   * Set the piece at a given coordinate
   *
   * @param \Drupal\vchess\Game\Piece $piece
   *   The piece to set.
   * @param string $coordinate
   *   The coordinate position to place the piece, e.g. "a1".
   *
   * @return $this
   */
  public function setPiece(Piece $piece, $coordinate) {
    $this->board[$coordinate] = $piece;
    return $this;
  }

  /**
   * Gets all the pieces on this board.
   *
   * @return \Drupal\vchess\Game\Piece[]
   */
  public function getPieces() {
    return array_values($this->board);
  }

  /**
   * Get an array of squares for a given color and piece type.
   *
   * e.g. array("a1", "h1") for white rooks.
   *
   * @param string $type
   *   The type of piece being searched, one of 'K', 'Q', 'R', 'B', 'N' or 'P'.
   * @param string $color
   *   The color of the pieces being searched for, either 'w' or 'b'.
   *   
   * @return \Drupal\vchess\Game\Square[]
   *   Returns an array of squares holding pieces of a given color and piece type.
   */
  public function getSquaresOfPieceType($type, $color) {
    $squares = [];
    
    foreach ($this->board as $coordinate => $piece) {
      if ($piece->getType() === $type && $piece->getColor() === $color) {
        $squares[] = (new Square())->setCoordinate($coordinate);
      }
    }
    
    return $squares;
  }
  
  /**
   * Gets an array of squares for a given piece color.
   *
   * For example, at the start of the game for color white "w", this will return
   * array("a1", "a2", ..., "h2").
   *
   * @param string $color
   *   Player color "w" or "b" for which piece locations are required.
   *
   * @return \Drupal\vchess\Game\Square[]
   *   Returns an array of squares for pieces of a given color.
   */
  public function getSquaresOfPieceColor($color) {
    $squares = [];

    foreach ($this->board as $coordinate => $piece) {
      if ($piece->getColor() === $color) {
        $squares[] = (new Square())->setCoordinate($coordinate);
      }
    }

    return $squares;
  }

  /**
   * Gets the squares on the diagonals from a given square.
   *
   * @param \Drupal\vchess\Game\Square $from_square
   *   The square from which the search for diagonal squares starts.
   *
   * @return \Drupal\vchess\Game\Square[]
   */
  public static function getDiagonalSquares(Square $from_square) {
    $squares = [];

    $squares = array_merge($squares, static::singleDiagonalSquares($from_square, Direction::UP_LEFT));
    $squares = array_merge($squares, static::singleDiagonalSquares($from_square, Direction::DOWN_LEFT));
    $squares = array_merge($squares, static::singleDiagonalSquares($from_square, Direction::UP_RIGHT));
    $squares = array_merge($squares, static::singleDiagonalSquares($from_square, Direction::DOWN_RIGHT));

    return array_values(array_unique($squares));
  }
  
  /**
   * Gets the squares from a given start square based on an index increment
   *
   * @param \Drupal\vchess\Game\Square $from_square
   *   Square where diagonal is starting.
   * @param integer $direction
   *   Increment of the diagonal, one of:
   *   - \Drupal\vchess\Game\Direction::UP_LEFT (7)
   *   - \Drupal\vchess\Game\Direction::UP_RIGHT (9)
   *   - \Drupal\vchess\Game\Direction::DOWN_LEFT (-9)
   *   - \Drupal\vchess\Game\Direction::DOWN_RIGHT (-7)
   *  
   * @return \Drupal\vchess\Game\Square[]
   *   An array of squares
   */
  protected static function singleDiagonalSquares(Square $from_square, $direction) {
    $squares = [];

    /** @var \Drupal\vchess\Game\Square $last_square */
    $last_square = null;
    do {
      $squares[] = $from_square;
      $last_square = $from_square;
      $from_square = $from_square->nextSquare($direction);
    }
    while ($last_square->getCoordinate() !== $from_square->getCoordinate());
    
    return $squares;
  }
  
  /**
   * Gets the squares on a given rank.
   * 
   * For instance, given a rank like "8" it will return the squares "a8" to "h8".
   * 
   * @param string $rank
   *    The number of the rank (1..8)
   *
   * @return \Drupal\vchess\Game\Square[]
   */
  public static function getSquaresOnRank($rank) {
    $squares = [];
    
    for ($col = 1; $col <= 8; $col++) {
      $squares[] = (new Square)
        ->setColumn($col)
        ->setRow($rank);
    }
    
    return $squares;
  }

  /**
   * Gets the squares on a given file.
   *
   * For instance, given a file like "a" it will return the squares "a1" to "a8".
   *
   * @param string $file
   *   The letter of the file, e.g. "a".
   *
   * @return \Drupal\vchess\Game\Square[]
   */
  public static function getSquaresOnFile($file) {
    $squares = [];

    for ($rank = 1; $rank <= 8; $rank++) {
      $squares[] = (new Square())->setCoordinate($file . $rank);
    }

    return $squares;
  }

  /**
   * Gets the squares on the rank and file from a given square
   * 
   * @param \Drupal\vchess\Game\Square $square
   *   Square to start from
   *   
   * @return \Drupal\vchess\Game\Square[]
   *   An array of squares
   */
  public static function getSquaresOnRankFile(Square $square) {
    $squares = array_merge(static::getSquaresOnRank($square->getRank()),
      static::getSquaresOnFile($square->getFile()));

    // The pivot square will always be duplicated, so remove one of them.
    $index = array_search($square, $squares);
    unset($squares[$index]);
    return array_values($squares);
  }

  /**
   * Gets the knight moves from a given square.
   *
   * @param \Drupal\vchess\Game\Square $from_square
   *   The square which we are starting from.
   *
   * @return \Drupal\vchess\Game\Square[]
   *   An array of squares
   */
  public static function getKnightMoveSquares(Square $from_square) {
    $squares = [];
    $from_rank = (int) $from_square->getRank();  // e.g. "7" in "d7"
    $from_col = (int) $from_square->getColumn(); // e.g. "4" for the "d" in "d7"

    $deltas = [
      [-2, -1],
      [-2, 1],
      [-1, -2],
      [-1, 2],
      [1, -2],
      [1, 2],
      [2, -1],
      [2, 1],
    ];
    foreach ($deltas as $delta_pair) {
      $delta_rank = $delta_pair[0];
      $delta_col = $delta_pair[1];
      if ($from_col + $delta_col >= 1 && $from_col + $delta_col <= 8
        && $from_rank + $delta_rank >= 1 && $from_rank + $delta_rank <= 8) {
        $squares[] = (new Square())
          ->setColumn($from_col + $delta_col)
          ->setRow($from_rank + $delta_rank);
      }
    }

    return $squares;
  }

  /**
   * Gets the square that the king is on.
   *
   * @param string $color
   *   The color of the king.
   *
   * @return \Drupal\vchess\Game\Square
   *   The king square.
   */
  public function getKingSquare($color) {
    $squares = $this->getSquaresOfPieceType('K', $color);
    // There should be only 1 square returned
    return $squares[0];
  }

  /**
   * Returns TRUE if the given square is empty
   * 
   * @param \Drupal\vchess\Game\Square $square
   *   The square to test.
   *
   * @return boolean
   */
  public function squareIsEmpty(Square $square) {
    return !array_key_exists($square->getCoordinate(), $this->board);
  }
  
  /**
   * Returns TRUE if the square at the given coordinate is empty.
   *
   * @param string $coordinate
   *   The board coordinate to test.
   *
   * @return boolean
   */
  public function squareAtCoordinateIsEmpty($coordinate) {
    return $this->squareIsEmpty((new Square)->setCoordinate($coordinate));
  }

  /**
   * Gets the player color whose piece is on a given square
   * 
   * @param \Drupal\vchess\Game\Square $square
   *   The square position e.g. "a1".
   *
   * @return string
   *   'w', 'b' or ''
   */
  public function getColorOnSquare(Square $square) {
    $color = "";
    if (!$this->squareIsEmpty($square)) {
      $color = $this->board[$square->getCoordinate()]->getColor();
    }

    return $color;
  }

  /**
   * Convert board in array format (for use in the program) into FEN string.
   *
   * It is the FEN string that is saved in the database. E.g. after 1.e4 the FEN
   * string will be:
   *   rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR
   *
   * The board is kept internally with one piece per square, starting with:
   * a1, a2, ..., b1, b2, ... h8.
   * For FEN, we start with the black side of the board (a8-h8), and finish with
   * the white pieces (a1-h1).
   * For FEN, white pieces are stored in UPPER CASE, black pieces in lower case
   * and blank squares as space.
   *
   * @return string
   */
  public function getFenString() {
    $FEN_string = '';
    
    $square = new Square();
    for ($row = 8; $row >= 1; $row--) {
      $empty_squares = 0;
      for ($col = 1; $col <= 8; $col++) {
        $square->setColumn($col)->setRow($row);
        if ($this->squareIsEmpty($square)) {
          $empty_squares++;
        }
        else {
          $piece = $this->getPiece($square);
          if ($empty_squares > 0) {
            $FEN_string .= $empty_squares;
            $empty_squares = 0;
          }
          $FEN_string .= $piece->getFenType();
        }
      }
      // The row may end with empty squares
      if ($empty_squares > 0) {
        $FEN_string .= $empty_squares;
      }
      // All rows except the row 1 with a / 
      if ($row > 1) {
        $FEN_string .= '/';
      }
    }
    
    return $FEN_string;
  }
  
  /**
   * Checks that the path from a start square to an end square is not blocked.
   *
   * Checks a number of squares given a start and end square (which is not
   * included to the check) and a position change for each iteration. Returns
   * TRUE if not blocked. All values are given for 1dim board.
   *
   * @param integer $start
   *   Start index of square, 0..63.
   * @param integer $end
   *   End index of square, 0..63.
   * @param int $change
   *   Steps to make for each check.
   *
   * @return boolean
   */
  public function pathIsNotBlocked($start, $end, $change) {
    $blocked = FALSE;

    for ($pos = $start; $pos != $end; $pos += $change) {
      if (!$this->squareIsEmpty(Square::fromIndex($pos))) {
        $blocked = TRUE;
      }
    }
  
    return !$blocked;
  }

  /**
   * @param \Drupal\vchess\Game\Square $square
   * @param $attacker
   * @return bool
   */
  public function squareIsUnderAttack(Square $square, $attacker) {
    return !empty($this->getSquaresAttackingSquare($square, $attacker));
  }
  
  /**
   * Gets an array of all the squares with pieces attacking a particular square.
   *
   * @param \Drupal\vchess\Game\Square $attacked
   *   The square which is being checked to see if it is under attack e.g. "d4".
   *   
   * @param string $attacker
   *   Color of player who is doing the attacking, either 'w' or 'b'.
   *
   * @return \Drupal\vchess\Game\Square[]
   */
  public function getSquaresAttackingSquare(Square $attacked, $attacker) {
    $attacking_squares = [];
  
    $pieces_squares = $this->getSquaresOfPieceColor($attacker);
    foreach ($pieces_squares as $attacking_square) {
      if ($this->pieceAttacks($attacking_square, $attacked)) {
        $attacking_squares[] = $attacking_square;
      }
    }
  
    return $attacking_squares;
  }
  
  /**
   * Checks if a given piece attacks the specified square.
   * 
   * @param \Drupal\vchess\Game\Square $attacking
   *   Square of the piece trying to attack.
   * 
   * @param \Drupal\vchess\Game\Square $attacked
   *   Square which is being tested to see if it is attacked or not.
   *
   * @return boolean
   */
  public function pieceAttacks(Square $attacking, Square $attacked) {
    if ($piece = $this->getPiece($attacking)) {
      if ($piece->getType() === 'P') {
        // For a pawn, we have to check whether it actually attacks
        return $this->pawnAttacks($attacking, $attacked);
      }
      else {
        return $this->squareIsReachable($attacking, $attacked);
      }
    }
    return FALSE;
  }
  
  /**
   * Checks whether a player's king is in check.
   *
   * @param string $player
   *   The color of the player to be checked.
   *
   * @return boolean
   */
  public function isInCheck($player) {
    $king_square = $this->getKingSquare($player);
  
    if ($player === 'w') {
      return !empty($this->getSquaresAttackingSquare($king_square, 'b'));
    }
    else {
      return !empty($this->getSquaresAttackingSquare($king_square, 'w'));
    }
  }
  
  /**
   * Gets the square in front of another square.
   *
   * This only applies to squares with pawns on, since only they have the
   * concept of in front or behind.
   * 
   * @param \Drupal\vchess\Game\Square $square
   *   The original square for which we want to find the square in front.
   * @param int $steps
   *   The number of steps to lookahead. Defaults to 1.
   *
   * @return \Drupal\vchess\Game\Square
   */
  public function getSquareInFront(Square $square, $steps = 1) {
    if ($this->getPiece($square)->getType() === 'P') {
      if ($this->getPiece($square)->getColor() === 'w') {
        return $square->nextSquare(Direction::UP, $steps);
      }
      else {
        return $square->nextSquare(Direction::DOWN, $steps);
      }
    }
    else {
      // Only pawns have the concept of square in front.
      return NULL;
    }
  }
  
  /**
   * Returns the array of adjacent squares (<=8).
   *
   * @param \Drupal\vchess\Game\Square $square
   *   The square around which to check.
   * 
   * @return \Drupal\vchess\Game\Square[]
   *   Returns an array of Squares.
   */
  public function getAdjacentSquares(Square $square) {
    $adjacent_squares = [];
    $directions = [
      Direction::UP, Direction::UP_RIGHT, Direction::RIGHT, Direction::DOWN_RIGHT,
      Direction::DOWN, Direction::DOWN_LEFT, Direction::LEFT, Direction::UP_LEFT,
    ];
    foreach ($directions as $direction) {
      $adjacent_square = $square->nextSquare($direction);
      if ($adjacent_square->getIndex() !== $square->getIndex()) {
        $adjacent_squares[] = $adjacent_square;
      }
    }
    return $adjacent_squares;
  }
  
  /**
   * Check whether a player's king is in checkmate.
   * 
   * @param string $defender
   *   The player that is under attack, either 'w' or 'b'.
   *
   * @return boolean
   */
  public function isInCheckmate($defender) {
    // Determine color of the opponent.
    if ($defender === 'w') {
      $opponent = 'b';
    }
    else {
      $opponent = 'w';
    }
  
    // Find the position of the player's king.
    $king_square = $this->getKingSquare($defender);
  
    // Test adjacent squares and confirm they are not available for the king.
    $adjacent_squares = $this->getAdjacentSquares($king_square);
    foreach ($adjacent_squares as $adjacent_square) {
      // If this adjacent square has a piece of the same color, then
      // we cannot move the king there
      if ($this->getPiece($adjacent_square)->getColor() === $defender) {
        continue;
      }
      // If this adjacent square is under attack, then we cannot
      // move the king there
      if ($this->getSquaresAttackingSquare($adjacent_square, $opponent)) {
        continue;
      }
      // Since this square is neither occupied by one of our own pieces
      // nor is under attack then we can move the king there and so it
      // isn't checkmate
      return FALSE;
    }
  
    // Get all pieces that are attacking the king.
    $attacker_squares = $this->getSquaresAttackingSquare($king_square, $opponent);
  
    // If there is only one attacker, then it might be possible to capture the piece to escape checkmate
    if (count($attacker_squares) < 2) {
      // There is only 1 attacker.  Check whether this attacker can be captured by own defending piece.
      foreach ($this->getSquaresOfPieceColor($defender) as $defender_piece_square) {
        if ($this->pieceAttacks($defender_piece_square, $attacker_squares[0])) {
          $piece = $this->getPiece($defender_piece_square);
          if ($piece->getType() === 'K') {
            // If the piece which could capture is the king, then we need to
            // check if the piece it wants to take is defended.  If so, this is
            // not valid.
            $defender_squares = $this->getSquaresAttackingSquare($attacker_squares[0], $opponent);
            if (count($defender_squares) === 0) {
              // There is nothing defending the attacking piece, so the king can
              // take it.
              return FALSE;
            }
          }
          else {
            // Attacker is not the king, so OK to use it to capture the piece.
            return FALSE;
          }
        }
      }
      
      // Still danger of checkmate at this point.
      // Next is to check whether a defending piece can move in the way to block.
      $inbetween_squares = static::getInbetweenSquares(
        $this->getPiece($attacker_squares[0])->getType(), $attacker_squares[0], $king_square);
      foreach ($inbetween_squares as $inbetween_square) {
        $defending_squares = $this->getSquaresOfPieceColor($defender);
        foreach ($defending_squares as $defending_square) {
          $piece = $this->getPiece($defending_square);
          $piece_type = $piece->getType();
          if ($this->moveIsOk($defending_square, $inbetween_square)) {
            return FALSE;
          }
        }
      }
    }
    
    return TRUE;
  }

  /**
   * Checks whether a square is reachable by a piece from another square.
   *
   * Checks whether $to_square is reachable for piece on the $piece_square. It
   * is not checked whether the square itself is occupied but only the squares
   * in between.
   * 
   * @param \Drupal\vchess\Game\Square $from_square
   *   Square on which piece starts from.
   * @param \Drupal\vchess\Game\Square $to_square
   *   Square where piece would like to go to if possible.
   *
   * @return bool
   */
  public function squareIsReachable(Square $from_square, Square $to_square) {
    $reachable = FALSE;
  
    $piece_type = $this->getPiece($from_square)->getType();
    if ($from_square->getCoordinate() !== $to_square->getCoordinate()) {
      $piece_pos = $from_square->getIndex();
      $dest_pos = $to_square->getIndex();

      // @todo Refactor using Piece::getRank() and Piece::getColumn() after tests
      // are added.
      $piece_y = floor($piece_pos / 8) + 1;
      $piece_x = $piece_pos % 8;
      $dest_y = floor($dest_pos / 8) + 1;
      $dest_x = $dest_pos % 8;
  
      switch ($piece_type) {
        // Pawn
        case 'P':
          // For a pawn we need to take into account the colour since a pawn is the one
          // piece which cannot go backwards
          $piece_color = $this->getPiece($from_square)->getColor();
          if ($piece_color === 'w') {
            if (($dest_y - $piece_y) === 1) { // Normal 1-square move
              $reachable = TRUE;
            }
            elseif ($piece_y === 2 && (($dest_y - $piece_y) === 2)) { // Initial 2-square move
              $reachable = TRUE;
            }
          }
          else { // $piece_color == "b"
            if (($dest_y - $piece_y) === -1) {
              $reachable = TRUE;
            }
            else {
              if ($piece_y === 7 && (($dest_y - $piece_y) === -2)) { // Initial 2-square move
                $reachable = TRUE;
              }
            }
          }
          break;
        // Knight
        case 'N':
          if (abs($piece_x - $dest_x) === 1 && abs($piece_y - $dest_y) === 2) {
            $reachable = TRUE;
          }
          if (abs($piece_y - $dest_y) === 1 && abs($piece_x - $dest_x) === 2) {
            $reachable = TRUE;
          }
          break;
        // Bishop
        case 'B':
          if (abs($piece_x - $dest_x) !== abs($piece_y - $dest_y)) {
            break;
          }
          if ($dest_y < $piece_y) {
            $change = -8;
          }
          else {
            $change = 8;
          }
          if ($dest_x < $piece_x) {
            $change--;
          }
          else {
            $change++;
          }
          if ($this->pathIsNotBlocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // rook
        case 'R':
          if ($piece_x !== $dest_x && $piece_y !== $dest_y) {
            break;
          }
          if ($piece_x === $dest_x) {
            if ($dest_y < $piece_y) {
              $change = -8;
            }
            else {
              $change = 8;
            }
          }
          else {
            if ($dest_x < $piece_x) {
              $change = -1;
            }
            else {
              $change = 1;
            }
          }
          if ($this->pathIsNotBlocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // queen
        case 'Q':
          if ($piece_x !== $dest_x && $piece_y !== $dest_y
            && abs($piece_x - $dest_x) !== abs($piece_y - $dest_y)) {
            break;
          }
          // Check if diagonal
          if (abs($piece_x - $dest_x) === abs($piece_y - $dest_y)) {
            if ($dest_y < $piece_y) {
              // diagonal down the board
              $change = -8;
            }
            else {
              // diagonal up the board
              $change = 8;
            }
            if ($dest_x < $piece_x) {
              // diagonal to the left
              $change--;
            }
            else {
              // diagonal to the right
              $change++;
            }
          }
          elseif ($piece_x === $dest_x) {
            // vertical
            if ($dest_y < $piece_y) {
              // vertical down the board
              $change = -8;
            }
            else {
              // vertical up the board
              $change = 8;
            }
          }
          else {
            // horizontal
            if ($dest_x < $piece_x) {
              // horizontal to the left
              $change = -1;
            }
            else {
              // horizontal to the right
              $change = 1;
            }
          }
          if ($this->pathIsNotBlocked($piece_pos + $change, $dest_pos, $change)) {
            $reachable = TRUE;
          }
          break;
        // king
        case 'K':
          if (abs($piece_x - $dest_x) > 1 || abs($piece_y - $dest_y) > 1) {
            break;
          }
          $kings = 0;
          $adj_squares = $this->getAdjacentSquares($from_square);
          foreach ($adj_squares as $adj_square) {
            if ($this->getPiece($adj_square)->getType() === 'K') {
              $kings++;
            }
          }
          if ($kings === 2) {
            break;
          }
          $reachable = TRUE;
          break;
      }
  
    }
  
    return $reachable;
  }
  
  /**
   * Get the piece on a given square.
   *
   * @param \Drupal\vchess\Game\Square $square
   *   The square e.g. "a1".
   *
   * @return \Drupal\vchess\Game\Piece
   *
   * @todo Determine the appropriate behaviour of this method.
   */
  public function getPiece(Square $square) {
    if (array_key_exists($square->getCoordinate(), $this->board)) {
      return $this->board[$square->getCoordinate()];
    }
    // Return empty piece.
    return new Piece();
  }

  /**
   * Move a piece from one square to another.
   * 
   * No checking is done here as to the validity of the move.
   */
  public function movePiece(Square $from_square, Square $to_square) {
    $this->board[$to_square->getCoordinate()] = $this->board[$from_square->getCoordinate()];
    unset($this->board[$from_square->getCoordinate()]);

    return $this;
  }
  
  /**
   * Perform en passant pawn capture.
   */
  public function performEnPassantCapture(Square $from_square, Square $to_square) {
    // Calculate the square of the pawn which has just moved 2 squares
    if ($from_square->getRank() == 4) {
      // Example: 
      // black pawn on e4 ($from_square->getCoordinate())
      // white pawn moves d2-d4,
      // black pawn captures with long move Pe4-d3 ($to_square->getCoordinate() is d3)
      // algebraic move is exd3
      $enemy_pawn_coord = $to_square->getFile() . "4";
    }
    elseif ($from_square->getRank() == 5) {
      // Example:
      // white pawn on b5 ($from_square->getCoordinate())
      // black pawn moves c7-c5,
      // white pawn captures with long move Pb5-c6 ($to_square->getCoordinate() is c6)
      // algebraic move is bxc6
      $enemy_pawn_coord = $to_square->getFile() . "5";
    }
    else {
      $enemy_pawn_coord = '';
    }
    
    // Move the pawn to the empty square
    $this->movePiece($from_square, $to_square);
    
    // Remove the pawn which has just moved 2 squares
    unset($this->board[$enemy_pawn_coord]);

    return $this;
  }
  
  /**
   * Promote a pawn.  This is effectively a move of a pawn with a change
   * of piece type.
   */
  public function promotePawn(Square $from_square, Square $to_square, Piece $new_piece) {
    $this->movePiece($from_square, $to_square);
    
    $coord = $to_square->getCoordinate();
    $this->board[$coord]->setType($new_piece->getType());

    return $this;
  }
  
  /**
   * Check whether pawn at $pawn_square attacks the
   * square $to_square, i.e. the diagonally attacked square
   *
   * Note that it is not necessary for a piece to be on the
   * destination square for that square to be attacked
   *
   * @param $pawn_square 
   *   Square of pawn
   *   
   * @param $to_square 
   *   Square of attacked square
   *   
   * @see pieceAttacks()
   */
  public function pawnAttacks(Square $pawn_square, Square $to_square) {
    $attacks = FALSE;
  
    $piece_color = $this->getPiece($pawn_square)->getColor();
  
    // Convert coord like "d4" into col=4 rank=4
    $piece_col = $pawn_square->getColumn(); // e.g. d -> 4
    $piece_rank = (int) $pawn_square->getRank();
  
    $dest_col = $to_square->getColumn();  // e.g. e -> 5
    $dest_rank = (int) $to_square->getRank();
  
    if ($piece_color === 'w') {
      if ($dest_rank === $piece_rank + 1
          && ($piece_col === ($dest_col - 1) || $piece_col === ($dest_col + 1))) {
        $attacks = TRUE;
      }
    }
    elseif ($piece_color === 'b') {
      if ($dest_rank === $piece_rank - 1
          && ($piece_col === ($dest_col - 1) || $piece_col === ($dest_col + 1))) {
        $attacks = TRUE;
      }
    }
  
    return $attacks;
  }
  
  /**
   * Check whether a given piece may legally move to the given square
   * 
   * @param $from_square
   *   Square where piece is trying to move from
   * @param $to_square
   *   Square where piece is trying to move to
   * 
   */
  public function moveIsOk(Square $from_square, Square $to_square) {
    if ($this->getPiece($from_square)->getType() === 'P') {
      return $this->pawnMayMoveToSquare($from_square, $to_square);
    }
    else {
      return $this->nonPawnMayMoveToSquare($from_square, $to_square);
    }
  }
  
  /**
   * Check whether the move is a pawn moving 2 squares
   * 
   * @param Square $from_square
   * @param Square $to_square
   * 
   * @return boolean TRUE if this was a pawn that moved 2 squares
   */
  public function pawnMoved2Squares(Square $from_square, Square $to_square) {
    $pawn_moved_2_squares = FALSE;
    
    $piece = $this->getPiece($from_square);
    if ($piece->getType() === 'P') {
      if ($piece->getColor() === 'w'
      && ($from_square->getRank() == 1 && $to_square->getRank() == 3)) {
        $pawn_moved_2_squares = TRUE;
      }
      elseif ($piece->getColor() === 'b'
      && ($from_square->getRank() == 7 && $to_square->getRank() == 5)) {
        $pawn_moved_2_squares = TRUE;
      }
    }
    
    return $pawn_moved_2_squares;
  }
  
  /**
   * Check whether there is no further move possible.  To do this, we look at each of
   * the opponent pieces and see if any of them have a move which they can make.
   *
   * @param string $player The player whose turn it is.
   *
   * @return bool
   */
  public function isStalemate($player) {
    // Look at each square to find each of the opponent pieces
    $pieces_squares = $this->getSquaresOfPieceColor($player);
    foreach ($pieces_squares as $piece_square) {
      // Can the piece move theoretically thus is there
      // at least one square free for one piece?
      $valid_moves = $this->getValidMoves($piece_square);
      if (count($valid_moves) > 0) {
        return FALSE;
      }
    }
  
    return TRUE;
  }
  
  /**
   * Get the possible valid moves for a particular piece
   *
   * @param \Drupal\vchess\Game\Square $piece_square
   *   The square where the piece stands.
   *
   * @return string[]
   *   An array of valid moves from the current square in long form.
   */
  public function getValidMoves(Square $piece_square) {
    $valid_moves = [];
  
    $piece_type = $this->getPiece($piece_square)->getType();
    switch ($piece_type) {
      case 'K':
        $adj_squares = $this->getAdjacentSquares($piece_square);
        foreach ($adj_squares as $to_square) {
          if ($this->moveIsOk($piece_square, $to_square)) {
            $valid_moves[] = $this->getLongMove($piece_square, $to_square);
          }
        }
        break;
      case 'Q':
        $squares = array_merge(static::getSquaresOnRankFile($piece_square),
          static::getDiagonalSquares($piece_square));
        foreach ($squares as $to_square) {
          if ($this->moveIsOk($piece_square, $to_square)) {
            $valid_moves[] = $this->getLongMove($piece_square, $to_square);
          }
        }
        break;
      case 'R':
        $squares = static::getSquaresOnRankFile($piece_square);
        foreach ($squares as $to_square) {
          if ($this->moveIsOk($piece_square, $to_square)) {
            $valid_moves[] = $this->getLongMove($piece_square, $to_square);
          }
        }
        break;
      case 'B':
        $squares = static::getDiagonalSquares($piece_square);
        foreach ($squares as $to_square) {
          if ($this->moveIsOk($piece_square, $to_square)) {
            $valid_moves[] = $this->getLongMove($piece_square, $to_square);
          }
        }
        break;
      case 'N':
        $squares = static::getKnightMoveSquares($piece_square);
        foreach ($squares as $to_square) {
          if ($this->moveIsOk($piece_square, $to_square)) {
            $valid_moves[] = $this->getLongMove($piece_square, $to_square);
          }
        }
        break;
      case 'P':
        // See if the move 1 square in front is possible.
        if ($this->moveIsOk($piece_square, $square_in_front = $this->getSquareInFront($piece_square))) {
          $valid_moves[] = $this->getLongMove($piece_square, $square_in_front);
        }
        // See if the move 2 squares in front is possible.
        if ($this->moveIsOk($piece_square, $square_2_in_front = $this->getSquareInFront($piece_square, 2))) {
          $valid_moves[] = $this->getLongMove($piece_square, $square_2_in_front);
        }
        // See if an en passant capture is possible.
        $en_passant_square = Square::fromCoordinate($this->getEnPassantSquare());
        if ($this->isEnPassant() && $this->moveIsOk($piece_square, $en_passant_square)) {
          $valid_moves[] = $this->getLongMove($piece_square, $en_passant_square);
        }
        break;
    }
  
    return $valid_moves;
  }
  
  /**
   * Calculate the long move notation given two squares.
   */
  public function getLongMove(Square $from_square, Square $to_square) {
    return $this->getPiece($from_square)->getType() .
      $from_square->getCoordinate() . "-" . $to_square->getCoordinate();
  }
  
  /**
   * Check whether pawn at $from_square may move to $to_square.
   * First move may be two squares instead of just one.
   *
   * @return
   *   TRUE if the pawn may move to the given square
   *   FALSE if the destination square is occupied or if a square
   *     on the way is occupied for the first 2-square move
   */
  protected function pawnMayMoveToSquare(Square $from_square, Square $to_square) {
    $move_ok = FALSE;
  
    if ($this->squareIsEmpty($to_square)) {
      $piece = $this->getPiece($from_square);
      $piece_file = $from_square->getFile(); // e.g. e
      $piece_rank = (int) $from_square->getRank(); // e.g. 2
      $dest_file = $to_square->getFile();  // e.g. e
      $dest_rank = (int) $to_square->getRank();  // e.g. 4
  
      // Check pawn stays on same file.
      // Captures are checked in pawn_attacks()
      if ($piece_file !== $dest_file) {
        $move_ok = FALSE;
      }
      elseif ($piece->getColor() === 'w') {
        // white pawn
        if ($piece_rank === 2 && $dest_rank === 4) {
          // Pawn moving 2 squares, so check if intermediate square is empty
          $intermediate_coord = (new Square())->setCoordinate($piece_file . '3');
          if ($this->squareIsEmpty($intermediate_coord)) {
            $move_ok = TRUE;
          }
        }
        elseif ($dest_rank === ($piece_rank + 1)) {
          $move_ok = TRUE;
        }
      }
      else {
        // black pawn
        if ($piece_rank === 7 && $dest_rank === 5) {
          // Pawn moving 2 squares, so check if intermediate square is empty
          $intermediate_coord = (new Square())->setCoordinate($piece_file . "6");
          if ($this->squareIsEmpty($intermediate_coord)) {
            $move_ok = TRUE;
          }
        }
        elseif ($dest_rank === ($piece_rank - 1)) {
          $move_ok = TRUE;
        }
      }
    }
  
    return $move_ok;
  }
  
  /**
   * Check whether piece at $from_square may move to $to_square.
   *
   * @param Square $from_square
   *   The square moving from.
   * @param Square $to_square
   *   The square moving to.
   *
   * @return bool
   *   TRUE if the knight may move to the given square
   *   FALSE if the knight may not legally move to the given square
   */
  protected function nonPawnMayMoveToSquare(Square $from_square, Square $to_square) {
    $move_ok = FALSE;
  
    $color = $this->getPiece($from_square)->getColor();
    if ($this->squareIsEmpty($to_square) && $this->squareIsReachable($from_square, $to_square)) {
      // The only thing which would stop it would be if moving the piece
      // would expose the player to a discovered check.  To test this,
      // make the move and see if they are in check.
      $new_board = (new Board())
        ->setupPosition($this->getFenString())
        ->movePiece($from_square, $to_square);
      if (!$new_board->isInCheck($color)) {
        $move_ok = TRUE;        
      }
    }
  
    return $move_ok;
  }
  
//  /**
//   * Set the en_passant square
//   *
//   * @param \Drupal\vchess\Game\Square $square_in_front
//   *   The square which is just in front of the en_passant.  For
//   *   example, if white has moved Pe2-e4, then the square passed will
//   *   be the e4 square and the en_passant will be e3
//   *
//   * @return $this
//   */
//  public function setEnPassant(Square $square_in_front) {
//
//    return $this;
//  }
//
  /**
   * Set en_passant square.
   * 
   * @param string $value
   *   Either a coord, e.g. "d3" or "-"
   *
   * @return $this
   */
  public function setEnPassantSquare($value) {
    $this->enPassantSquare = $value;
    return $this;
  }
  
  /**
   * Reset the en_passant square.
   */
  public function resetEnPassantSquare() {
    $this->enPassantSquare = '-';
    return $this;
  }
  
  /**
   * Tests if there is an en_passant square.
   * 
   * @return bool
   *   TRUE if an en_passant square currently exists
   */
  public function isEnPassant() {
    return $this->enPassantSquare !== '-';
  }
  
  /**
   * Gets the en_passant square.
   *
   * The en_passant is the coordinates of the square
   * behind the pawn in the last move to have moved 2 squares.
   *
   * @return string
   *   Returns the en_passant square's coordinate (e.g. "d3") if there is one,
   *   otherwise it returns "-"
   */
  public function getEnPassantSquare() {
    return $this->enPassantSquare;
  }
  
  /**
   * Get empty squares between start and end as 1dim array.
   * Whether the path is clear is not checked.
   *
   * @param $start_square Start square
   * @param $end_square End square
   * @param $change Change in index value on each move, e.g. -8 for vertical down the board
   *
   * @return
   *   Returns an array of squares which are between the $start_square and the $end_square,
   *   not including the $start_square and $end_square themselves.
   *
   */
  public static function getInbetweenSquares($piece_type, Square $start_square, Square $end_square) {
    $change = static::getInbetweenSquaresChange($piece_type, $start_square, $end_square);

    $start = $start_square->getIndex();
    $end = $end_square->getIndex();

    $inbetween_squares = array();
    $i = 0;
    for ($pos = $start + $change; $pos !== $end; $pos += $change) {
      $inbetween_squares[$i++] = Square::fromIndex($pos);
    }
    return $inbetween_squares;
  }

  /**
   * Get the change value that must be added to create
   * the 1dim path for piece moving from piece_pos to
   * dest_pos. It is assumed that the move is valid!
   * No additional checks as in tileIsReachable are
   * performed. Rook, queen and bishop are the only
   * units that can have empty tiles in between.
   *
   * @param $piece_type Type of piece, "K", "Q", "R", "N", "B" or "P"
   * @param $from_square Piece square
   * @param $dest_index Destination square
   */
  public static function getInbetweenSquaresChange($piece_type, Square $from_square, Square $dest_square) {

    $piece_index = $from_square->getIndex();
    $dest_index = $dest_square->getIndex();

    $change = 0;
    $piece_y = floor($piece_index / 8);
    $piece_x = $piece_index % 8;
    $dest_y = floor($dest_index / 8);
    $dest_x = $dest_index % 8;
    switch ($piece_type) {
      // bishop
      case 'B':
        if ($dest_y < $piece_y) {
          $change = -8;
        }
        else {
          $change = 8;
        }
        if ($dest_x < $piece_x) {
          $change--;
        }
        else {
          $change++;
        }
        break;
      // rook
      case 'R':
        if ($piece_x === $dest_x) {
          if ($dest_y < $piece_y) {
            $change = -8;
          }
          else {
            $change = 8;
          }
        }
        else {
          if ($dest_x < $piece_x) {
            $change = -1;
          }
          else {
            $change = 1;
          }
        }
        break;
      // queen
      case 'Q':
        if (abs($piece_x -$dest_x) === abs($piece_y -$dest_y)) {
          if ($dest_y < $piece_y) {
            $change = -8;
          }
          else {
            $change = 8;
          }
          if ($dest_x < $piece_x) {
            $change--;
          }
          else {
            $change++;
          }
        }
        elseif ($piece_x === $dest_x) {
          if ($dest_y < $piece_y) {
            $change = -8;
          }
          else {
            $change = 8;
          }
        }
        else {
          if ($dest_x < $piece_x) {
            $change = -1;
          }
          else {
            $change = 1;
          }
        }
        break;
    }
    return $change;
  }

  /**
   * Returns an array of captured pieces in FEN piece notation.
   *
   * e.g. ['q','Q','B','b','p','p','p'] means: black queen, white queen, white
   *   bishop, black bishop and 3 black pawns were all captured.
   *
   * @return string[]
   */
  public function getCapturedPieces() {
    $full = ['K','Q','B','B','N','N','R','R','P','P','P','P','P','P','P','P',
      'k','q','b','b','n','n','r','r','p','p','p','p','p','p','p','p'];
    foreach ($this->board as $piece) {
      $index = array_search($piece->getFenType(), $full, TRUE);
      if ($index !== FALSE) {
        unset($full[$index]);
      }
    }
    sort($full);
    $partial = [];
    foreach ($full as $piece_fen) {
      $color = strtoupper($piece_fen) === $piece_fen ? 'w' : 'b';
      $partial[$piece_fen] = (new Piece())->setType($piece_fen)->setColor($color);
    }
    return $partial;
  }

}
