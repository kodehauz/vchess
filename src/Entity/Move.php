<?php

namespace Drupal\vchess\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\vchess\Game\Board;
use Drupal\vchess\Game\Square;

/**
 * @ContentEntityType(
 *   id = "vchess_move",
 *   base_table = "vchess_move",
 *   data_table = "vchess_move_field_date",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 * )
 */

class Move extends ContentEntityBase {
//  protected $long_format; // e.g. "Pd2-d4", "Nb1xBc3", "Ke1-g1"
//  protected $algebraic; // e.g. "e4"
//  protected $timestamp; // the GMT timestamp of when the move was made

  /**
   * Get the destination square from a given move
   *
   * e.g. in a move like "Rh4-d4" the destination square is d4
   */
  public function toSquare() {
    $to_square = new Square();
    
    if ($this->getLongMove()[3] == "x") {
      // In a move like "Bf5xPe4"
      //   $move[0] = source piece
      //   $move[1-2] = source coord
      //   $move[3] = move type, "x"
      //   $move[4] = dest piece
      //   $move[5-6] = dest square
      $to_square->setCoordinate(substr($this->getLongMove(), 5, 2));
    }
    else { // Move type = "-"
      // In a move like "Rh4-d4":
      //   $move[0] = source piece
      //   $move[1-2] = source coord
      //   $move[3] = move type, "-"
      //   $move[4-5] = dest square
      $to_square->setCoordinate(substr($this->getLongMove(), 4, 2));
    }
  
    return $to_square;
  }
  
  /**
   * Get the from square for a given move
   *
   * e.g.
   * In a move like "Bf5xPe4" return "f5"
   * In a move like "Rh4-d4" return "h4"
   */
  public function fromSquare() {
    $from_square = new Square();
    $from_square->setCoordinate(substr($this->getLongMove(), 1, 2));
  
    return $from_square;
  }
  
  /**
   * Get the move type
   * e.g. "-" for a move like "Ra1-a4"
   *   or "x" for a move like "Ra1xNa6"
   */
  public function getType() {
    return $this->getLongMove()[3];
  }
  
  /**
   * Set the $long_format
   */
//  public function setLongFormat($long_format) {
//    $this->long_format = $long_format;
//  }
  
  /**
   * Set the algebraic format
   */
//  public function setAlgebraic($algebraic) {
//    $this->algebraic = $algebraic;
//  }
//
  /**
   * Get the source piece from a given move
   * 
   * e.g. "Ra1-a4" returns "R"
   * 
   * @return
   *   A piece type, one of: 'K', 'Q', 'R', 'B', 'N', 'P'
   */
  public function getSourcePieceType() {
    return $this->getLongMove()[0];
  }
  
  /**
   * Get the destination piece from a given move
   *
   * If there is no destination piece, return ""
   *
   * e.g.
   * "Qd1xBd7" returns "B"
   * "Ra1-a4" returns ""
   */
  public function getDestinationPieceType() {
    if ($this->getLongMove()[3] == "x") {
      $dest_piece_type = $this->getLongMove()[4];
    }
    else {
      $dest_piece_type = "";
    }
  
    return $dest_piece_type;
  }
  
  /** 
   * Get the piece type for pawn promotion
   *  e.g. Ph7-h8=Q returns "Q"
   *  
   * @return 
   *   the piece type which is selected, one of: Q, R, B or N
   * 
   * If the move is not a valid promotion move then "" is returned.
   * 
   */
  public function getPromotionPieceType() {
    $piece_type = "";
    
    // Check that a pawn promotion is happening
    if ($this->fromSquare()->getRank() == 7 && $this->toSquare()->getRank() == 8) {
      $white_promotion = TRUE;
      $black_promotion = FALSE;
    }
    else {
      $white_promotion = FALSE;
      
      if ($this->fromSquare()->getRank() == 2 && $this->toSquare()->getRank() == 1) {
        $black_promotion = TRUE;
      }
      else {
        $black_promotion = FALSE;
      }
    }
    
    if (($this->getSourcePieceType() == "P")
    && ($white_promotion || $black_promotion)) {
      if ($this->getType() == "-") {
        // e.g. In "Pb7-b8=Q" the "Q" is the 7th element
        $piece_type = substr($this->getLongMove(), 7, 1);
      }
      else {
        // e.g. In "Pb7xRa8=Q" the "Q" is the 8th element
        $piece_type = substr($this->getLongMove(), 8, 1);
      } 
    }
    
    return $piece_type;
  } 
  
