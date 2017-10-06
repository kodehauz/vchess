<?php

namespace Drupal\vchess\Game;

use Drupal\gamer\Entity\GamerStatistics;
use Drupal\user\UserInterface;
use Drupal\vchess\Entity\Game;
use Drupal\vchess\Entity\Move;

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
   * The last move made just before this one.
   *
   * @var string
   */
  protected $lastMove;

  /**
   * The game board.
   *
   * @var \Drupal\vchess\Game\Board
   */
  protected $board;

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
  function __construct(Game $game = NULL) {
    if ($game === NULL) {
      // Setup the board
      $this->board = new Board();
      $this->board
        ->setupAsStandard()
//        ->setupPosition("4k3/8/8/2p5/8/8/2P5/4K5")
        ->resetEnPassantSquare();

      // Initialize the game entity.
      $this->game = Game::create([
        'turn' => 'w',
        'castling' => 'KQkq',
        'status' => static::STATUS_AWAITING_PLAYERS,
        'time_per_move' => DEFAULT_TIME_PER_MOVE,
        'time_units' => DEFAULT_TIME_UNITS,
        // @todo other stuff
      ]);
    }
    else {
      $this->game = $game;
      $this->board = new Board();
      $this->board
        ->setupPosition($game->getBoard())
        ->setEnPassantSquare($game->getEnPassantSquare());
    }
  }
  
  /**
   * Get the timestamp of when the game started
   *
   * @return string|false
   *   A timestamp, e.g. "2012-05-03 12:01:29", false if the game has not yet
   *  started
   */
  public function getTimeStarted() {
    if ($this->game->getStatus() === static::STATUS_IN_PROGRESS) {
      return $this->game->getStatus();
    }
    else {
      return FALSE;
    }
  }

  /**
   * Deal with the case that the player has lost on time
   */
  protected function handleLostOnTime() {
    if ($this->getTurn() == 'w') {
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
      if (rand(1, 100) < 50) {
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
   * Load an existing game (try active games first, then archived games) and set various
   * user-based variables, too. Return game as array or NULL on error.
   *
   * @param $gid: Game id
   *
   */
  public function load($gid) {
    $this->game = Game::load($gid);
    $this->board->setEnPassantSquare($this->game->getEnPassantSquare());
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
    return GamerStatistics::loadForUser($this->game->getWhiteUser());
  }
  
  /**
   * Get the the black player
   */
  public function blackPlayer() {
    return GamerStatistics::loadForUser($this->game->getBlackUser());
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
  public function getBoard() {
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
    return $this->board->getFenString();
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
  public function getEnPassantSquare() {
    return $this->board->getEnPassantSquare();
  }
  
  /**
   * Get the player whose turn it is, either 'w' or 'b'
   * 
   * @return
   *   Whose turn it is, 'w' or 'b'
   */
  public function getTurn() {
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
  public function getStatus() {
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
   * Checks whether the king is in checkmate.
   * 
   * @param string $player
   *   Player, either 'w' or 'b'
   *
   * @return boolean
   */
  public function isCheckmate($player) {
    return $this->board->isInCheckmate($player);
  }
  
  /**
   * Checks whether the king is in check.
   * 
   * @param string $player
   *   Player, either 'w' or 'b'
   *
   * @return boolean
   */
  public function isCheck($player) {
    return $this->board->isInCheck($player);
  }
  
  /**
   * Resigns a particular game.
   */
  public function resign($uid) {
    if ($this->playerColor($uid) === 'w') {
      $this->game
        ->setStatus(static::STATUS_BLACK_WIN)
        ->save();
    }
    else {
      $this->game
        ->setStatus(static::STATUS_WHITE_WIN)
        ->save();
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
    if ($this->game->getWhiteUser()->id() == $this->game->getBlackUser()->id()) {
      return $this->getTurn();
    }
    elseif ($this->game->getWhiteUser()->id() == $uid) {
      return 'w';
    }
    elseif ($this->game->getBlackUser()->id() == $uid) {
      return 'b';
    }
  
    return "";
  }
  
  /**
   * Set status
   */
  public function setStatus($status) {
    $this->game->setStatus($status)->save();
  }
  
  /**
   * Set last move
   */
  public function setLastMove($last_move) {
    $this->lastMove = $last_move;
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
   * @return object
   *   @todo Please document this parameter
   */
  public function makeMove(UserInterface $user, $move_string) {
    if (!$this->game->isPlayersMove($user)) {
      return 'It is not your turn!';
    }
    else {
      /** @var \Drupal\vchess\Entity\Move $move */
      $move = Move::create()->setLongMove($move_string);

      // We will use this board at the end to get the algebraic move.
      $board_clone = clone $this->board;

      $result = "";
      $pawn_promoted = FALSE;
      $en_passant_set = FALSE;
      $move_ok = TRUE;

      $piece_square = $move->fromSquare();
      $to_square = $move->toSquare();

//    $result .= "move_string = $move_string. ";

      if ($this->getTurn() == 'w') {
        $opponent = 'b';
      }
      else {
        $opponent = 'w';
      }

      // HANDLE MOVES:
      if ($move->getType() === 'draw?' && $this->getStatus() === '?') {
        // Offer draw
        $this->setStatus('D');
        $result .= 'You have offered a draw.';
        $draw_handled = 1;
        $this->lastMove = 'DrawOffered';
      }
      elseif ($move->getType() == 'refuse_draw' && $this->getStatus() === 'D') {
        // Refuse draw
        $this->setStatus('?');
        $draw_handled = 1;
        $result .= 'You refused the draw.';
        $this->lastMove = 'DrawRefused';
      }
      elseif ($move->getType() == 'accept_draw' && $this->getStatus() === 'D') {
        // Accept draw
        $this->setStatus('-');
        $draw_handled = 1;
        $result .= 'You accepted the draw.';
        $this->lastMove = 'DrawAccepted';
        if ($this->game->getTurn() === 'b') {
          $this->game->setCurrentMove($this->game->getCurrentMove() + 1); // new move as white offered
        }
        $game['mhistory'][count($game['mhistory'])] = 'draw';
      }
      elseif ($move->getLongMove() == 'Ke1-g1'
      || $move->getLongMove() == 'Ke8-g8'
      || $move->getLongMove() == 'Ke1-c1'
      || $move->getLongMove() == 'Ke8-c8') {
        $error = "";
        switch ($move->getLongMove()) {
          case 'Ke1-g1':
            $error = $this->castle('w', 'e1', 'g1', 'h1', 'f1', array('f1', 'g1'), $this->board);
            break;
          case 'Ke1-c1':  
            $error = $this->castle('w', 'e1', 'c1', 'a1', 'd1', array('b1', 'c1', 'd1'), $this->board);
            break;
          case 'Ke8-g8':
            $error = $this->castle('b', 'e8', 'g8', 'h8', 'f8', array('f8', 'g8'), $this->board);
            break;
          case 'Ke8-c8':
            $error = $this->castle('b', 'e8', 'c8', 'a8', 'd8', array('b8', 'c8', 'd8'), $this->board);
            break;
          default:
            break;  
        }
        if ($error !== "") {
          return $error;
        }
      }
      elseif ($move->getType() === "-") {
        // Validate piece and position.
        // Move is e.g. "Nb1-c3"
        $piece = new Piece();
        $piece->setType($move->getSourcePieceType());
        $piece->setColor($this->getTurn());

        if ($piece->getType() === 'P' && $to_square->getCoordinate() === $this->getEnPassantSquare()) {
          // Perform en passant pawn capture.
          $this->board->performEnPassantCapture($piece_square, $to_square);
        }
        elseif (!$this->board->moveIsOk($piece_square, $to_square)) {
          $move_ok = FALSE;
        }
        else {
          // If pawn moved 2 squares, then record the en_passant square
          // (the square behind the pawn which has just moved)
          if ($this->board->pawnMoved2Squares($piece_square, $to_square)) {
            $this->board->setEnPassant($to_square);

            $en_passant_set = TRUE;
          } 
          
          // If pawn reached last rank, promote it
          $pawn_promoted = $this->handlePawnPromotion($move, $this->board);
        
          if (!$pawn_promoted) {
            // Perform normal move
            $this->board->movePiece($piece_square, $to_square);
          }
        }
      }
      elseif ($move->getType() === "x") {
        if ($this->board->squareIsEmpty($to_square)) {
          // En passant of pawn?
          if ($move->getSourcePieceType() === 'P') {

          }
          else {
            $result .= 'ERROR: ' . $to_square->getCoordinate() . ' is empty!';
            $move_ok = FALSE;
          }
        }
        elseif ($this->board->getPiece($to_square)->getColor() === $this->getTurn()) {
          $result .= 'ERROR: You cannot attack own chessman at ' . $to_square->getCoordinate() . '!';
          $move_ok = FALSE;
        }
        elseif (!$this->board->pieceAttacks($piece_square, $to_square)) {
          $result .= 'ERROR: You cannot take the piece on ' . $to_square->getCoordinate() . '!';
          $move_ok = FALSE;
        }
        
        // If pawn reached last rank, promote it
        $pawn_promoted = $this->handlePawnPromotion($move, $this->board);
        
        if ($move_ok && !$pawn_promoted) {
          // Perform normal capture
          $this->board->movePiece($piece_square, $to_square);
        }
      }
    
      // If OWN king is still in check, then invalid move
      if ($this->board->isInCheck($this->getTurn())) {
        $result .= 'ERROR: King is in check. ';
        $move_ok = FALSE;        
      }

      $move->calculateAlgebraic($this->getTurn(), $board_clone);
      
      // If move was executed update game state.
      if ($move_ok) {
        $result .= 'Your last move: ' . $move->getAlgebraic();
        
        // If this wasn't a 2-square pawn move, we need to reset 
        // the en_passant square
        if (!$en_passant_set) {
          $this->board->resetEnPassantSquare();
        }
        
        // Check checkmate/stalemate
        if ($this->board->isInCheck($opponent)) {
          // If this is checkmate finish the game, otherwise add '+' to the move.
          if ($this->board->isInCheckmate($opponent)) {
            if ($this->getTurn() === 'w') {
              $this->setStatus(static::STATUS_WHITE_WIN);
            }
            else {
              $this->setStatus(static::STATUS_BLACK_WIN);
            }
            $result .= '... CHECKMATE!';
          }
        }
        elseif ($this->board->isStalemate($opponent)) {
          $this->setStatus(static::STATUS_DRAW);
          $result .= '... STALEMATE!';
        }
        
        // Update whose turn it is.  Even if mate has occured, it
        // is still logically the opponents move, even if they have
        // no valid move that they can make  
        if ($this->getTurn() == 'b') {
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
      
        // Update game scoresheet.
        $this->game->appendMove($move);
      }
      else {
        $result .= 'ERROR: ' . $move->getAlgebraic() . ' is not a legal move!  ';
      }
    }
    return $result;
  }

  /**
   * Handle castling
   *
   * @param string $turn
   *   Whose turn to play, either 'w' or 'b'.
   * @param string $king_from
   *   The coordinate of the king, e.g. "e1"
   * @param array $castling_coords
   *   Array of the coords of all the squares involved, from
   * left to right, e.g. array("e1", "f1", "g1", "h1"
   *
   * @return string
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

    if (count($gap_coords) === 2) {
      if (!$this->mayCastleShort($turn)) {
        $error = static::ERROR_CANNOT_CASTLE_SHORT;
      }
    }
    else { // count == 3
      if (!$this->mayCastleLong($turn)) {
        $error = static::ERROR_CANNOT_CASTLE_LONG;
      }
    }

    if ($error === "") {
      foreach ($gap_coords as $gap_coord) {
        if (!$this->board->squareAtCoordinateIsEmpty($gap_coord)) {
          $error = static::ERROR_CASTLING_SQUARES_BLOCKED;
        }
      }
    }

    if ($error == "") {
      if ($this->board->isInCheck($turn)) {
        $error = static::ERROR_CANNOT_ESCAPE_CHECK_BY_CASTLING;
      }
    }
    // Ensure the squares between the king's current position and where he will
    // move to are not under attack.
    if ($error === "") {
      foreach ($gap_coords as $gap_coord) {
        $square = (new Square)->setCoordinate($gap_coord);
        if ($this->board->squareIsUnderAttack($square, $opponent)) {
          $error = static::ERROR_CANNOT_CASTLE_ACROSS_CHECK;
        }
      }
    }

    if ($error === "") {
      $this->board->movePiece(Square::fromCoordinate($king_from), Square::fromCoordinate($king_to));  // White King
      $this->board->movePiece(Square::fromCoordinate($rook_from), Square::fromCoordinate($rook_to));  // Rook

      $this->mayNotCastle($turn);
      $this->lastMove = 'Ke1-g1';
    }

    return $error;
  }

  /**
   * Handle pawn promotion
   */
  public function handlePawnPromotion(Move $move, Board $board) {
    $pawn_promoted = FALSE;
    
    $piece = $board->getPiece($move->fromSquare());
    if ($piece->getType() == 'P') {
      if (($this->getTurn() == 'w' && $move->toSquare()->getRank() == 8) ||
          ($this->getTurn() == 'b' && $move->toSquare()->getRank() == 1)) {
        $promote_to = new Piece();
        $promote_to->setType($move->getPromotionPieceType());
        $promote_to->setColor($this->getTurn());
    
        $board->promotion($move->fromSquare(), $move->toSquare(), $promote_to);
        
        $pawn_promoted = TRUE;
      }
    }
    
    return $pawn_promoted;
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
  
    $gamefolder =  \Drupal::config('vchess.settings')->get('game_files_folder');
    $res_games = $gamefolder;
  
    //  ioLock();
  
//    $game = new Game($gid);
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
  public static function checkForLostOnTimeGames() {
    $games = Game::loadAllCurrentGames();

    foreach ($games as $game) {
      if ($game->isLostOnTime()) {
        GamerStatistics::updatePlayerStatistics($game);
      }
    }
  }

}
