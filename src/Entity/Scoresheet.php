<?php

namespace Drupal\vchess\Entity;

/**
 * The scoresheet is the list of moves in the game.
 * 
 * This class maintains track of moves in a game and helps in
 */
class Scoresheet {

  /**
   * The moves in this scoresheet.
   *
   * @var \Drupal\vchess\Entity\Move[][]
   */
  protected $moves = [];

  /**
   * The ID of the game that this scoresheet is tracking.
   *
   * @var int
   */
  protected $gameId;
  
  /**
   * Create a new scoresheet for a given game.
   * 
   * @param Game $game
   */
  public function __construct($game_id) {
    $this->gameId = $game_id;
    $this->loadMoves();
  }
  
  /**
   * Load the moves for a scoresheet.
   */
  protected function loadMoves() {
    if ($this->gameId) {
      /** @var \Drupal\vchess\Entity\Move[] $moves */
      $moves = \Drupal::entityTypeManager()->getStorage('vchess_move')
        ->loadByProperties(['gid' => $this->gameId]);

      foreach ($moves as $move) {
        $this->moves[$move->getMoveNo()][$move->getColor()] = $move;
      }
      ksort($this->moves);
    }
  }

  /**
   * Saves the moves that have been added to the database.
   * 
   * @return $this
   */
  public function saveMoves() {
    foreach ($this->moves as $moves) {
      foreach ($moves as $move) {
        $move->save();
      }
    }
    return $this;
  }
  
  /**
   * Gets the move number.
   *
   * If black has not yet moved, then the move number is the length of the array,
   * otherwise it is the length plus one.
   *
   * For example, if 3. Nc3, then scoresheet has:
   *   $this->moves[3]['w'] = "Nc3"
   * and so move number is 3.
   *
   * But if 3. Nc3 Nc6, then scoresheet has:
   *   $this->moves[3]['w'] = "Nc3"
   *   $this->moves[3]['b'] = "Nc6"
   * and so move number is 4.
   *
   * @return int
   */
  public function getNextMoveNumber() {
    $move_no = count($this->moves);
    // If the scoresheet is empty then we are on move 1.
    if ($move_no === 0) {
      $move_no = 1;
    }
    else {
      if (array_key_exists('b', $this->moves[$move_no])) {
        $move_no++;
      }
    }
  
    return $move_no;
  }

  /**
   * Appends the latest move to the end of the scoresheet.
   *
   * @param \Drupal\vchess\Entity\Move $move
   *   The move to be appended.
   * 
   * @return $this
   */
  public function appendMove(Move $move) {
    $move_no = $this->getNextMoveNumber();
    if (!array_key_exists($move_no, $this->moves)) {
      $this->moves[$move_no] = [];
    }

    // Ensure the move color and number matches the position.
    $move->setColor($this->getTurn());
    $move->setMoveNo($move_no);
    $move->setGameId($this->gameId);
    
    if (array_key_exists('w', $this->moves[$move_no])) {
      $this->moves[$move_no]['b'] = $move;
    }
    else {
      $this->moves[$move_no]['w'] = $move;
    }
    return $this;
  }

  /**
   * Gets the white move of a particular number.
   * 
   * @param int $move_no
   *
   * @return \Drupal\vchess\Entity\Move
   */
  public function getWhiteMove($move_no) {
    return $this->getMove($move_no, "w");
  }
  
  /**
   * Gets the black move of a particular number.
   * 
   * @param int $move_no
   *
   * @return \Drupal\vchess\Entity\Move
   */
  public function getBlackMove($move_no) {
    return $this->getMove($move_no, "b");
  }
  
  /**
   * Gets the move of a given number and color, or null if it doesn't exist.
   *
   * @return \Drupal\vchess\Entity\Move|null
   *   Returns null if there is no move of that number and color.
   */
  protected function getMove($move_no, $color) {
    if (array_key_exists($move_no, $this->moves) && array_key_exists($color, $this->moves[$move_no])) {
      return $this->moves[$move_no][$color];
    }
    return NULL;
  }
  
  /**
   * Gets the last move.
   * 
   * @return \Drupal\vchess\Entity\Move
   *   The last move that was added.
   */
  public function getLastMove() {
    $move_no = count($this->moves);
    
    if ($move_no < 1) {
      // No moves have been recorded for this game.
      return NULL;
    }
    else if (array_key_exists('b', $this->moves[$move_no])) {
      return $this->moves[$move_no]['b'];
    }
    else {
      // Looks like black hasn't moved yet for this move no.
      return $this->moves[$move_no]['w'];
    }
  }
  
  /** 
   * Get the moves for this game.
   * 
   * @return \Drupal\vchess\Entity\Move[][]
   *   Returns a themed table of moves.
   */
  public function getMoves() {
    return $this->moves;
  }

  /**
   * Gets the color whose turn it is to play.
   */
  public function getTurn() {
    $move_no = $this->getNextMoveNumber();

    if (array_key_exists('w', $this->moves[$move_no])) {
      // No move played yet or last move was a black move.
      return 'b';
    }
    else {
      // Looks like black hasn't moved yet for this move no.
      return 'w';
    }
  }

}