  /**
   * Get short notation of move
   * e.g. Pe2-e4 -> e4
   *      Re1xNa6 -> Rxa6
   *
   * When two (or more) identical pieces can move to the same square,
   * the moving piece is uniquely identified by specifying the piece's letter,
   * followed by (in descending order of preference):
   * - the file of departure (if they differ); or
   * - the rank of departure (if the files are the same but the ranks differ);
   * - or both the rank and file (if neither alone is sufficient to identify the piece�which occurs only in rare cases where one or more pawns have promoted, resulting
   *   in a player having three or more identical pieces able to reach the same square).
   *
   * For example, with knights on g1 and d2, either of which might move to f3,
   * the move is specified as Ngf3 or Ndf3, as appropriate. With knights on g5 and g1,
   * the moves are N5f3 or N1f3. As above, an x can be inserted to indicate a capture,
   * for example: N5xf3.
   *
   * Occasionally, it may be possible to disambiguate in two different ways -
   * for example, two rooks on d3 and h5, either one of which may move to d5.
   * If the rook on d3 moves to d5, it is possible to disambiguate with either Rdd5
   * or R3d5. In cases such as these the file takes precedence over the rank,
   * so Rdd5 is correct.
   *
   * See http://en.wikipedia.org/wiki/Algebraic_notation_(chess)
   *
   * @param string $player
   *   The color of the player making the move, either "b" or "w"
   * @param \Drupal\vchess\Game\Board $board
   *   The board on which the move is being made.
   *
   * @return string
   *   Algebraic notation for the move.
   */
  public function calculateAlgebraic($player, Board $board) {
    // If all else fails, just return the long move
    $this->setAlgebraic($this->getLongMove());

    $from_square = $this->fromSquare();
    $to_square = $this->toSquare();

    $source_piece_type = $this->getSourcePieceType();

    if ($player == 'w') {
      $opponent = 'b';
    }
    else {
      $opponent = 'w';
    }

    // Castling short
    if ($this->getLongMove() == "Ke1-g1" || $this->getLongMove() == "Ke8-g8") {
      $this->setAlgebraic("O-O");
    }
    // Castling long
    elseif ($this->getLongMove() == "Ke1-c1" || $this->getLongMove() == "Ke8-c8") {
      $this->setAlgebraic("O-O-O");
    }
    // Pawn moves
    elseif ($source_piece_type == 'P') {
      // P moves are always unambiguous. For attacks skip source digit
      // and for moves skip source pos and "-"
      if ($this->getType() == '-') {
        if ($from_square->getFile() == $to_square->getFile()) {
          // e.g. e4
          $this->setAlgebraic($to_square->getCoordinate());
        }
        else {
          // A pawn move to another file which is not a capture (e.g. Pa5-b6)
          // must be an en passant capture, e.g. "axb5"
          $this->setAlgebraic($from_square->getFile() . "x" . $to_square->getCoordinate());
        }
      }
      else {
        // e.g. cxd4
        $this->setAlgebraic($this->getLongMove()[1] . "x" . $to_square->getCoordinate());
      }

      // Check if pawn promotion, e.g. e8=Q
      if ($this->toSquare()->getRank() == 1 || $this->toSquare()->getRank() == 8) {
        $this->setAlgebraic($this->getAlgebraic() . "=" . $this->getPromotionPieceType());
      }
    }
    // All other moves
    else {
      // First find out where all possible pieces of this type are
      $pieces_squares = $board->pieceTypeSquares($source_piece_type, $player);

      // If there is only 1 piece of this type, then move is unambiguous
      if (count($pieces_squares) == 1) {
        if ($this->getType() == '-') {
          // e.g. Ne4
          $this->setAlgebraic($source_piece_type . $to_square->getCoordinate());
        }
        else {
          // e.g. Nxd4
          $this->setAlgebraic($source_piece_type . "x" . $to_square->getCoordinate());
        }
      }
      else {
        // Find how many other pieces of this type may move to the dest square
        $trouble_squares = array();
        foreach ($pieces_squares as $piece_square) {
          // Only look at the other pieces
          if ($piece_square != $from_square) {
            if ($board->moveIsOk($piece_square, $to_square)) {
              $trouble_squares[] = $piece_square;
            }
          }
        }

        if (count($trouble_squares) == 0) {
          // No other piece of this type can reach the square, so unambiguous
          if ($this->getType() == '-') {
            // e.g. Ne4
            $this->setAlgebraic($source_piece_type . $to_square->getCoordinate());
          }
          else {
            // e.g. Nxd4
            $this->setAlgebraic($source_piece_type . "x" . $to_square->getCoordinate());
          }
        }
        else {
          // First try to disambiguate by looking at the file, e.g. Ngf3
          $source_file = $from_square->getFile();
          $file_unique = TRUE;
          foreach ($trouble_squares as $trouble_coord) {
            if ($trouble_coord->file() == $source_file) {
              $file_unique = FALSE;
            }
          }

          // In this case the file is enough to make the move unique, e.g. Ngf3
          if ($file_unique) {
            if ($this->getType() == '-') {
              $this->setAlgebraic($source_piece_type . $source_file . $to_square->getCoordinate());
            }
            else {
              // e.g. Nxd4
              $this->setAlgebraic($source_piece_type . $source_file . "x" . $to_square->getCoordinate());
            }
          }
          else {
            // Try to disambiguate by looking at the rank, e.g. N1f3
            $source_rank = $from_square->getRank();
            $rank_unique = TRUE;
            foreach ($trouble_squares as $trouble_coord) {
              if ($trouble_coord->rank() == $source_rank) {
                $rank_unique = FALSE;
              }
            }

            // In this case the rank is enough to make the move unique, e.g. N1f3
            if ($rank_unique) {
              if ($this->getType() == '-') {
                // e.g. N1f3
                $this->setAlgebraic($source_piece_type . $source_rank . $to_square->getCoordinate());
              }
              else {
                // e.g. N1xf3
                $this->setAlgebraic($source_piece_type . $source_rank . "x" . $to_square->getCoordinate());
              }
            }
            else {
              // File is not unique, rank is not unique, so we need full source square, e.g. Ng1f3
              // This can only ever happen when promotion to a third piece has occured.
              $prefix = $source_piece_type . $source_rank . $source_file;
              if ($this->getType() == '-') {
                // e.g. Ng1f3
                $this->setAlgebraic($prefix . $to_square->getCoordinate());
              }
              else {
                // e.g. Ng1xf3
                $this->setAlgebraic($prefix . "x" . $to_square->getCoordinate());
              }
            }
          }
        }
      }
    }

    // Finally we need to see if the move results in check or checkmate.  We make the move on
    // a copy of the board to not muck up the existing board
    $clone_board = clone $board;

    $clone_board->movePiece($from_square, $to_square);
    if ($clone_board->isCheck($opponent)) {
      if ($clone_board->isCheckmate($opponent)) {
        $this->setAlgebraic($this->getAlgebraic() . "#") ;
        if ($player == 'w') {
          $this->setAlgebraic($this->getAlgebraic() . " 1-0");
        }
        else {
          $this->setAlgebraic($this->getAlgebraic() . " 0-1");
        }
      }
      else {
        $this->setAlgebraic($this->getAlgebraic() . "+");
      }
    }
    elseif ($clone_board->isStalemate($opponent)) {
      $this->setAlgebraic($this->getAlgebraic() . " 1/2-1/2");
    }

    return $this->getAlgebraic();
  }

