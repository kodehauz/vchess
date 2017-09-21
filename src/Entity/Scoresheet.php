<?php

namespace Drupal\vchess\Entity;
/**
 * @file
 * Definition of Scoresheet (list of moves).
 * 
 * The scoresheet is the table of moves in the game.
 */

/**
 * Scoresheet class
 */
class Scoresheet {

  /**
   * The moves in this scoresheet.
   *
   * @var \Drupal\vchess\Entity\Move[][]
   */
  protected $moves = [];

  /**
   * The game that this scoresheet is tracking.
   *
   * @var \Drupal\vchess\Entity\Game
   */
  protected $game;
  
  /**
   * Create a new scoresheet for a given game.
   * 
   * @param Game $game
   */
  function __construct(Game $game) {
    $this->game = $game;
    $this->loadMoves();
  }
  
  /**
   * Load the moves for a scoresheet
   */
  protected function loadMoves() {
    /** @var \Drupal\vchess\Entity\Move[] $moves */
    $moves = \Drupal::entityTypeManager()->getStorage('vchess_move')
      ->loadByProperties(['gid' => $this->game->id()]);
    
    foreach ($moves as $move) {
      $this->moves[$move->getMoveNo()][$move->getColor()] = $move;
    }
    ksort($this->moves);
  }
  
  /**
   * Get the move number
   */
  public function getMoveNumber() {
    $move_no = count($this->moves);
    // If the scoresheet is empty then we are on move 1
    if ($move_no == 0) {
      $move_no = 1;
    }
    else {
      // if black has not yet moved, then the move number is the length of the array,
      //  otherwise it is the length plus one
      //
      //  e.g. if 3. Nc3
      //   then scoresheet has:
      // $scoresheet[3]['w'] = "Nc3"
      // and so => move number = 3
      //
      // e.g. if 3. Nc3 Nc6
      // then scoresheet has:
      // $scoresheet[3]['w'] = "Nc3"
      // $scoresheet[3]['b'] = "Nc6"
      // and so => move number = 4
      if (array_key_exists("b", $this->moves[$move_no])) {
        $move_no += 1;
      }
    }
  
    return $move_no;
  }
  
  /**
   * Write the latest move down.  The move is added to the end
   * of the scoresheet.
   *
   * @param Move $move
   *   The move to be appended
   */
  public function appendMove(Move $move) {
    $move_no = $this->getMoveNumber();
    if (!array_key_exists($move_no, $this->moves)) {
      $this->moves[$move_no] = [];
    }

    // @todo Use the move color $move->getColor() instead of this check.
    if (array_key_exists('w', $this->moves[$move_no])) {
      $this->moves[$move_no]['b'] = $move;
    }
    else {
      $this->moves[$move_no]['w'] = $move;
    }
  }
  

  /**
   * Gets the white move of a particular number.
   * 
   * @param int $move_no
   */
  public function getWhiteMove($move_no) {
    return $this->getMove($move_no, "w");
  }
  
  /**
   * Gets the black move of a particular number.
   * 
   * @param int $move_no
   *
   * @return Move
   */
  public function getBlackMove($move_no) {
    return $this->getMove($move_no, "b");
  }
  
  /**
   * Gets the move of a given color.
   *
   * @return Move
   */
  protected function getMove($move_no, $color) {
    if (array_key_exists($move_no, $this->moves) && array_key_exists($color, $this->moves[$move_no])) {
      return $this->moves[$move_no][$color];
    }
    
    return Move::create([
      'move_no' => $move_no,
      'color' => $color,
    ]);
  }
  
  /**
   * Get the last move
   */
  function getLastMove() {
    $move_no = $this->getMoveNumber();
    
    $move = $this->getBlackMove($move_no);
    if ($move->getAlgebraic() == "") {
      // Looks like black hasn't moved yet for this move no
      $move = $this->getWhiteMove($move_no);
      
      if ($move->getAlgebraic() == "" && $move_no > 1) {
        // Looks like black has just moved, so we need to look at the previous move number
        $move = $this->getBlackMove($move_no - 1);
      }
    }
    
    return $move;
  }
  
  /** 
   * Get the scoresheet as a table
   * 
   * @return
   *   Returns a themed table of moves.
   */
  function get_table() {
    return [
      '#type' => 'vchess_moves_list',
      '#moves' => $this->moves,
    ];
  }
}