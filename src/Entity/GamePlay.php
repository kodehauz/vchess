<?php

namespace Drupal\vchess\Entity;

use Drupal\user\UserInterface;
use Drupal\vchess\Game\Board;

class GamePlay {

  // Define game statuses
  const STATUS_WHITE_WIN = '1-0';
  const STATUS_BLACK_WIN = '0-1';
  const STATUS_DRAW = '1/2-1/2';
  const STATUS_IN_PROGRESS = 'in progress';
  const STATUS_AWAITING_PLAYERS = 'awaiting players';

    // Define time units
  const TIME_UNITS_DAYS = 'days';
  const TIME_UNITS_HOURS = 'hours';
  const TIME_UNITS_MINS = 'mins';
  const TIME_UNITS_SECS = 'secs';

    // Define errors
  const ERROR_CASTLING_SQUARES_BLOCKED = 'ERROR: Castling squares are blocked!';
  const ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING = 'ERROR: You cannot escape check by castling!';
  const ERROR_CANNOT_CASTLE_ACROSS_CHECK = 'ERROR: You cannot castle across check!';
  const ERROR_CANNOT_CASTLE_SHORT = 'ERROR: You cannot castle short anymore!';
  const ERROR_CANNOT_CASTLE_LONG = 'ERROR: You cannot castle long anymore!';


  /**
   * The entity holding game information.
   *
   * @var \Drupal\vchess\Entity\Game
   */
  protected $game;

  /**
   * The game board.
   *
   * @var \Drupal\vchess\Game\Board
   */
  protected $board;

  /**
   * The game scoresheet.
   *
   * @var \Drupal\vchess\Entity\Scoresheet
   */
  protected $scoresheet;

  
  protected $time_per_move;  // e.g. 3
  protected $time_units;     // e.g. TIME_UNITS_DAYS
  
  // chatter: list of chatter lines (first is newest)
  
  // Dynamic (based on user id) entries:
  // p_maymove: whether it's player's turn (always 0 if user is not playing)
  // p_mayundo: player may undo last move
  // p_mayabort: player may abort game (first move or opponent took too long)
  // p_mayarchive: player may move game to archive
  
  /**
   * Game constructor
   */
  function __construct() {
    // Setup the board
    $this->board = new Board();
    $this->board->setupAsStandard();
//    $this->board->setupPosition("4k3/8/8/2p5/8/8/2P5/4K5");
    $this->board->resetEnPassant();

    // Initialize the game entity.
    $this->game = Game::create([
      'turn' => 'w',
      'castling' => 'KQkq',
      'status' => static::STATUS_AWAITING_PLAYERS,
      'time_per_move' => DEFAULT_TIME_PER_MOVE,
      'time_units' => DEFAULT_TIME_UNITS,
      // @todo other stuff
    ]);

    // Initialize the scoresheet for this game.
    $this->scoresheet = new Scoresheet($this->game);
  }
  