  /**
   * Get the algebraic version of the move
   * 
   * @return
   *   The algebraic version of the move, e.g. "Nc3"
   */
//  public function algebraic() {
//    return $this->algebraic;
//  }

  public function getGameId() {
    return $this->get('gid')->value;
  }

  public function setGameId($value) {
    $this->set('gid', $value);
    return $this;
  }

  public function getMoveNo() {
    return $this->get('move_no')->value;
  }

  public function setMoveNo($value) {
    $this->set('move_no', $value);
    return $this;
  }

  public function getColor() {
    return $this->get('color')->value;
  }

  public function setColor($value) {
    if (!in_array($value, ['w', 'b'])) {
      throw new \InvalidArgumentException('Color must be either "b" or "w"');
    }
    $this->set('color', $value);
    return $this;
  }

  public function getLongMove() {
    return $this->get('long_move')->value;
  }

  /**
   * Sets the long move notation.
   *
   * @param string $value
   * A move in one of the following formats:
   * - "Bf5xPe4" i.e. a capture which includes the type of the piece being captured
   * - "Rh4-d4" i.e. a move to a square.
   *
   * @return $this
   *   For method chaining.
   */
  public function setLongMove($value) {
    $this->set('long_move', $value);
    return $this;
  }

  public function getAlgebraic() {
    return $this->get('algebraic')->value;
  }

  public function setAlgebraic($value) {
    $this->set('algebraic', $value);
    return $this;
  }

  /**
   * Get the timestamp of the move
   */
  public function getTimestamp() {
    return $this->get('timestamp')->value;
  }
  
  /**
   * Set the timestamp of the move
   * 
   * @param $timestamp
   *   The timestamp to be set
   */
  public function setTimestamp($value) {
    $this->set('timestamp', $value);
    return $this;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Table of the moves of each game, one row per move
    $fields['gid'] = BaseFieldDefinition::create('integer')
      ->setDescription(t('Game ID'))
      ->setRequired(TRUE);

    $fields['move_no'] = BaseFieldDefinition::create('integer')
      ->setDescription(t('Move number'))
      ->setRequired(TRUE);

    $fields['color'] = BaseFieldDefinition::create('string')
      ->setDescription('Move color')
      ->setRequired(TRUE);

    $fields['long_move'] = BaseFieldDefinition::create('string')
      ->setDescription(t('The actual move in full detail format, e.g. "Pe2-e4", "Nf6xBg8", "Ke1-g1"'))
      ->setRequired(TRUE);


    $fields['algebraic'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Move in algebraic notation (e.g. "e4", "Nc3", "O-O")'))
      ->setRequired(TRUE);

    $fields['datetime'] = BaseFieldDefinition::create('timestamp')
      ->setDescription(t('Exact date and time of the move'))
      ->setRequired(TRUE);

    return $fields;
  }

}