  /**
   * Get the timestamp of when the game started
   *
   * @return string|false
   *   A timestamp, e.g. "2012-05-03 12:01:29", false if the game has not yet
   *  started
   */
  public function timeStarted() {
    if ($this->game->getStatus() == static::STATUS_IN_PROGRESS) {
      return $this->game->getStatus();
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get the game speed, which is the combination of the time_per_move
   * and the time_units, e.g. "3 days"
   *
   * @return
   *   Returns the speed per move, e.g. "3 days"
   */
  public function speed() {
    return $this->game->getTimePerMove() . " " . $this->game->getTimeUnits();
  }
  
  /**
   * Set the time per move
   * 
   * This just sets the value of the time per move (e.g. 1 or 3).  The units of time 
   * would be set in set_time_units(), which isn't currently needed so does not exist. 
   * 
   * @parm $time_per_move
   *   Time per move, e.g. "3".  
   */
//  public function setTimePerMove($time_per_move) {
//    $this->time_per_move = $time_per_move;
//
//    $this->save();
//  }
//
  /**
   * Deal with the case that the player has lost on time
   */
  protected function handleLostOnTime() {
    if ($this->turn() == 'w') {
      $this->game->setStatus(static::STATUS_BLACK_WIN)->save();
    }
    else {
      $this->game->setStatus(static::STATUS_WHITE_WIN)->save();
    }
  }
  
  /**
   * Set the players for a new game.
   * 
   * It is at this stage that the game really begins playing.
   * 
   * @param $white_user
   *   Userid of white player
   * @param $black_user
   *   Userid of black player
   *
   * @return
   *   Game id, $gid.   
   */
  public function setPlayers(UserInterface $white_user, UserInterface $black_user) {
    // Build new game context 
    $this->game
      ->setWhiteUser($white_user)
      ->setBlackUser($black_user)
      ->setStatus(static::STATUS_IN_PROGRESS)
      ->save();
    return $this->game->id();
  }
  
  /**
   * Set a single player
   * 
   * Note that it is an error to set a player when there are already 2 players
   * assigned to the game
   */
  public function setPlayer(UserInterface $user) {
//     watchdog("VChess", "In game.inc for game %gid, at start of set_player() setting player uid=%uid." .  
//         " Currently white_uid=%white_uid and black_uid=%black_uid", 
//         array('%gid' => $this->gid(), 
//             '%uid' => $uid, 
//             '%white_uid' => $this->white_uid, 
//             '%black_uid' => $this->black_uid));
    
    if ($this->game->getWhiteUser() == NULL && $this->game->getBlackUser() == NULL) {
      if (rand(1,100) < 50) {
        $this->game
          ->setWhiteUser($user)
          ->save();
      }
      else {
        $this->game
          ->setBlackUser($user)
          ->save();
      }
    }
    else {
      if ($this->game->getWhiteUser() == NULL) {
        $this->game
          ->setWhiteUser($user)
          ->setStatus(static::STATUS_IN_PROGRESS)
          ->save();
      }
      elseif ($this->game->getBlackUser() == NULL) {
        $this->game
          ->setBlackUser($user)
          ->setStatus(static::STATUS_IN_PROGRESS)
          ->save();
      }
      else {
        \Drupal::logger('VChess')->error(t( "Attempt to set a player when both players are already assigned"));
      }
    }
  }
   
  /**
   * Get the player who is the current challenger
   */
  public function challenger() {
    $uid = 0;

    if ($this->game->getWhiteUser() != NULL && $this->game->getBlackUser() == NULL) {
      $uid = $this->game->getWhiteUser()->id();
    }
    elseif ($this->game->getBlackUser() != NULL && $this->game->getWhiteUser() == NULL) {
      $uid = $this->game->getBlackUser()->id();
    }
  
    $challenger = new Player($uid);
    
    return $challenger;
  }
  
  /**
   * Load an existing game (try active games first, then archived games) and set various
   * user-based variables, too. Return game as array or NULL on error.
   *
   * @param $gid: Game id
   *
   */
  public function load($gid) {
    $this->game = Game::load($gid);
    $this->board->setEnPassantValue($this->game->getEnPassant());
    $this->scoresheet = new Scoresheet($this->game);
  }
  
  /**
   * Get the last move
   */
  public function lastMove() {
    return $this->scoresheet->getLastMove();
  }
  
 
  /**
   * This sets up the castling state
   * 
   * If neither side can castle, this is "-". 
   * Otherwise, this has one or more letters: 
   * - "K" (White can castle kingside), 
   * - "Q" (White can castle queenside), 
   * - "k" (Black can castle kingside), and/or 
   * - "q" (Black can castle queenside)
   * 
   * @see http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation 
   */
//  public function initialiseCastling($castling) {
//    if (strpos($castling, "K") !== FALSE) {
//      $this->white_may_castle_short = TRUE;
//    }
//    else {
//      $this->white_may_castle_short = FALSE;
//    }
//    if (strpos($castling, "Q") !== FALSE) {
//      $this->white_may_castle_long = TRUE;
//    }
//    else {
//      $this->white_may_castle_long = FALSE;
//    }
//    if (strpos($castling, "k") !== FALSE) {
//      $this->black_may_castle_short = TRUE;
//    }
//    else {
//      $this->black_may_castle_short = FALSE;
//    }
//    if (strpos($castling, "q") !== FALSE) {
//      $this->black_may_castle_long = TRUE;
//    }
//    else {
//      $this->black_may_castle_long = FALSE;
//    }
//  }
//

  /**
   * Get the white player
   */
  public function whitePlayer() {
    return new Player($this->game->getWhiteUser()->id());
  }
  
  /**
   * Get the the black player
   */
  public function blackPlayer() {
    return new Player($this->game->getBlackUser()->id());
  }
  
  /**
   * Get the number of the current move.  
   * 
   * The move number will be the number of the move which is currently not yet complete.  
   * Each move has a white move and a black move.
   * 
   * i.e.
   * No moves, i.e.
   * 1. ... ...
   * move_no = 1 (i.e. waiting for move 1 of white)
   * After 1.e4 ... 
   * move_no = 1 (i.e. waiting for move 1 of black)
   * After 1. e4 Nf6 
   * move_no = 2 (i.e. waiting for move 2) 
   */
  public function moveNo() {
    return $this->scoresheet->getMoveNumber();
  }
  
  /**
   * See if it's the given players move
   */
  public function isPlayersMove($uid) {
    if (($this->game->getTurn() == 'w' && $this->game->getWhiteUser()->id() == $uid)
    || ($this->game->getTurn() == 'b' && $this->game->getBlackUser()->id() == $uid)) {
      $players_move = TRUE;
    }
    else {
      $players_move = FALSE;
    }
    
    return $players_move;
  }
  
  /**
   * See if the game is started yet
   * 
   * @return
   *   TRUE if a move has already been made
   */
  public function isMoveMade() {
    if ($this->moveNo() == 1 && $this->turn() == "w") {
      $is_move_made = FALSE;
    }
    else {
      $is_move_made = TRUE;
    }
    
    return $is_move_made;
  }
  
  /**
   * See if the given user is one of the players
   * 
   * @return
   *   TRUE if the given user is one of the players
   */
  public function isUserPlaying($uid) {
    if ($this->game->getBlackUser()->id() == $uid || $this->game->getWhiteUser()->id() == $uid) {
      $playing = TRUE;
    }
    else {
      $playing = FALSE;
    }
    
    return $playing;
  }
  
  /** 
   * Get the game id
   */
  public function gid() {
    // This game may never have been saved.
    if ($this->game->isNew()) {
      $this->game->save();
    }
    return $this->game->id();
  }
  
  /**
   * Get the game board.
   */
  public function board() {
    return $this->board;
  }
  
  /**
   * Set the board
   */
  public function setBoard(Board $board) {
    $this->board = $board;
  }
  
  /**
   * Setup a postion
   * 
   * @param $fen_string
   *   A FEN string, e.g. after 1.e4 the FEN string will be:
   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
   */
  public function setupPosition($fen_string) {
    $this->board->setupPosition($fen_string);
  }
  
  /**
   * Get the current position as a FEN string
   * 
   * @return string
   *   the current position as a FEN string e.g. after 1.e4 the FEN string will be:
   *   "rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR"
   */
  public function position() {
    return $this->board->position();
  }

  /**
   * Get the uid of the white player
   */
  public function whiteUid() {
    return $this->game->getWhiteUser()->id();
  }
  
  /**
   * Get the uid of the black player
   */
  public function blackUid() {
    return $this->game->getBlackUser()->id();
  }
  
  /**
   * Get the en_passant 
   * 
   * The en_passant is the coordinates of the square
   * behind the pawn in the last move to have moved 2 squares.
   * 
   * @return 
   *   Returns the en_passant coord (e.g. "d3"), if there is one,
   *   otherwise it returns "-" 
   */
  public function enPassant() {
    return $this->board->enPassant();
  }
  
  /**
   * Get the player whose turn it is, either 'w' or 'b'
   * 
   * @return
   *   Whose turn it is, 'w' or 'b'
   */
  public function turn() {
    return $this->game->getTurn();
  }
  
  /**
   * Get the status
   * 
   * Status can be one of:
   * - "awaiting players"
   * - "in progress"
   * - "1-0"
   * - "0-1"
   * - "1/2-1/2"
   */
  public function status() {
    return $this->game->getStatus();
  }
  
  /**
   * Set the player whose turn it is to move to be 'w'
   */
  public function setTurnWhite() {
    $this->game->setTurn('w');
  }
  
  /**
   * Set the player whose turn it is to move to be 'b'
   */
  public function setTurnBlack() {
    $this->game->setTurn('b');
  }
  
  /**
   * Say whether the game is over or not
   * 
   * @return TRUE if the game is over
   */
  public function isOver() {
    if ($this->status() == static::STATUS_IN_PROGRESS) {
      $is_over = FALSE;
    }
    else {
      $is_over = TRUE;
    }
    
    return $is_over;
  }
  
  /**
   * Say whether the king is in checkmate
   * 
   * @param $player
   *   Player, either 'w' or 'b'
   */
  public function isCheckmate($player) {
    return $this->board->isCheckmate($player);
  }
  
  /**
   * Say whether the king is in check
   * 
   * @param $player
   *   Player, either 'w' or 'b'
   */
  public function isCheck($player) {
    return $this->board->isCheck($player);
  }
  
  /**
   * Find for a particular player who the opponent is.
   *
   * @param $uid
   *   User id of one of the players
   *
   * @return Player $player
   *   The opposing player
   */
  public function opponent($uid) {
    if ($this->game->getWhiteUser()->id() == $uid) {
      $opponent = $this->blackPlayer();
    }
    else {
      $opponent = $this->whitePlayer();
    }
  
    return $opponent;
  }
  
  /**
   * Resign a particular game
   */
  public function resign($uid) {
    $winner = $this->opponent($uid);
  
    if ($this->playerColor($uid) == 'w') {
      $this->setStatus(static::STATUS_BLACK_WIN);
    }
    else {
      $this->setStatus(static::STATUS_WHITE_WIN);
    }
  }
  
  /**
   * Find out what color a particular player is
   * 
   * In the case where a player is playing against themself (!), which we allow
   * at least for testing purposes, the color is the color of whoever's turn it 
   * is to move.
   * 
   * @param $uid
   *   The user id of a player
   *   
   * @return
   *   'w' if the player is playing white
   *   'b' if the player is playing black
   *   '' if the player is not a player of this game
   */
  public function playerColor($uid) {
    $color = "";
    
    if ($this->game->getWhiteUser()->id() == $this->game->getBlackUser()->id()) {
      $color = $this->turn();
    }
    elseif ($this->game->getWhiteUser()->id() == $uid) {
      $color = 'w';
    }
    elseif ($this->game->getBlackUser()->id() == $uid) {
      $color = 'b';
    }
  
    return $color;
  }
  
  /**
   * Set status
   */
  public function setStatus($status) {
    $this->status = $status;
  }
  
  /**
   * Set last move
   */
  public function setLastMove($last_move) {
//  $this->game['last_move'] = $last_move;
  }
  
  public function whiteMayNotCastle() {
    $this->white_may_castle_short = FALSE;
    $this->white_may_castle_long = FALSE;
  }
  
  public function blackMayNotCastle() {
    $this->black_may_castle_short = FALSE;
    $this->black_may_castle_long = FALSE;
  }
  
  public function setWhiteMayCastleShort($may_castle_short) {
    $this->white_may_castle_short = $may_castle_short;  
  }
  
  public function setWhiteMayCastleLong($may_castle_long) {
    $this->white_may_castle_long = $may_castle_long;
  }
  
  public function setBlackMayCastleShort($may_castle_short) {
    $this->black_may_castle_short = $may_castle_short;
  }
  
  public function setBlackMayCastleLong($may_castle_long) {
    $this->black_may_castle_long = $may_castle_long;
  }
  
  public function blackMayCastleLong() {
    return $this->black_may_castle_long;
  }
  
  public function blackMayCastleShort() {
    return $this->black_may_castle_short;
  }

  public function whiteMayCastleLong() {
    return $this->white_may_castle_long;
  }
  
  public function whiteMayCastleShort() {
    return $this->white_may_castle_short;
  }
  
  /**
   * Find out if the given player may castle short
   * 
   * @param unknown_type $player
   * @return TRUE if player may castle short
   */
  public function mayCastleShort($player) {
    if ($player == 'w') {
      $may_castle_short = $this->whiteMayCastleShort();
    }
    else {
      $may_castle_short = $this->blackMayCastleShort();
    }
    
    return $may_castle_short;
  }
  
  public function mayCastleLong($player) {
    if ($player == 'w') {
      $may_castle_short = $this->whiteMayCastleLong();
    }
    else {
      $may_castle_short = $this->blackMayCastleLong();
    }
  
    return $may_castle_short;
  }
  
  /**
   * Verify move, execute it and modify game.
   *
   * @param $uid 
   *   User id of current player
   *   
   * @param $move_string 
   *   String of current move in long format, e.g. "Pe2-e4"
   *   
   * @param $comment
   *   @todo Please document this parameter
   */
  public function makeMove($uid, $move_string) {
    $mate_type = "";

    /** @var \Drupal\vchess\Entity\Move $move */
    $move = Move::create()->setLongMove($move_string);
    
    $board = $this->board();
    
    // We will use this board at the end to get the algebraic move
    $board_clone = clone $board;
  
    $result = "";
    $pawn_promoted = FALSE;
    $en_passant_set = FALSE;
    $move_ok = TRUE;
    
    $piece_square = $move->from_square();
    $to_square = $move->to_square();
    
//    $result .= "move_string = $move_string. ";
  
    if (!$this->isPlayersMove($uid)) {
      $result .= 'It is not your turn!';
    }
    else {
      if ($this->turn() == 'w') {
        $opponent = 'b';
      }
      else {
        $opponent = 'w';
      }

      // HANDLE MOVES:
      if ($move->type() == 'draw?' && $this->status() == '?') {
        // Offer draw
        $this->setStatus('D');
        $result .= 'You have offered a draw.';
        $draw_handled = 1;
        $game['lastmove'] = 'DrawOffered';
      }
      elseif ($move->type() == 'refuse_draw' && $this->status() == 'D') {
        // Refuse draw
        $this->setStatus('?');
        $draw_handled = 1;
        $result .= 'You refused the draw.';
        $game['lastmove'] = 'DrawRefused';
      }
      elseif ($move->type() == 'accept_draw' && $this->status() == 'D') {
        // Accept draw
        $this->setStatus('-');
        $draw_handled = 1;
        $result .= 'You accepted the draw.';
        $this->setLastMove('DrawAccepted');
        if ($game['curplyr'] == 'b') {
          $game['curmove']++; // new move as white offered
        }
        $game['mhistory'][count($game['mhistory'])] = 'draw';
      }
      elseif ($move->long_format() == 'Ke1-g1' 
      || $move->long_format() == 'Ke8-g8' 
      || $move->long_format() == 'Ke1-c1' 
      || $move->long_format() == 'Ke8-c8') {
        switch ($move->long_format()) {
          case 'Ke1-g1':
            $error = $this->castle('w', 'e1', 'g1', 'h1', 'f1', array('f1', 'g1'), $board);
            break;
          case 'Ke1-c1':  
            $error = $this->castle('w', 'e1', 'c1', 'a1', 'd1', array('b1', 'c1', 'd1'), $board);
            break;
          case 'Ke8-g8':
            $error = $this->castle('b', 'e8', 'g8', 'h8', 'f8', array('f8', 'g8'), $board);
            break;
          case 'Ke8-c8':
            $error = $this->castle('b', 'e8', 'c8', 'a8', 'd8', array('b8', 'c8', 'd8'), $board);
            break;
          default:
            break;  
        }
        if ($error <> "") {
          return $error;
        }
      }
      elseif ($move->type() == "-") {
        // Validate piece and position.
        // Move is e.g. "Nb1-c3"
        $piece = new Piece();
        $piece->set_type($move->source_piece_type());
        $piece->set_color($this->turn());

        if ($piece->type() == 'P' && $to_square->coord() == $this->enPassant()) {
          // Perform en passant pawn capture
          $board->enPassantCapture($piece_square, $to_square);
        }
        elseif (!$board->moveIsOk($piece_square, $to_square)) {
          $move_ok = FALSE;
        }
        else {
          // If pawn moved 2 squares, then record the en_passant square
          // (the square behind the pawn which has just moved)
          if ($board->pawnMoved2Squares($piece_square, $to_square)) {
            $this->setEnPassant($to_square);

            $en_passant_set = TRUE;
          } 
          
          // If pawn reached last rank, promote it
          $pawn_promoted = $this->handlePawnPromotion($move, $board);
        
          if (!$pawn_promoted) {
            // Perform normal move
            $board->movePiece($piece_square, $to_square);
          }
        }
      }
      elseif ($move->getType() == "x") {
        if ($board->squareIsEmpty($to_square)) {
          // En passant of pawn?
          if ($piece_type == 'P') {

          }
          else {
            $result .= 'ERROR: ' . $to_square->coord() . ' is empty!';
            $move_ok = FALSE;
          }
        }
        elseif ($board->piece($to_square)->color() == $this->turn()) {
          $result .= 'ERROR: You cannot attack own chessman at ' . $to_square->coord() . '!';
          $move_ok = FALSE;
        }
        elseif (!$board->pieceAttacks($piece_square, $to_square)) {
          $result .= 'ERROR: You cannot take the piece on ' . $to_square->coord() . '!';
          $move_ok = FALSE;
        }
        
        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handlePawnPromotion($move, $board);
        
        if ($move_ok && !$pawn_promoted) {
          // Perform normal capture
          $board->movePiece($piece_square, $to_square);
        }
      }
    
      // If OWN king is still in check, then invalid move
      if ($board->isCheck($this->turn())) {
        $result .= 'ERROR: King is in check. ';
        $move_ok = FALSE;        
      }

      $move->calculate_algebraic($this->turn(), $board_clone);
      
      // If move was executed update game state.
      if ($move_ok) {
        $result .= 'Your last move: ' . $move->algebraic();
        
        // If this wasn't a 2-square pawn move, we need to reset 
        // the en_passant square
        if (!$en_passant_set) {
          $this->resetEnPassant();
        }
        
        // Check checkmate/stalemate
        if ($board->isCheck($opponent)) {
          // If this is check mate finish the game otherwise
          // add '+' to the move.
          if ($board->isCheckmate($opponent)) {
            if ($this->turn() == 'w') {
              $this->setStatus(STATUS_WHITE_WIN);
            }
            else {
              $this->setStatus(STATUS_BLACK_WIN);
            }
            $result .= '... CHECKMATE!';
          }
        }
        elseif ($board->isStalemate($opponent)) {
          $this->setStatus(STATUS_DRAW);
          $result .= '... STALEMATE!';
        }
        
        // Update whose turn it is.  Even if mate has occured, it
        // is still logically the opponents move, even if they have
        // no valid move that they can make  
        if ($this->turn() == 'b') {
          $this->setTurnWhite();
        }
        else {
          $this->setTurnBlack();
        }
      
        // Add comment to head of chatter. Right now we have only two
        // chatter items. Strip backslashes and replace newlines to get
        // a single line.
        if (empty($comment)) {
          $comment = '(silence)';
        }
        else {
          $comment = str_replace("\\", '', strip_tags($comment));
          $comment = str_replace("\n", '<br />', $comment);
        }
        $comment = '<u>' . $uid . '</u>: ' . $comment;
        //    $game['chatter'][1] = $game['chatter'][0];
        //    $game['chatter'][1] = "Hugh hard coding for now";
        //    $game['chatter'][0] = $comment;
      
        // Store changed board
        $this->setBoard($board);
        
        $this->scoresheet->appendMove($move);
      }
      else {
        $result .= 'ERROR: ' . $move->algebraic() . ' is not a legal move!  ';
      }
    }
    return $result;
  }
  
  /**
   * Set the en_passant square
   */
  public function setEnPassant(Square $square_in_front) {
    $this->board()->setEnPassant($square_in_front);
  }

  /**
   * Test if there is an en_passant square
   */
  public function isEnPassant() {
    return $this->board->isEnPassant();
  }
  
  /**
   * Handle pawn promotion
   */
  public function handlePawnPromotion(Move $move, Board $board) {
    $pawn_promoted = FALSE;
    
    $piece = $board->piece($move->from_square());
    if ($piece->type() == 'P') {
      if (($this->turn() == 'w' && $move->to_square()->rank() == 8) ||
          ($this->turn() == 'b' && $move->to_square()->rank() == 1)) {
        $promote_to = new Piece();
        $promote_to->set_type($move->promotion_piece_type());
        $promote_to->set_color($this->turn());
    
        $board->promotion($move->from_square(), $move->to_square(), $promote_to);
        
        $pawn_promoted = TRUE;
      }
    }
    
    return $pawn_promoted;
  }
  
  /**
   * Handle castling
   * 
   * @param $turn either 'w' or 'b'
   * @param $king_coord the coord of the king, e.g. "e1"
   * @param $castling_coords array of the coords of all the squares involved, from
   * left to right, e.g. array("e1", "f1", "g1", "h1"
   *       
   */
  public function castle($turn, $king_from, $king_to, $rook_from, $rook_to, $gap_coords, $board) {
    $error = "";  
    
    if ($turn == 'w') {
      $opponent = 'b';
    }
    else {
      $opponent = 'w';
    }
  
    if (count($gap_coords) == 2) {
      if (!$this->mayCastleShort($turn)) {
        $error = ERROR_CANNOT_CASTLE_SHORT;
      }
    }
    else { // count == 3
      if (!$this->mayCastleLong($turn)) {
        $error = ERROR_CANNOT_CASTLE_LONG;
      }
    }
    
    if ($error == "") {
      foreach ($gap_coords as $gap_coord) {    
        if (!$board->square_at_coord_is_empty($gap_coord)) {
          $error = ERROR_CASTLING_SQUARES_BLOCKED;
        }
      }
    }
    
    if ($error == "") {      
      if ($board->is_check($turn)) {
        $error = ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING;
      }
    }
    // Check the squares between the king's current position and where he will move to are not attacked  
    if ($error == "") {
      foreach ($gap_coords as $gap_coord) {
        $square = new Square;
        $square->set_coord($gap_coord);
        if ($board->square_is_under_attack($square, $opponent)) {
          $error = ERROR_CANNOT_CASTLE_ACROSS_CHECK;
        }
      }
    }

    if ($error == "") {
      $board->move_piece(vchess_coord2square($king_from), vchess_coord2square($king_to));  // White King
      $board->move_piece(vchess_coord2square($rook_from), vchess_coord2square($rook_to));  // Rook
      
      $this->mayNotCastle($turn);
      $this->setLastMove('Ke1-g1');
    }
    
    return $error;
  }
  
  /**
   * Set the fact that player may not castle
   * 
   * @param $turn 
   *   $turn is 'w' or 'b'
   */
  public function mayNotCastle($turn) {
    if ($turn == 'w') {
      $this->whiteMayNotCastle();
    }
    else {
      $this->blackMayNotCastle();
    }
  }
  
  /**
   * Save a game move
   *
   * @param int $gid Game id
   * @param int $move_no Number of move, e.g. 3
   * @param $turn Whose turn it is, either "w" or "b"
   * @param $move e.g. "Nb1-c3" or "Bc1xNf4"
   */
//  function save_move($move_no, $turn, Move $move) {
//    db_insert('vchess_moves')
//    ->fields(array(
//        'gid' => $this->gid,
//        'move_no' => $move_no,
//        'color' => $turn,
//        'long_move' => $move->long_format(),
//        'algebraic' => $move->algebraic(),
//        // In order to avoid timestamp problems, we always store
//        // the timestamp as GMT, which does not change for summer time
//        'datetime' => gmdate("Y-m-d H:i:s"),
//        ))
//        ->execute();
//  }
  
  /**
   * Save an open game
   */
//  function save() {
//
//    if (!isset($this->gid)) {
//      $this->gid = db_insert('vchess_games')
//      ->fields(array(
//          'turn' => 'w',
//          'status' => $this->status(),
//          'white_uid' => $this->white_uid,
//          'black_uid' => $this->black_uid,
//          'board' => $this->board->position(),
//          'castling' => $this->castling(),
//          'en_passant' => $this->enPassant(),
//          'time_per_move' => $this->time_per_move,
//          'time_units' => $this->time_units,
//          // In order to avoid timestamp problems, we always store
//          // the timestamp as GMT, which does not change for summer time
//          'time_started' => gmdate("Y-m-d H:i:s"),
//          )
//      )
//      ->execute();
//    }
//    else {
//      $sql = "SELECT gid FROM {vchess_games} WHERE gid = '" . $this->gid . "'";
//
//      $result = db_query($sql);
//      $exists = $result->fetchField();
//
//      if ($exists) {
//        db_update('vchess_games')->fields(array(
//            'turn' => $this->turn(),
//            'status' => $this->status(),
//            'white_uid' => $this->whiteUid(),
//            'black_uid' => $this->blackUid(),
//            'board' => $this->board()->position(),
//            'castling' => $this->castling(),
//            'en_passant' => $this->enPassant(),
//            'time_per_move' => $this->time_per_move,
//            'time_units' => $this->time_units,
//        ))
//        ->condition('gid', $this->gid)
//        ->execute();
//      }
//    }
//  }

  /**
   * Abort an open game. This is only possible if your opponent did not move
   * at all yet or did not move for more than four weeks. Aborting a game will
   * have NO influence on the game statistics. Return a status message.
   *
   * @todo
   */
  function abort($uid) {
    //  global $res_games;
  
    $gamefolder =  variable_get('vchess_game_files_folder', 'vchess-data');
    $res_games = $gamefolder;
  
    //  ioLock();
  
    $game = new Game($gid);
    if ($game == NULL) {
      return 'ERROR: Game "' . $gid . '" does not exist!';
    }
    if (!$game['p_mayabort']) {
      return 'ERROR: You cannot abort the game!';
    }
    unlink("$res_games/$gid");
  
    //  ioUnlock();
  
    return 'Game "' . $gid . '" deleted.';
  }
  
  /**
   * Undo last move (only possible if game is not over yet).
   */
  function handle_undo($uid) {

  }

  /**
   * HACK: this function checks whether en-passant is possible
   */
  public static function enPassantIsOk($player, $pos, $dest, $opp_ep_flag) {
    if ($opp_ep_flag != 'x') {
      if ($dest % 8 == $opp_ep_flag) {
        // if (pawn_attacks($pos,$dest)) right now
        // this is not required as we only use this
        // function in isStaleMate which uses correct dests
        if (($player == 'w' && floor($dest / 8) == 5)
          || ($player == 'b' && floor($dest / 8) == 2)) {
          return 1;
        }
      }
    }
    return 0;
  }

  /**
   * Check through all the current games to see if any have been lost on time
   *
   * If games are found which are lost on time, then the game is finished and
   * the player statistics are updated
   */
  function checkForLostOnTimeGames() {
    $games = Game::loadAllCurrentGames();

    foreach ($games as $game) {
      if ($game->isLostOnTime()) {
        vchess_update_player_stats($game);
      }
    }
  }

}
